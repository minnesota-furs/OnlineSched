<?php
/**
 * Event cancellation controls and removal warnings.
 *
 * @package OnlineSched
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('add_meta_boxes_os_event', 'onlinesched_register_cancellation_meta_box');
add_action('save_post_os_event', 'onlinesched_save_cancellation_meta_box', 20, 3);
add_action('admin_enqueue_scripts', 'onlinesched_enqueue_event_safety_assets');

/**
 * Cancellation tag slugs accepted by existing OnlineSched content.
 *
 * @return string[]
 */
function onlinesched_cancellation_tag_slugs() {
	return array('cancelled', 'canceled');
}

/**
 * Whether an event has either supported cancellation tag.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function onlinesched_event_is_cancelled($post_id) {
	$slugs = wp_get_post_terms($post_id, 'os_tag', array('fields' => 'slugs'));
	if (is_wp_error($slugs)) {
		return false;
	}

	return !empty(array_intersect(onlinesched_cancellation_tag_slugs(), $slugs));
}

/**
 * Register the native Classic Editor cancellation control.
 */
function onlinesched_register_cancellation_meta_box() {
	add_meta_box(
		'onlinesched-event-cancellation',
		__('Cancellation', 'onlinesched'),
		'onlinesched_render_cancellation_meta_box',
		'os_event',
		'side',
		'high'
	);
}

/**
 * Render the cancellation tag shortcut.
 *
 * @param WP_Post $post Event being edited.
 */
function onlinesched_render_cancellation_meta_box($post) {
	$is_cancelled = onlinesched_event_is_cancelled($post->ID);
	wp_nonce_field('onlinesched_save_event_cancellation', 'onlinesched_event_cancellation_nonce');
	?>
	<input type="hidden" name="onlinesched_event_cancellation_present" value="1">
	<input
		type="hidden"
		name="onlinesched_event_cancellation_initial"
		value="<?php echo $is_cancelled ? '1' : '0'; ?>"
	>
	<input
		id="onlinesched-event-cancellation-changed"
		type="hidden"
		name="onlinesched_event_cancellation_changed"
		value="0"
	>
	<p>
		<label>
			<input
				id="onlinesched-event-cancelled"
				type="checkbox"
				name="onlinesched_event_cancelled"
				value="1"
				<?php checked($is_cancelled); ?>
			>
			<?php esc_html_e('This event is cancelled', 'onlinesched'); ?>
		</label>
	</p>
	<p class="description">
		<?php esc_html_e('Keep the event published so schedule, calendar, and app users can see that it was cancelled.', 'onlinesched'); ?>
	</p>
	<?php
}

/**
 * Find the preferred cancellation term, creating the canonical term if needed.
 *
 * @return WP_Term|WP_Error
 */
function onlinesched_get_cancellation_term() {
	foreach (onlinesched_cancellation_tag_slugs() as $slug) {
		$term = get_term_by('slug', $slug, 'os_tag');
		if ($term instanceof WP_Term) {
			return $term;
		}
	}

	$created = wp_insert_term(
		__('Cancelled', 'onlinesched'),
		'os_tag',
		array('slug' => 'cancelled')
	);
	if (is_wp_error($created)) {
		return $created;
	}

	$term = get_term((int) $created['term_id'], 'os_tag');
	if (!($term instanceof WP_Term)) {
		return new WP_Error(
			'onlinesched_cancellation_term_unavailable',
			__('The cancellation tag could not be loaded.', 'onlinesched')
		);
	}

	return $term;
}

/**
 * Apply the checkbox state through the existing os_tag taxonomy.
 *
 * @param int  $post_id  Event post ID.
 * @param bool $cancelled Desired cancellation state.
 * @return true|WP_Error
 */
function onlinesched_set_event_cancelled($post_id, $cancelled) {
	if ($cancelled) {
		if (onlinesched_event_is_cancelled($post_id)) {
			return true;
		}

		$term = onlinesched_get_cancellation_term();
		if (is_wp_error($term)) {
			return $term;
		}

		$result = wp_set_post_terms($post_id, array($term->term_id), 'os_tag', true);
		return is_wp_error($result) ? $result : true;
	}

	$assigned = wp_get_post_terms($post_id, 'os_tag');
	if (is_wp_error($assigned)) {
		return $assigned;
	}

	$remaining_ids = array();
	$has_cancellation_tag = false;
	foreach ($assigned as $term) {
		if (in_array($term->slug, onlinesched_cancellation_tag_slugs(), true)) {
			$has_cancellation_tag = true;
			continue;
		}
		$remaining_ids[] = $term->term_id;
	}

	if (!$has_cancellation_tag) {
		return true;
	}

	$result = wp_set_post_terms($post_id, $remaining_ids, 'os_tag', false);
	return is_wp_error($result) ? $result : true;
}

/**
 * Save the cancellation checkbox without affecting programmatic post saves.
 *
 * @param int     $post_id Event post ID.
 * @param WP_Post $post    Event post.
 * @param bool    $update  Whether this is an update.
 */
function onlinesched_save_cancellation_meta_box($post_id, $post, $update) {
	unset($update);

	if (!($post instanceof WP_Post) || 'os_event' !== $post->post_type) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	if (
		!isset(
			$_POST['onlinesched_event_cancellation_present'],
			$_POST['onlinesched_event_cancellation_initial'],
			$_POST['onlinesched_event_cancellation_changed'],
			$_POST['onlinesched_event_cancellation_nonce']
		)
		|| !is_string($_POST['onlinesched_event_cancellation_present'])
		|| !is_string($_POST['onlinesched_event_cancellation_initial'])
		|| !is_string($_POST['onlinesched_event_cancellation_changed'])
		|| !is_string($_POST['onlinesched_event_cancellation_nonce'])
		|| (isset($_POST['onlinesched_event_cancelled']) && !is_string($_POST['onlinesched_event_cancelled']))
	) {
		return;
	}

	$nonce = sanitize_text_field(wp_unslash($_POST['onlinesched_event_cancellation_nonce']));
	if (!wp_verify_nonce($nonce, 'onlinesched_save_event_cancellation')) {
		return;
	}

	if (!current_user_can('edit_post', $post_id) || !current_user_can('assign_os_tag')) {
		return;
	}

	$cancelled = isset($_POST['onlinesched_event_cancelled'])
		&& '1' === sanitize_text_field(wp_unslash($_POST['onlinesched_event_cancelled']));
	$initial_cancelled = '1' === sanitize_text_field(
		wp_unslash($_POST['onlinesched_event_cancellation_initial'])
	);
	$checkbox_changed = '1' === sanitize_text_field(
		wp_unslash($_POST['onlinesched_event_cancellation_changed'])
	);
	$tags_cancelled = onlinesched_event_is_cancelled($post_id);

	if (
		!$checkbox_changed
		&& $cancelled === $initial_cancelled
		&& $tags_cancelled !== $initial_cancelled
	) {
		$cancelled = $tags_cancelled;
	}

	onlinesched_set_event_cancelled($post_id, $cancelled);
}

/**
 * Configuration passed to the event-safety admin script.
 *
 * @param string $screen_base Current screen base.
 * @param int    $post_id     Current post ID, if any.
 * @return array
 */
function onlinesched_event_safety_config($screen_base, $post_id = 0) {
	return array(
		'screen'          => 'edit' === $screen_base ? 'list' : 'post',
		'editorPublished' => $post_id > 0 && 'publish' === get_post_status($post_id),
		'confirmMessage'  => __(
			'This event is currently published. Removing it may make it disappear from attendee schedules and calendars. If the event was cancelled, keep it published and use the Cancelled checkbox instead. Remove it anyway?',
			'onlinesched'
		),
	);
}

/**
 * Load the removal warning only on OnlineSched event admin screens.
 *
 * @param string $hook Current admin page hook.
 */
function onlinesched_enqueue_event_safety_assets($hook) {
	if (!in_array($hook, array('post.php', 'post-new.php', 'edit.php'), true)) {
		return;
	}

	$screen = get_current_screen();
	if (!$screen || 'os_event' !== $screen->post_type) {
		return;
	}

	$post_id = 0;
	if ('post' === $screen->base && isset($_GET['post'])) {
		$post_id = absint($_GET['post']);
	}

	wp_enqueue_script(
		'onlinesched-event-safety',
		ONLINESCHED_PLUGIN_URL . 'assets/js/admin-event-safety.js',
		array(),
		'3.2.0',
		true
	);
	wp_localize_script(
		'onlinesched-event-safety',
		'OnlineSchedEventSafety',
		onlinesched_event_safety_config($screen->base, $post_id)
	);
}
