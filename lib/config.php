<?php

if (!defined('ABSPATH')) {
    exit;
}

function onlinesched_get_option_name($key)
{
    return 'onlinesched_' . sanitize_key($key);
}

function onlinesched_get_constant_name($key)
{
    return 'ONLINESCHED_' . strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $key));
}

function onlinesched_get_config($key, $default = null)
{
    $option_name = onlinesched_get_option_name($key);
    $constant_name = onlinesched_get_constant_name($key);
    $value = get_option($option_name, $default);

    if (defined($constant_name)) {
        $value = constant($constant_name);
    }

    return apply_filters('os_config_' . sanitize_key($key), $value, $default);
}

function onlinesched_get_config_status($key, $default = null)
{
    $option_name = onlinesched_get_option_name($key);
    $constant_name = onlinesched_get_constant_name($key);
    $missing = '__onlinesched_missing__';
    $option_value = get_option($option_name, $missing);
    $source = 'default';
    $value = $default;

    if ($missing !== $option_value) {
        $value = $option_value;
        $source = 'saved option';
    }

    if (defined($constant_name)) {
        $value = constant($constant_name);
        $source = 'constant';
    }

    $filtered = apply_filters('os_config_' . sanitize_key($key), $value, $default);
    if ($filtered !== $value) {
        $value = $filtered;
        $source = 'filter';
    }

    return array(
        'key' => $key,
        'value' => $value,
        'source' => $source,
        'option' => $option_name,
        'constant' => $constant_name,
        'filter' => 'os_config_' . sanitize_key($key),
    );
}

function onlinesched_get_page_id_by_slug($slug)
{
    if (!$slug) {
        return 0;
    }

    $page = get_page_by_path($slug);
    return $page ? (int) $page->ID : 0;
}

function onlinesched_get_page_id($key, $fallback_slug = '')
{
    $page_id = absint(onlinesched_get_config($key . '_page_id', 0));

    if (!$page_id && $fallback_slug) {
        $page_id = onlinesched_get_page_id_by_slug($fallback_slug);
    }

    return $page_id;
}

function onlinesched_is_configured_page($post, $key, $fallback_slug = '')
{
    if (!$post || !is_object($post)) {
        return false;
    }

    $page_id = onlinesched_get_page_id($key, $fallback_slug);
    if ($page_id && (int) $post->ID === $page_id) {
        return true;
    }

    return $fallback_slug && isset($post->post_name) && $post->post_name === $fallback_slug;
}

function onlinesched_auto_detect_pages()
{
    $page_defaults = array(
        'schedule_page_id' => 'schedule',
        'kiosk_page_id' => 'kiosk-schedule',
        'live_page_id' => 'live',
        'hours_page_id' => 'schedule',
        'map_page_id' => 'kiosk-schedule',
    );

    $missing = '__onlinesched_missing__';
    foreach ($page_defaults as $key => $slug) {
        $option_name = onlinesched_get_option_name($key);
        if ($missing !== get_option($option_name, $missing)) {
            continue;
        }

        add_option($option_name, onlinesched_get_page_id_by_slug($slug));
    }
}

function onlinesched_get_sticky_offsets()
{
    $offsets = array(
        'desktop' => absint(onlinesched_get_config('sticky_offset_desktop', 0)),
        'mobile' => absint(onlinesched_get_config('sticky_offset_mobile', 0)),
    );

    $offsets = apply_filters('os_sticky_offsets', $offsets);

    return array(
        'desktop' => isset($offsets['desktop']) ? absint($offsets['desktop']) : 0,
        'mobile' => isset($offsets['mobile']) ? absint($offsets['mobile']) : 0,
    );
}

function onlinesched_get_schedule_page_url()
{
    $page_id = onlinesched_get_page_id('schedule', 'schedule');

    if ($page_id) {
        return get_permalink($page_id);
    }

    return home_url('/schedule/');
}

function onlinesched_get_calendar_name()
{
    $site_name = get_bloginfo('name');
    $year = trim((string) get_option('event_schedule_year', wp_date('Y')));

    if ('' === $year || 'Event Schedule Year' === $year) {
        $year = wp_date('Y');
    }

    return sanitize_text_field(onlinesched_get_config('calendar_name', trim($site_name . ' ' . $year)));
}

function onlinesched_get_page_content($key, $fallback_slug = '')
{
    $page_id = onlinesched_get_page_id($key, $fallback_slug);
    if (!$page_id) {
        return '';
    }

    $page = get_post($page_id);
    if (!$page || 'page' !== $page->post_type) {
        return '';
    }

    global $post;
    $previous_post = $post;
    $post = $page;
    setup_postdata($post);
    $content = apply_filters('the_content', $page->post_content);
    wp_reset_postdata();
    $post = $previous_post;
    if ($post instanceof WP_Post) {
        setup_postdata($post);
    }

    return $content;
}
