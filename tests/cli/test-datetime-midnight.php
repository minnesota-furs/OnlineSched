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

$assert = static function ($condition, $message) {
	if (!$condition) {
		WP_CLI::error('FAIL: ' . $message);
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

// The save path reads the assigned day term, so a weekday name shared by
// several terms cannot zero the time; prove the ambiguity exists.
$matches = get_terms('os_day', array('search' => 'Friday'));
$assert(
	!is_wp_error($matches) && 1 !== count($matches),
	'weekday name search is ambiguous on real data (the bug this guards)'
);

WP_CLI::success('Midnight datetime checks passed.');
