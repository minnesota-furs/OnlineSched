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

function onlinesched_ical_load_composer_autoload()
{
    if (class_exists('\\Soundasleep\\Html2Text')) {
        return;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        wp_die(
            esc_html__('OnlineSched Composer dependencies are missing. Run composer install for the OnlineSched plugin.', 'onlinesched'),
            esc_html__('OnlineSched dependencies missing', 'onlinesched'),
            array('response' => 500)
        );
    }

    require_once $autoload;
}

function onlinesched_ical_html_to_text($html)
{
    onlinesched_ical_load_composer_autoload();

    return \Soundasleep\Html2Text::convert((string) $html);
}

function onlinesched_ical_sanitize_raw_value($value)
{
    return preg_replace('/[\r\n\x00-\x1F\x7F]/', '', (string) $value);
}

function onlinesched_ical_timezone_id()
{
    $timezone = function_exists('wp_timezone_string')
        ? wp_timezone_string()
        : get_option('timezone_string');
    if (!is_string($timezone) || '' === trim($timezone)) {
        $timezone = 'UTC';
    }

    return apply_filters('os_ical_timezone', $timezone);
}

/**
 * Convert a legacy wall-clock timestamp to an iCalendar UTC date.
 *
 * @param int $timestamp Event timestamp stored by OnlineSched.
 * @return string UTC date in iCalendar format.
 */
function onlinesched_ical_utc_date($timestamp)
{
    $timestamp = absint($timestamp);
    $timezone = new DateTimeZone(onlinesched_ical_timezone_id());
    $wall_time = gmdate('Y-m-d H:i:s', $timestamp);
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $wall_time,
        $timezone
    );

    if (!$date) {
        return gmdate(ONLINESCHED_ICAL_DATE_FORMAT, $timestamp);
    }

    return $date
        ->setTimezone(new DateTimeZone('UTC'))
        ->format(ONLINESCHED_ICAL_DATE_FORMAT);
}

function onlinesched_ical_calendar_name()
{
    if (function_exists('onlinesched_get_calendar_name')) {
        return onlinesched_get_calendar_name();
    }

    $site_name = get_bloginfo('name');

    return $site_name ? $site_name : 'OnlineSched';
}

function onlinesched_ical_calendar_description()
{
    return apply_filters('os_ical_calendar_description', 'Event Schedule');
}

function onlinesched_ical_uid($post_id, $context = 'event')
{
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $host = is_string($host) ? strtolower(preg_replace('/[^a-z0-9.-]/i', '', $host)) : '';
    if ('' === $host) {
        $host = 'onlinesched.local';
    }

    $prefix = apply_filters('os_ical_uid_prefix', 'os-');
    $prefix = preg_replace('/[^a-z0-9._-]/i', '', (string) $prefix);
    $prefix = '' !== $prefix ? $prefix : 'os-';

    $context = sanitize_key($context);
    $context = '' !== $context ? $context : 'event';

    return $prefix . $context . '-' . absint($post_id) . '@' . $host;
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

function onlinesched_ical_calendar_header($calendar_name = '')
{
    $prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';
    $calendar_name = '' !== (string) $calendar_name ? $calendar_name : onlinesched_ical_calendar_name();
    $calendar_description = onlinesched_ical_calendar_description();
    $timezone = onlinesched_ical_timezone_id();

    return 'BEGIN:VCALENDAR' . ONLINESCHED_ICAL_EOL .
        'VERSION:2.0' . ONLINESCHED_ICAL_EOL .
        'CALSCALE:GREGORIAN' . ONLINESCHED_ICAL_EOL .
        'METHOD:PUBLISH' . ONLINESCHED_ICAL_EOL .
        onlinesched_ical_line('PRODID', $prodid, false) .
        onlinesched_ical_line('NAME', $calendar_name) .
        onlinesched_ical_line('X-WR-CALNAME', $calendar_name) .
        onlinesched_ical_line('X-WR-CALDESC', $calendar_description) .
        onlinesched_ical_line('X-WR-TIMEZONE', $timezone, false);
}

function onlinesched_ical_calendar_footer()
{
    return 'END:VCALENDAR' . ONLINESCHED_ICAL_EOL;
}

function onlinesched_ical_empty_calendar()
{
    return onlinesched_ical_calendar_header() . onlinesched_ical_calendar_footer();
}

function onlinesched_ical_send_headers($filename)
{
    header('Content-Type: text/calendar; charset=UTF-8; method=PUBLISH');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
