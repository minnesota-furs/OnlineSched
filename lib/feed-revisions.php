<?php
/**
 * Central feed invalidation service.
 *
 * All mutations that can change app-feed output route through
 * onlinesched_touch_feed(). Per-section revision counters back the feed's
 * ETag/Last-Modified handling and the meta section's change stamp; batch
 * operations (CSV/WP-CLI import, year delete) suspend per-row touches and
 * fire exactly one touch at their completion point.
 *
 * @package OnlineSched
 */

if (!defined('ABSPATH')) {
	exit;
}

define('ONLINESCHED_FEED_REVISIONS_OPTION', 'onlinesched_feed_revisions');

/**
 * Feed sections clients can fetch.
 *
 * @return string[]
 */
function onlinesched_feed_sections() {
	return array('schedule', 'hours', 'info');
}

/**
 * Sections tracked by the revision service. Includes the internal 'meta'
 * revision so meta-only payload changes (e.g. calendar name) invalidate the
 * meta ETag without falsely bumping a fetchable section.
 *
 * @return string[]
 */
function onlinesched_feed_tracked_sections() {
	return array('schedule', 'hours', 'info', 'meta');
}

/**
 * Current per-section revisions, normalized.
 *
 * @return array{schedule: array{rev:int, time:int}, hours: array{rev:int, time:int}, info: array{rev:int, time:int}}
 */
function onlinesched_get_feed_revisions() {
	$revisions = array();
	foreach (onlinesched_feed_tracked_sections() as $section) {
		$revisions[$section] = array(
			'rev'  => max(1, (int) get_option(onlinesched_feed_rev_option($section), 1)),
			'time' => max(0, (int) get_option(onlinesched_feed_revtime_option($section), 0)),
		);
	}
	return $revisions;
}

/**
 * Public composite stamp of the fetchable-section revisions.
 *
 * The internal meta revision deliberately stays out of this value: the
 * public contract is 3-part (schedule.hours.info). Meta-only payload changes
 * surface through the meta ETag, which combines this stamp with the internal
 * meta revision (see onlinesched_app_feed_etag()).
 *
 * @return string e.g. "41.7.12"
 */
function onlinesched_get_feed_change_stamp() {
	$revisions = onlinesched_get_feed_revisions();
	$parts = array();
	foreach (onlinesched_feed_sections() as $section) {
		$parts[] = $revisions[$section]['rev'];
	}

	return implode('.', $parts);
}

/**
 * Per-section revision storage: one integer option row per section, bumped
 * with a single atomic SQL increment. Concurrent requests can never lose an
 * increment — there is no read-modify-write to race and no retry budget to
 * exhaust (an earlier optimistic-CAS design measurably lost ~10% of
 * increments under 8-way contention). Revision *times* are informational and
 * stored as ordinary options; last-writer-wins is fine for a timestamp.
 * Rows autoload off; option caches invalidated after every direct write.
 */
function onlinesched_feed_rev_option($section) {
	return 'onlinesched_feed_rev_' . $section;
}

function onlinesched_feed_revtime_option($section) {
	return 'onlinesched_feed_revtime_' . $section;
}

/**
 * Atomically increment one section's revision counter.
 *
 * @param string $section Tracked section name.
 */
function onlinesched_feed_atomic_bump($section) {
	global $wpdb;

	$option = onlinesched_feed_rev_option($section);
	$moved = false;
	for ($attempt = 0; $attempt < 3; $attempt++) {
		$updated = $wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s",
			$option
		));
		if ($updated) {
			$moved = true;
			break;
		}
		if (false === $updated) {
			break; // SQL error — take the fallback below, never a silent no-op.
		}
		// Row absent (fresh install): baseline is 1, so first bump lands at 2.
		$inserted = $wpdb->query($wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '2', 'no')",
			$option
		));
		if ($inserted) {
			$moved = true;
			break;
		}
		if (false === $inserted) {
			break;
		}
		// Lost the insert race to another process; its row now exists — retry
		// the atomic UPDATE.
	}

	if (!$moved) {
		// Try to guarantee movement even at the cost of a possible lost
		// concurrent increment on this degraded path — but VERIFY: under a
		// persistent DB failure update_option also fails, and reporting
		// success while nothing moved would let clients cache stale content
		// with no signal anywhere.
		$current = max(1, (int) get_option($option, 1));
		$moved = (bool) update_option($option, (string) ($current + 1), false);
		if (!$moved) {
			error_log(sprintf('OnlineSched: feed revision bump FAILED for section "%s" (persistent database failure?)', $section));
		}
	}

	onlinesched_feed_flush_revisions_cache($option);
	return $moved;
}

/**
 * Advance a section's revision time monotonically.
 *
 * GREATEST() in SQL so a slower concurrent touch carrying an older timestamp
 * can never regress Last-Modified/generated.
 *
 * @param string $section Tracked section name.
 * @param int    $now     Timestamp of this touch.
 */
function onlinesched_feed_advance_revtime($section, $now) {
	global $wpdb;

	$option = onlinesched_feed_revtime_option($section);
	$now = (int) $now;
	$updated = $wpdb->query($wpdb->prepare(
		"UPDATE {$wpdb->options} SET option_value = GREATEST(CAST(option_value AS UNSIGNED), %d) WHERE option_name = %s",
		$now,
		$option
	));
	if (0 === $updated || false === $updated) {
		// Row may not exist yet; create it, then re-apply the monotonic
		// update in case another process won the insert race.
		$wpdb->query($wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			$option,
			(string) $now
		));
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = GREATEST(CAST(option_value AS UNSIGNED), %d) WHERE option_name = %s",
			$now,
			$option
		));
	}

	onlinesched_feed_flush_revisions_cache($option);
}

/** Invalidate cached copies after a direct options-table write. */
function onlinesched_feed_flush_revisions_cache($option) {
	wp_cache_delete($option, 'options');
	wp_cache_delete('notoptions', 'options');
	wp_cache_delete('alloptions', 'options');
}

/**
 * Suspend touch handling during a batch operation. Calls may nest.
 */
function onlinesched_feed_touch_suspend() {
	onlinesched_feed_touch_suspension(1);
}

/**
 * Resume touch handling after a batch operation.
 */
function onlinesched_feed_touch_resume() {
	onlinesched_feed_touch_suspension(-1);
}

/**
 * @param int $delta +1 suspend, -1 resume, 0 read.
 * @return int Current suspension depth.
 */
function onlinesched_feed_touch_suspension($delta = 0) {
	static $depth = 0;
	$depth = max(0, $depth + (int) $delta);
	return $depth;
}

/**
 * Bump the revision for one or more feed sections.
 *
 * The $reason string is forwarded on the onlinesched_feed_touched action so a
 * future change journal (push fan-out) can record why a section moved.
 *
 * @param string|string[] $sections Section name(s) from onlinesched_feed_sections().
 * @param string          $reason   Short machine-friendly cause, e.g. "csv-import".
 * @return bool Whether any revision moved.
 */
function onlinesched_touch_feed($sections, $reason = '') {
	if (onlinesched_feed_touch_suspension() > 0) {
		return false;
	}

	$valid = onlinesched_feed_tracked_sections();
	$sections = array_values(array_intersect((array) $sections, $valid));
	if (empty($sections)) {
		return false;
	}

	$now = time();
	$moved_sections = array();
	foreach ($sections as $section) {
		if (onlinesched_feed_atomic_bump($section)) {
			$moved_sections[] = $section;
			onlinesched_feed_advance_revtime($section, $now);
		}
	}

	// Honest result: if nothing moved (persistent database failure), no
	// invalidation happened — fire no action, flush nothing, report failure.
	if (empty($moved_sections)) {
		return false;
	}

	/**
	 * Fires after feed section revisions move.
	 *
	 * @param string[] $sections Sections whose revisions actually moved.
	 * @param string   $reason   Cause supplied by the mutating code path.
	 */
	do_action('onlinesched_feed_touched', $moved_sections, $reason);

	if (function_exists('w3tc_flush_all')) {
		w3tc_flush_all();
	}

	return true;
}

// ---------------------------------------------------------------------------
// Wiring: every known mutation path routes into the touch service.
// ---------------------------------------------------------------------------

/**
 * Request-scoped ID sets used to coalesce the several hooks that fire during
 * one logical mutation (save flow, permanent delete) into a single revision
 * bump. One meaningful change should move a revision by one.
 *
 * @param string   $set     'save-flow' | 'transitioned' | 'deleting'.
 * @param int|null $post_id ID to add (or remove when $remove) — null reads.
 * @param bool     $remove  Remove instead of add.
 * @return array<int, true>
 */
function onlinesched_feed_id_set($set, $post_id = null, $remove = false) {
	static $sets = array();
	if (!isset($sets[$set])) {
		$sets[$set] = array();
	}
	if (null !== $post_id) {
		if ($remove) {
			unset($sets[$set][(int) $post_id]);
		} else {
			$sets[$set][(int) $post_id] = true;
		}
	}
	return $sets[$set];
}

/**
 * Mark the save flow early so per-field hooks inside it coalesce.
 * save_post_{$type} fires BEFORE the generic save_post where the plugin's
 * meta/term writes live (priority 10), so the marker goes on the earliest
 * hook and the closing handler on generic save_post at a late priority —
 * otherwise the flow would be unmarked before the meta writes happen.
 */
add_action('save_post_os_event', 'onlinesched_feed_mark_save_flow', 1);
function onlinesched_feed_mark_save_flow($post_id) {
	onlinesched_feed_id_set('save-flow', $post_id);
}

/**
 * Close of the save flow — covers same-status title/content edits, quick
 * edit, and programmatic wp_update_post, which fire no status transition.
 * Generic save_post at priority 99 so every plugin meta/term write
 * (priority 10) has already happened and coalesced. Also assigns the durable
 * UUID for manually created events here, so the feed's read path never has
 * to write (see onlinesched_get_event_uid).
 */
add_action('save_post', 'onlinesched_feed_touch_on_event_save', 99, 3);
function onlinesched_feed_touch_on_event_save($post_id, $post, $update) {
	if (!($post instanceof WP_Post) || 'os_event' !== $post->post_type) {
		return;
	}
	onlinesched_feed_id_set('save-flow', $post_id, true);

	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}

	if (function_exists('onlinesched_ensure_event_uid_meta')) {
		onlinesched_ensure_event_uid_meta($post_id);
	}

	// The status-transition handler already bumped for this same save.
	if (isset(onlinesched_feed_id_set('transitioned')[(int) $post_id])) {
		onlinesched_feed_id_set('transitioned', $post_id, true);
		return;
	}

	if ('publish' !== $post->post_status) {
		return;
	}
	onlinesched_touch_feed('schedule', 'event-save');
}

/**
 * The one list of event meta keys whose values shape feed output. New
 * output-relevant meta belongs here and nowhere else.
 *
 * @return string[]
 */
function onlinesched_feed_output_meta_keys() {
	return array(
		'onlinesched_sorttime',
		'onlinesched_timelen',
		'onlinesched_year',
		'onlinesched_external_event_id',
		'onlinesched_event_uid',
	);
}

/** Output-relevant event meta changes outside a full save flow. */
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $onlinesched_feed_meta_hook) {
	add_action($onlinesched_feed_meta_hook, 'onlinesched_feed_touch_on_event_meta', 10, 3);
}
unset($onlinesched_feed_meta_hook);
function onlinesched_feed_touch_on_event_meta($meta_id, $object_id, $meta_key) {
	if (!in_array($meta_key, onlinesched_feed_output_meta_keys(), true)) {
		return;
	}
	// Coalesce into the single save-flow or delete bump for this post.
	if (isset(onlinesched_feed_id_set('save-flow')[(int) $object_id])
		|| isset(onlinesched_feed_id_set('deleting')[(int) $object_id])) {
		return;
	}
	if ('os_event' !== get_post_type($object_id)) {
		return;
	}
	// Draft/pending meta churn never reaches the feed.
	if ('publish' !== get_post_status($object_id)) {
		return;
	}
	onlinesched_touch_feed('schedule', 'event-meta');
}

/** Publish/trash/restore and other status movement for events. */
add_action('transition_post_status', 'onlinesched_feed_touch_on_status_change', 10, 3);
function onlinesched_feed_touch_on_status_change($new_status, $old_status, $post) {
	if ($new_status === $old_status || !($post instanceof WP_Post)) {
		return;
	}
	if ('os_event' !== $post->post_type) {
		return;
	}
	if ('publish' !== $new_status && 'publish' !== $old_status) {
		return;
	}
	onlinesched_feed_id_set('transitioned', $post->ID);
	onlinesched_touch_feed('schedule', 'event-status');
}

/** Permanent deletion of events (delete-year batches are suspended). */
add_action('before_delete_post', 'onlinesched_feed_touch_on_delete', 10, 2);
function onlinesched_feed_touch_on_delete($post_id, $post = null) {
	$post = $post instanceof WP_Post ? $post : get_post($post_id);
	if ($post && 'os_event' === $post->post_type) {
		// Meta-row deletions inside wp_delete_post coalesce into this bump.
		onlinesched_feed_id_set('deleting', $post_id);
		onlinesched_touch_feed('schedule', 'event-delete');
	}
}

add_action('deleted_post', 'onlinesched_feed_clear_deleting', 10, 1);
function onlinesched_feed_clear_deleting($post_id) {
	onlinesched_feed_id_set('deleting', $post_id, true);
}

/** Term assignment changes from quick edit / bulk edit (no metabox nonce). */
add_action('set_object_terms', 'onlinesched_feed_touch_on_term_assignment', 10, 6);
function onlinesched_feed_touch_on_term_assignment($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
	if (!in_array($taxonomy, array('os_room', 'os_tag', 'os_panelist', 'os_day'), true)) {
		return;
	}

	$new_ids = array_map('intval', (array) $tt_ids);
	$old_ids = array_map('intval', (array) $old_tt_ids);
	sort($new_ids);
	sort($old_ids);
	if ($new_ids === $old_ids && !$append) {
		return;
	}

	if ('os_event' !== get_post_type($object_id)) {
		return;
	}
	onlinesched_touch_feed('schedule', 'event-terms');
}

/** Term rename/description edits and deletions change rendered names. */
foreach (array('os_room', 'os_tag', 'os_panelist', 'os_day') as $onlinesched_feed_taxonomy) {
	add_action('edited_' . $onlinesched_feed_taxonomy, 'onlinesched_feed_touch_on_term_edit');
	add_action('delete_' . $onlinesched_feed_taxonomy, 'onlinesched_feed_touch_on_term_edit');
}
unset($onlinesched_feed_taxonomy);
function onlinesched_feed_touch_on_term_edit() {
	onlinesched_touch_feed('schedule', 'term-edit');
}

/**
 * The one map of options to the feed sections their changes invalidate. New
 * output-relevant options belong here and nowhere else.
 *
 * Output-changing FILTERS (os_json_room_groups, os_event_uid,
 * os_app_schedule_published, …) have no change event to hook; their resolved
 * values must enter the ETag instead (the schedule ETag variant already
 * carries resolved filters and the publication flag — see
 * onlinesched_app_feed_etag callers), or the site code changing filter
 * behavior must call onlinesched_touch_feed() itself. Documented in README.
 *
 * @return array<string, string[]>
 */
function onlinesched_feed_option_section_map() {
	return array(
		'onlinesched_year'                   => array('schedule'),
		'onlinesched_app_schedule_published' => array('schedule'),
		'onlinesched_con_start'              => array('schedule'),
		'onlinesched_con_end'                => array('schedule'),
		'onlinesched_public_date_start'      => array('schedule'),
		'onlinesched_public_date_end'        => array('schedule'),
		'onlinesched_json_room_groups'       => array('schedule'),
		// Timezone changes alter every rendered event offset.
		'timezone_string'                    => array('schedule'),
		'gmt_offset'                         => array('schedule'),
		// Meta-only handshake surfaces (internal meta revision).
		'onlinesched_calendar_name'          => array('meta'),
		// con_name falls back to the site title when the calendar name is blank.
		'blogname'                           => array('meta'),
		'onlinesched_hours_page_id'          => array('hours'),
		'onlinesched_app_info_page_ids'      => array('info'),
	);
}

add_action('init', 'onlinesched_feed_register_option_hooks');
function onlinesched_feed_register_option_hooks() {
	foreach (onlinesched_feed_option_section_map() as $option => $sections) {
		$handler = static function () use ($sections) {
			onlinesched_touch_feed($sections, 'settings');
		};
		add_action('update_option_' . $option, $handler);
		add_action('add_option_' . $option, $handler);
		add_action('delete_option_' . $option, $handler);
	}
}

// ---------------------------------------------------------------------------
// Versioned upgrade: runs once per schema version, only in admin/CLI contexts
// so anonymous feed requests never perform writes.
// ---------------------------------------------------------------------------

define('ONLINESCHED_FEED_SCHEMA_INSTALLED_OPTION', 'onlinesched_feed_schema_installed');
define('ONLINESCHED_FEED_SCHEMA_INSTALL_VERSION', 3);

add_action('init', 'onlinesched_feed_maybe_upgrade', 5);
function onlinesched_feed_maybe_upgrade() {
	if (!is_admin() && !(defined('WP_CLI') && WP_CLI)) {
		return;
	}
	if ((int) get_option(ONLINESCHED_FEED_SCHEMA_INSTALLED_OPTION, 0) >= ONLINESCHED_FEED_SCHEMA_INSTALL_VERSION) {
		return;
	}

	// v3: migrate the legacy serialized-blob revisions option (earlier 3.0.0
	// dev builds) into per-section integer rows, then retire the blob.
	$legacy = get_option(ONLINESCHED_FEED_REVISIONS_OPTION, null);
	if (is_array($legacy)) {
		foreach (onlinesched_feed_tracked_sections() as $section) {
			$entry = isset($legacy[$section]) && is_array($legacy[$section]) ? $legacy[$section] : array();
			add_option(onlinesched_feed_rev_option($section), (string) max(1, (int) ($entry['rev'] ?? 1)), '', 'no');
			add_option(onlinesched_feed_revtime_option($section), (string) max(0, (int) ($entry['time'] ?? 0)), '', 'no');
		}
		delete_option(ONLINESCHED_FEED_REVISIONS_OPTION);
	}

	// Seed counter rows and initialize persisted revision times so
	// `generated`/Last-Modified never depend on request time.
	$now = time();
	foreach (onlinesched_feed_tracked_sections() as $section) {
		add_option(onlinesched_feed_rev_option($section), '1', '', 'no');
		if ((int) get_option(onlinesched_feed_revtime_option($section), 0) <= 0) {
			update_option(onlinesched_feed_revtime_option($section), $now, false);
		}
	}

	// Backfill durable UUIDs for manually created events (no external id), so
	// the feed's read path never needs to write. Exception-safe: a throw from
	// a meta hook must not leave touch handling suspended for the request.
	if (function_exists('onlinesched_ensure_event_uid_meta')) {
		onlinesched_feed_touch_suspend();
		try {
			$event_ids = get_posts(array(
				'post_type'      => 'os_event',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			));
			foreach ($event_ids as $event_id) {
				onlinesched_ensure_event_uid_meta($event_id);
			}
		} finally {
			onlinesched_feed_touch_resume();
		}
	}

	update_option(ONLINESCHED_FEED_SCHEMA_INSTALLED_OPTION, ONLINESCHED_FEED_SCHEMA_INSTALL_VERSION, true);
	onlinesched_touch_feed(onlinesched_feed_sections(), 'feed-schema-upgrade');
}

/**
 * Sections a configured page feeds, or empty when the page is not configured.
 *
 * @param int $post_id Page ID.
 * @return string[]
 */
function onlinesched_feed_sections_for_page($post_id) {
	$sections = array();
	if ((int) get_option('onlinesched_hours_page_id', 0) === (int) $post_id) {
		$sections[] = 'hours';
	}
	if (function_exists('onlinesched_app_info_page_ids')
		&& in_array((int) $post_id, onlinesched_app_info_page_ids(), true)) {
		$sections[] = 'info';
	}
	return $sections;
}

/** Content edits to the configured hours page or any app info page. */
add_action('save_post_page', 'onlinesched_feed_touch_on_page_save');
function onlinesched_feed_touch_on_page_save($post_id) {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	$sections = onlinesched_feed_sections_for_page($post_id);
	if (!empty($sections)) {
		onlinesched_touch_feed($sections, 'page-content');
	}
}

/** Trash/untrash/status changes and permanent deletion of configured pages. */
add_action('transition_post_status', 'onlinesched_feed_touch_on_page_status', 10, 3);
function onlinesched_feed_touch_on_page_status($new_status, $old_status, $post) {
	if (!($post instanceof WP_Post) || 'page' !== $post->post_type || $new_status === $old_status) {
		return;
	}
	if ('publish' !== $new_status && 'publish' !== $old_status) {
		return;
	}
	$sections = onlinesched_feed_sections_for_page($post->ID);
	if (!empty($sections)) {
		onlinesched_touch_feed($sections, 'page-status');
	}
}

add_action('before_delete_post', 'onlinesched_feed_touch_on_page_delete', 10, 2);
function onlinesched_feed_touch_on_page_delete($post_id, $post = null) {
	$post = $post instanceof WP_Post ? $post : get_post($post_id);
	if (!$post || 'page' !== $post->post_type) {
		return;
	}
	$sections = onlinesched_feed_sections_for_page($post_id);
	if (!empty($sections)) {
		onlinesched_touch_feed($sections, 'page-delete');
	}
}
