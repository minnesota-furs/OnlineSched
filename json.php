<?php
/**
 * Public JSON feed for lightweight schedule displays.
 *
 * @package OnlineSched
 */

require_once('../../../wp-load.php');

function onlinesched_json_get_request_value(array $keys) {
	foreach ($keys as $key) {
		if (!isset($_REQUEST[$key]) || '' === $_REQUEST[$key] || is_array($_REQUEST[$key])) {
			continue;
		}

		return sanitize_text_field(wp_unslash($_REQUEST[$key]));
	}

	return '';
}

function onlinesched_json_get_request_slugs(array $keys) {
	$value = onlinesched_json_get_request_value($keys);
	if ('' === $value) {
		return array();
	}

	return onlinesched_json_sanitize_slugs(explode(',', $value));
}

function onlinesched_json_sanitize_slugs($slugs) {
	if (!is_array($slugs)) {
		$slugs = array($slugs);
	}

	return array_values(array_filter(array_map('sanitize_title', $slugs)));
}

function onlinesched_json_get_room_groups() {
	$groups = get_option('onlinesched_json_room_groups', array());
	if (is_string($groups) && '' !== trim($groups)) {
		$decoded_groups = json_decode($groups, true);
		$groups = is_array($decoded_groups) ? $decoded_groups : array();
	}

	if (!is_array($groups)) {
		$groups = array();
	}

	$groups = apply_filters('os_json_room_groups', $groups);
	return is_array($groups) ? $groups : array();
}

function onlinesched_json_normalize_group($group) {
	if (!is_array($group)) {
		return array(
			'rooms'        => onlinesched_json_sanitize_slugs($group),
			'exclude_rooms'=> array(),
			'tags'         => array(),
			'exclude_tags' => array(),
		);
	}

	if (array_keys($group) === range(0, count($group) - 1)) {
		$group = array('rooms' => $group);
	}

	return array(
		'rooms'         => onlinesched_json_sanitize_slugs($group['rooms'] ?? array()),
		'exclude_rooms' => onlinesched_json_sanitize_slugs($group['exclude_rooms'] ?? array()),
		'tags'          => onlinesched_json_sanitize_slugs($group['tags'] ?? array()),
		'exclude_tags'  => onlinesched_json_sanitize_slugs($group['exclude_tags'] ?? array()),
	);
}

function onlinesched_json_add_tax_clause(&$tax_query, $taxonomy, array $terms, $operator = 'IN') {
	if (empty($terms)) {
		return;
	}

	$tax_query[] = array(
		'taxonomy' => $taxonomy,
		'field'    => 'slug',
		'terms'    => $terms,
		'operator' => $operator,
	);
}

function onlinesched_json_get_requested_group_key() {
	$group = onlinesched_json_get_request_value(array('group'));
	if ('' !== $group) {
		return sanitize_key($group);
	}

	$legacy_group_params = apply_filters('os_json_legacy_group_params', array(
		'programming' => 'programming',
		'gaming'      => 'gaming',
	));
	if (is_array($legacy_group_params)) {
		foreach ($legacy_group_params as $param => $legacy_group) {
			if (!empty($_REQUEST[$param]) && !is_array($_REQUEST[$param])) {
				return sanitize_key($legacy_group);
			}
		}
	}

	return '';
}

$args = array(
	'post_type'   => 'os_event',
	'post_status' => 'publish',
	'meta_key'    => 'onlinesched_sorttime',
	'orderby'     => 'meta_value_num',
	'order'       => 'ASC',
	'nopaging'    => true,
);

$tax_query = array('relation' => 'AND');
$group_key = onlinesched_json_get_requested_group_key();

if ('' !== $group_key) {
	$groups = onlinesched_json_get_room_groups();
	$group = onlinesched_json_normalize_group($groups[$group_key] ?? array());
	$group_has_filters = !empty($group['rooms']) || !empty($group['exclude_rooms']) || !empty($group['tags']) || !empty($group['exclude_tags']);

	if (!$group_has_filters) {
		$args['post__in'] = array(0);
	}

	onlinesched_json_add_tax_clause($tax_query, 'os_room', $group['rooms']);
	onlinesched_json_add_tax_clause($tax_query, 'os_room', $group['exclude_rooms'], 'NOT IN');
	onlinesched_json_add_tax_clause($tax_query, 'os_tag', $group['tags']);
	onlinesched_json_add_tax_clause($tax_query, 'os_tag', $group['exclude_tags'], 'NOT IN');
} else {
	$room_slugs = onlinesched_json_get_request_slugs(array('room', 'rooms'));
	if (!in_array('all', array_map('strtolower', $room_slugs), true)) {
		onlinesched_json_add_tax_clause($tax_query, 'os_room', $room_slugs);
	}

	$tag_slugs = onlinesched_json_get_request_slugs(array('tag', 'tags'));
	if (!empty($tag_slugs) && !in_array('all', array_map('strtolower', $tag_slugs), true)) {
		onlinesched_json_add_tax_clause($tax_query, 'os_tag', $tag_slugs);
	}
}

if (count($tax_query) > 1) {
	$args['tax_query'] = $tax_query;
}

$limit = -1;
if (isset($_REQUEST['limit']) && !is_array($_REQUEST['limit'])) {
	$limit = intval(wp_unslash($_REQUEST['limit']));
}

$loop = new WP_Query($args);
$posts_arr = empty($loop->posts) ? array() : $loop->posts;
$now = new DateTime('@' . current_time('timestamp', true));
$now->setTimeZone(new DateTimeZone('UTC'));
$json_out = array();

foreach ($posts_arr as $item) {
	$post_id = $item->ID;
	$year = get_post_meta($post_id, 'onlinesched_year', true);

	if (0 === $limit) {
		break;
	}

	if ($year != get_option('onlinesched_year')) {
		continue;
	}

	$start_time = get_post_meta($post_id, 'onlinesched_sorttime', true);
	if (!is_numeric($start_time)) {
		continue;
	}
	$start_time = intval($start_time);

	$duration = get_post_meta($post_id, 'onlinesched_timelen', true);
	$duration = (is_numeric($duration) && intval($duration) >= 0) ? intval($duration) : 0;
	$end_time = $start_time + ($duration * 60);

	$end_datetime = new DateTime('@' . $end_time);
	$end_datetime->setTimeZone(new DateTimeZone('UTC'));

	if ($limit > 0 && $end_datetime < $now) {
		continue;
	}
	$limit--;

	$rooms = OnlineSched_terms_list2('os_room', $post_id);
	$tags = OnlineSched_terms_list2('os_tag', $post_id);
	$tags_array = array_map('trim', explode(',', $tags));
	$lower_tags = array_map('strtolower', $tags_array);
	$event_cancelled = in_array('canceled', $lower_tags, true) || in_array('cancelled', $lower_tags, true);

	if ($event_cancelled) {
		$rooms = 'Canceled';
	}

	$add_adult_tag = in_array('restricted', $lower_tags, true) ? ' [Adult]' : '';

	$json_out[] = array(
		'room'        => html_entity_decode($rooms, ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
		'title'       => html_entity_decode(get_the_title($post_id) . $add_adult_tag, ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
		'startTime'   => wp_date('g:i A', $start_time),
		'description' => wp_kses_post($item->post_content),
	);
}

wp_send_json($json_out);
