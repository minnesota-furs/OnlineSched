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
	$fields = ($masterList === null) ? 'names' : 'all';
	$tags_arr = wp_get_post_terms(get_the_ID(), $term, ['fields' => $fields]);

	if (empty($tags_arr)) {
		return 'None';
	}

	$tag_names = [];
	foreach ($tags_arr as $tag) {
		$name = ($masterList === null) ? $tag : $tag->name ;
		$classes = ['os-term-item'];
		$attrs = '';
		if ('os_tag' === $term) {
			$classes[] = 'os-schedule-tag';
			$route = preg_replace('/[^a-z0-9]/', '', strtolower(remove_accents($name)));
			$attrs .= ' data-os-tag-route="' . esc_attr($route) . '"';
		}
		$tag_names[] = '<span class="' . esc_attr(implode(' ', $classes)) . '"' . $attrs . '>' . esc_html($name) . '</span>';

		if ($masterList !== null) {
			$masterList[$tag->name] = $tag->slug;
		}
	}

	return implode(', ', $tag_names);
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
?>
