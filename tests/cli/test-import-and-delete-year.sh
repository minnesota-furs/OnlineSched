#!/usr/bin/env bash

set -euo pipefail

if [[ "${ONLINESCHED_CLI_TEST_ALLOW_DESTRUCTIVE:-}" != "1" ]]; then
	echo "Refusing destructive CLI tests without ONLINESCHED_CLI_TEST_ALLOW_DESTRUCTIVE=1." >&2
	exit 1
fi

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONTAINER="${ONLINESCHED_CLI_TEST_CONTAINER:-onlinesched-vanilla-cli}"
WP=(docker exec "$CONTAINER" wp --allow-root --path=/var/www/html)
CONTAINER_PLUGIN="/var/www/html/wp-content/plugins/OnlineSched"
FIXTURE="$PLUGIN_DIR/tests/fixtures/generated/cli-furry-test-data.csv"
CONTAINER_FIXTURE="$CONTAINER_PLUGIN/tests/fixtures/generated/cli-furry-test-data.csv"
FAILURE_FIXTURE="$PLUGIN_DIR/tests/fixtures/generated/cli-term-failure.csv"
CONTAINER_FAILURE_FIXTURE="$CONTAINER_PLUGIN/tests/fixtures/generated/cli-term-failure.csv"
VARIANT_DIR="$PLUGIN_DIR/tests/fixtures/generated/cli-variants"
CONTAINER_VARIANT_DIR="$CONTAINER_PLUGIN/tests/fixtures/generated/cli-variants"
TARGET_YEAR="2197"
OTHER_YEAR="2198"
PREFLIGHT_YEAR="2196"

if [[ "$CONTAINER" != "onlinesched-vanilla-cli" ]]; then
	echo "Refusing unrecognized CLI container: $CONTAINER" >&2
	exit 1
fi

site_url="$("${WP[@]}" option get siteurl)"
if [[ "$site_url" != "http://localhost:8081" ]]; then
	echo "Refusing unrecognized WordPress site URL: $site_url" >&2
	exit 1
fi

if [[ "${ONLINESCHED_CLI_TEST_REJECTION_PROBE:-}" != "1" ]]; then
	set +e
	rejection_output="$(ONLINESCHED_CLI_TEST_REJECTION_PROBE=1 ONLINESCHED_CLI_TEST_CONTAINER=fm-php bash "$0" 2>&1)"
	rejection_status=$?
	set -e
	if [[ $rejection_status -eq 0 || "$rejection_output" != *"Refusing unrecognized CLI container: fm-php"* ]]; then
		echo "Harness did not refuse the main Furry Migration container." >&2
		exit 1
	fi
fi

original_active_year="$("${WP[@]}" option get onlinesched_year 2>/dev/null || true)"
cleanup() {
	"${WP[@]}" onlinesched delete-year "$TARGET_YEAR" --yes >/dev/null 2>&1 || true
	"${WP[@]}" onlinesched delete-year "$OTHER_YEAR" --yes >/dev/null 2>&1 || true
	"${WP[@]}" onlinesched delete-year "$PREFLIGHT_YEAR" --yes >/dev/null 2>&1 || true
	"${WP[@]}" onlinesched delete-year 2199 --yes >/dev/null 2>&1 || true
	"${WP[@]}" option update onlinesched_year "$original_active_year" >/dev/null 2>&1 || true
}
trap cleanup EXIT

count_year() {
	"${WP[@]}" post list --post_type=os_event --post_status=publish \
		--meta_key=onlinesched_year --meta_value="$1" --format=count
}

count_year_all_statuses() {
	docker exec -e ONLINESCHED_TEST_YEAR="$1" "$CONTAINER" wp --allow-root --path=/var/www/html eval '$q=new WP_Query(["post_type"=>"os_event","post_status"=>array_values(get_post_stati([],"names")),"posts_per_page"=>-1,"fields"=>"ids","meta_key"=>"onlinesched_year","meta_value"=>getenv("ONLINESCHED_TEST_YEAR")]); echo count($q->posts);'
}

snapshot_year() {
	docker exec -e ONLINESCHED_TEST_YEAR="$1" "$CONTAINER" wp --allow-root --path=/var/www/html eval '$year=getenv("ONLINESCHED_TEST_YEAR"); $q=new WP_Query(["post_type"=>"os_event","post_status"=>array_values(get_post_stati([],"names")),"posts_per_page"=>-1,"fields"=>"ids","meta_key"=>"onlinesched_year","meta_value"=>$year,"orderby"=>"ID","order"=>"ASC"]); $events=[]; foreach($q->posts as $id){$terms=[]; foreach(["os_room","os_day","os_panelist","os_tag"] as $tax){$terms[$tax]=wp_get_post_terms($id,$tax,["fields"=>"names"]); sort($terms[$tax]);} $events[get_post_meta($id,"onlinesched_external_event_id",true)]=[$id,get_post_field("post_title",$id),get_post_field("post_content",$id),get_post_meta($id,"onlinesched_sorttime",true),get_post_meta($id,"onlinesched_timelen",true),$terms];} ksort($events); echo hash("sha256",wp_json_encode($events));'
}

expect_failure() {
	if "$@" >/dev/null 2>&1; then
		echo "Expected command to fail: $*" >&2
		exit 1
	fi
}

expect_failure_with_output() {
	local expected="$1"
	shift
	set +e
	local output
	output="$("$@" 2>&1)"
	local status=$?
	set -e
	if [[ $status -eq 0 ]]; then
		echo "Expected command to fail: $*" >&2
		exit 1
	fi
	if [[ "$output" != *"$expected"* ]]; then
		echo "Failure output did not contain '$expected':" >&2
		echo "$output" >&2
		exit 1
	fi
}

php "$PLUGIN_DIR/tests/fixtures/test-generate-furry-test-data.php"
php "$PLUGIN_DIR/tests/fixtures/generate-furry-test-data.php" \
	--start-date=2027-06-30 --days=4 --output="$FIXTURE" >/dev/null
php "$PLUGIN_DIR/tests/cli/build-import-fixtures.php" "$FIXTURE" "$VARIANT_DIR"

"${WP[@]}" onlinesched delete-year "$TARGET_YEAR" --yes >/dev/null
"${WP[@]}" onlinesched delete-year "$OTHER_YEAR" --yes >/dev/null
"${WP[@]}" option update onlinesched_year cli-active-year >/dev/null
active_year_preview="$("${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --dry-run)"
[[ "$active_year_preview" == *"Schedule year: cli-active-year"* ]]

"${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$TARGET_YEAR"
[[ "$(count_year "$TARGET_YEAR")" == "150" ]]
first_snapshot="$(snapshot_year "$TARGET_YEAR")"

anchor_id="$(docker exec -e ONLINESCHED_TEST_YEAR="$TARGET_YEAR" "$CONTAINER" wp --allow-root --path=/var/www/html eval '$q=new WP_Query(["post_type"=>"os_event","post_status"=>array_values(get_post_stati([],"names")),"fields"=>"ids","posts_per_page"=>2,"meta_query"=>[["key"=>"onlinesched_year","value"=>getenv("ONLINESCHED_TEST_YEAR")],["key"=>"onlinesched_external_event_id","value"=>"4126"]]]); echo $q->posts[0];')"
docker exec -e ONLINESCHED_TEST_POST_ID="$anchor_id" "$CONTAINER" wp --allow-root --path=/var/www/html eval '$id=(int)getenv("ONLINESCHED_TEST_POST_ID"); $expected=(new DateTimeImmutable("2027-07-03 17:45",wp_timezone()))->getTimestamp(); if((int)get_post_meta($id,"onlinesched_sorttime",true)!==$expected){throw new RuntimeException("Timestamp anchor mismatch.");} foreach(["os_room","os_day","os_panelist","os_tag"] as $tax){if(is_wp_error(wp_get_post_terms($id,$tax))){throw new RuntimeException("Term read failed: ".$tax);}} echo "timestamp and terms verified\n";'

"${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$TARGET_YEAR"
[[ "$(count_year "$TARGET_YEAR")" == "150" ]]
[[ "$(snapshot_year "$TARGET_YEAR")" == "$first_snapshot" ]]

"${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/modified.csv" --year="$TARGET_YEAR"
[[ "$(count_year "$TARGET_YEAR")" == "150" ]]
docker exec -e ONLINESCHED_TEST_POST_ID="$anchor_id" "$CONTAINER" wp --allow-root --path=/var/www/html eval '$id=(int)getenv("ONLINESCHED_TEST_POST_ID"); $expected=(new DateTimeImmutable("2027-07-01 16:00",wp_timezone()))->getTimestamp(); if(get_post_field("post_title",$id)!=="Sound Design for Games - CLI Update" || get_post_field("post_content",$id)!=="Updated through the CLI integration fixture." || get_post_meta($id,"onlinesched_timelen",true)!=="75" || (int)get_post_meta($id,"onlinesched_sorttime",true)!==$expected || get_post_meta($id,"onlinesched_time_hr",true)!=="16" || get_post_meta($id,"onlinesched_time_min",true)!=="00"){throw new RuntimeException("Changed row fields or timestamp did not update in place.");} $terms=[]; foreach(["os_room","os_day","os_panelist","os_tag"] as $tax){$terms[$tax]=wp_get_post_terms($id,$tax,["fields"=>"names"]); sort($terms[$tax]);} if($terms["os_room"]!==["Dealers Den"] || $terms["os_day"]!==["Thursday"] || $terms["os_panelist"]!==["Brushfox","Coyote Tester"] || $terms["os_tag"]!==["Games","Updated"]){throw new RuntimeException("Changed taxonomy terms did not update in place: ".wp_json_encode($terms));} echo "changed row verified\n";'
"${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$TARGET_YEAR" >/dev/null
[[ "$(snapshot_year "$TARGET_YEAR")" == "$first_snapshot" ]]

before_dry_run="$(snapshot_year "$TARGET_YEAR")"
dry_run_output="$("${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/modified.csv" --year="$TARGET_YEAR" --dry-run)"
[[ "$dry_run_output" == *"would insert: 0; would update: 150; skipped: 0; failed: 0"* ]]
[[ "$(snapshot_year "$TARGET_YEAR")" == "$before_dry_run" ]]
[[ "$("${WP[@]}" option get onlinesched_year)" == "cli-active-year" ]]

"${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$OTHER_YEAR"
[[ "$(count_year "$OTHER_YEAR")" == "150" ]]
other_snapshot="$(snapshot_year "$OTHER_YEAR")"

for status in draft private pending trash; do
	"${WP[@]}" post update "$anchor_id" --post_status="$status" >/dev/null
	"${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$TARGET_YEAR" >/dev/null
	[[ "$("${WP[@]}" post get "$anchor_id" --field=post_status)" == "publish" ]]
	[[ "$(count_year "$TARGET_YEAR")" == "150" ]]
done

printf '%s\n' \
	'ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags' \
	'8999,Successful first row,2027-06-30,09:00,Must persist,Main Stage,Tester,30,Test' \
	'9000,Forced failure,2027-06-30,10:00,Failure row,Forced Failure Room,Tester,60,Test' \
	> "$FAILURE_FIXTURE"
term_preview="$("${WP[@]}" onlinesched import "$CONTAINER_FAILURE_FIXTURE" --year=2199 --dry-run)"
[[ "$term_preview" == *"os_room:Forced Failure Room"* ]]
docker exec -e ONLINESCHED_TEST_CSV="$CONTAINER_FAILURE_FIXTURE" -e ONLINESCHED_TEST_YEAR=2199 \
	"$CONTAINER" wp --allow-root --path=/var/www/html eval-file "$CONTAINER_PLUGIN/tests/cli/test-service-state.php"

duplicate_id="$("${WP[@]}" post create --post_type=os_event --post_status=publish --post_title='Ambiguous duplicate' --porcelain)"
"${WP[@]}" post meta update "$duplicate_id" onlinesched_year "$TARGET_YEAR" >/dev/null
"${WP[@]}" post meta update "$duplicate_id" onlinesched_external_event_id 4126 >/dev/null
expect_failure "${WP[@]}" onlinesched import "$CONTAINER_FIXTURE" --year="$TARGET_YEAR"
"${WP[@]}" post delete "$duplicate_id" --force >/dev/null

expect_failure_with_output "line 3, event 4000" "${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/duplicate-id.csv" --year="$PREFLIGHT_YEAR"
expect_failure_with_output "line 2: The external event ID is empty" "${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/empty-id.csv" --year="$PREFLIGHT_YEAR"
expect_failure_with_output "line 1: CSV file format is incorrect" "${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/bad-header.csv" --year="$PREFLIGHT_YEAR"
expect_failure_with_output "line 2, event 4000: The row has an invalid date or time" "${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/invalid-date.csv" --year="$PREFLIGHT_YEAR"
expect_failure_with_output "line 2: The row has fewer than nine fields" "${WP[@]}" onlinesched import "$CONTAINER_VARIANT_DIR/short-row.csv" --year="$PREFLIGHT_YEAR"
[[ "$(count_year "$PREFLIGHT_YEAR")" == "0" ]]

"${WP[@]}" post update "$anchor_id" --post_status=trash >/dev/null
delete_preview="$("${WP[@]}" onlinesched delete-year "$TARGET_YEAR" --dry-run)"
[[ "$delete_preview" == *"Matching events: 150"* ]]
[[ "$(count_year_all_statuses "$TARGET_YEAR")" == "150" ]]
"${WP[@]}" onlinesched delete-year "$TARGET_YEAR" --yes
[[ "$(count_year_all_statuses "$TARGET_YEAR")" == "0" ]]
[[ "$(count_year "$OTHER_YEAR")" == "150" ]]
[[ "$(snapshot_year "$OTHER_YEAR")" == "$other_snapshot" ]]
[[ "$("${WP[@]}" option get onlinesched_year)" == "cli-active-year" ]]
"${WP[@]}" onlinesched delete-year "$TARGET_YEAR" --yes >/dev/null

expect_failure_with_output "placeholder is not a valid year" "${WP[@]}" onlinesched delete-year 'Event Schedule Year' --yes
expect_failure_with_output "nonempty schedule year is required" "${WP[@]}" onlinesched delete-year '' --yes
expect_failure "${WP[@]}" onlinesched delete-year
expect_failure_with_output "readable regular file" "${WP[@]}" onlinesched import /definitely/missing.csv --year="$TARGET_YEAR"

echo "OnlineSched WP-CLI import and delete-year integration tests passed."
