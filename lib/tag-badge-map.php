<?php
/**
 * The tag-to-badge-type map: one option, one authority.
 *
 * Term meta stays readable as a migration fallback and is mirrored on save,
 * but it never outranks the map. A mapping is keyed by tag slug and survives
 * its term being deleted, so a re-imported tag with the same slug heals.
 */

const ONLINESCHED_TAG_BADGE_MAP_OPTION = 'onlinesched_tag_badge_map';

/** Stored when staff choose None, so the map can say "deliberately nothing". */
const ONLINESCHED_BADGE_NONE = '__none__';

/**
 * @return array<string,string> Tag slug to badge type name, or the None marker.
 */
function onlinesched_get_tag_badge_map() {
	$map = get_option(ONLINESCHED_TAG_BADGE_MAP_OPTION, array());
	return is_array($map) ? $map : array();
}

/**
 * @param array<string,string> $map Tag slug to badge type name.
 * @return void
 */
function onlinesched_save_tag_badge_map($map) {
	$clean = array();
	foreach ($map as $slug => $type) {
		$slug = sanitize_title((string) $slug);
		$type = sanitize_text_field((string) $type);
		if ('' === $slug || '' === $type) {
			continue;
		}
		$clean[$slug] = $type;
	}
	ksort($clean);

	$previous = onlinesched_get_tag_badge_map();
	update_option(ONLINESCHED_TAG_BADGE_MAP_OPTION, $clean);
	onlinesched_sync_badge_meta_mirror($previous, $clean);
}

/**
 * Brings term meta in line with the map.
 *
 * Every write lands here, so a released or reassigned tag cannot keep meta
 * that would resurrect its old type through the fallback.
 *
 * @param array<string,string> $before Map as it was.
 * @param array<string,string> $after Map as it now is.
 * @return void
 */
function onlinesched_sync_badge_meta_mirror($before, $after) {
	$slugs = array_unique(array_merge(array_keys($before), array_keys($after)));
	foreach ($slugs as $slug) {
		$was = isset($before[$slug]) ? $before[$slug] : null;
		$now = isset($after[$slug]) ? $after[$slug] : null;
		if ($was === $now) {
			continue;
		}
		$term = get_term_by('slug', $slug, 'os_tag');
		if (!$term || is_wp_error($term)) {
			continue;
		}
		if (null === $now || ONLINESCHED_BADGE_NONE === $now) {
			delete_term_meta($term->term_id, 'badge_type');
		} else {
			update_term_meta($term->term_id, 'badge_type', $now);
		}
	}
}

/**
 * @param string $type Badge type name.
 * @return bool
 */
function onlinesched_badge_type_is_configured($type) {
	if (ONLINESCHED_BADGE_NONE === $type) {
		return true;
	}
	$types = get_option('onlinesched_badge_types', array());
	$ok = is_array($types) && in_array($type, $types, true);

	return (bool) apply_filters('os_badge_type_is_configured', $ok, $type);
}

/**
 * The badge type for a tag, or '' for none.
 *
 * @param string $slug Tag slug.
 * @param int $term_id Term id when one exists, for the meta fallback.
 * @return string
 */
function onlinesched_badge_type_for_tag($slug, $term_id = 0) {
	$slug = sanitize_title((string) $slug);
	$map = onlinesched_get_tag_badge_map();

	if (isset($map[$slug])) {
		return ONLINESCHED_BADGE_NONE === $map[$slug] ? '' : $map[$slug];
	}

	if ($term_id) {
		$meta = (string) get_term_meta((int) $term_id, 'badge_type', true);
		if ('' !== trim($meta)) {
			return $meta;
		}
	}

	return onlinesched_default_badge_type_for_tag_slug($slug);
}

/**
 * Assigns a badge type to a tag, or the None marker to suppress one.
 *
 * @param string $slug Tag slug.
 * @param string $type Badge type name, or the None marker.
 * @return void
 */
function onlinesched_set_tag_badge_type($slug, $type) {
	$slug = sanitize_title((string) $slug);
	if ('' === $slug) {
		return;
	}

	$type = sanitize_text_field((string) $type);
	// A type nobody configured would map a tag to a name the Badge Types page
	// cannot show, which is how an assignment becomes invisible.
	if (!onlinesched_badge_type_is_configured($type)) {
		return;
	}

	$map = onlinesched_get_tag_badge_map();
	$map[$slug] = $type;
	onlinesched_save_tag_badge_map($map);
}

/**
 * @param string $slug Tag slug.
 * @return void
 */
function onlinesched_clear_tag_badge_type($slug) {
	$slug = sanitize_title((string) $slug);
	$map = onlinesched_get_tag_badge_map();
	unset($map[$slug]);
	onlinesched_save_tag_badge_map($map);
}

/**
 * Follows a badge type through a rename, or drops it on delete.
 *
 * @param string $from Existing badge type name.
 * @param string|null $to New name, or null to unmap every tag using it.
 * @return int Number of mappings changed.
 */
function onlinesched_reconcile_tag_badge_map($from, $to) {
	$map = onlinesched_get_tag_badge_map();
	$changed = 0;
	foreach ($map as $slug => $type) {
		if ($type !== $from) {
			continue;
		}
		if (null === $to) {
			unset($map[$slug]);
		} else {
			$map[$slug] = $to;
		}
		$changed++;
	}
	if ($changed) {
		onlinesched_save_tag_badge_map($map);
	}
	return $changed;
}

/**
 * Every os_tag with the type it resolves to, for the admin association screen.
 *
 * @return array<int,array{slug:string,name:string,type:string,missing:bool,explicit:bool,none:bool}>
 */
function onlinesched_tag_badge_rows() {
	$map = onlinesched_get_tag_badge_map();
	$terms = get_terms(array('taxonomy' => 'os_tag', 'hide_empty' => false));
	if (is_wp_error($terms)) {
		$terms = array();
	}

	$rows = array();
	$seen = array();
	foreach ($terms as $term) {
		$seen[$term->slug] = true;
		$rows[] = array(
			'slug'     => $term->slug,
			'name'     => $term->name,
			'type'     => onlinesched_badge_type_for_tag($term->slug, $term->term_id),
			'missing'  => false,
			'explicit' => isset($map[$term->slug]),
			// A deliberate None and a tag nobody has classified both resolve to
			// no type. They are not the same thing and must not read alike.
			'none'     => isset($map[$term->slug])
				&& ONLINESCHED_BADGE_NONE === $map[$term->slug],
		);
	}

	// A mapping whose tag is gone stays visible: that is what lets a re-imported
	// tag with the same slug pick its type back up.
	foreach ($map as $slug => $type) {
		if (isset($seen[$slug])) {
			continue;
		}
		$rows[] = array(
			'slug'     => $slug,
			'name'     => $slug,
			'type'     => ONLINESCHED_BADGE_NONE === $type ? '' : $type,
			'missing'  => true,
			'explicit' => true,
			'none'     => ONLINESCHED_BADGE_NONE === $type,
		);
	}

	usort(
		$rows,
		static function ($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		}
	);

	return $rows;
}
