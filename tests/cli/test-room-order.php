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

// The one-time converter. It rewrites the stored setting, so nothing has to
// resolve names at read time.
$terms = array(
	array('slug' => 'main-stage', 'name' => 'Main Stage'),
	array('slug' => 'greenway-a', 'name' => 'Greenway (A)'),
	array('slug' => 'lakeshore', 'name' => 'Lakeshore'),
	// This room's NAME is another room's SLUG.
	array('slug' => 'decoy-room', 'name' => 'lakeshore'),
);

$converted = onlinesched_convert_room_priority_tokens(
	array('main-stage', 'greenway-a'),
	$terms
);
$check('exact slugs convert to themselves', array('main-stage', 'greenway-a'), $converted['slugs']);
$check('nothing is rejected when every token is a slug', array(), $converted['rejected']);

$converted = onlinesched_convert_room_priority_tokens(
	array('Main Stage', 'Greenway (A)'),
	$terms
);
$check(
	'legacy names convert to slugs, including a name that does not sanitize to its slug',
	array('main-stage', 'greenway-a'),
	$converted['slugs']
);

$converted = onlinesched_convert_room_priority_tokens(array('No Such Room'), $terms);
$check('an unresolved token is rejected, not guessed', array(), $converted['slugs']);
$check('the rejection names the token', 'No Such Room', $converted['rejected'][0]['token']);

$converted = onlinesched_convert_room_priority_tokens(array('lakeshore'), $terms);
$check(
	'a token that is one room\'s slug and another room\'s name is rejected',
	array(),
	$converted['slugs']
);

$converted = onlinesched_convert_room_priority_tokens(
	array('main-stage', 'Main Stage', 'main-stage'),
	$terms
);
$check('duplicates collapse to one entry', array('main-stage'), $converted['slugs']);

$converted = onlinesched_convert_room_priority_tokens(
	array(' main-stage ', '', 'greenway-a'),
	$terms
);
$check('padding and empty entries are handled', array('main-stage', 'greenway-a'), $converted['slugs']);

// End to end against real terms: convert once, then rename, and the order
// holds because what is stored is a slug.
$made = array();
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
$original_flag = get_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION, '');

// WP_CLI::error exits, so the restore runs on shutdown rather than only on a
// clean finish. An aborted run must not leave the site's priority rewritten.
register_shutdown_function(static function () use ($original_setting, $original_flag) {
	if ('' === $original_setting) {
		delete_option('onlinesched_room_sort_priority');
	} else {
		update_option('onlinesched_room_sort_priority', $original_setting);
	}
	if ('' === $original_flag) {
		delete_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION);
	} else {
		update_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION, $original_flag);
	}
});

update_option('onlinesched_room_sort_priority', 'AFT Order Two, AFT Order One');
$report = onlinesched_run_room_priority_slug_conversion(true);
$check('the converter reports what it did', 'converted', $report['status']);
$check(
	'the option now holds slugs, not names',
	'aft-two-alt, aft-order-one',
	get_option('onlinesched_room_sort_priority')
);

wp_update_term($made['aft-two-alt'], 'os_room', array('name' => 'AFT Order Two Renamed'));
$check(
	'a rename after conversion leaves the order alone',
	array('aft-two-alt', 'aft-order-one'),
	onlinesched_get_room_sort_priority()
);

$check(
	'running it again changes nothing',
	'aft-two-alt, aft-order-one',
	(string) (onlinesched_run_room_priority_slug_conversion(true) ? get_option('onlinesched_room_sort_priority') : '')
);

$check('a converted site is not converted twice', 'already-converted', onlinesched_run_room_priority_slug_conversion()['status']);

// A token nobody can resolve must stop the conversion, not vanish from it.
update_option('onlinesched_room_sort_priority', 'aft-order-one, No Such Room Here');
delete_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION);
$blocked = onlinesched_run_room_priority_slug_conversion(true);
$check('an unresolved token blocks the conversion', 'blocked', $blocked['status']);
$check(
	'the setting is left exactly as it was',
	'aft-order-one, No Such Room Here',
	get_option('onlinesched_room_sort_priority')
);
$check(
	'the converted flag is not set, so it can be retried',
	false,
	(bool) get_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION)
);
$check('and the report names the token', 'No Such Room Here', $blocked['rejected'][0]['token']);

update_option('onlinesched_room_sort_priority', 'aft-order-one');
$fixed = onlinesched_run_room_priority_slug_conversion(true);
$check('removing it lets the conversion through', 'converted', $fixed['status']);
$check('and the flag is set once it succeeds', true, (bool) get_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION));

if ('' === $original_setting) {
	delete_option('onlinesched_room_sort_priority');
} else {
	update_option('onlinesched_room_sort_priority', $original_setting);
}
if ('' === $original_flag) {
	delete_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION);
} else {
	update_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION, $original_flag);
}
foreach ($made as $term_id) {
	wp_delete_term($term_id, 'os_room');
}

// The enqueue once pointed at a file that was not there: a 404, nothing thrown,
// and the widget simply never appeared. Render and parse checks both missed it.
$_GET['page'] = 'onlinesched-settings';
do_action('admin_enqueue_scripts', 'settings_page_onlinesched-settings');
unset($_GET['page']);

$registered = wp_scripts()->registered;
$check(
	'the room order script is registered on the settings page',
	true,
	isset($registered['onlinesched-room-order'])
);

if (isset($registered['onlinesched-room-order'])) {
	$src = $registered['onlinesched-room-order']->src;
	$path = str_replace(content_url(), WP_CONTENT_DIR, $src);
	$check('its URL resolves to a file that ships', true, file_exists($path));
	$check(
		'and that file is the widget, not an empty placeholder',
		true,
		false !== strpos((string) file_get_contents($path), 'onlinesched-room-ordered')
	);
}

if ($failures > 0) {
	WP_CLI::error($failures . ' room order check(s) failed.');
}
WP_CLI::success('Room order checks passed.');
