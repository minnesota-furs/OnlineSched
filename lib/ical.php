<?php
/**
 * Shared iCalendar helpers.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ONLINESCHED_ICAL_EOL')) {
    define('ONLINESCHED_ICAL_EOL', "\r\n");
}

if (!defined('ONLINESCHED_ICAL_DATE_FORMAT')) {
    define('ONLINESCHED_ICAL_DATE_FORMAT', 'Ymd\THis\Z');
}

function onlinesched_ical_sanitize_raw_value($value)
{
    return preg_replace('/[\r\n\x00-\x1F\x7F]/', '', (string) $value);
}

function onlinesched_ical_escape_text($value)
{
    $charset = get_option('blog_charset') ?: 'UTF-8';
    $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, $charset);
    $value = str_replace(array("\r\n", "\r"), "\n", $value);
    $value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';', '\\;', $value);
    $value = str_replace(',', '\\,', $value);
    $value = str_replace("\n", '\\n', $value);

    return $value;
}

function onlinesched_ical_fold_line($line)
{
    $line = (string) $line;
    if (strlen($line) <= 75) {
        return $line;
    }

    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        $chars = str_split($line);
    }

    $lines = array();
    $current = '';
    foreach ($chars as $char) {
        if ('' !== $current && strlen($current . $char) > 75) {
            $lines[] = $current;
            $current = ' ' . $char;
            continue;
        }

        $current .= $char;
    }

    if ('' !== $current) {
        $lines[] = $current;
    }

    return implode(ONLINESCHED_ICAL_EOL, $lines);
}

function onlinesched_ical_line($name, $value, $escape_text = true)
{
    $name = strtoupper(preg_replace('/[^A-Z0-9-]/i', '', (string) $name));
    $value = $escape_text ? onlinesched_ical_escape_text($value) : onlinesched_ical_sanitize_raw_value($value);

    return onlinesched_ical_fold_line($name . ':' . $value) . ONLINESCHED_ICAL_EOL;
}

function onlinesched_ical_categories($post_id, $taxonomy = 'os_tag')
{
    $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    return implode(',', array_map('onlinesched_ical_escape_text', $terms));
}

function onlinesched_ical_empty_calendar()
{
    $prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';

    return 'BEGIN:VCALENDAR' . ONLINESCHED_ICAL_EOL .
        'VERSION:2.0' . ONLINESCHED_ICAL_EOL .
        'CALSCALE:GREGORIAN' . ONLINESCHED_ICAL_EOL .
        'METHOD:PUBLISH' . ONLINESCHED_ICAL_EOL .
        onlinesched_ical_line('PRODID', $prodid, false) .
        'X-WR-TIMEZONE:GMT' . ONLINESCHED_ICAL_EOL .
        'END:VCALENDAR' . ONLINESCHED_ICAL_EOL;
}
