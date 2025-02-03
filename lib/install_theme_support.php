<?php
function online_schedule_register_plugin_page_template($page_templates, $theme, $post)
{
	$page_templates['page-schedule.php'] = 'Online Schedule';
	return $page_templates;
}

add_filter('theme_page_templates', 'online_schedule_register_plugin_page_template', 10, 3);


function online_schedule_load_plugin_template($template)
{

	global $post;

	if (!$post) {
		return $template;
	}

// Check if this is a page with slug 'schedule' first
	if (is_page('schedule')) {
// Check if theme has page-schedulephp
		$theme_template = plugin_dir_url(dirname(__FILE__)) . '/page-schedule.php';

		if (file_exists($theme_template)) {
			return $theme_template;
		}
	}

// Get template name selected for the page
	$template_name = get_post_meta($post->ID, '_wp_page_template', true);

	if ('page-schedule.php' === $template_name) {

// If the template is from our plugin, load it from the plugin directory
		$template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/page-schedule.php';
		if (file_exists($template_path)) {
			return $template_path;
		}
	}

	return $template;
}

add_filter('template_include', 'online_schedule_load_plugin_template');