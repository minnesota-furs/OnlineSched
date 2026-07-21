<?php
/**
 * OnlineSched date and time helpers.
 *
 * @package OnlineSched
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Parse an event date and time in the configured WordPress timezone.
 *
 * Stored event timestamps are true Unix timestamps. Date and time strings from
 * imports and admin forms represent local wall time in the site timezone.
 *
 * @param string $date Event date.
 * @param string $time Event start time.
 * @return DateTimeImmutable|false Parsed event date, or false when invalid.
 */
function onlinesched_parse_local_datetime($date, $time)
{
	$date = trim((string) $date);
	$time = trim((string) $time);

	if ('' === $date || '' === $time) {
		return false;
	}

	$date_formats = array('Y-m-d', 'Y-n-j', 'n/j/Y', 'n/j/y');
	$time_formats = array('H:i:s', 'H:i', 'g:i:s A', 'g:i A');
	$timezone = wp_timezone();

	foreach ($date_formats as $date_format) {
		foreach ($time_formats as $time_format) {
			$format = '!' . $date_format . ' ' . $time_format;
			$date_time = DateTimeImmutable::createFromFormat(
				$format,
				$date . ' ' . $time,
				$timezone
			);
			$errors = DateTimeImmutable::getLastErrors();

			if (!$date_time) {
				continue;
			}

			if (is_array($errors) && (0 < $errors['warning_count'] || 0 < $errors['error_count'])) {
				continue;
			}

			if (false !== strpos($date_format, 'Y') && 1000 > (int) $date_time->format('Y')) {
				continue;
			}

			return $date_time;
		}
	}

	return false;
}

/**
 * Get the Unix timestamp for local midnight on an event's site-local date.
 *
 * @param int $timestamp Event timestamp.
 * @return int Local midnight as a Unix timestamp.
 */
function onlinesched_local_day_start($timestamp)
{
	$timestamp = absint($timestamp);
	$date = wp_date('Y-m-d', $timestamp, wp_timezone());
	$day_start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());

	return $day_start ? $day_start->getTimestamp() : $timestamp;
}
