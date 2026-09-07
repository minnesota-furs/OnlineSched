<?php
/**
 * Badge class regressions.
 *
 * Run on the FM dev stack:
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/badge-class-test.php \
 *     --path=/var/www/html --allow-root
 *
 * Renames the Essentials badge type to Essential for the run, renders the
 * schedule with a temporary tagged event, and checks that the badge keeps
 * its os-badge--essentials class while showing the new name; a Guest Of
 * Honor badge keeps its fixed class and row class. Restores everything.
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

$option_names = array(
	'onlinesched_badge_types',
	'onlinesched_badge_types_display',
	'onlinesched_badge_types_colors',
	'onlinesched_badge_types_fg_colors',
	'onlinesched_badge_types_icons',
	'onlinesched_badge_types_row_colors',
	ONLINESCHED_BADGE_TYPE_KEYS_OPTION,
);
$saved = array();
foreach ($option_names as $name) {
	$saved[$name] = get_option($name);
}

$year = get_option('onlinesched_year');
$types = get_option('onlinesched_badge_types', array());
$types = array_values(array_diff($types, array('Essentials', 'Essential')));
$types[] = 'Essential';
if (!in_array('Guest Of Honor', $types, true)) {
	$types[] = 'Guest Of Honor';
}
update_option('onlinesched_badge_types', $types);
$display = get_option('onlinesched_badge_types_display', array());
$display['Essential'] = true;
$display['Guest Of Honor'] = true;
update_option('onlinesched_badge_types_display', $display);
$keys = onlinesched_get_badge_type_keys();
$keys['Essential'] = 'essentials';
onlinesched_save_badge_type_keys($keys);

$tag = wp_insert_term('Badge Class Test Essential', 'os_tag', array('slug' => 'badge-class-test-essential'));
$goh = wp_insert_term('Badge Class Test GOH', 'os_tag', array('slug' => 'badge-class-test-goh'));
$tag_id = is_wp_error($tag) ? 0 : (int) $tag['term_id'];
$goh_id = is_wp_error($goh) ? 0 : (int) $goh['term_id'];
update_term_meta($tag_id, 'badge_type', 'Essential');
update_term_meta($goh_id, 'badge_type', 'Guest Of Honor');

$post_id = wp_insert_post(array(
	'post_type' => 'os_event',
	'post_status' => 'publish',
	'post_title' => 'Badge Class Test Event',
));
update_post_meta($post_id, 'onlinesched_year', $year);
update_post_meta($post_id, 'onlinesched_sorttime', 1);
update_post_meta($post_id, 'onlinesched_timelen', 60);
wp_set_post_terms($post_id, array($tag_id, $goh_id), 'os_tag');

$html = onlinesched_render_schedule();
$row_start = strpos($html, 'id="onlineevt-' . $post_id . '"');
$row = false === $row_start ? '' : substr($html, $row_start, 4000);

$check('the test event rendered', true, '' !== $row);
// A configured icon sits between the tag and the label; the class is the claim.
$check('renamed badge keeps the essentials class', 1, preg_match("/os-badge--essentials'[^>]*>(?:<i[^>]*><\/i>\s*)?Essential</", $row));
$check('no class is built from the display name', 0, preg_match("/os-badge--essential'/", $row));
$check('guest of honor keeps its fixed badge class', 1, preg_match("/os-badge--goh'[^>]*>(?:<i[^>]*><\/i>\s*)?Guest Of Honor</", $row));
$check('guest of honor keeps its row class', 1, preg_match('/class="os-row schedule-item[^"]* goh[ "]/', $row));

wp_delete_post($post_id, true);
if ($tag_id) {
	wp_delete_term($tag_id, 'os_tag');
}
if ($goh_id) {
	wp_delete_term($goh_id, 'os_tag');
}
foreach ($saved as $name => $value) {
	if (false === $value) {
		delete_option($name);
	} else {
		update_option($name, $value);
	}
}

if ($failures > 0) {
	WP_CLI::error("$failures check(s) failed.");
}
WP_CLI::success('Badge class checks passed.');
