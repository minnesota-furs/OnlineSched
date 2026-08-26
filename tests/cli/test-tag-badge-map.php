<?php
/**
 * The tag-to-badge-type map is the authority: explicit assignment beats the
 * built-in rule, None beats both, a mapping survives its term, and a renamed
 * or deleted badge type is reconciled.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-tag-badge-map.php \
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

// Fixture slugs are prefixed and refused if anything already holds them.
$made = array();
$make_tag = static function ($slug, $name) use (&$made) {
	if (0 !== strpos($slug, 'aft-map-')) {
		WP_CLI::error('Fixture slugs must be prefixed aft-map-, refusing ' . $slug);
	}
	if (get_term_by('slug', $slug, 'os_tag')) {
		WP_CLI::error('Fixture slug ' . $slug . ' already exists; refusing to touch it.');
	}
	$term = wp_insert_term($name, 'os_tag', array('slug' => $slug));
	if (is_wp_error($term)) {
		WP_CLI::error('Could not create ' . $slug . ': ' . $term->get_error_message());
	}
	$made[$slug] = $term['term_id'];
	return $term['term_id'];
};
$drop_tag = static function ($slug) use (&$made) {
	$term = get_term_by('slug', $slug, 'os_tag');
	if ($term && isset($made[$slug]) && (int) $term->term_id === (int) $made[$slug]) {
		wp_delete_term($term->term_id, 'os_tag');
		unset($made[$slug]);
	}
};

$original_map = get_option(ONLINESCHED_TAG_BADGE_MAP_OPTION, array());
update_option(ONLINESCHED_TAG_BADGE_MAP_OPTION, array());

// A built-in slug resolves through the defaults with nothing mapped.
$check(
	'the built-in rule answers when the map is silent',
	'Essentials',
	onlinesched_badge_type_for_tag('essentials')
);

$goh_id = $make_tag('aft-map-goh', 'AFT Map GoH');

$check(
	'an unmapped, unknown slug has no type',
	'',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);

onlinesched_set_tag_badge_type('aft-map-goh', 'Guest Of Honor');
$check(
	'an explicit assignment is what resolves',
	'Guest Of Honor',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);

// Explicit assignment beats the built-in rule for a slug the rule knows.
onlinesched_set_tag_badge_type('essentials', 'Guest Of Honor');
$check(
	'an explicit assignment beats the built-in rule',
	'Guest Of Honor',
	onlinesched_badge_type_for_tag('essentials')
);

onlinesched_set_tag_badge_type('essentials', ONLINESCHED_BADGE_NONE);
$check(
	'None beats both the rule and any meta',
	'',
	onlinesched_badge_type_for_tag('essentials')
);
onlinesched_clear_tag_badge_type('essentials');
$check(
	'clearing the mapping hands the slug back to the rule',
	'Essentials',
	onlinesched_badge_type_for_tag('essentials')
);

// Term meta is a fallback, never an override.
$meta_id = $make_tag('aft-map-meta', 'AFT Map Meta');
update_term_meta($meta_id, 'badge_type', 'Streaming');
$check(
	'meta answers when the map has no entry',
	'Streaming',
	onlinesched_badge_type_for_tag('aft-map-meta', $meta_id)
);
onlinesched_set_tag_badge_type('aft-map-meta', 'Essentials');
$check(
	'the map outranks stale meta',
	'Essentials',
	onlinesched_badge_type_for_tag('aft-map-meta', $meta_id)
);

// A mapping outlives its term, so the same slug coming back heals.
$drop_tag('aft-map-goh');
$map = onlinesched_get_tag_badge_map();
$check(
	'the mapping survives the term being deleted',
	'Guest Of Honor',
	isset($map['aft-map-goh']) ? $map['aft-map-goh'] : ''
);
$rows = onlinesched_tag_badge_rows();
$missing = array_values(array_filter($rows, static function ($row) {
	return 'aft-map-goh' === $row['slug'];
}));
$check('a mapping with no term is shown as missing', true, !empty($missing) && $missing[0]['missing']);

$goh_id = $make_tag('aft-map-goh', 'AFT Map GoH Again');
$check(
	'the same slug coming back picks its type up again',
	'Guest Of Honor',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);

// A different spelling is a different tag. Nothing is inferred.
$other_id = $make_tag('aft-map-goh-2', 'AFT Map GoH Two');
$check(
	'a changed spelling does not inherit the old mapping',
	'',
	onlinesched_badge_type_for_tag('aft-map-goh-2', $other_id)
);

// Reconciliation.
$check(
	'a renamed badge type takes its tags with it',
	1,
	onlinesched_reconcile_tag_badge_map('Guest Of Honor', 'Guests Of Honor')
);
$check(
	'the tag now resolves to the new name',
	'Guests Of Honor',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);
$check(
	'a deleted badge type releases its tags',
	1,
	onlinesched_reconcile_tag_badge_map('Guests Of Honor', null)
);
$check(
	'a released tag falls back to the rule, which knows nothing here',
	'',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);

foreach (array_keys($made) as $slug) {
	$drop_tag($slug);
}
update_option(ONLINESCHED_TAG_BADGE_MAP_OPTION, $original_map);

if ($failures > 0) {
	WP_CLI::error($failures . ' tag badge map check(s) failed.');
}
WP_CLI::success('Tag badge map checks passed.');
