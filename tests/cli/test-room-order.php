<?php
/**
 * Room sort priority: stored by slug, published in the feed, and unmoved by a
 * room rename.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-room-order.php \
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

$slugs = static function ($rooms) {
	return array_map(static function ($room) {
		return $room['slug'];
	}, $rooms);
};

// Sorting is a pure function of the rooms and the priority list, so most of
// this needs no fixtures.
$rooms = array(
	'zebra-hall'  => array('slug' => 'zebra-hall', 'name' => 'Zebra Hall'),
	'main-stage'  => array('slug' => 'main-stage', 'name' => 'Main Stage'),
	'alpha-room'  => array('slug' => 'alpha-room', 'name' => 'Alpha Room'),
	'lakeshore'   => array('slug' => 'lakeshore', 'name' => 'Lakeshore'),
);

$check(
	'no priority sorts by name, not by slug',
	array('alpha-room', 'lakeshore', 'main-stage', 'zebra-hall'),
	$slugs(onlinesched_app_feed_sort_rooms($rooms, array()))
);

$ordered = onlinesched_app_feed_sort_rooms($rooms, array('main-stage', 'zebra-hall'));
$check(
	'priority slugs lead, the rest follow by name',
	array('main-stage', 'zebra-hall', 'alpha-room', 'lakeshore'),
	$slugs($ordered)
);
$check(
	'each room carries its own index',
	array(0, 1, 2, 3),
	array_column($ordered, 'sort')
);

// The point of keying on slug: renaming a room must not move it. "Main Stage"
// becomes "Zulu Stage", which would sort last by name.
$renamed = $rooms;
$renamed['main-stage']['name'] = 'Zulu Stage';
$check(
	'a name-only rename keeps the priority position',
	array('main-stage', 'zebra-hall', 'alpha-room', 'lakeshore'),
	$slugs(onlinesched_app_feed_sort_rooms($renamed, array('main-stage', 'zebra-hall')))
);

$check(
	'a priority entry for a room that is not in the payload is skipped',
	array('lakeshore', 'alpha-room', 'main-stage', 'zebra-hall'),
	$slugs(onlinesched_app_feed_sort_rooms($rooms, array('gone-room', 'lakeshore', 'alpha-room')))
);

// The stored setting resolves to slugs, including entries saved as names
// before the switch. This part needs real terms.
$made = array();
// The second room's slug is deliberately unrelated to its name: a name that
// sanitizes to its own slug resolves by accident and proves nothing.
$fixtures = array(
	'aft-order-one' => 'AFT Order One',
	'aft-two-alt'   => 'AFT Order Two',
);
foreach ($fixtures as $slug => $name) {
	$existing = get_term_by('slug', $slug, 'os_room');
	if ($existing) {
		wp_delete_term($existing->term_id, 'os_room');
	}
	$term = wp_insert_term($name, 'os_room', array('slug' => $slug));
	if (is_wp_error($term)) {
		WP_CLI::error('Could not create the fixture room ' . $slug . ': ' . $term->get_error_message());
	}
	$made[$slug] = $term['term_id'];
}

$original_setting = get_option('onlinesched_room_sort_priority', '');

update_option('onlinesched_room_sort_priority', 'aft-two-alt, aft-order-one');
$check(
	'slug entries are read straight through',
	array('aft-two-alt', 'aft-order-one'),
	onlinesched_get_room_sort_priority()
);

update_option('onlinesched_room_sort_priority', 'AFT Order Two, AFT Order One');
$check(
	'entries saved as names before the switch resolve to slugs',
	array('aft-two-alt', 'aft-order-one'),
	onlinesched_get_room_sort_priority()
);

// Rename both rooms. The name-shaped setting is now pointing at names that no
// longer exist, which is the breakage the slug switch removes.
wp_update_term($made['aft-two-alt'], 'os_room', array('name' => 'AFT Order Two Renamed'));
wp_update_term($made['aft-order-one'], 'os_room', array('name' => 'AFT Order One Renamed'));
$check(
	'a stale name entry stops resolving, which is the failure slugs avoid',
	array('AFT Order Two', 'aft-order-one'),
	onlinesched_get_room_sort_priority()
);

update_option('onlinesched_room_sort_priority', 'aft-two-alt, aft-order-one');
$check(
	'the same rename leaves slug entries untouched',
	array('aft-two-alt', 'aft-order-one'),
	onlinesched_get_room_sort_priority()
);

if ('' === $original_setting) {
	delete_option('onlinesched_room_sort_priority');
} else {
	update_option('onlinesched_room_sort_priority', $original_setting);
}
foreach ($made as $term_id) {
	wp_delete_term($term_id, 'os_room');
}

if ($failures > 0) {
	WP_CLI::error($failures . ' room order check(s) failed.');
}
WP_CLI::success('Room order checks passed.');
