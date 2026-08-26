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

// The live map is left alone: only aft-map-* keys are written and removed, so
// an early exit cannot strand the site's own map.
$fixture_only = static function ($map) {
	$out = array();
	foreach ((array) $map as $slug => $type) {
		if (0 === strpos($slug, 'aft-map-')) {
			$out[$slug] = $type;
		}
	}
	return $out;
};

// A badge type only this fixture uses, so reconciling it cannot move a real
// tag's mapping.
add_filter(
	'os_badge_type_is_configured',
	static function ($ok, $type) {
		return 'AFT Map Type' === $type ? true : $ok;
	},
	10,
	2
);

// aft-map-ruled stands in for a slug the built-in defaults know, so the rule
// is exercised without borrowing a real tag.
add_filter(
	'os_default_badge_type_for_tag_slug',
	static function ($type, $slug) {
		return 'aft-map-ruled' === $slug ? 'Essentials' : $type;
	},
	10,
	2
);

// A built-in slug resolves through the defaults with nothing mapped.
$check(
	'the built-in rule answers when the map is silent',
	'Essentials',
	onlinesched_badge_type_for_tag('aft-map-ruled')
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
onlinesched_set_tag_badge_type('aft-map-ruled', 'Guest Of Honor');
$check(
	'an explicit assignment beats the built-in rule',
	'Guest Of Honor',
	onlinesched_badge_type_for_tag('aft-map-ruled')
);

onlinesched_set_tag_badge_type('aft-map-ruled', ONLINESCHED_BADGE_NONE);
$check(
	'None beats both the rule and any meta',
	'',
	onlinesched_badge_type_for_tag('aft-map-ruled')
);
onlinesched_clear_tag_badge_type('aft-map-ruled');
$check(
	'clearing the mapping hands the slug back to the rule',
	'Essentials',
	onlinesched_badge_type_for_tag('aft-map-ruled')
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

// Reconciliation, on a badge type no real tag carries.
onlinesched_set_tag_badge_type('aft-map-goh', 'AFT Map Type');
$check(
	'a renamed badge type takes its tags with it',
	1,
	onlinesched_reconcile_tag_badge_map('AFT Map Type', 'AFT Map Type Renamed')
);
$check(
	'the tag now resolves to the new name',
	'AFT Map Type Renamed',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);
$check(
	'a deleted badge type releases its tags',
	1,
	onlinesched_reconcile_tag_badge_map('AFT Map Type Renamed', null)
);
$check(
	'a released tag falls back to the rule, which knows nothing here',
	'',
	onlinesched_badge_type_for_tag('aft-map-goh', $goh_id)
);

// Filling in defaults runs the real handler body, not a copy of its rule. The
// fixture tag carries the injected default, so no real tag is touched.
$ruled_id = $make_tag('aft-map-ruled', 'AFT Map Ruled');
onlinesched_set_tag_badge_type('aft-map-ruled', ONLINESCHED_BADGE_NONE);
onlinesched_fill_default_badge_types();
$check(
	'an explicit None survives a defaults pass',
	'',
	onlinesched_badge_type_for_tag('aft-map-ruled', $ruled_id)
);

onlinesched_clear_tag_badge_type('aft-map-ruled');
onlinesched_fill_default_badge_types();
$check(
	'an unmapped slug with a built-in default gets mapped',
	'Essentials',
	onlinesched_badge_type_for_tag('aft-map-ruled', $ruled_id)
);
$map_after = onlinesched_get_tag_badge_map();
$check(
	'and it is written to the map, not only to meta',
	'Essentials',
	isset($map_after['aft-map-ruled']) ? $map_after['aft-map-ruled'] : ''
);
onlinesched_clear_tag_badge_type('aft-map-ruled');

// The association form's save path, exercised as the page runs it. Results are
// filtered to fixture keys, because the live map is carried through untouched.
$types = array('Dance', 'Essentials');
$both = array('aft-map-goh', 'aft-map-meta');

$result = onlinesched_tag_map_from_submission(
	array('Dance' => array('aft-map-goh'), 'Essentials' => array('aft-map-meta')),
	$types,
	$both
);
$check('a straight submission maps each tag to its type', array(
	'aft-map-goh' => 'Dance',
	'aft-map-meta' => 'Essentials',
), $fixture_only($result['map']));
$check('and reports no conflict', array(), $result['conflicts']);

$result = onlinesched_tag_map_from_submission(
	array('Dance' => array('aft-map-goh'), 'Essentials' => array('aft-map-goh')),
	$types,
	$both
);
$check('two types claiming one tag is a conflict', array('aft-map-goh'), $result['conflicts']);
$check('and nothing about that tag is stored', false, isset($result['map']['aft-map-goh']));

$result = onlinesched_tag_map_from_submission(
	array('Not A Type' => array('aft-map-goh')),
	$types,
	$both
);
$check('a badge type that does not exist is ignored', array(), $fixture_only($result['map']));

onlinesched_set_tag_badge_type('aft-map-meta', ONLINESCHED_BADGE_NONE);
$result = onlinesched_tag_map_from_submission(
	array('Dance' => array('aft-map-goh')),
	$types,
	array('aft-map-goh')
);
$check(
	'a mapping the form never offered is carried forward, None included',
	ONLINESCHED_BADGE_NONE,
	isset($result['map']['aft-map-meta']) ? $result['map']['aft-map-meta'] : ''
);
onlinesched_clear_tag_badge_type('aft-map-meta');

$result = onlinesched_tag_map_from_submission(array('Dance' => array()), $types, $both);
$check(
	'a type emptied on the form releases its tags',
	array(),
	$fixture_only($result['map'])
);

// The tag form hooks, run as WordPress runs them.
$hook_id = $make_tag('aft-map-hook', 'AFT Map Hook');
$_POST['badge_type'] = 'Essentials';
do_action('edited_os_tag', $hook_id);
$check(
	'the edit hook writes through the map',
	'Essentials',
	onlinesched_badge_type_for_tag('aft-map-hook', $hook_id)
);

$_POST['badge_type'] = '';
do_action('edited_os_tag', $hook_id);
$check(
	'an empty choice on edit is None, not no action',
	'',
	onlinesched_badge_type_for_tag('aft-map-hook', $hook_id)
);
$check(
	'and the mirror is cleared with it',
	'',
	(string) get_term_meta($hook_id, 'badge_type', true)
);

$_POST['badge_type'] = 'Not A Configured Type';
do_action('edited_os_tag', $hook_id);
$check(
	'an unconfigured type is refused rather than stored',
	'',
	onlinesched_badge_type_for_tag('aft-map-hook', $hook_id)
);
unset($_POST['badge_type']);

// A release through the association form must not resurrect from meta.
onlinesched_set_tag_badge_type('aft-map-hook', 'Essentials');
$check(
	'set writes the mirror',
	'Essentials',
	(string) get_term_meta($hook_id, 'badge_type', true)
);
$released = onlinesched_tag_map_from_submission(
	array('Essentials' => array()),
	array('Essentials'),
	array('aft-map-hook')
);
onlinesched_save_tag_badge_map($released['map']);
$check(
	'releasing on the form clears the mirror too',
	'',
	(string) get_term_meta($hook_id, 'badge_type', true)
);
$check(
	'so the released tag cannot resurrect its old type',
	'',
	onlinesched_badge_type_for_tag('aft-map-hook', $hook_id)
);

// A mapping the form never showed survives a save.
onlinesched_set_tag_badge_type('aft-map-hook', 'Essentials');
$kept = onlinesched_tag_map_from_submission(
	array('Essentials' => array()),
	array('Essentials'),
	array()
);
$check(
	'a mapping the form did not offer is carried forward',
	'Essentials',
	isset($kept['map']['aft-map-hook']) ? $kept['map']['aft-map-hook'] : ''
);

// None chosen on the association form.
$noned = onlinesched_tag_map_from_submission(
	array(ONLINESCHED_BADGE_NONE => array('aft-map-hook')),
	array('Essentials'),
	array('aft-map-hook')
);
$check(
	'the form can assign an explicit None',
	ONLINESCHED_BADGE_NONE,
	isset($noned['map']['aft-map-hook']) ? $noned['map']['aft-map-hook'] : ''
);
onlinesched_clear_tag_badge_type('aft-map-hook');
$check(
	'clearing a mapping clears the mirror',
	'',
	(string) get_term_meta($hook_id, 'badge_type', true)
);

// An explicit None must not read as an unclassified tag.
onlinesched_set_tag_badge_type('aft-map-hook', ONLINESCHED_BADGE_NONE);
$row = null;
foreach (onlinesched_tag_badge_rows() as $candidate) {
	if ('aft-map-hook' === $candidate['slug']) {
		$row = $candidate;
	}
}
$check('a deliberate None is flagged as such', true, !empty($row['none']));
$check('and still resolves to no type', '', $row['type']);

onlinesched_clear_tag_badge_type('aft-map-hook');
$row = null;
foreach (onlinesched_tag_badge_rows() as $candidate) {
	if ('aft-map-hook' === $candidate['slug']) {
		$row = $candidate;
	}
}
$check('an unmapped tag is not flagged as a deliberate None', false, !empty($row['none']));

foreach (array_keys($made) as $slug) {
	$drop_tag($slug);
}
// Prefix cleanup: the live map keeps every entry it had.
$final = onlinesched_get_tag_badge_map();
foreach (array_keys($final) as $slug) {
	if (0 === strpos($slug, 'aft-map-')) {
		unset($final[$slug]);
	}
}
onlinesched_save_tag_badge_map($final);

if ($failures > 0) {
	WP_CLI::error($failures . ' tag badge map check(s) failed.');
}
WP_CLI::success('Tag badge map checks passed.');
