#!/usr/bin/env bash
# HTTP checks for calendar caching against the local Furry Migration stack.
# Creates its own event and favorites row and deletes only those on exit.

set -euo pipefail

CONTAINER="${ONLINESCHED_ICAL_TEST_CONTAINER:-fm-php}"
SITE="${ONLINESCHED_ICAL_TEST_SITE:-https://furrymigration.local}"
WP=(docker exec "$CONTAINER" wp --allow-root --path=/var/www/html)
PLUGIN="$SITE/wp-content/plugins/OnlineSched"
WORK="$(mktemp -d)"
PROVIDER="ical-cache-test"
IDENTIFIER="ical-cache-test-$$"
EVENT_ID=""
FAILURES=0

if [[ "$CONTAINER" != "fm-php" || "$SITE" != "https://furrymigration.local" ]]; then
	echo "Refusing unrecognized test target: $CONTAINER $SITE" >&2
	exit 1
fi

wp_eval() {
	"${WP[@]}" eval "$1" 2>/dev/null | tail -n 1
}

site_url="$(wp_eval 'echo get_option("siteurl");')"
if [[ "$site_url" != "$SITE" ]]; then
	echo "Refusing: WordPress reports $site_url, expected $SITE" >&2
	exit 1
fi

cleanup() {
	if [[ -n "$EVENT_ID" ]]; then
		"${WP[@]}" post delete "$EVENT_ID" --force >/dev/null 2>&1 || true
	fi
	wp_eval "global \$wpdb; \$wpdb->delete(\$wpdb->prefix . 'onlinesched_favorites', array('provider' => '$PROVIDER', 'identifier' => '$IDENTIFIER'));" >/dev/null || true
	rm -rf "$WORK"
}
trap cleanup EXIT

check() {
	local label="$1" ok="$2"
	if [[ "$ok" == 1 ]]; then
		echo "PASS $label"
	else
		echo "FAIL $label"
		FAILURES=$((FAILURES + 1))
	fi
}

fetch() {
	local name="$1" url="$2"
	shift 2
	curl -sk -D "$WORK/$name.h" -o "$WORK/$name.b" "$@" "$url"
}

header() {
	grep -i "^$2:" "$WORK/$1.h" | head -n 1 | cut -d' ' -f2- | tr -d '\r'
}

status() {
	head -n 1 "$WORK/$1.h" | awk '{print $2}'
}

# An owned event a day ahead, so the test never edits real schedule data.
EVENT_ID="$(wp_eval '
$id = wp_insert_post(array(
	"post_type" => "os_event", "post_status" => "publish",
	"post_title" => "ICAL CACHE TEST EVENT", "post_content" => "Disposable calendar cache fixture.",
));
update_post_meta($id, "onlinesched_sorttime", time() + DAY_IN_SECONDS);
update_post_meta($id, "onlinesched_timelen", 60);
echo $id;')"
if [[ -z "$EVENT_ID" || "$EVENT_ID" == 0 ]]; then
	echo "Could not create the test event." >&2
	exit 1
fi
EVENT_URL="$PLUGIN/ical.php?cal-id=$EVENT_ID"

fetch first "$EVENT_URL"
# A second apart, so a "now" timestamp would make the bodies differ.
sleep 1
fetch second "$EVENT_URL"
etag="$(header first etag)"
check "two polls return identical bodies" "$(cmp -s "$WORK/first.b" "$WORK/second.b" && echo 1 || echo 0)"
check "two polls return the same ETag" "$([[ -n "$etag" && "$etag" == "$(header second etag)" ]] && echo 1 || echo 0)"
check "single-event feed is publicly cacheable" "$([[ "$(header first cache-control)" == public,* ]] && echo 1 || echo 0)"

fetch conditional "$EVENT_URL" -H "If-None-Match: $etag"
check "matching If-None-Match returns 304" "$([[ "$(status conditional)" == 304 ]] && echo 1 || echo 0)"
check "304 has an empty body" "$([[ ! -s "$WORK/conditional.b" ]] && echo 1 || echo 0)"

token="$(wp_eval "onlinesched_save_favorites_for_identity('$PROVIDER', '$IDENTIFIER', array($EVENT_ID)); echo onlinesched_get_or_mint_feed_token('$PROVIDER', '$IDENTIFIER');")"
fetch favorites "$PLUGIN/icalby.php?feed=$token"
cache_control="$(header favorites cache-control)"
check "favorites feed answers" "$([[ "$(status favorites)" == 200 && -n "$token" ]] && echo 1 || echo 0)"
check "favorites feed is private" "$([[ "$cache_control" == *private* ]] && echo 1 || echo 0)"
check "favorites feed never allows shared caching" "$([[ "$cache_control" != *public* && "$cache_control" != *s-maxage* ]] && echo 1 || echo 0)"

"${WP[@]}" post term add "$EVENT_ID" os_tag cancelled >/dev/null 2>&1
fetch cancelled "$EVENT_URL"
check "cancelling sets STATUS:CANCELLED" "$(grep -q $'^STATUS:CANCELLED\r$' "$WORK/cancelled.b" && echo 1 || echo 0)"
check "cancelling moves the ETag" "$([[ -n "$(header cancelled etag)" && "$(header cancelled etag)" != "$etag" ]] && echo 1 || echo 0)"
fetch stale "$EVENT_URL" -H "If-None-Match: $etag"
check "the old ETag no longer gets 304" "$([[ "$(status stale)" == 200 ]] && echo 1 || echo 0)"

"${WP[@]}" post term remove "$EVENT_ID" os_tag cancelled >/dev/null 2>&1
fetch restored "$EVENT_URL"
check "removing the tag restores the original ETag" "$([[ "$(header restored etag)" == "$etag" ]] && echo 1 || echo 0)"

if [[ "$FAILURES" -gt 0 ]]; then
	echo "$FAILURES calendar cache check(s) failed." >&2
	exit 1
fi
echo "All calendar cache checks passed for event $EVENT_ID."
