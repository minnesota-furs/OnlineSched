<?php
/**
 * Meta essentials export regressions.
 *
 * Run on the FM dev stack:
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/essentials-feed-test.php \
 *     --path=/var/www/html --allow-root
 *
 * Snapshots both options first and restores them afterward. Covers:
 *  1. configured label and tags reach the Meta payload;
 *  2. slugs are sanitized, deduplicated, and deterministically ordered;
 *  3. a valid empty selection stays empty, never the legacy slug;
 *  4. a blank label falls back to Essentials;
 *  5. the Meta fingerprint moves when either option changes.
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
	WP_CLI::warning("FAIL: $label");
	WP_CLI::log('  expected: ' . var_export($expected, true));
	WP_CLI::log('  actual:   ' . var_export($actual, true));
};

if (!function_exists('onlinesched_app_feed_meta')) {
	require_once WP_PLUGIN_DIR . '/OnlineSched/lib/app-feed.php';
}

$saved_tags  = get_option('onlinesched_essentials_tags', array());
$saved_label = get_option('onlinesched_essentials_tab_name', '');
$essentials  = static function () {
	return onlinesched_app_feed_meta()['essentials'];
};

update_option('onlinesched_essentials_tags', array('guest-of-honor', 'essentials'));
update_option('onlinesched_essentials_tab_name', 'Must See');
$check('configured label reaches Meta', 'Must See', $essentials()['label']);
$check(
	'configured tags reach Meta in deterministic order',
	array('essentials', 'guest-of-honor'),
	$essentials()['tags']
);

update_option('onlinesched_essentials_tags', array('VIP Lounge', 'vip-lounge', '', 'Guest Of Honor'));
$check(
	'tags are sanitized, deduplicated, and sorted',
	array('guest-of-honor', 'vip-lounge'),
	$essentials()['tags']
);

update_option('onlinesched_essentials_tags', array());
$check('a valid empty selection stays empty', array(), $essentials()['tags']);

update_option('onlinesched_essentials_tab_name', '   ');
$check('a blank label falls back', 'Essentials', $essentials()['label']);

$before = onlinesched_app_feed_meta_fingerprint();
update_option('onlinesched_essentials_tags', array('essentials'));
$after_tags = onlinesched_app_feed_meta_fingerprint();
if ($before === $after_tags) {
	$failures++;
	WP_CLI::warning('FAIL: the fingerprint must move when the tag set changes');
} else {
	WP_CLI::log('PASS: the fingerprint moves when the tag set changes');
}
update_option('onlinesched_essentials_tab_name', 'Featured');
$after_label = onlinesched_app_feed_meta_fingerprint();
if ($after_tags === $after_label) {
	$failures++;
	WP_CLI::warning('FAIL: the fingerprint must move when the label changes');
} else {
	WP_CLI::log('PASS: the fingerprint moves when the label changes');
}

update_option('onlinesched_essentials_tags', $saved_tags);
update_option('onlinesched_essentials_tab_name', $saved_label);

if ($failures > 0) {
	WP_CLI::error("essentials feed tests: $failures failure(s).");
}
WP_CLI::success('essentials feed tests passed.');
