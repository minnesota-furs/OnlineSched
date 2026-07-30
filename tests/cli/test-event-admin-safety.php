<?php
/**
 * Disposable Vanilla-only integration checks for event cancellation controls.
 */

if (!defined('ABSPATH')) {
	exit(1);
}

$assert = static function ($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$administrators = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$assert(!empty($administrators), 'The disposable site must have an administrator.');
$original_user_id = get_current_user_id();
wp_set_current_user((int) $administrators[0]);

$run_id = wp_generate_uuid4();
$post_ids = array();
$created_term_ids = array();
$post_backup = $_POST;

$term_id = static function ($slug, $name) use (&$created_term_ids) {
	$term = get_term_by('slug', $slug, 'os_tag');
	if ($term instanceof WP_Term) {
		return $term->term_id;
	}

	$created = wp_insert_term($name, 'os_tag', array('slug' => $slug));
	if (is_wp_error($created)) {
		throw new RuntimeException($created->get_error_message());
	}

	$created_term_ids[] = (int) $created['term_id'];
	return (int) $created['term_id'];
};

$create_event = static function ($suffix, $status = 'publish') use (&$post_ids, $run_id) {
	$request_post = $_POST;
	$_POST = array();
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'os_event',
			'post_status' => $status,
			'post_title'  => 'Admin safety test ' . $suffix . ' ' . $run_id,
		),
		true
	);
	$_POST = $request_post;
	if (is_wp_error($post_id)) {
		throw new RuntimeException($post_id->get_error_message());
	}

	$post_ids[] = (int) $post_id;
	return (int) $post_id;
};

$render_box = static function ($post_id) {
	ob_start();
	onlinesched_render_cancellation_meta_box(get_post($post_id));
	return ob_get_clean();
};

$save_box = static function ($post_id, $checked, $nonce = null, $present = true, $initial = null, $changed = false) {
	$_POST = array();
	if ($present) {
		$_POST['onlinesched_event_cancellation_present'] = '1';
	}
	if (null !== $initial) {
		$_POST['onlinesched_event_cancellation_initial'] = $initial ? '1' : '0';
		$_POST['onlinesched_event_cancellation_changed'] = $changed ? '1' : '0';
	}
	if (null !== $nonce) {
		$_POST['onlinesched_event_cancellation_nonce'] = $nonce;
	}
	if ($checked) {
		$_POST['onlinesched_event_cancelled'] = '1';
	}

	onlinesched_save_cancellation_meta_box($post_id, get_post($post_id), true);
};

$slugs_for = static function ($post_id) {
	$slugs = wp_get_post_terms($post_id, 'os_tag', array('fields' => 'slugs'));
	if (is_wp_error($slugs)) {
		throw new RuntimeException($slugs->get_error_message());
	}
	sort($slugs);
	return $slugs;
};

try {
	$cancelled_id = $term_id('cancelled', 'Cancelled');
	$canceled_id = $term_id('canceled', 'Canceled');
	$other_name = 'Admin Safety ' . $run_id;
	$other_id = $term_id('admin-safety-' . sanitize_title($run_id), $other_name);
	$nonce = wp_create_nonce('onlinesched_save_event_cancellation');

	$canonical_event = $create_event('canonical');
	wp_set_post_terms($canonical_event, array($cancelled_id, $other_id), 'os_tag');
	$canonical_html = $render_box($canonical_event);
	$assert(false !== strpos($canonical_html, 'checked='), 'The checkbox must be checked for the Cancelled tag.');
	$assert(false !== strpos($canonical_html, 'This event is cancelled'), 'The native cancellation label must render.');
	$assert(false !== strpos($canonical_html, 'Keep the event published'), 'The cancellation guidance must render.');
	$assert(false !== strpos($canonical_html, 'onlinesched_event_cancellation_initial'), 'The rendered control must record its initial state.');

	$legacy_event = $create_event('legacy');
	wp_set_post_terms($legacy_event, array($canceled_id), 'os_tag');
	$legacy_html = $render_box($legacy_event);
	$assert(false !== strpos($legacy_html, 'checked='), 'The checkbox must be checked for the Canceled compatibility tag.');

	$checked_event = $create_event('checked');
	wp_set_post_terms($checked_event, array($other_id), 'os_tag');
	$_POST = array(
		'onlinesched_event_cancellation_present' => '1',
		'onlinesched_event_cancellation_initial' => '0',
		'onlinesched_event_cancellation_changed' => '1',
		'onlinesched_event_cancellation_nonce'   => $nonce,
		'onlinesched_event_cancelled'            => '1',
	);
	$revision_before = onlinesched_get_feed_revisions()['schedule']['rev'];
	wp_update_post(
		array(
			'ID'         => $checked_event,
			'post_title' => get_the_title($checked_event) . ' updated',
		)
	);
	$revision_after = onlinesched_get_feed_revisions()['schedule']['rev'];
	$assert(onlinesched_event_is_cancelled($checked_event), 'Checking the box must assign a cancellation tag.');
	$assert(in_array($other_id, wp_get_post_terms($checked_event, 'os_tag', array('fields' => 'ids')), true), 'Checking the box must preserve unrelated tags.');
	$assert('publish' === get_post_status($checked_event), 'Checking the box must not change publication status.');
	$assert($revision_before + 1 === $revision_after, 'A full event save with a cancellation change must move the schedule revision exactly once.');

	$unchecked_event = $create_event('unchecked');
	wp_set_post_terms($unchecked_event, array($cancelled_id, $canceled_id, $other_id), 'os_tag');
	$save_box($unchecked_event, false, $nonce, true, true);
	$assert(!onlinesched_event_is_cancelled($unchecked_event), 'Unchecking the box must remove both cancellation aliases.');
	$assert(array('admin-safety-' . sanitize_title($run_id)) === $slugs_for($unchecked_event), 'Unchecking the box must preserve unrelated tags.');

	$tag_removed_event = $create_event('tag removed');
	wp_set_post_terms($tag_removed_event, array($cancelled_id, $other_id), 'os_tag');
	wp_set_post_terms($tag_removed_event, array($other_id), 'os_tag');
	$save_box($tag_removed_event, true, $nonce, true, true);
	$assert(!onlinesched_event_is_cancelled($tag_removed_event), 'Removing the tag must uncheck cancellation when the checkbox was unchanged.');
	$assert(array('admin-safety-' . sanitize_title($run_id)) === $slugs_for($tag_removed_event), 'Removing the tag must preserve unrelated tags.');

	$tag_added_event = $create_event('tag added');
	wp_set_post_terms($tag_added_event, array($other_id), 'os_tag');
	wp_set_post_terms($tag_added_event, array($cancelled_id, $other_id), 'os_tag');
	$save_box($tag_added_event, false, $nonce, true, false);
	$assert(onlinesched_event_is_cancelled($tag_added_event), 'Adding the tag must check cancellation when the checkbox was unchanged.');
	$assert(in_array($other_id, wp_get_post_terms($tag_added_event, 'os_tag', array('fields' => 'ids')), true), 'Adding the tag must preserve unrelated tags.');

	$checkbox_wins_event = $create_event('checkbox wins');
	wp_set_post_terms($checkbox_wins_event, array($cancelled_id, $other_id), 'os_tag');
	wp_set_post_terms($checkbox_wins_event, array($other_id), 'os_tag');
	$save_box($checkbox_wins_event, true, $nonce, true, true, true);
	$assert(onlinesched_event_is_cancelled($checkbox_wins_event), 'A changed checkbox must win when both controls submit conflicting states.');

	$native_tag_removed_event = $create_event('native tag removed');
	wp_set_post_terms($native_tag_removed_event, array($cancelled_id, $other_id), 'os_tag');
	$_POST = array(
		'onlinesched_event_cancellation_present' => '1',
		'onlinesched_event_cancellation_initial' => '1',
		'onlinesched_event_cancellation_changed' => '0',
		'onlinesched_event_cancellation_nonce'   => $nonce,
		'onlinesched_event_cancelled'            => '1',
	);
	wp_update_post(
		array(
			'ID'        => $native_tag_removed_event,
			'tax_input' => array('os_tag' => array($other_name)),
		)
	);
	$assert(!onlinesched_event_is_cancelled($native_tag_removed_event), 'A native post save that removes the tag must clear cancellation.');

	$native_tag_added_event = $create_event('native tag added');
	wp_set_post_terms($native_tag_added_event, array($other_id), 'os_tag');
	$_POST = array(
		'onlinesched_event_cancellation_present' => '1',
		'onlinesched_event_cancellation_initial' => '0',
		'onlinesched_event_cancellation_changed' => '0',
		'onlinesched_event_cancellation_nonce'   => $nonce,
	);
	wp_update_post(
		array(
			'ID'        => $native_tag_added_event,
			'tax_input' => array('os_tag' => array('Cancelled', $other_name)),
		)
	);
	$assert(onlinesched_event_is_cancelled($native_tag_added_event), 'A native post save that adds the tag must set cancellation.');

	$missing_nonce_event = $create_event('missing nonce');
	wp_set_post_terms($missing_nonce_event, array($cancelled_id), 'os_tag');
	$save_box($missing_nonce_event, false, null, true, true);
	$assert(onlinesched_event_is_cancelled($missing_nonce_event), 'A missing nonce must leave cancellation unchanged.');

	$invalid_nonce_event = $create_event('invalid nonce');
	wp_set_post_terms($invalid_nonce_event, array($cancelled_id), 'os_tag');
	$save_box($invalid_nonce_event, false, 'invalid', true, true);
	$assert(onlinesched_event_is_cancelled($invalid_nonce_event), 'An invalid nonce must leave cancellation unchanged.');

	$programmatic_event = $create_event('programmatic');
	wp_set_post_terms($programmatic_event, array($cancelled_id), 'os_tag');
	$save_box($programmatic_event, false, $nonce, false, true);
	$assert(onlinesched_event_is_cancelled($programmatic_event), 'A save without the rendered control marker must leave cancellation unchanged.');

	$capability_event = $create_event('capability');
	wp_set_post_terms($capability_event, array($cancelled_id), 'os_tag');
	$deny_assignment = static function ($allcaps) {
		$allcaps['assign_os_tag'] = false;
		return $allcaps;
	};
	add_filter('user_has_cap', $deny_assignment, PHP_INT_MAX);
	$save_box($capability_event, false, $nonce, true, true);
	remove_filter('user_has_cap', $deny_assignment, PHP_INT_MAX);
	$assert(onlinesched_event_is_cancelled($capability_event), 'A user without tag assignment permission must not change cancellation.');

	$revision_event = $create_event('revision');
	wp_set_post_terms($revision_event, array($cancelled_id), 'os_tag');
	$revision_id = wp_save_post_revision($revision_event);
	if ($revision_id && !is_wp_error($revision_id)) {
		$_POST = array(
			'onlinesched_event_cancellation_present' => '1',
			'onlinesched_event_cancellation_initial' => '1',
			'onlinesched_event_cancellation_changed' => '0',
			'onlinesched_event_cancellation_nonce'   => $nonce,
		);
		onlinesched_save_cancellation_meta_box((int) $revision_id, get_post($revision_event), true);
		$assert(onlinesched_event_is_cancelled($revision_event), 'A revision save must leave cancellation unchanged.');
	}

	$published_config = onlinesched_event_safety_config('post', $canonical_event);
	$assert('post' === $published_config['screen'], 'Editor configuration must identify the post screen.');
	$assert(true === $published_config['editorPublished'], 'Editor configuration must identify a published event.');
	$assert(false !== strpos($published_config['confirmMessage'], 'Cancelled checkbox'), 'The warning must direct staff to the cancellation control.');

	$draft_event = $create_event('draft', 'draft');
	$draft_config = onlinesched_event_safety_config('post', $draft_event);
	$assert(false === $draft_config['editorPublished'], 'Editor configuration must not mark a draft as published.');
	$list_config = onlinesched_event_safety_config('edit');
	$assert('list' === $list_config['screen'], 'Events list configuration must identify the list screen.');

	$autosave_event = $create_event('autosave');
	wp_set_post_terms($autosave_event, array($cancelled_id), 'os_tag');
	if (!defined('DOING_AUTOSAVE')) {
		define('DOING_AUTOSAVE', true);
	}
	$save_box($autosave_event, false, $nonce, true, true);
	$assert(onlinesched_event_is_cancelled($autosave_event), 'An autosave must leave cancellation unchanged.');
} finally {
	$_POST = $post_backup;
	wp_set_current_user($original_user_id);

	foreach ($post_ids as $post_id) {
		wp_delete_post($post_id, true);
	}
	foreach ($created_term_ids as $created_term_id) {
		wp_delete_term($created_term_id, 'os_tag');
	}
}

echo "event admin safety checks passed\n";
