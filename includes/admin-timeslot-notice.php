<?php
/**
 * Event time save notices.
 *
 * @package OnlineSched
 */

defined('ABSPATH') || exit;

/**
 * Store an event time save error for the current editor.
 *
 * @param int    $post_id Event being saved.
 * @param string $message Plain text shown to the editor.
 * @return void
 */
function onlinesched_flag_timeslot_refusal($post_id, $message)
{
	set_transient(
		'onlinesched_timeslot_refusal_' . get_current_user_id() . '_' . (int) $post_id,
		$message,
		60
	);
}

add_action('admin_notices', 'onlinesched_render_timeslot_refusal');

/**
 * Show and clear the refusal for the event being edited.
 *
 * @return void
 */
function onlinesched_render_timeslot_refusal()
{
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || 'os_event' !== $screen->id) {
		return;
	}
	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	if ($post_id < 1) {
		return;
	}
	$key = 'onlinesched_timeslot_refusal_' . get_current_user_id() . '_' . $post_id;
	$message = get_transient($key);
	if (!$message) {
		return;
	}
	delete_transient($key);
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html($message)
	);
}
