<?php
if (!defined('ABSPATH')) {
	exit;
}

/* Used for theming */
function onlinesched_get_template_part($slug, $args = array())
{
	$slug = trim((string) $slug, '/');
	if ('' === $slug || preg_match('/[^A-Za-z0-9_\/-]/', $slug)) {
		return false;
	}

	$template = locate_template(array('onlinesched/partials/' . $slug . '.php'), false, false);
	if (!$template) {
		$template = ONLINESCHED_PLUGIN_DIR . 'templates/partials/' . $slug . '.php';
	}

	if (!file_exists($template)) {
		return false;
	}

	if (is_array($args) && !empty($args)) {
		extract($args, EXTR_SKIP);
	}

	include $template;
	return true;
}

function OnlineSched_terms_list($term, &$masterList = null): string
{
	// Rooms always need whole term objects: the stored slug is their identity.
	$fields = ($masterList === null && 'os_room' !== $term) ? 'names' : 'all';
	$tags_arr = wp_get_post_terms(get_the_ID(), $term, ['fields' => $fields]);

	if (empty($tags_arr)) {
		return 'None';
	}

	$tag_names = [];
	$tag_count = count($tags_arr);
	foreach ($tags_arr as $index => $tag) {
		$name = is_object($tag) ? $tag->name : $tag;
		$classes = ['os-term-item'];
		$attrs = ' data-os-term-label="' . esc_attr($name) . '"';
		if ('os_room' === $term && is_object($tag)) {
			$attrs .= ' data-os-term-slug="' . esc_attr($tag->slug) . '"';
		}
		if ('os_tag' === $term) {
			$classes[] = 'os-schedule-tag';
			$route = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($name)));
			$attrs .= ' data-os-tag-route="' . esc_attr($route) . '"';
		}
		$separator = ($index < $tag_count - 1) ? '<span class="os-term-separator" aria-hidden="true">,</span>' : '';
		$tag_names[] = '<span class="' . esc_attr(implode(' ', $classes)) . '"' . $attrs . '>' . esc_html($name) . $separator . '</span>';

		if ($masterList !== null && is_object($tag)) {
			$masterList[$tag->name] = $tag->slug;
		}
	}

	return implode(' ', $tag_names);
}


// return as array
function OnlineSched_terms_slug_array($term) {
    $tags = wp_get_post_terms(get_the_ID(), $term, array('fields' => 'slugs'));
    return $tags;
}
function OnlineSched_terms_list2($term, $id = 0) {
	if ($id == 0) {
		$id = get_the_ID();
	}
        $tags_arr = wp_get_post_terms($id, $term);
        $tags = '';
        if ($tags_arr) {
                $first_entry = true;
                for ($i = 0; $i < count($tags_arr); $i++) {
                        if ($first_entry == false) {
                                $tags .= ', ';
                        }
                        $tags .= $tags_arr[$i]->name;
                        $first_entry = false;
                }
        } else {
                $tags .= 'None';
        }

        return $tags;
}

function OnlineSched_terms_list_desc($term) {
	$tags_arr = wp_get_post_terms(get_the_ID(), $term);
	$tags = '';
	if ($tags_arr) {
		$first_entry = true;
		for ($i = 0; $i < count($tags_arr); $i++) {
			if ($first_entry == false) {
				$tags .= ', ';
			}
			$tags .= $tags_arr[$i]->description;
			$first_entry = false;
		}
	} else {
		$tags .= 'None';
	}

	return $tags;
}

/**
 * Calendar subscription links for a single event, plus its end time.
 *
 * Subscriptions keep a time or room change in step on the phone; the end
 * time lets the page refuse new subscriptions to finished events. A finished
 * event gets "#" so crawlers have no dead feed to follow; the button stays.
 *
 * @param int      $post_id Event post ID.
 * @param int|null $now     Current timestamp; defaults to time().
 * @return array{ical:string,google:string,end:int}
 */
function onlinesched_single_event_calendar_links($post_id, $now = null) {
	$post_id = absint($post_id);
	$start = (int) get_post_meta($post_id, 'onlinesched_sorttime', true);
	$duration = max(0, (int) get_post_meta($post_id, 'onlinesched_timelen', true));
	$end = $start > 0 ? $start + ($duration * 60) : 0;

	$now = null === $now ? time() : (int) $now;
	if ($end > 0 && $end < $now) {
		return array('ical' => '#', 'google' => '#', 'end' => $end);
	}

	$ical = 'webcal://' . preg_replace('/^https?:\/\//', '', ONLINESCHED_PLUGIN_URL) . 'ical.php?cal-id=' . $post_id;

	return array(
		'ical' => $ical,
		'google' => 'https://calendar.google.com/calendar/r?cid=' . urlencode($ical),
		'end' => $end,
	);
}
?>
