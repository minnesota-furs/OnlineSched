<?php
/**
 * The app feed publishes every badge type an event carries, not only sensory.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-event-badges.php \
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

$made_tags = array();
$make_tag = static function ($slug, $name, $badge_type = null) use (&$made_tags) {
	$existing = get_term_by('slug', $slug, 'os_tag');
	if ($existing) {
		wp_delete_term($existing->term_id, 'os_tag');
	}
	$term = wp_insert_term($name, 'os_tag', array('slug' => $slug));
	if (is_wp_error($term)) {
		WP_CLI::error('Could not create the fixture tag ' . $slug . ': ' . $term->get_error_message());
	}
	if (null !== $badge_type) {
		update_term_meta($term['term_id'], 'badge_type', $badge_type);
	}
	$made_tags[] = $term['term_id'];
	return $term['term_id'];
};

// A tag whose badge type comes from term meta, and one that relies on the
// built-in slug defaults.
$make_tag('aft-dance-tag', 'AFT Dance', 'Dance');
$make_tag('guest-of-honor', 'Guest of Honor');
$make_tag('essentials', 'Essentials');

$post_id = wp_insert_post(array(
	'post_type'   => 'os_event',
	'post_title'  => 'AFT Badge Fixture',
	'post_status' => 'publish',
));
if (is_wp_error($post_id) || !$post_id) {
	WP_CLI::error('Could not create the fixture event.');
}

wp_set_object_terms($post_id, array('essentials'), 'os_tag');
$check('an essentials tag publishes its badge type', array('essentials'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('guest-of-honor'), 'os_tag');
$check('a guest of honor tag publishes its badge type', array('guest-of-honor'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('aft-dance-tag'), 'os_tag');
$check('a dance badge type set in term meta is published', array('dance'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('essentials', 'guest-of-honor', 'aft-dance-tag'), 'os_tag');
$check(
	'an event carrying all three publishes all three, so the client can rank them',
	array('dance', 'essentials', 'guest-of-honor'),
	onlinesched_app_feed_event_badges($post_id)
);

wp_set_object_terms($post_id, array(), 'os_tag');
$check('an event with no tags publishes no badges', array(), onlinesched_app_feed_event_badges($post_id));

wp_delete_post($post_id, true);
foreach ($made_tags as $term_id) {
	wp_delete_term($term_id, 'os_tag');
}

if ($failures > 0) {
	WP_CLI::error($failures . ' badge publication check(s) failed.');
}
WP_CLI::success('Badge publication checks passed.');
