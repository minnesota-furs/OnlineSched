<?php

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

class OnlineSched_WP_CLI_Command
{
	/**
	 * Import events from an OnlineSched CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Absolute path to a CSV file.
	 *
	 * [--year=<year>]
	 * : Schedule year to assign. Defaults to the active OnlineSched year.
	 *
	 * [--dry-run]
	 * : Validate and report changes without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp onlinesched import /tmp/events.csv --year=2027 --dry-run
	 *
	 * @when after_wp_load
	 */
	public function import($args, $assoc_args)
	{
		$file = isset($args[0]) ? (string) $args[0] : '';
		$options = array('dry_run' => isset($assoc_args['dry-run']));
		if (array_key_exists('year', $assoc_args)) {
			$options['year'] = $assoc_args['year'];
		}

		$result = onlinesched_import_csv($file, $options);
		WP_CLI::log(sprintf('Schedule year: %s', $result['year'] !== '' ? $result['year'] : '(invalid)'));
		if ($result['dry_run']) {
			WP_CLI::log('Dry run: no changes were written.');
		}

		foreach ($result['errors'] as $error) {
			$parts = array();
			if (!empty($error['line'])) {
				$parts[] = 'line ' . (int) $error['line'];
			}
			if ($error['external_id'] !== '') {
				$parts[] = 'event ' . $error['external_id'];
			}
			$prefix = empty($parts) ? '' : implode(', ', $parts) . ': ';
			WP_CLI::warning($prefix . $error['message']);
		}
		if ($result['dry_run'] && !empty($result['would_create_terms'])) {
			WP_CLI::log('Terms that would be created:');
			foreach ($result['would_create_terms'] as $term) {
				WP_CLI::log('  - ' . $term);
			}
		}

		$insert_label = $result['dry_run'] ? 'would insert' : 'inserted';
		$update_label = $result['dry_run'] ? 'would update' : 'updated';
		WP_CLI::log(sprintf(
			'Rows: %d; %s: %d; %s: %d; skipped: %d; failed: %d; elapsed: %.3fs',
			(int) $result['rows_read'],
			$insert_label,
			(int) $result['inserted'],
			$update_label,
			(int) $result['updated'],
			(int) $result['skipped'],
			(int) $result['failed'],
			(float) $result['duration_seconds']
		));

		if (!empty($result['errors']) || $result['skipped'] > 0 || $result['failed'] > 0) {
			WP_CLI::error('Import completed with errors.', false);
			WP_CLI::halt(1);
		}

		WP_CLI::success($result['dry_run'] ? 'Import dry run passed.' : 'Import completed.');
	}

	/**
	 * Permanently delete every event assigned to one exact schedule year.
	 *
	 * ## OPTIONS
	 *
	 * <year>
	 * : Exact schedule-year metadata value.
	 *
	 * [--dry-run]
	 * : List matching post IDs without deleting them.
	 *
	 * [--yes]
	 * : Confirm deletion without prompting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp onlinesched delete-year 2027 --dry-run
	 *     wp onlinesched delete-year 2027 --yes
	 *
	 * @when after_wp_load
	 */
	public function delete_year($args, $assoc_args)
	{
		$year = isset($args[0]) ? (string) $args[0] : '';
		$selection = onlinesched_delete_schedule_year($year, array('dry_run' => true));
		if (!empty($selection['errors'])) {
			foreach ($selection['errors'] as $error) {
				WP_CLI::warning($error);
			}
			WP_CLI::halt(1);
		}

		WP_CLI::log(sprintf('Schedule year: %s', $selection['year']));
		WP_CLI::log(sprintf('Matching events: %d', (int) $selection['selected']));
		if (isset($assoc_args['dry-run']) && !empty($selection['selected_ids'])) {
			WP_CLI::log('Post IDs: ' . implode(',', $selection['selected_ids']));
		}

		if (isset($assoc_args['dry-run'])) {
			WP_CLI::success('Dry run complete; no events were deleted.');
			return;
		}

		if ($selection['selected'] === 0) {
			WP_CLI::success('No matching events; nothing was deleted.');
			return;
		}

		WP_CLI::confirm(
			sprintf('Permanently delete %d OnlineSched events for exact year "%s"?', $selection['selected'], $selection['year']),
			$assoc_args
		);

		$result = onlinesched_delete_schedule_year($selection['year']);
		foreach ($result['errors'] as $error) {
			WP_CLI::warning($error);
		}
		WP_CLI::log(sprintf('Deleted: %d; failed: %d; elapsed: %.3fs', $result['deleted'], $result['failed'], $result['duration_seconds']));
		if ($result['failed'] > 0) {
			WP_CLI::halt(1);
		}

		WP_CLI::success(sprintf('Permanently deleted %d events for %s.', $result['deleted'], $result['year']));
	}
}

WP_CLI::add_command('onlinesched import', array(new OnlineSched_WP_CLI_Command(), 'import'));
WP_CLI::add_command('onlinesched delete-year', array(new OnlineSched_WP_CLI_Command(), 'delete_year'));
