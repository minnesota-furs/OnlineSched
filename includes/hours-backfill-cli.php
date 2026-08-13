<?php

if (!defined('WP_CLI') || !WP_CLI) {
	return;
}

/**
 * Fills the structured Opens/Closes fields from the hours line staff already
 * typed, so an existing Hours page does not have to be re-entered by hand.
 */
class OnlineSched_Hours_Backfill_CLI
{
	/**
	 * Read each hours line and fill start, end, all-day and closed from it.
	 *
	 * Reports every row it could not read, because a line this cannot parse is
	 * a line the app will never call open.
	 *
	 * ## OPTIONS
	 *
	 * [--page=<id>]
	 * : Hours page to read. Defaults to the configured Hours page.
	 *
	 * [--write]
	 * : Apply the changes. Without it nothing is saved.
	 *
	 * [--force]
	 * : Also overwrite rows that already carry structured times.
	 *
	 * ## EXAMPLES
	 *
	 *     wp onlinesched hours backfill
	 *     wp onlinesched hours backfill --write
	 *
	 * @when after_wp_load
	 */
	public function backfill($args, $assoc_args)
	{
		$page_id = isset($assoc_args['page'])
			? (int) $assoc_args['page']
			: (int) get_option('onlinesched_hours_page_id', 0);
		$write = isset($assoc_args['write']);
		$force = isset($assoc_args['force']);

		$post = $page_id ? get_post($page_id) : null;
		if (!$post) {
			WP_CLI::error('No Hours page found. Pass --page=<id>.');
		}

		$filled = array();
		$kept = array();
		$dropped = array();
		$content = preg_replace_callback(
			'~<!--\s*wp:onlinesched/hours-time\s*(\{.*?\})\s*/-->~s',
			function ($match) use (&$filled, &$kept, &$dropped, $force) {
				$attrs = json_decode($match[1], true);
				if (!is_array($attrs)) {
					$dropped[] = array('text' => $match[1], 'why' => 'unreadable block attributes');
					return $match[0];
				}
				$text = (string) ($attrs['hours'] ?? '');
				$already = '' !== (string) ($attrs['start'] ?? '')
					|| '' !== (string) ($attrs['end'] ?? '')
					|| !empty($attrs['allDay']);
				if ($already && !$force) {
					$kept[] = $text;
					return $match[0];
				}

				$parsed = onlinesched_hours_parse_line($text);
				if (null === $parsed) {
					$dropped[] = array('text' => $text, 'why' => 'not a time range');
					return $match[0];
				}

				$attrs = array_merge($attrs, $parsed);
				// A range that means shut must never publish as open hours.
				$note = (string) ($attrs['smallText'] ?? '');
				if ('' !== $note && preg_match('/\bclosed\b/i', $note)) {
					$attrs['closed'] = true;
				}
				$filled[] = array(
					'text'  => $text,
					'as'    => !empty($attrs['allDay'])
						? '24 hours'
						: $attrs['start'] . ' - ' . $attrs['end'],
					'closed' => !empty($attrs['closed']),
					'note'  => $note,
				);
				return '<!-- wp:onlinesched/hours-time ' . wp_json_encode($attrs) . ' /-->';
			},
			$post->post_content
		);

		WP_CLI::log(sprintf(
			'Hours page %d: %d filled, %d already structured, %d unreadable.',
			$page_id,
			count($filled),
			count($kept),
			count($dropped)
		));

		foreach ($filled as $row) {
			WP_CLI::log(sprintf(
				'  FILL  %-32s -> %-16s%s%s',
				'"' . $row['text'] . '"',
				$row['as'],
				$row['closed'] ? '  [CLOSED PERIOD]' : '',
				'' !== $row['note'] ? '  note: ' . $row['note'] : ''
			));
		}
		foreach ($dropped as $row) {
			WP_CLI::warning(sprintf('SKIP  "%s" (%s)', $row['text'], $row['why']));
		}

		if (!$write) {
			WP_CLI::success('Dry run. Nothing saved. Re-run with --write to apply.');
			return;
		}
		if (empty($filled)) {
			WP_CLI::success('Nothing to change.');
			return;
		}

		update_option('onlinesched_hours_backfill_backup', array(
			'page'    => $page_id,
			'content' => $post->post_content,
			'time'    => time(),
		), false);
		wp_update_post(array('ID' => $page_id, 'post_content' => $content));
		WP_CLI::success(sprintf(
			'%d rows filled. Previous content saved to option onlinesched_hours_backfill_backup.',
			count($filled)
		));
	}

	/**
	 * Put back the content saved by the last --write run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp onlinesched hours restore
	 *
	 * @when after_wp_load
	 */
	public function restore($args, $assoc_args)
	{
		$backup = get_option('onlinesched_hours_backfill_backup', false);
		if (!is_array($backup) || empty($backup['page']) || !isset($backup['content'])) {
			WP_CLI::error('No backfill backup found.');
		}
		wp_update_post(array(
			'ID'           => (int) $backup['page'],
			'post_content' => $backup['content'],
		));
		WP_CLI::success(sprintf(
			'Restored Hours page %d from the backup taken %s.',
			(int) $backup['page'],
			gmdate('Y-m-d H:i:s', (int) $backup['time']) . ' UTC'
		));
	}
}

/**
 * One authored hours line as structured fields, or null when it is not a range.
 *
 * @param string $text Authored line.
 * @return array|null {start, end, allDay}
 */
function onlinesched_hours_parse_line($text)
{
	$text = trim(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'));
	if ('' === $text) {
		return null;
	}
	if (preg_match('/^24\s*hours?$/i', $text)) {
		return array('start' => '', 'end' => '', 'allDay' => true);
	}
	// "till 10am" is the tail of an overnight run, so it starts at midnight.
	if (preg_match('/^(?:till|until|to)\s+(.+)$/i', $text, $m)) {
		$end = onlinesched_hours_parse_clock($m[1]);
		return null === $end ? null : array('start' => '00:00', 'end' => $end, 'allDay' => false);
	}
	// Any dash shape, spaced or not, including en and em dashes.
	$parts = preg_split('/\s*[-\x{2010}-\x{2015}]\s*/u', $text, 2);
	if (2 !== count($parts)) {
		return null;
	}
	$start = onlinesched_hours_parse_clock($parts[0]);
	$end = onlinesched_hours_parse_clock($parts[1]);
	if (null === $start || null === $end) {
		return null;
	}
	return array('start' => $start, 'end' => $end, 'allDay' => false);
}

/**
 * One clock word as HH:MM, or null. Accepts 9am, 9:30 am, Noon and Midnight.
 *
 * @param string $value Authored time.
 * @return string|null
 */
function onlinesched_hours_parse_clock($value)
{
	$value = strtolower(trim((string) $value));
	if ('noon' === $value || '12 noon' === $value) {
		return '12:00';
	}
	if ('midnight' === $value || '12 midnight' === $value) {
		return '00:00';
	}
	if (!preg_match('/^(\d{1,2})(?::([0-5]\d))?\s*([ap])\.?m\.?$/', $value, $m)) {
		return null;
	}
	$hour = (int) $m[1];
	if ($hour < 1 || $hour > 12) {
		return null;
	}
	$minute = isset($m[2]) && '' !== $m[2] ? (int) $m[2] : 0;
	if ('p' === $m[3] && 12 !== $hour) {
		$hour += 12;
	}
	if ('a' === $m[3] && 12 === $hour) {
		$hour = 0;
	}
	return sprintf('%02d:%02d', $hour, $minute);
}

WP_CLI::add_command('onlinesched hours backfill', array(new OnlineSched_Hours_Backfill_CLI(), 'backfill'));
WP_CLI::add_command('onlinesched hours restore', array(new OnlineSched_Hours_Backfill_CLI(), 'restore'));
