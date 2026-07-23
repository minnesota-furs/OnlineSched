<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Return every registered post status name explicitly.
 *
 * @return string[]
 */
function onlinesched_event_post_statuses()
{
	$statuses = array_values(get_post_stati(array(), 'names'));

	return array_values(array_unique(array_filter(array_map('strval', $statuses))));
}

/**
 * Normalize and validate an exact schedule-year metadata value.
 *
 * @param mixed $year Candidate year.
 * @return string|WP_Error
 */
function onlinesched_validate_schedule_year($year)
{
	$year = sanitize_text_field((string) $year);
	$year = trim($year);

	if ($year === '') {
		return new WP_Error('onlinesched_empty_year', 'A nonempty schedule year is required.');
	}

	if ($year === 'Event Schedule Year') {
		return new WP_Error('onlinesched_placeholder_year', 'The schedule-year placeholder is not a valid year.');
	}

	return $year;
}

/**
 * Query one deterministic batch of event IDs for an exact schedule year.
 *
 * @param string $year Schedule year.
 * @param int    $limit Batch size.
 * @param int    $page Page number for read-only pagination.
 * @param int[]  $exclude_ids IDs to exclude after failed deletion attempts.
 * @return int[]|WP_Error
 */
function onlinesched_query_schedule_year_ids($year, $limit, $page = 1, $exclude_ids = array())
{
	global $wpdb;

	$statuses = onlinesched_event_post_statuses();
	$status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
	$exclude_ids = array_values(array_filter(array_map('intval', $exclude_ids)));
	$exclude_sql = '';
	$params = array_merge($statuses, array($year));
	if (!empty($exclude_ids)) {
		$exclude_sql = ' AND p.ID NOT IN (' . implode(',', array_fill(0, count($exclude_ids), '%d')) . ')';
		$params = array_merge($params, $exclude_ids);
	}
	$params[] = max(0, (max(1, (int) $page) - 1) * $limit);
	$params[] = $limit;

	$sql = "SELECT DISTINCT p.ID
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} ym ON ym.post_id = p.ID
		WHERE p.post_type = 'os_event'
		AND p.post_status IN ({$status_placeholders})
		AND ym.meta_key = 'onlinesched_year'
		AND ym.meta_value = %s
		{$exclude_sql}
		ORDER BY p.ID ASC
		LIMIT %d, %d";

	$ids = $wpdb->get_col($wpdb->prepare($sql, $params));
	if ($wpdb->last_error) {
		return new WP_Error('onlinesched_year_query_failed', 'Could not query schedule-year events: ' . $wpdb->last_error);
	}

	return array_map('intval', $ids);
}

/**
 * Select or permanently delete events assigned to one exact schedule year.
 *
 * @param string $year Schedule year.
 * @param array  $options dry_run and batch_size options.
 * @return array<string,mixed>
 */
function onlinesched_delete_schedule_year(string $year, array $options = array()): array
{
	$started = microtime(true);
	$dry_run = !empty($options['dry_run']);
	$batch_size = isset($options['batch_size']) ? (int) $options['batch_size'] : 100;
	$result = array(
		'year'             => '',
		'dry_run'          => $dry_run,
		'selected'         => 0,
		'deleted'          => 0,
		'failed'           => 0,
		'selected_ids'     => array(),
		'deleted_ids'      => array(),
		'failed_ids'       => array(),
		'errors'           => array(),
		'duration_seconds' => 0.0,
	);

	$validated_year = onlinesched_validate_schedule_year($year);
	if (is_wp_error($validated_year)) {
		$result['errors'][] = $validated_year->get_error_message();
		$result['failed'] = 1;
		$result['duration_seconds'] = microtime(true) - $started;
		return $result;
	}

	$result['year'] = $validated_year;
	if ($batch_size < 1 || $batch_size > 1000) {
		$result['errors'][] = 'Batch size must be between 1 and 1000.';
		$result['failed'] = 1;
		$result['duration_seconds'] = microtime(true) - $started;
		return $result;
	}

	if ($dry_run) {
		$page = 1;
		do {
			$ids = onlinesched_query_schedule_year_ids($validated_year, $batch_size, $page);
			if (is_wp_error($ids)) {
				$result['errors'][] = $ids->get_error_message();
				$result['failed'] = 1;
				$result['duration_seconds'] = microtime(true) - $started;
				return $result;
			}
			$result['selected_ids'] = array_merge($result['selected_ids'], $ids);
			$page++;
		} while (count($ids) === $batch_size);

		$result['selected'] = count($result['selected_ids']);
		$result['duration_seconds'] = microtime(true) - $started;
		return $result;
	}

	$failed_ids = array();
	onlinesched_feed_touch_suspend();
	try {
	try {
	while (true) {
		$ids = onlinesched_query_schedule_year_ids($validated_year, $batch_size, 1, $failed_ids);
		if (is_wp_error($ids)) {
			$result['errors'][] = $ids->get_error_message();
			$result['failed'] = max(1, count($result['failed_ids']));
			break;
		}
		if (empty($ids)) {
			break;
		}

		$result['selected_ids'] = array_merge($result['selected_ids'], $ids);
		foreach ($ids as $post_id) {
			if (wp_delete_post($post_id, true)) {
				$result['deleted_ids'][] = $post_id;
				continue;
			}

			$failed_ids[] = $post_id;
			$result['failed_ids'][] = $post_id;
			$result['errors'][] = sprintf('Failed to permanently delete event post %d.', $post_id);
		}
	}

	} finally {
		onlinesched_feed_touch_resume();
	}
	} catch (\Throwable $delete_exception) {
		// Deletions before the failure still changed feed output.
		if (!empty($result['deleted_ids'])) {
			onlinesched_touch_feed('schedule', 'delete-year-partial');
		}
		throw $delete_exception;
	}
	$result['selected'] = count(array_unique(array_merge($result['selected_ids'], $result['failed_ids'])));
	$result['deleted'] = count($result['deleted_ids']);
	$result['failed'] = max((int) $result['failed'], count($result['failed_ids']));
	$result['duration_seconds'] = microtime(true) - $started;

	if ($result['deleted'] > 0) {
		// onlinesched_touch_feed() owns the W3TC flush — exactly once.
		onlinesched_touch_feed('schedule', 'delete-year');
	}

	return $result;
}
