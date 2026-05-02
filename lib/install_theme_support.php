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

	$is_os_page = onlinesched_is_configured_page($post, 'schedule', 'schedule')
		|| onlinesched_is_configured_page($post, 'kiosk', 'kiosk-schedule')
		|| onlinesched_is_configured_page($post, 'live', 'live');

	$template_name = get_post_meta($post->ID, '_wp_page_template', true);
	$is_os_template = ('page-schedule.php' === $template_name);

	if ($is_os_page || $is_os_template) {
		$template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/page-schedule.php';
		if (file_exists($template_path)) {
			return $template_path;
		}
	}

	return $template;
}

add_filter('template_include', 'online_schedule_load_plugin_template');
