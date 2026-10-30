<?php
/**
 * Refresh hints and validators for calendar feeds. Read-only: no options,
 * posts or files are written.
 *
 * Run: wp eval-file wp-content/plugins/OnlineSched/tests/cli/test-ical-refresh.php
 */

require_once dirname(__DIR__, 2) . '/lib/ical.php';

$window = array(1000, 2000);
$cases = array(
	array('past single event gets the long hint', onlinesched_ical_refresh_interval(1400, 1500, $window), 'P30D'),
	array('upcoming single event during the con', onlinesched_ical_refresh_interval(1800, 1500, $window), 'PT1H'),
	array('feed during the con', onlinesched_ical_refresh_interval(null, 1500, $window), 'PT1H'),
	array('feed before the con', onlinesched_ical_refresh_interval(null, 500, $window), 'P1D'),
	array('feed at the exclusive window end', onlinesched_ical_refresh_interval(null, 2000, $window), 'P1D'),
	array('feed with no con dates', onlinesched_ical_refresh_interval(null, 1500, null), 'P1D'),
	array('dateless event is not treated as past', onlinesched_ical_refresh_interval(0, 1500, $window), 'PT1H'),
	array('exact ETag matches', onlinesched_ical_etag_matches('"abc"', '"abc"'), true),
	array('weak ETag in a list matches', onlinesched_ical_etag_matches('"x", W/"abc"', '"abc"'), true),
	array('wildcard matches', onlinesched_ical_etag_matches('*', '"abc"'), true),
	array('different ETag does not match', onlinesched_ical_etag_matches('"abd"', '"abc"'), false),
	array('missing header does not match', onlinesched_ical_etag_matches('', '"abc"'), false),
);

$header = onlinesched_ical_calendar_header('Test', 'P30D');
$cases[] = array('header carries REFRESH-INTERVAL', false !== strpos($header, "REFRESH-INTERVAL;VALUE=DURATION:P30D\r\n"), true);
$cases[] = array('header carries X-PUBLISHED-TTL', false !== strpos($header, "X-PUBLISHED-TTL:P30D\r\n"), true);

$dated = get_posts(array(
	'post_type' => 'os_event', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids',
	'meta_query' => array(
		array('key' => 'onlinesched_sorttime', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
		array('key' => 'onlinesched_timelen', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
	),
));
if (empty($dated)) {
	WP_CLI::error('No dated event with a duration to check calendar links against. Run npm run test:seed first.');
}
$event_id = (int) $dated[0];
$event_start = (int) get_post_meta($event_id, 'onlinesched_sorttime', true);
$upcoming = onlinesched_single_event_calendar_links($event_id, $event_start - 60);
$running = onlinesched_single_event_calendar_links($event_id, $event_start + 60);
$finished = onlinesched_single_event_calendar_links($event_id, $upcoming['end'] + 60);
$cases[] = array('upcoming event links subscribe', 0 === strpos($upcoming['ical'], 'webcal://') && false !== strpos($upcoming['ical'], 'cal-id=' . $event_id), true);
$cases[] = array('upcoming event links Google to the feed', false !== strpos($upcoming['google'], 'calendar/r?cid='), true);
$cases[] = array('an event in progress can still subscribe', $running['ical'], $upcoming['ical']);
$cases[] = array('finished event exposes no Apple feed', $finished['ical'], '#');
$cases[] = array('finished event exposes no Google feed', $finished['google'], '#');
$cases[] = array('finished event keeps its end time', $finished['end'], $upcoming['end']);

$failures = 0;
foreach ($cases as $case) {
	list($label, $actual, $expected) = $case;
	$ok = $actual === $expected;
	$failures += $ok ? 0 : 1;
	WP_CLI::log(($ok ? 'PASS ' : 'FAIL ') . $label . ' => ' . var_export($actual, true));
}

if ($failures) {
	WP_CLI::error($failures . ' calendar refresh check(s) failed.');
}
WP_CLI::success(count($cases) . ' calendar refresh checks passed.');
