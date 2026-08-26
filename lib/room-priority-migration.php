<?php
/**
 * One-time conversion of onlinesched_room_sort_priority from room names to
 * room slugs.
 *
 * TEMPORARY. Delete this file, its require in OnlineSched.php, and the
 * onlinesched_room_priority_converted option once local, proof and production
 * have all reported a clean conversion. The getter does not alias names, so
 * nothing outside this file depends on it.
 */

const ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION = 'onlinesched_room_priority_converted';

/**
 * Resolves stored tokens against exact slugs and exact names.
 *
 * WordPress `get_term_by('slug', ...)` runs sanitize_title on its input, so a
 * name can match a slug lookup. Exact comparison against both maps is the only
 * way to tell a slug entry from a legacy name entry.
 *
 * @param string[] $tokens Stored priority entries in order.
 * @param array<int,array{slug:string,name:string}> $terms Every os_room term.
 * @return array{slugs:string[], rejected:array<int,array{token:string,reason:string}>}
 */
function onlinesched_convert_room_priority_tokens(array $tokens, array $terms) {
	$by_slug = array();
	$by_name = array();
	foreach ($terms as $term) {
		$by_slug[$term['slug']] = $term['slug'];
		$by_name[$term['name']] = $term['slug'];
	}

	$slugs = array();
	$rejected = array();
	foreach ($tokens as $token) {
		$token = trim($token);
		if ('' === $token) {
			continue;
		}

		$as_slug = isset($by_slug[$token]) ? $by_slug[$token] : null;
		$as_name = isset($by_name[$token]) ? $by_name[$token] : null;

		if (null !== $as_slug && null !== $as_name && $as_slug !== $as_name) {
			$rejected[] = array(
				'token'  => $token,
				'reason' => 'ambiguous: it is one room\'s slug and another room\'s name',
			);
			continue;
		}

		$resolved = null !== $as_slug ? $as_slug : $as_name;
		if (null === $resolved) {
			$rejected[] = array('token' => $token, 'reason' => 'no room has this slug or name');
			continue;
		}

		if (!in_array($resolved, $slugs, true)) {
			$slugs[] = $resolved;
		}
	}

	return array('slugs' => $slugs, 'rejected' => $rejected);
}

/**
 * @return array<int,array{slug:string,name:string}>
 */
function onlinesched_room_priority_terms() {
	$terms = get_terms(array('taxonomy' => 'os_room', 'hide_empty' => false));
	if (is_wp_error($terms) || !is_array($terms)) {
		return array();
	}

	$out = array();
	foreach ($terms as $term) {
		$out[] = array('slug' => $term->slug, 'name' => $term->name);
	}

	return $out;
}

/**
 * Converts the stored setting once and records that it ran.
 *
 * @param bool $force Convert again even if it already ran.
 * @return array{status:string, slugs?:string[], rejected?:array}
 */
function onlinesched_run_room_priority_slug_conversion($force = false) {
	if (!$force && get_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION)) {
		return array('status' => 'already-converted');
	}

	$raw = get_option('onlinesched_room_sort_priority', '');
	if (!is_string($raw) || '' === trim($raw)) {
		update_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION, 1);
		return array('status' => 'nothing-to-convert');
	}

	$terms = onlinesched_room_priority_terms();
	if (empty($terms)) {
		return array('status' => 'deferred-no-rooms');
	}

	$result = onlinesched_convert_room_priority_tokens(explode(',', $raw), $terms);
	update_option('onlinesched_room_sort_priority', implode(', ', $result['slugs']));
	update_option(ONLINESCHED_ROOM_PRIORITY_CONVERTED_OPTION, 1);

	return array(
		'status'   => 'converted',
		'slugs'    => $result['slugs'],
		'rejected' => $result['rejected'],
	);
}

add_action('init', 'onlinesched_run_room_priority_slug_conversion', 20);
