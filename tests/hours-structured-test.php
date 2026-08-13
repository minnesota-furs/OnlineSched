<?php
/**
 * Structured Hours contract checks.
 *
 * Run with:
 * docker exec fm-php wp eval-file \
 *   wp-content/plugins/OnlineSched/tests/hours-structured-test.php \
 *   --path=/var/www/html --allow-root
 */

if (!defined('WP_CLI') || !WP_CLI) {
	echo "This test must run through WP-CLI.\n";
	exit(1);
}

$failures = 0;
$check = function ($label, $expected, $actual) use (&$failures) {
	if ($expected === $actual) {
		WP_CLI::log("PASS: $label");
		return;
	}
	$failures++;
	WP_CLI::warning("FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
};

$timezone = function () {
	return 'America/Chicago';
};
add_filter('pre_option_timezone_string', $timezone);
$wrapper = OnlineSchedHoursRenderer::render_wrapper(array(), '<section>Hours</section>');
$check('wrapper publishes the convention timezone', 1, substr_count($wrapper, 'data-timezone="America/Chicago"'));
remove_filter('pre_option_timezone_string', $timezone);

$time = OnlineSchedHoursRenderer::render_time(
	array(
		'hours' => 'legacy text',
		'start' => '09:00',
		'end'   => '17:30',
	)
);
$check('structured time replaces legacy display text', 1, substr_count($time, '>9am - 5:30pm<'));
$check('structured time publishes its range', 1, substr_count($time, 'data-start="09:00" data-end="17:30"'));

$blocks = array(
	array(
		'blockName'   => 'onlinesched/hours-department',
		'attrs'       => array('department' => 'Registration'),
		'innerBlocks' => array(
			array(
				'blockName'   => 'onlinesched/hours-day',
				'attrs'       => array('day' => 'Friday'),
				'innerBlocks' => array(
					array(
						'blockName' => 'onlinesched/hours-time',
						'attrs'     => array('start' => '09:00', 'end' => '17:30'),
					),
				),
			),
		),
	),
);
$departments = onlinesched_app_feed_collect_hours_departments($blocks);
$entry = $departments[0]['days'][0]['entries'][0] ?? array();
$check('structured-only entry remains in the app feed', '9am - 5:30pm', $entry['hours_text'] ?? null);
$check('app feed publishes the opening time', '09:00', $entry['start'] ?? null);
$check('app feed publishes the closing time', '17:30', $entry['end'] ?? null);

if ($failures > 0) {
	WP_CLI::error("structured hours: $failures failure(s).");
}
WP_CLI::success('Structured Hours checks passed.');
