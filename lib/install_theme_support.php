<?php
if (!defined('ABSPATH')) {
	exit;
}

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

	$is_os_page = onlinesched_is_configured_page($post, 'schedule', 'schedule')
		|| onlinesched_is_configured_page($post, 'kiosk', 'kiosk-schedule')
		|| onlinesched_is_configured_page($post, 'live', 'live');

	$template_name = get_post_meta($post->ID, '_wp_page_template', true);
	$is_os_template = ('page-schedule.php' === $template_name);

	if (!$is_os_page && !$is_os_template) {
		return $template;
	}

	$theme_template = locate_template(array('onlinesched/page-schedule.php'), false, false);
	if ($theme_template) {
		return $theme_template;
	}

	$plugin_template = ONLINESCHED_PLUGIN_DIR . 'templates/page-schedule.php';
	if (file_exists($plugin_template)) {
		return $plugin_template;
	}

	return $template;
}

add_filter('template_include', 'online_schedule_load_plugin_template');
