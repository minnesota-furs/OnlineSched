#!/usr/bin/env bash

set -euo pipefail

CONTAINER="${ONLINESCHED_APP_FEED_TEST_CONTAINER:-onlinesched-vanilla-cli}"
WP=(docker exec "$CONTAINER" wp --allow-root --path=/var/www/html)
CONTAINER_TEST="/var/www/html/wp-content/plugins/OnlineSched/tests/cli/test-app-feed.php"
BASE_URL="http://localhost:8081"
START_HINT="cd tests/docker-vanilla && docker compose up -d && ./seed-vanilla.sh"

if [[ "$CONTAINER" != "onlinesched-vanilla-cli" ]]; then
	echo "Refusing unrecognized app feed test container: $CONTAINER" >&2
	exit 1
fi

if [[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null || true)" != "true" ]]; then
	echo "The disposable Vanilla WordPress environment is not running (container '$CONTAINER' not found)." >&2
	echo "Start it first:" >&2
	echo "  $START_HINT" >&2
	exit 1
fi

site_url="$("${WP[@]}" option get siteurl)"
if [[ "$site_url" != "$BASE_URL" ]]; then
	echo "Refusing unrecognized WordPress site URL: $site_url" >&2
	exit 1
fi

if ! "${WP[@]}" plugin is-active OnlineSched; then
	echo "Refusing to run because OnlineSched is not active on the disposable Vanilla site." >&2
	exit 1
fi

echo "== WP-CLI: revision service, mutation matrix, CSV/delete-year batch semantics, event_uid, builder shapes =="
"${WP[@]}" eval-file "$CONTAINER_TEST"

echo
echo "== Concurrency: atomic per-row increment under 8 parallel workers x 25 touches each =="

# The revision store is two plain option rows per section
# (onlinesched_feed_rev_{section} / onlinesched_feed_revtime_{section}),
# bumped by a single atomic SQL
# `UPDATE ... SET option_value = CAST(option_value AS UNSIGNED) + 1`. There is
# no read-modify-write to race, so this must land at exactly +200 every time —
# unlike the earlier optimistic-CAS design, which measurably lost ~10% of
# increments under this same load. Spawns real separate `wp eval` processes
# (not threads/fibers inside one PHP process) to touch 'schedule' concurrently.
# Run twice in one invocation for extra confidence.
for concurrency_round in 1 2; do
	concurrency_start_rev="$("${WP[@]}" eval 'echo onlinesched_get_feed_revisions()["schedule"]["rev"];')"
	if ! [[ "$concurrency_start_rev" =~ ^[0-9]+$ ]]; then
		echo "FAIL: round $concurrency_round: could not read the starting schedule revision for the concurrency check (got '$concurrency_start_rev')." >&2
		exit 1
	fi

	concurrency_pids=()
	for worker in $(seq 1 8); do
		"${WP[@]}" eval 'for ($i = 0; $i < 25; $i++) { onlinesched_touch_feed("schedule", "cc"); }' >/dev/null 2>&1 &
		concurrency_pids+=("$!")
	done

	concurrency_failed=0
	for pid in "${concurrency_pids[@]}"; do
		if ! wait "$pid"; then
			concurrency_failed=1
		fi
	done
	if [[ "$concurrency_failed" != "0" ]]; then
		echo "FAIL: round $concurrency_round: one or more concurrent touch workers exited nonzero." >&2
		exit 1
	fi

	concurrency_end_rev="$("${WP[@]}" eval 'echo onlinesched_get_feed_revisions()["schedule"]["rev"];')"
	concurrency_expected=$((concurrency_start_rev + 200))
	if [[ "$concurrency_end_rev" != "$concurrency_expected" ]]; then
		echo "FAIL: round $concurrency_round: concurrent touches lost updates. start=$concurrency_start_rev end=$concurrency_end_rev expected=$concurrency_expected (8 workers x 25 touches)." >&2
		exit 1
	fi
	echo "PASS: round $concurrency_round: 8 workers x 25 touches landed exactly +200 (start=$concurrency_start_rev, end=$concurrency_end_rev) — no concurrent increment was lost"
done

echo
echo "== HTTP: json.php section responses =="

response_dir="$(mktemp -d)"
trap 'rm -rf "$response_dir"' EXIT

http_status() {
	local url="$1"
	shift
	curl -sS -o "$response_dir/body" -D "$response_dir/headers" -w '%{http_code}' "$@" "$url"
}

# 1. section=meta must be 200, application/json, and carry an ETag.
http_status_code="$(http_status "$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=meta")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=meta returned HTTP $http_status_code, expected 200." >&2
	exit 1
fi
if ! grep -qi '^content-type:.*application/json' "$response_dir/headers"; then
	echo "FAIL: json.php?section=meta did not send a Content-Type: application/json header." >&2
	cat "$response_dir/headers" >&2
	exit 1
fi
etag="$(grep -i '^etag:' "$response_dir/headers" | head -n1 | sed -E 's/^[Ee][Tt][Aa][Gg]: *//' | tr -d '\r\n')"
if [[ -z "$etag" ]]; then
	echo "FAIL: json.php?section=meta did not send an ETag header." >&2
	cat "$response_dir/headers" >&2
	exit 1
fi
echo "PASS: section=meta returns 200 + application/json + ETag ($etag)"

# 2. Same request with If-None-Match must 304.
http_status_code="$(http_status "$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=meta" -H "If-None-Match: $etag")"
if [[ "$http_status_code" != "304" ]]; then
	echo "FAIL: a repeat json.php?section=meta request with If-None-Match returned HTTP $http_status_code, expected 304." >&2
	exit 1
fi
echo "PASS: repeat request with If-None-Match returns 304"

# 3. Bare json.php (no section param) must default to the schedule section shape.
http_status_code="$(http_status "$BASE_URL/wp-content/plugins/OnlineSched/json.php")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: bare json.php returned HTTP $http_status_code, expected 200." >&2
	exit 1
fi
for key in '"schedule_published"' '"events"' '"rooms"' '"tags"'; do
	if ! grep -q "$key" "$response_dir/body"; then
		echo "FAIL: bare json.php response is missing the $key key expected of the schedule section." >&2
		cat "$response_dir/body" >&2
		exit 1
	fi
done
echo "PASS: bare json.php defaults to the schedule section"

# 4. section=info&page=<unknown slug> must 404.
http_status_code="$(http_status "$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=info&page=onlinesched-app-feed-test-missing-page")"
if [[ "$http_status_code" != "404" ]]; then
	echo "FAIL: json.php?section=info&page=<unknown> returned HTTP $http_status_code, expected 404." >&2
	exit 1
fi
echo "PASS: section=info&page=<unknown slug> returns 404"

# 5. Fresh-state stability: two consecutive GETs of the same section with no
#    intervening mutation must return an identical body, ETag, and
#    Last-Modified. section=meta is used because its ETag/body depend on both
#    the public 3-part change_stamp (schedule.hours.info) and the internal
#    meta revision that rides along in the ETag ("...-{stamp}+{metaRev}").
meta_url="$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=meta"
schedule_url="$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=schedule"
http_status_code="$(http_status "$meta_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=meta returned HTTP $http_status_code, expected 200 for the stability check." >&2
	exit 1
fi
cp "$response_dir/body" "$response_dir/stable_body_1"
cp "$response_dir/headers" "$response_dir/stable_headers_1"
stable_etag_1="$(grep -i '^etag:' "$response_dir/stable_headers_1" | head -n1)"
stable_lastmod_1="$(grep -i '^last-modified:' "$response_dir/stable_headers_1" | head -n1)"

http_status_code="$(http_status "$meta_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: repeat json.php?section=meta returned HTTP $http_status_code, expected 200 for the stability check." >&2
	exit 1
fi
stable_etag_2="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"
stable_lastmod_2="$(grep -i '^last-modified:' "$response_dir/headers" | head -n1)"

if ! diff -q "$response_dir/stable_body_1" "$response_dir/body" >/dev/null; then
	echo "FAIL: two consecutive section=meta GETs with no mutation between them returned different bodies." >&2
	exit 1
fi
if [[ "$stable_etag_1" != "$stable_etag_2" ]]; then
	echo "FAIL: two consecutive section=meta GETs with no mutation between them returned different ETags ('$stable_etag_1' vs '$stable_etag_2')." >&2
	exit 1
fi
if [[ "$stable_lastmod_1" != "$stable_lastmod_2" ]]; then
	echo "FAIL: two consecutive section=meta GETs with no mutation between them returned different Last-Modified headers ('$stable_lastmod_1' vs '$stable_lastmod_2')." >&2
	exit 1
fi
echo "PASS: two consecutive section=meta GETs with no mutation are identical (body + ETag + Last-Modified)"

# 6. A real option change (onlinesched_calendar_name, meta-only) must move the
#    section=meta ETag and body away from that stable state, while leaving
#    section=schedule (a fetchable/public section) untouched. Original value
#    is restored even if a later check fails.
calendar_name_missing=0
if ! calendar_name_original="$("${WP[@]}" option get onlinesched_calendar_name 2>/dev/null)"; then
	calendar_name_missing=1
fi
restore_calendar_name() {
	if [[ "$calendar_name_missing" == "1" ]]; then
		"${WP[@]}" option delete onlinesched_calendar_name >/dev/null 2>&1 || true
	else
		"${WP[@]}" option update onlinesched_calendar_name "$calendar_name_original" >/dev/null 2>&1 || true
	fi
}
trap 'restore_calendar_name; rm -rf "$response_dir"' EXIT

http_status_code="$(http_status "$schedule_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=schedule returned HTTP $http_status_code, expected 200 before the calendar_name change." >&2
	exit 1
fi
schedule_etag_before="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"
cp "$response_dir/body" "$response_dir/schedule_body_before"

"${WP[@]}" option update onlinesched_calendar_name "AFT HTTP Stability Check $$" >/dev/null

http_status_code="$(http_status "$meta_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=meta returned HTTP $http_status_code, expected 200 after the calendar_name change." >&2
	exit 1
fi
moved_etag="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"
meta_body_after="$response_dir/body"
cp "$meta_body_after" "$response_dir/meta_body_after"

http_status_code="$(http_status "$schedule_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=schedule returned HTTP $http_status_code, expected 200 after the calendar_name change." >&2
	exit 1
fi
schedule_etag_after="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"
schedule_body_matches=1
diff -q "$response_dir/schedule_body_before" "$response_dir/body" >/dev/null || schedule_body_matches=0

restore_calendar_name
trap 'rm -rf "$response_dir"' EXIT

if diff -q "$response_dir/stable_body_1" "$response_dir/meta_body_after" >/dev/null; then
	echo "FAIL: changing onlinesched_calendar_name did not move the section=meta body." >&2
	exit 1
fi
if [[ "$moved_etag" == "$stable_etag_2" ]]; then
	echo "FAIL: changing onlinesched_calendar_name did not move the section=meta ETag." >&2
	exit 1
fi
if [[ "$schedule_etag_before" != "$schedule_etag_after" ]]; then
	echo "FAIL: changing onlinesched_calendar_name moved the section=schedule ETag ('$schedule_etag_before' vs '$schedule_etag_after'), but it must only affect meta." >&2
	exit 1
fi
if [[ "$schedule_body_matches" != "1" ]]; then
	echo "FAIL: changing onlinesched_calendar_name moved the section=schedule body, but it must only affect meta." >&2
	exit 1
fi
echo "PASS: a real option change (onlinesched_calendar_name) moves the section=meta ETag/body while leaving section=schedule untouched"

# 7. Payload-hash property: onlinesched_app_feed_send() now hashes the exact
#    response bytes into the ETag, so the ETag must change whenever the
#    representation changes for ANY reason — including a different filter
#    variant at an otherwise IDENTICAL revision (no mutation happens between
#    these two GETs).
unfiltered_url="$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=schedule"
filtered_url="$BASE_URL/wp-content/plugins/OnlineSched/json.php?section=schedule&room=onlinesched-app-feed-test-nonexistent-room-$$"

http_status_code="$(http_status "$unfiltered_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=schedule (unfiltered) returned HTTP $http_status_code, expected 200 for the payload-hash check." >&2
	exit 1
fi
payload_hash_etag_unfiltered="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"
cp "$response_dir/body" "$response_dir/payload_hash_body_unfiltered"

http_status_code="$(http_status "$filtered_url")"
if [[ "$http_status_code" != "200" ]]; then
	echo "FAIL: json.php?section=schedule (room-filtered to a nonexistent slug) returned HTTP $http_status_code, expected 200 for the payload-hash check." >&2
	exit 1
fi
payload_hash_etag_filtered="$(grep -i '^etag:' "$response_dir/headers" | head -n1)"

if [[ "$payload_hash_etag_unfiltered" == "$payload_hash_etag_filtered" ]]; then
	echo "FAIL: an unfiltered and a room-filtered section=schedule request (identical revision, no mutation between them) returned the same ETag; the payload hash must distinguish them." >&2
	exit 1
fi
if diff -q "$response_dir/payload_hash_body_unfiltered" "$response_dir/body" >/dev/null; then
	echo "FAIL: an unfiltered and a room-filtered section=schedule request returned an identical body; the filter should have changed the representation (or the seed data has no room-tagged events to filter out)." >&2
	exit 1
fi
echo "PASS: the schedule ETag and body differ between an unfiltered and a differently-filtered request at an identical revision (payload-hash property)"

echo
echo "OnlineSched app feed integration tests (WP-CLI + HTTP) passed."
