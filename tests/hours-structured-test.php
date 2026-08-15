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
$con_start = function () {
	return '2026-09-10';
};
$con_end = function () {
	return '2026-09-14';
};
add_filter('pre_option_timezone_string', $timezone);
add_filter('pre_option_onlinesched_con_start', $con_start);
add_filter('pre_option_onlinesched_con_end', $con_end);
$wrapper = OnlineSchedHoursRenderer::render_wrapper(array(), '<section>Hours</section>');
$check('wrapper publishes the convention timezone', 1, substr_count($wrapper, 'data-timezone="America/Chicago"'));
$check('wrapper publishes the operational start date', 1, substr_count($wrapper, 'data-operational-start="2026-09-10"'));
$check('wrapper publishes the operational end date', 1, substr_count($wrapper, 'data-operational-end="2026-09-14"'));
remove_filter('pre_option_timezone_string', $timezone);
remove_filter('pre_option_onlinesched_con_start', $con_start);
remove_filter('pre_option_onlinesched_con_end', $con_end);

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
					array(
						'blockName' => 'onlinesched/hours-time',
						'attrs'     => array(
							'start'  => '08:00',
							'end'    => '09:00',
							'access' => 'sponsor_and_super_sponsor',
						),
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
$check('public entries omit the access field', false, array_key_exists('access', $entry));
$early_entry = $departments[0]['days'][0]['entries'][1] ?? array();
$check(
	'app feed publishes restricted access',
	'sponsor_and_super_sponsor',
	$early_entry['access'] ?? null
);

// The website badge reads these data attributes; a restricted window must
// carry data-access so the public badge never counts it.
foreach (array('sponsor_and_super_sponsor', 'super_sponsor') as $tier) {
	$html = OnlineSchedHoursRenderer::render_time(array(
		'hours' => '', 'smallText' => '', 'start' => '08:00', 'end' => '09:00',
		'access' => $tier,
	));
	$check("renderer marks $tier for the badge", true,
		false !== strpos($html, 'data-access="' . $tier . '"'));
}
$html = OnlineSchedHoursRenderer::render_time(array(
	'hours' => '', 'smallText' => '', 'start' => '09:00', 'end' => '17:00',
));
$check('a public row carries no access mark', false, strpos($html, 'data-access'));

if ($failures > 0) {
	WP_CLI::error("structured hours: $failures failure(s).");
}
WP_CLI::success('Structured Hours checks passed.');
