<?php
/**
 * Stores each tag's badge type.
 *
 * Term meta stays readable as a migration fallback and is mirrored on save,
 * but it never outranks the map. A mapping is keyed by tag slug and survives
 * its term being deleted, so a re-imported tag with the same slug heals.
 */

const ONLINESCHED_TAG_BADGE_MAP_OPTION = 'onlinesched_tag_badge_map';

const ONLINESCHED_BADGE_TYPE_KEYS_OPTION = 'onlinesched_badge_type_keys';

/** Stored for an explicit No badge assignment. */
const ONLINESCHED_BADGE_NONE = '__none__';

/**
 * @return array<string,string> Badge type name to permanent client key.
 */
function onlinesched_get_badge_type_keys() {
	$keys = get_option(ONLINESCHED_BADGE_TYPE_KEYS_OPTION, array());
	return is_array($keys) ? $keys : array();
}

/**
 * @param array<string,string> $keys Badge type name to permanent client key.
 * @return void
 */
function onlinesched_save_badge_type_keys($keys) {
	$clean = array();
	foreach ($keys as $name => $key) {
		$name = sanitize_text_field((string) $name);
		$key = sanitize_title((string) $key);
		if ('' !== $name && '' !== $key) {
			$clean[$name] = $key;
		}
	}
	ksort($clean);
	update_option(ONLINESCHED_BADGE_TYPE_KEYS_OPTION, $clean);
}

/**
 * @param string[] $types Badge type names.
 * @return array<string,string> Badge type name to permanent client key.
 */
function onlinesched_ensure_badge_type_keys($types) {
	$keys = onlinesched_get_badge_type_keys();
	$used = array_fill_keys(array_values($keys), true);
	$changed = false;

	foreach ($types as $type) {
		$type = sanitize_text_field((string) $type);
		if ('' === $type || !empty($keys[$type])) {
			continue;
		}

		$base = sanitize_title($type);
		if ('' === $base) {
			continue;
		}
		$key = $base;
		$suffix = 2;
		while (isset($used[$key])) {
			$key = $base . '-' . $suffix;
			$suffix++;
		}
		$keys[$type] = $key;
		$used[$key] = true;
		$changed = true;
	}

	if ($changed) {
		onlinesched_save_badge_type_keys($keys);
	}
	return $keys;
}

/**
 * @param string $type Badge type name.
 * @return string Permanent client key.
 */
function onlinesched_badge_type_key($type) {
	$keys = onlinesched_get_badge_type_keys();
	return isset($keys[$type]) ? $keys[$type] : sanitize_title($type);
}

/**
 * @param string $from Existing badge type name.
 * @param string|null $to New name, or null when deleting the type.
 * @return void
 */
function onlinesched_reconcile_badge_type_key($from, $to) {
	$keys = onlinesched_get_badge_type_keys();
	$key = isset($keys[$from]) ? $keys[$from] : sanitize_title($from);
	unset($keys[$from]);
	if (null !== $to && '' !== $key) {
		$keys[$to] = $key;
	}
	onlinesched_save_badge_type_keys($keys);
}

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
	// Reject types the Badge Types page cannot display.
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
			// Keep an explicit No badge distinct from an unassigned tag.
			'none'     => isset($map[$term->slug])
				&& ONLINESCHED_BADGE_NONE === $map[$term->slug],
		);
	}

	// Missing tags stay mapped in case the same slug is imported again.
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
