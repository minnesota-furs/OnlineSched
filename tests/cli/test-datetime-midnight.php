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
$fixture_id = wp_generate_uuid4();
$decoy_name = 'Friday archive ' . $fixture_id;
$real_name = 'Friday save fixture ' . $fixture_id;
$unknown_name = 'Nonesuchday ' . $fixture_id;
$decoy = wp_insert_term($decoy_name, 'os_day', array('description' => '2025-09-05'));
$real = wp_insert_term($real_name, 'os_day', array('description' => '2026-09-11'));
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

	$save = static function ($hour, $minute, $day_name = null) use ($event_id) {
		$_POST = array(
			'onlinesched_timeslot_nonce' => wp_create_nonce('onlinesched_save_timeslot'),
			'os_event_time_hr' => $hour,
			'os_event_time_min' => $minute,
		);
		if (null !== $day_name) {
			$_POST['os_day'] = $day_name;
		}
		OnlineSched_add_timeslot_fields($event_id, get_post($event_id));
		$_POST = array();
		return (int) get_post_meta($event_id, 'onlinesched_sorttime', true);
	};

	$assigned_day = static function () use ($event_id) {
		$terms = wp_get_post_terms($event_id, 'os_day');
		return (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : '';
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

	// A day change carrying an unusable time must move neither.
	$good = $save('14', '30', $real_name);
	$check('a new day with an invalid time changes nothing', $good,
		$save('99', '30', $decoy_name));
	$check('and the day is still the one that parsed', $real_name,
		$assigned_day());
	$check('and the stored hour is still the one that parsed', '14',
		(string) get_post_meta($event_id, 'onlinesched_time_hr', true));

	// An unknown name would otherwise be created as a term with no date.
	$check('a day that is not on the schedule is refused', $good,
		$save('14', '30', $unknown_name));
	$check('and leaves the assigned day alone', $real_name,
		$assigned_day());
	$check('and does not become a new term', false,
		(bool) get_term_by('name', $unknown_name, 'os_day'));

	// A valid day change still goes through.
	$archive = onlinesched_parse_local_datetime('2025-09-05', '14:30');
	$check('a valid day change is saved',
		$archive ? $archive->getTimestamp() : -1,
		$save('14', '30', $decoy_name));
	$check('and moves the assigned day', $decoy_name, $assigned_day());
	$save('14', '30', $real_name);

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
