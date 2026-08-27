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

// Never replace an existing term while creating fixtures.
$make_tag = static function ($slug, $name, $badge_type) use (&$made_tags) {
	if (0 !== strpos($slug, 'aft-badge-')) {
		WP_CLI::error('Fixture slugs must be prefixed aft-badge-, refusing ' . $slug);
	}
	if (get_term_by('slug', $slug, 'os_tag')) {
		WP_CLI::error('Fixture slug ' . $slug . ' already exists; refusing to touch it.');
	}
	$term = wp_insert_term($name, 'os_tag', array('slug' => $slug));
	if (is_wp_error($term)) {
		WP_CLI::error('Could not create the fixture tag ' . $slug . ': ' . $term->get_error_message());
	}
	update_term_meta($term['term_id'], 'badge_type', $badge_type);
	$made_tags[$slug] = $term['term_id'];
	return $term['term_id'];
};

$make_tag('aft-badge-dance', 'AFT Badge Dance', 'Dance');
$make_tag('aft-badge-goh', 'AFT Badge GoH', 'Guest Of Honor');
$make_tag('aft-badge-essentials', 'AFT Badge Essentials', 'Essentials');
$make_tag('aft-badge-adult', 'AFT Badge Adult', 'Adult');

$original_type_keys = get_option(ONLINESCHED_BADGE_TYPE_KEYS_OPTION, false);
onlinesched_ensure_badge_type_keys(array('Adult'));

$post_id = wp_insert_post(array(
	'post_type'   => 'os_event',
	'post_title'  => 'AFT Badge Fixture',
	'post_status' => 'publish',
));
if (is_wp_error($post_id) || !$post_id) {
	WP_CLI::error('Could not create the fixture event.');
}

wp_set_object_terms($post_id, array('aft-badge-essentials'), 'os_tag');
$check('an essentials tag publishes its badge type', array('essentials'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('aft-badge-goh'), 'os_tag');
$check('a guest of honor tag publishes its badge type', array('guest-of-honor'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('aft-badge-dance'), 'os_tag');
$check('a badge type set in term meta is published', array('dance'), onlinesched_app_feed_event_badges($post_id));

wp_set_object_terms($post_id, array('aft-badge-adult'), 'os_tag');
$adult_badges = onlinesched_app_feed_event_badges($post_id);
$check('an adult tag publishes its badge type', array('adult'), $adult_badges);
$check('an adult badge sets the legacy adult flag', true, onlinesched_app_feed_event_is_adult($adult_badges, array()));
$check('the legacy Restricted tag still sets the adult flag', true, onlinesched_app_feed_event_is_adult(array(), array('restricted')));

onlinesched_reconcile_badge_type_key('Adult', 'Mature');
$check('renaming Adult keeps its client key', 'adult', onlinesched_badge_type_key('Mature'));
$check('a renamed Adult badge keeps the legacy adult flag', true, onlinesched_app_feed_event_is_adult(array(onlinesched_badge_type_key('Mature')), array()));

wp_set_object_terms(
	$post_id,
	array('aft-badge-essentials', 'aft-badge-goh', 'aft-badge-dance'),
	'os_tag'
);
$check(
	'an event carrying all three publishes all three, so the client can rank them',
	array('dance', 'essentials', 'guest-of-honor'),
	onlinesched_app_feed_event_badges($post_id)
);

wp_set_object_terms($post_id, array(), 'os_tag');
$check('an event with no tags publishes no badges', array(), onlinesched_app_feed_event_badges($post_id));

wp_delete_post($post_id, true);
foreach ($made_tags as $slug => $term_id) {
	$term = get_term_by('slug', $slug, 'os_tag');
	if ($term && (int) $term->term_id === (int) $term_id) {
		wp_delete_term($term_id, 'os_tag');
	}
}

if (false === $original_type_keys) {
	delete_option(ONLINESCHED_BADGE_TYPE_KEYS_OPTION);
} else {
	update_option(ONLINESCHED_BADGE_TYPE_KEYS_OPTION, $original_type_keys);
}

if ($failures > 0) {
	WP_CLI::error($failures . ' badge publication check(s) failed.');
}
WP_CLI::success('Badge publication checks passed.');
