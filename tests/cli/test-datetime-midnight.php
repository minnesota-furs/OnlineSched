<?php
/**
 * Midnight and day-term regressions for event times.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-datetime-midnight.php \
 *     --path=/var/www/html --allow-root
 */

if (!defined('WP_CLI') || !WP_CLI) {
	echo "This test must run through WP-CLI.\n";
	exit(1);
}

$failures = 0;
$check = static function ($label, $expected, $actual) use (&$failures) {
	if ($expected === $actual) {
		WP_CLI::log('PASS: ' . $label);
		return;
	}
	$failures++;
	WP_CLI::warning("FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
};

$assert = static function ($condition, $message) use (&$failures) {
	if (!$condition) {
		$failures++;
		WP_CLI::warning('FAIL: ' . $message);
		return;
	}
	WP_CLI::log('PASS: ' . $message);
};

$midnight = onlinesched_parse_local_datetime('2026-09-11', '24:00');
$assert(
	$midnight && '2026-09-12 00:00' === $midnight->format('Y-m-d H:i'),
	'24:00 parses as next-day midnight'
);
$late = onlinesched_parse_local_datetime('2026-09-11', '23:59');
$assert(
	$late && '2026-09-11 23:59' === $late->format('Y-m-d H:i'),
	'23:59 stays on its own day'
);
$assert(
	false === onlinesched_parse_local_datetime('2026-09-11', '25:00'),
	'25:00 is still refused'
);
$assert(
	false !== onlinesched_parse_local_datetime('9/11/2026', '24:30'),
	'24:30 works with imported date formats'
);

// Similar names ensure the save uses the assigned term.
$decoy = wp_insert_term('Friday (2025 archive)', 'os_day', array('description' => '2025-09-05'));
$real = wp_insert_term('Friday (save handler fixture)', 'os_day', array('description' => '2026-09-11'));
$event_id = 0;

try {
	foreach (array($decoy, $real) as $term) {
		if (is_wp_error($term)) {
			WP_CLI::error('Could not build the day-term fixture: ' . $term->get_error_message());
		}
	}
	$matches = get_terms(array('taxonomy' => 'os_day', 'search' => 'Friday', 'hide_empty' => false));
	$assert(
		!is_wp_error($matches) && count($matches) > 1,
		'the fixture makes a weekday-name search ambiguous'
	);

	$event_id = wp_insert_post(array(
		'post_type' => 'os_event',
		'post_title' => 'Datetime save fixture',
		'post_status' => 'draft',
	), true);
	if (is_wp_error($event_id)) {
		WP_CLI::error('Could not create the fixture event: ' . $event_id->get_error_message());
	}
	wp_set_post_terms($event_id, array((int) $real['term_id']), 'os_day');

	$user_id = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
	wp_set_current_user($user_id ? (int) $user_id[0] : 1);

	$save = static function ($hour, $minute) use ($event_id) {
		$_POST = array(
			'onlinesched_timeslot_nonce' => wp_create_nonce('onlinesched_save_timeslot'),
			'os_event_time_hr' => $hour,
			'os_event_time_min' => $minute,
			'onlinesched_time_hr' => $hour,
			'onlinesched_time_min' => $minute,
		);
		OnlineSched_add_timeslot_fields($event_id, get_post($event_id));
		$_POST = array();
		return (int) get_post_meta($event_id, 'onlinesched_sorttime', true);
	};

	$expected = onlinesched_parse_local_datetime('2026-09-11', '14:30');
	$check('the assigned day term determines the sort time',
		$expected ? $expected->getTimestamp() : -1, $save('14', '30'));

	$good = $save('14', '30');
	$check('a malformed hour cannot zero a stored sort time', $good, $save('99', '30'));
		$check('and the stored hour is left alone', '14',
		(string) get_post_meta($event_id, 'onlinesched_time_hr', true));
	$check('a malformed minute cannot zero it either', $good, $save('14', 'xx'));
	$check('the editor is told the time was refused', true,
		false !== get_transient('onlinesched_timeslot_refusal_' . get_current_user_id() . '_' . $event_id));

	// Verify midnight through the save handler.
	$midnight_save = onlinesched_parse_local_datetime('2026-09-11', '24:00');
	$check('24:00 still saves through the handler',
		$midnight_save ? $midnight_save->getTimestamp() : -1, $save('24', '00'));
} finally {
	if ($event_id && !is_wp_error($event_id)) {
		delete_transient('onlinesched_timeslot_refusal_' . get_current_user_id() . '_' . $event_id);
		wp_delete_post($event_id, true);
	}
	foreach (array($decoy, $real) as $term) {
		if (!is_wp_error($term)) {
			wp_delete_term((int) $term['term_id'], 'os_day');
		}
	}
}

if ($failures > 0) {
	WP_CLI::error("$failures datetime check(s) failed.");
}
WP_CLI::success('Midnight datetime checks passed.');
