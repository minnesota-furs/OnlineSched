<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Import the existing OnlineSched CSV format through a presentation-neutral API.
 *
 * @param string $file_path CSV file path.
 * @param array  $options year and dry_run options.
 * @return array<string,mixed>
 */
function onlinesched_import_csv(string $file_path, array $options = array()): array
{
	$started = microtime(true);
	$dry_run = !empty($options['dry_run']);
	$year_value = array_key_exists('year', $options) ? $options['year'] : get_option('onlinesched_year');
	$result = array(
		'year'               => '',
		'dry_run'            => $dry_run,
		'rows_read'          => 0,
		'inserted'           => 0,
		'updated'            => 0,
		'skipped'            => 0,
		'failed'             => 0,
		'errors'             => array(),
		'would_create_terms' => array(),
		'duration_seconds'   => 0.0,
	);

	$year = onlinesched_validate_schedule_year($year_value);
	if (is_wp_error($year)) {
		onlinesched_import_add_error($result, 0, '', $year->get_error_message(), true);
		return onlinesched_import_finish_result($result, $started);
	}
	$result['year'] = $year;

	if (!is_file($file_path) || !is_readable($file_path)) {
		onlinesched_import_add_error($result, 0, '', 'The CSV path must be a readable regular file.', true);
		return onlinesched_import_finish_result($result, $started);
	}

	$preflight = onlinesched_import_preflight($file_path, $year, $result);
	$result = $preflight['result'];
	if ($preflight['fatal']) {
		return onlinesched_import_finish_result($result, $started);
	}

	$rows = $preflight['rows'];
	$existing = onlinesched_import_existing_event_map($year);
	if (is_wp_error($existing)) {
		onlinesched_import_add_error($result, 0, '', $existing->get_error_message(), true);
		return onlinesched_import_finish_result($result, $started);
	}

	$apply_rows = array();
	foreach ($rows as $row) {
		$matches = isset($existing[$row['external_id']]) ? $existing[$row['external_id']] : array();
		if (count($matches) > 1) {
			onlinesched_import_add_error(
				$result,
				$row['line'],
				$row['external_id'],
				'More than one existing event has this schedule year and external ID.',
				false
			);
			$result['failed']++;
			continue;
		}

		$row['post_id'] = count($matches) === 1 ? (int) $matches[0] : 0;
		$apply_rows[] = $row;
	}

	$term_state = onlinesched_import_load_term_state();
	if (is_wp_error($term_state)) {
		onlinesched_import_add_error($result, 0, '', $term_state->get_error_message(), true);
		return onlinesched_import_finish_result($result, $started);
	}

	if ($dry_run) {
		foreach ($apply_rows as $row) {
			onlinesched_import_preview_terms($row, $term_state, $result);
			if ($row['post_id'] > 0) {
				$result['updated']++;
			} else {
				$result['inserted']++;
			}
		}
		$result['would_create_terms'] = array_values(array_unique($result['would_create_terms']));
		return onlinesched_import_finish_result($result, $started);
	}

	if (empty($apply_rows)) {
		return onlinesched_import_finish_result($result, $started);
	}

	$prior_defer = wp_defer_term_counting();
	$save_post_state = onlinesched_import_action_state('save_post', 'OnlineSched_add_timeslot_fields');
	$added_filters = array();
	$filter_names = array(
		'wp_sitemaps_enabled',
		'wpseo_enable_metabox_insights',
		'wpseo_use_page_analysis',
		'wpseo_should_index_link',
	);

	try {
	try {
		wp_defer_term_counting(true);
		onlinesched_feed_touch_suspend();
		if ($save_post_state !== null) {
			remove_action('save_post', 'OnlineSched_add_timeslot_fields', $save_post_state['priority']);
		}

		foreach ($filter_names as $filter_name) {
			if (has_filter($filter_name, '__return_false') === false) {
				add_filter($filter_name, '__return_false', 10);
				$added_filters[] = $filter_name;
			}
		}

		foreach ($apply_rows as $row) {
			$term_ids = onlinesched_import_resolve_terms($row, $term_state);
			if (is_wp_error($term_ids)) {
				onlinesched_import_add_error($result, $row['line'], $row['external_id'], $term_ids->get_error_message(), false);
				$result['failed']++;
				continue;
			}

			$prior_post_state = $row['post_id'] > 0 ? onlinesched_import_snapshot_post($row['post_id']) : null;
			$post_data = onlinesched_import_post_data($row, $year);
			if ($row['post_id'] > 0) {
				$post_data['ID'] = $row['post_id'];
			}

			$post_id = wp_insert_post($post_data, true);
			if (is_wp_error($post_id) || !$post_id) {
				$message = is_wp_error($post_id) ? $post_id->get_error_message() : 'WordPress returned no post ID.';
				onlinesched_import_add_error($result, $row['line'], $row['external_id'], $message, false);
				$result['failed']++;
				continue;
			}

			$assignment_error = onlinesched_import_assign_terms((int) $post_id, $term_ids);
			if (is_wp_error($assignment_error)) {
				if ($row['post_id'] === 0) {
					wp_delete_post((int) $post_id, true);
				} else {
					$restored = onlinesched_import_restore_post($prior_post_state);
					if (is_wp_error($restored)) {
						$assignment_error = new WP_Error(
							'onlinesched_restore_failed',
							$assignment_error->get_error_message() . ' The prior post state also could not be restored: ' . $restored->get_error_message()
						);
					}
				}
				onlinesched_import_add_error($result, $row['line'], $row['external_id'], $assignment_error->get_error_message(), false);
				$result['failed']++;
				continue;
			}

			if ($row['post_id'] > 0) {
				$result['updated']++;
			} else {
				$result['inserted']++;
				$existing[$row['external_id']] = array((int) $post_id);
			}
		}
	} finally {
		foreach ($added_filters as $filter_name) {
			remove_filter($filter_name, '__return_false', 10);
		}
		if ($save_post_state !== null && has_action('save_post', 'OnlineSched_add_timeslot_fields') === false) {
			add_action(
				'save_post',
				'OnlineSched_add_timeslot_fields',
				$save_post_state['priority'],
				$save_post_state['accepted_args']
			);
		}
		wp_defer_term_counting($prior_defer);
		onlinesched_feed_touch_resume();
	}
	} catch (\Throwable $import_exception) {
		// Partial writes before the failure still changed feed output; a
		// revision that fails to move would leave clients stale forever.
		if (($result['inserted'] + $result['updated']) > 0) {
			onlinesched_touch_feed('schedule', 'csv-import-partial');
		}
		throw $import_exception;
	}

	if (($result['inserted'] + $result['updated']) > 0) {
		// onlinesched_touch_feed() owns the W3TC flush — exactly once.
		onlinesched_touch_feed('schedule', 'csv-import');
	}

	return onlinesched_import_finish_result($result, $started);
}

/**
 * Parse and validate the complete CSV without writes.
 *
 * @param string $file_path CSV path.
 * @param string $year Schedule year.
 * @param array  $result Current result.
 * @return array<string,mixed>
 */
function onlinesched_import_preflight($file_path, $year, $result)
{
	$required = array('id', 'name', 'date', 'time', 'description', 'room_type', 'speakers', 'length', 'tags');
	$rows = array();
	$seen_ids = array();
	$fatal = false;
	$handle = fopen($file_path, 'r');
	if ($handle === false) {
		onlinesched_import_add_error($result, 0, '', 'Failed to open the CSV file.', true);
		return array('rows' => array(), 'result' => $result, 'fatal' => true);
	}

	try {
		$headers = fgetcsv($handle, 0, ',', '"', '');
		if (!is_array($headers)) {
			onlinesched_import_add_error($result, 1, '', 'The CSV file is empty.', true);
			return array('rows' => array(), 'result' => $result, 'fatal' => true);
		}

		$normalized_headers = array_map(
			static function ($header) {
				return strtolower(trim((string) $header));
			},
			$headers
		);
		if (array_slice($normalized_headers, 0, count($required)) !== $required) {
			onlinesched_import_add_error(
				$result,
				1,
				'',
				'CSV file format is incorrect. Expected headers: ID, Name, Date, Time, Description, Room_Type, Speakers, Length, Tags.',
				true
			);
			return array('rows' => array(), 'result' => $result, 'fatal' => true);
		}

		$line = 1;
		while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
			$line++;
			$result['rows_read']++;
			if (count($data) < count($required)) {
				onlinesched_import_add_error($result, $line, '', 'The row has fewer than nine fields.', false);
				$result['skipped']++;
				continue;
			}

			$external_id = schedule_convert_to_utf8_and_santize($data[0]);
			if ($external_id === '') {
				onlinesched_import_add_error($result, $line, '', 'The external event ID is empty.', true);
				$fatal = true;
				continue;
			}
			if (isset($seen_ids[$external_id])) {
				onlinesched_import_add_error(
					$result,
					$line,
					$external_id,
					sprintf('The external event ID duplicates CSV line %d.', $seen_ids[$external_id]),
					true
				);
				$fatal = true;
				continue;
			}
			$seen_ids[$external_id] = $line;

			$name = schedule_convert_to_utf8_and_santize($data[1]);
			if ($name === '') {
				$name = trim(wp_kses_post(schedule_convert_to_utf8($data[1])));
			}
			if ($name === '') {
				onlinesched_import_add_error($result, $line, $external_id, 'The event name is empty.', false);
				$result['skipped']++;
				continue;
			}

			$date = schedule_convert_to_utf8_and_santize($data[2]);
			$time = schedule_convert_to_utf8_and_santize($data[3]);
			if ($date === '' || $time === '') {
				$day_name = 'Unscheduled';
				$day_date = '0';
				$hour = 0;
				$minute = 0;
				$timestamp = 0;
			} else {
				$date_time = onlinesched_parse_local_datetime($date, $time);
				if (!$date_time) {
					onlinesched_import_add_error($result, $line, $external_id, 'The row has an invalid date or time.', false);
					$result['skipped']++;
					continue;
				}
				$day_name = $date_time->format('l');
				$day_date = $date_time->format('n/j/Y');
				$hour = $date_time->format('H');
				$minute = $date_time->format('i');
				$timestamp = $date_time->getTimestamp();
			}

			$rows[] = array(
				'line'        => $line,
				'external_id' => $external_id,
				'name'        => $name,
				'description' => schedule_convert_to_utf8_and_kses($data[4]),
				'room'        => schedule_convert_to_utf8_and_santize($data[5]),
				'speakers'    => onlinesched_import_split_values(schedule_convert_to_utf8_and_santize($data[6])),
				'length'      => schedule_convert_to_utf8_and_santize($data[7]),
				'tags'        => onlinesched_import_split_values(schedule_convert_to_utf8_and_santize($data[8])),
				'day_name'    => $day_name,
				'day_slug'    => sanitize_title($day_name),
				'day_date'    => $day_date,
				'hour'        => $hour,
				'minute'      => $minute,
				'timestamp'   => $timestamp,
				'year'        => $year,
			);
		}
	} finally {
		fclose($handle);
	}

	return array('rows' => $rows, 'result' => $result, 'fatal' => $fatal);
}

/**
 * Build the composite-identity map for one schedule year in bounded pages.
 *
 * @param string $year Schedule year.
 * @return array<string,int[]>|WP_Error
 */
function onlinesched_import_existing_event_map($year)
{
	global $wpdb;

	$map = array();
	$last_id = 0;
	$batch_size = 500;
	$statuses = onlinesched_event_post_statuses();
	$status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
	while (true) {
		$params = array_merge($statuses, array($year, $last_id, $batch_size));
		$sql = "SELECT DISTINCT p.ID, em.meta_value AS external_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} ym ON ym.post_id = p.ID
			INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID
			WHERE p.post_type = 'os_event'
			AND p.post_status IN ({$status_placeholders})
			AND ym.meta_key = 'onlinesched_year'
			AND ym.meta_value = %s
			AND em.meta_key = 'onlinesched_external_event_id'
			AND p.ID > %d
			ORDER BY p.ID ASC
			LIMIT %d";
		$records = $wpdb->get_results($wpdb->prepare($sql, $params));
		if ($wpdb->last_error) {
			return new WP_Error('onlinesched_event_query_failed', 'Could not query existing OnlineSched events: ' . $wpdb->last_error);
		}
		foreach ($records as $record) {
			$post_id = (int) $record->ID;
			$external_id = (string) $record->external_id;
			if ($external_id !== '') {
				$map[$external_id][] = $post_id;
			}
			$last_id = max($last_id, $post_id);
		}

		if (count($records) < $batch_size) {
			break;
		}
	}

	return $map;
}

/**
 * Load taxonomy terms for read-only preview and apply caches.
 *
 * @return array<string,array>|WP_Error
 */
function onlinesched_import_load_term_state()
{
	global $wpdb;

	$taxonomies = array('os_room', 'os_day', 'os_panelist', 'os_tag');
	$placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));
	$sql = "SELECT t.term_id, t.name, t.slug, tt.description, tt.taxonomy
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
		WHERE tt.taxonomy IN ({$placeholders})
		ORDER BY t.term_id ASC";
	$terms = $wpdb->get_results($wpdb->prepare($sql, $taxonomies));
	if ($wpdb->last_error) {
		return new WP_Error('onlinesched_term_read_failed', 'Could not read OnlineSched taxonomy terms: ' . $wpdb->last_error);
	}

	$state = array_fill_keys($taxonomies, array());
	foreach ($terms as $term) {
		$key = $term->taxonomy === 'os_panelist' ? strtolower(trim($term->name)) : $term->slug;
		$state[$term->taxonomy][$key] = array(
			'term_id'     => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
		);
	}

	return $state;
}

/**
 * Record terms a dry run would need without writing them.
 */
function onlinesched_import_preview_terms($row, $state, &$result)
{
	if ($row['room'] !== '') {
		$slug = online_create_custom_slug($row['room']);
		if (!isset($state['os_room'][$slug])) {
			$result['would_create_terms'][] = 'os_room:' . $row['room'];
		}
	}
	if (!isset($state['os_day'][$row['day_slug']]) || $state['os_day'][$row['day_slug']]['description'] !== $row['day_date']) {
		$result['would_create_terms'][] = 'os_day:' . $row['day_name'] . ':' . $row['day_date'];
	}
	foreach ($row['speakers'] as $speaker) {
		if (!isset($state['os_panelist'][strtolower(trim($speaker))])) {
			$result['would_create_terms'][] = 'os_panelist:' . $speaker;
		}
	}
	foreach ($row['tags'] as $tag) {
		$tag = ucwords($tag);
		if (!isset($state['os_tag'][online_create_custom_slug($tag)])) {
			$result['would_create_terms'][] = 'os_tag:' . $tag;
		}
	}
}

/**
 * Resolve all terms for one row before its post write.
 *
 * @return array<string,int[]>|WP_Error
 */
function onlinesched_import_resolve_terms($row, &$state)
{
	$resolved = array('os_room' => array(), 'os_day' => array(), 'os_panelist' => array(), 'os_tag' => array());

	if ($row['room'] !== '') {
		$room_id = onlinesched_import_resolve_term('os_room', $row['room'], online_create_custom_slug($row['room']), $state['os_room']);
		if (is_wp_error($room_id)) {
			return $room_id;
		}
		$resolved['os_room'][] = $room_id;
	}

	$day_id = onlinesched_import_resolve_day($row, $state['os_day']);
	if (is_wp_error($day_id)) {
		return $day_id;
	}
	$resolved['os_day'][] = $day_id;

	foreach ($row['speakers'] as $speaker) {
		$key = strtolower(trim($speaker));
		if (isset($state['os_panelist'][$key])) {
			$resolved['os_panelist'][] = $state['os_panelist'][$key]['term_id'];
			continue;
		}
		$term = wp_insert_term($speaker, 'os_panelist');
		$term_id = onlinesched_import_term_result($term);
		if (is_wp_error($term_id)) {
			return $term_id;
		}
		$state['os_panelist'][$key] = array('term_id' => $term_id, 'name' => $speaker, 'slug' => '', 'description' => '');
		$resolved['os_panelist'][] = $term_id;
	}

	foreach ($row['tags'] as $tag) {
		$tag = ucwords($tag);
		$tag_id = onlinesched_import_resolve_term('os_tag', $tag, online_create_custom_slug($tag), $state['os_tag']);
		if (is_wp_error($tag_id)) {
			return $tag_id;
		}
		$resolved['os_tag'][] = $tag_id;
	}

	return $resolved;
}

function onlinesched_import_resolve_term($taxonomy, $name, $slug, &$cache)
{
	if (isset($cache[$slug])) {
		return (int) $cache[$slug]['term_id'];
	}
	$term = wp_insert_term($name, $taxonomy, array('slug' => $slug));
	$term_id = onlinesched_import_term_result($term);
	if (is_wp_error($term_id)) {
		return $term_id;
	}
	$cache[$slug] = array('term_id' => $term_id, 'name' => $name, 'slug' => $slug, 'description' => '');
	return $term_id;
}

function onlinesched_import_resolve_day($row, &$cache)
{
	$slug = $row['day_slug'];
	if (isset($cache[$slug]) && $cache[$slug]['description'] === $row['day_date']) {
		return (int) $cache[$slug]['term_id'];
	}

	if (isset($cache[$slug])) {
		$old = $cache[$slug];
		$old_date = DateTimeImmutable::createFromFormat('!n/j/Y', trim($old['description']), wp_timezone());
		$suffix = $old_date ? $old_date->format('Y') : 'old';
		$new_name = $old['name'] . '-' . $suffix;
		$new_slug = sanitize_title($new_name);
		if (isset($cache[$new_slug])) {
			$new_slug .= '-' . $old['term_id'];
			$new_name .= '-' . $old['term_id'];
		}
		$updated = wp_update_term($old['term_id'], 'os_day', array('name' => $new_name, 'slug' => $new_slug));
		if (is_wp_error($updated)) {
			return new WP_Error('onlinesched_day_archive_failed', 'Could not archive the prior day term: ' . $updated->get_error_message());
		}
		$cache[$new_slug] = array_merge($old, array('name' => $new_name, 'slug' => $new_slug));
		unset($cache[$slug]);
	}

	$term = wp_insert_term($row['day_name'], 'os_day', array('description' => $row['day_date'], 'slug' => $slug));
	$term_id = onlinesched_import_term_result($term);
	if (is_wp_error($term_id)) {
		return $term_id;
	}
	$description_update = wp_update_term($term_id, 'os_day', array('description' => $row['day_date']));
	if (is_wp_error($description_update)) {
		return new WP_Error('onlinesched_day_update_failed', 'Could not update the day term: ' . $description_update->get_error_message());
	}
	$cache[$slug] = array('term_id' => $term_id, 'name' => $row['day_name'], 'slug' => $slug, 'description' => $row['day_date']);
	return $term_id;
}

function onlinesched_import_term_result($term)
{
	if (!is_wp_error($term)) {
		return (int) $term['term_id'];
	}
	if ($term->get_error_code() === 'term_exists') {
		return (int) $term->get_error_data('term_exists');
	}
	return new WP_Error('onlinesched_term_write_failed', $term->get_error_message());
}

function onlinesched_import_post_data($row, $year)
{
	return array(
		'post_title'   => $row['name'],
		'post_content' => $row['description'],
		'post_status'  => 'publish',
		'post_type'    => 'os_event',
		'meta_input'   => array(
			'onlinesched_time_hr'           => $row['hour'],
			'onlinesched_time_min'          => $row['minute'],
			'onlinesched_year'              => $year,
			'onlinesched_sorttime'          => (int) $row['timestamp'],
			'onlinesched_timelen'           => $row['length'],
			'onlinesched_external_event_id' => $row['external_id'],
		),
	);
}

function onlinesched_import_assign_terms($post_id, $terms)
{
	foreach ($terms as $taxonomy => $term_ids) {
		$assigned = wp_set_object_terms($post_id, array_map('intval', $term_ids), $taxonomy, false);
		if (is_wp_error($assigned)) {
			return new WP_Error('onlinesched_term_assignment_failed', sprintf('Could not assign %s terms: %s', $taxonomy, $assigned->get_error_message()));
		}
	}
	return true;
}

function onlinesched_import_snapshot_post($post_id)
{
	$post = get_post($post_id);
	$meta_keys = array(
		'onlinesched_time_hr',
		'onlinesched_time_min',
		'onlinesched_year',
		'onlinesched_sorttime',
		'onlinesched_timelen',
		'onlinesched_external_event_id',
	);
	$state = array(
		'ID'           => (int) $post_id,
		'post_title'   => $post ? $post->post_title : '',
		'post_content' => $post ? $post->post_content : '',
		'post_status'  => $post ? $post->post_status : 'draft',
		'meta'         => array(),
		'terms'        => array(),
	);
	foreach ($meta_keys as $meta_key) {
		$state['meta'][$meta_key] = array(
			'exists' => metadata_exists('post', $post_id, $meta_key),
			'value'  => get_post_meta($post_id, $meta_key, true),
		);
	}
	foreach (array('os_room', 'os_day', 'os_panelist', 'os_tag') as $taxonomy) {
		$term_ids = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
		$state['terms'][$taxonomy] = is_wp_error($term_ids) ? array() : array_map('intval', $term_ids);
	}
	return $state;
}

function onlinesched_import_restore_post($state)
{
	$restored = wp_update_post(array(
		'ID'           => $state['ID'],
		'post_title'   => $state['post_title'],
		'post_content' => $state['post_content'],
		'post_status'  => $state['post_status'],
	), true);
	if (is_wp_error($restored)) {
		return $restored;
	}
	foreach ($state['meta'] as $meta_key => $meta_state) {
		if ($meta_state['exists']) {
			update_post_meta($state['ID'], $meta_key, $meta_state['value']);
		} else {
			delete_post_meta($state['ID'], $meta_key);
		}
	}
	foreach ($state['terms'] as $taxonomy => $term_ids) {
		$term_result = wp_set_object_terms($state['ID'], $term_ids, $taxonomy, false);
		if (is_wp_error($term_result)) {
			return $term_result;
		}
	}
	return true;
}

function onlinesched_import_split_values($value)
{
	return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
}

function onlinesched_import_add_error(&$result, $line, $external_id, $message, $fatal)
{
	$result['errors'][] = array(
		'line'        => (int) $line,
		'external_id' => (string) $external_id,
		'message'     => (string) $message,
		'fatal'       => (bool) $fatal,
	);
}

function onlinesched_import_finish_result($result, $started)
{
	$result['duration_seconds'] = microtime(true) - $started;
	return $result;
}

/**
 * Capture the priority and accepted-argument count for one registered action.
 *
 * @param string          $hook_name Hook name.
 * @param callable|string $callback Callback.
 * @return array<string,int>|null
 */
function onlinesched_import_action_state($hook_name, $callback)
{
	global $wp_filter;

	$priority = has_action($hook_name, $callback);
	if ($priority === false || !isset($wp_filter[$hook_name])) {
		return null;
	}

	$accepted_args = 1;
	$callbacks = $wp_filter[$hook_name]->callbacks[$priority] ?? array();
	foreach ($callbacks as $registered) {
		if (($registered['function'] ?? null) === $callback) {
			$accepted_args = (int) ($registered['accepted_args'] ?? 1);
			break;
		}
	}

	return array(
		'priority'      => (int) $priority,
		'accepted_args' => $accepted_args,
	);
}
