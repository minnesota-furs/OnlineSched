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

    // Staff write event text by hand, so a bare & is routine input here.
    return \Soundasleep\Html2Text::convert((string) $html, ['ignore_errors' => true]);
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
 * Convert an event Unix timestamp to an iCalendar UTC date.
 *
 * @param int $timestamp Event timestamp stored by OnlineSched.
 * @return string UTC date in iCalendar format.
 */
function onlinesched_ical_utc_date($timestamp)
{
	return gmdate(ONLINESCHED_ICAL_DATE_FORMAT, absint($timestamp));
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

/**
 * Convention window from the configured con dates, end day inclusive.
 *
 * @return array{0:int,1:int}|null Start and exclusive end timestamps, or null when unset.
 */
function onlinesched_ical_con_window()
{
    $start = (string) get_option('onlinesched_con_start', '');
    $end = (string) get_option('onlinesched_con_end', '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        return null;
    }

    $zone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $from = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $zone);
    $to = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $zone);
    if (!$from || !$to) {
        return null;
    }

    return array($from->getTimestamp(), $to->modify('+1 day')->getTimestamp());
}

/**
 * Refresh hint for subscribed calendars, as an iCalendar duration.
 *
 * @param int|null   $event_end Single-event end timestamp, or null for a multi-event feed.
 * @param int|null   $now       Current timestamp; defaults to time().
 * @param array|null $window    Con window override; defaults to the configured dates.
 * @return string
 */
function onlinesched_ical_refresh_interval($event_end = null, $now = null, $window = false)
{
    $now = null === $now ? time() : (int) $now;
    if (null !== $event_end && (int) $event_end > 0 && (int) $event_end < $now) {
        return 'P30D';
    }

    $window = false === $window ? onlinesched_ical_con_window() : $window;
    if (is_array($window) && $now >= $window[0] && $now < $window[1]) {
        return 'PT1H';
    }

    return 'P1D';
}

function onlinesched_ical_calendar_header($calendar_name = '', $refresh_interval = null)
{
    $prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';
    $calendar_name = '' !== (string) $calendar_name ? $calendar_name : onlinesched_ical_calendar_name();
    $calendar_description = onlinesched_ical_calendar_description();
    $timezone = onlinesched_ical_timezone_id();
    $refresh_interval = null === $refresh_interval ? onlinesched_ical_refresh_interval() : $refresh_interval;

    return 'BEGIN:VCALENDAR' . ONLINESCHED_ICAL_EOL .
        'VERSION:2.0' . ONLINESCHED_ICAL_EOL .
        'CALSCALE:GREGORIAN' . ONLINESCHED_ICAL_EOL .
        'METHOD:PUBLISH' . ONLINESCHED_ICAL_EOL .
        onlinesched_ical_line('PRODID', $prodid, false) .
        onlinesched_ical_line('NAME', $calendar_name) .
        onlinesched_ical_line('X-WR-CALNAME', $calendar_name) .
        onlinesched_ical_line('X-WR-CALDESC', $calendar_description) .
        onlinesched_ical_line('X-WR-TIMEZONE', $timezone, false) .
        'REFRESH-INTERVAL;VALUE=DURATION:' . onlinesched_ical_sanitize_raw_value($refresh_interval) . ONLINESCHED_ICAL_EOL .
        onlinesched_ical_line('X-PUBLISHED-TTL', $refresh_interval, false);
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

function onlinesched_ical_send_feed_headers($filename, $validator)
{
    header('Content-Type: text/calendar; charset=UTF-8; method=PUBLISH');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    ## Private no-cache preserves the validator but keeps shared caches out.
    header('Cache-Control: private, no-cache, must-revalidate');
    header('ETag: ' . $validator);
}

/**
 * True when an If-None-Match header names the given strong ETag.
 *
 * @param string $if_none_match Raw request header value.
 * @param string $etag          Quoted ETag sent with the response.
 * @return bool
 */
function onlinesched_ical_etag_matches($if_none_match, $etag)
{
    $if_none_match = trim((string) $if_none_match);
    if ('' === $if_none_match) {
        return false;
    }
    if ('*' === $if_none_match) {
        return true;
    }

    foreach (explode(',', $if_none_match) as $candidate) {
        $candidate = trim($candidate);
        if (0 === strpos($candidate, 'W/')) {
            $candidate = substr($candidate, 2);
        }
        if ($candidate === $etag) {
            return true;
        }
    }

    return false;
}

/**
 * Send a public, shared-cacheable calendar that answers 304 when unchanged.
 * Only for feeds with no per-person content; favorites feeds stay private.
 *
 * @param string $body          Complete calendar text.
 * @param string $filename      Download filename.
 * @param int    $last_modified Unix timestamp of the newest source edit.
 * @param int    $max_age       Shared cache lifetime in seconds.
 */
function onlinesched_ical_send_public_response($body, $filename, $last_modified, $max_age)
{
    $etag = '"' . sha1((string) $body) . '"';

    header('Content-Type: text/calendar; charset=UTF-8; method=PUBLISH');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Cache-Control: public, max-age=' . absint($max_age));
    header('ETag: ' . $etag);
    if ((int) $last_modified > 0) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) $last_modified) . ' GMT');
    }

    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']) : '';
    if (onlinesched_ical_etag_matches($if_none_match, $etag)) {
        status_header(304);
        exit;
    }

    echo $body;
    exit;
}

function onlinesched_ical_send_unpublished_schedule($filename)
{
    onlinesched_ical_send_headers($filename);
    header('X-Robots-Tag: noindex, nofollow');
    echo onlinesched_ical_empty_calendar();
}
