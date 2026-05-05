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

function onlinesched_get_color_defaults()
{
    return array(
        'color_primary' => '#017940',
        'color_secondary' => '#0d375a',
        'color_accent' => '#f36d21',
        'color_gold' => '#f6c700',
        'color_fav_inactive' => '#cccccc',
        'color_fav_active' => '#d12229',
        'color_danger' => '#d12229',
    );
}

function onlinesched_sanitize_color_option($value)
{
    return sanitize_hex_color($value) ?: '';
}

function onlinesched_hex_to_rgb($hex)
{
    $hex = sanitize_hex_color($hex);
    if (!$hex) {
        return null;
    }

    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return array(
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    );
}

function onlinesched_relative_luminance($hex)
{
    $rgb = onlinesched_hex_to_rgb($hex);
    if (!$rgb) {
        return 0.0;
    }

    $linear = array_map(function ($channel) {
        $channel = $channel / 255;
        return $channel <= 0.03928 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4);
    }, $rgb);

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function onlinesched_contrast_ratio($hex_a, $hex_b)
{
    $luminance_a = onlinesched_relative_luminance($hex_a);
    $luminance_b = onlinesched_relative_luminance($hex_b);
    $lighter = max($luminance_a, $luminance_b);
    $darker = min($luminance_a, $luminance_b);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function onlinesched_get_favorite_icon_classes($active = false)
{
    if ($active) {
        $icon = get_option('onlinesched_icon_fav_active', 'fas fa-star');
        return !empty($icon) ? $icon : 'fas fa-star';
    } else {
        $icon = get_option('onlinesched_icon_fav_inactive', 'far fa-star');
        return !empty($icon) ? $icon : 'far fa-star';
    }
}

function onlinesched_get_colors()
{
    $colors = array();
    foreach (onlinesched_get_color_defaults() as $key => $default) {
        $colors[$key] = sanitize_hex_color(onlinesched_get_config($key, $default)) ?: $default;
    }

    return $colors;
}

function onlinesched_add_color_inline_style($handle = 'online-schedule-css')
{
    if (!wp_style_is($handle, 'enqueued')) {
        return;
    }

    $defaults = onlinesched_get_color_defaults();
    $colors = onlinesched_get_colors();

    if ($colors === $defaults) {
        return;
    }

    $primary_rgb = onlinesched_hex_to_rgb($colors['color_primary']);
    $primary_soft = '#e6f7ee';
    $primary_focus = 'rgba(1, 121, 64, 0.2)';
    $modal_chrome = $colors['color_primary'];
    $modal_chrome_focus = $primary_focus;

    if ($primary_rgb) {
        $primary_soft = sprintf(
            'rgba(%1$d, %2$d, %3$d, 0.1)',
            $primary_rgb[0],
            $primary_rgb[1],
            $primary_rgb[2]
        );
        $primary_focus = sprintf(
            'rgba(%1$d, %2$d, %3$d, 0.2)',
            $primary_rgb[0],
            $primary_rgb[1],
            $primary_rgb[2]
        );
        $modal_chrome_focus = $primary_focus;
    }

    if (onlinesched_contrast_ratio($colors['color_primary'], '#ffffff') < 4.5) {
        $modal_chrome = '#4a4a4a';
        $modal_chrome_focus = 'rgba(74, 74, 74, 0.25)';
    }

    $css = sprintf(
        ':root { --os-green: %1$s; --os-blue: %2$s; --os-orange: %3$s; --os-gold: %4$s; --os-fav-inactive: %5$s; --os-fav-active: %6$s; --os-danger: %7$s; --os-green-soft: %8$s; --os-green-focus: %9$s; --os-modal-chrome: %10$s; --os-modal-chrome-focus: %11$s; }',
        wp_strip_all_tags($colors['color_primary']),
        wp_strip_all_tags($colors['color_secondary']),
        wp_strip_all_tags($colors['color_accent']),
        wp_strip_all_tags($colors['color_gold']),
        wp_strip_all_tags($colors['color_fav_inactive']),
        wp_strip_all_tags($colors['color_fav_active']),
        wp_strip_all_tags($colors['color_danger']),
        wp_strip_all_tags($primary_soft),
        wp_strip_all_tags($primary_focus),
        wp_strip_all_tags($modal_chrome),
        wp_strip_all_tags($modal_chrome_focus)
    );

    $css .= " .schedule-favorite-toggle:hover i, .schedule-favorite-toggle.active i { color: var(--os-fav-active) !important; }";

    // Header Flare — opacity only; the icon itself is injected as a DOM element by onlinesched_get_flare_html().
    $enable_flare = get_option('onlinesched_enable_header_flare', '1');
    $flare_opacity = ($enable_flare === '1' ? '0.15' : '0');
    $css .= " :root { --os-flare-opacity: {$flare_opacity}; }";

    wp_add_inline_style($handle, $css);
}

/**
 * Returns the flare <span> to inject inside day header <h2> elements.
 * Priority: Image URL > Custom FA class text field > Select preset.
 * Returns empty string when flare is disabled or set to none.
 */
function onlinesched_get_flare_html()
{
    if (get_option('onlinesched_enable_header_flare', '1') !== '1') {
        return '';
    }

    // Image URL overrides everything.
    $image = get_option('onlinesched_header_flare_image', '');
    if (!empty($image)) {
        return '<span class="os-flare-icon" aria-hidden="true"><img src="' . esc_url($image) . '" alt=""></span>';
    }

    // Resolve icon class: custom text field overrides select when set.
    $icon = get_option('onlinesched_header_flare_icon', 'fa-paw');
    if ($icon === 'fa-custom') {
        $icon = get_option('onlinesched_header_flare_custom_class', '');
    }

    if (empty($icon) || $icon === 'fa-none') {
        return '';
    }

    // Allow only valid FA slug characters — no injection risk.
    $icon = preg_replace('/[^a-z0-9\-]/', '', $icon);
    if (empty($icon)) {
        return '';
    }

    return '<span class="os-flare-icon" aria-hidden="true"><i class="fas ' . esc_attr($icon) . '"></i></span>';
}

function onlinesched_get_provider_icon_allowed_html()
{
    return array(
        'i' => array(
            'aria-hidden' => true,
            'class' => true,
            'style' => true,
            'title' => true,
        ),
        'span' => array(
            'aria-hidden' => true,
            'class' => true,
            'style' => true,
            'title' => true,
        ),
        'svg' => array(
            'aria-hidden' => true,
            'aria-label' => true,
            'class' => true,
            'fill' => true,
            'focusable' => true,
            'height' => true,
            'role' => true,
            'style' => true,
            'viewBox' => true,
            'viewbox' => true,
            'width' => true,
            'xmlns' => true,
        ),
        'path' => array(
            'clip-rule' => true,
            'd' => true,
            'fill' => true,
            'fill-rule' => true,
            'stroke' => true,
            'stroke-width' => true,
        ),
        'g' => array(
            'fill' => true,
            'stroke' => true,
            'transform' => true,
        ),
        'title' => array(),
    );
}

function onlinesched_get_provider_icon_html($provider, $provider_data)
{
    $icon = '<i class="fas fa-user" style="margin-right:8px;" aria-hidden="true"></i>';

    if (isset($provider_data['use-favicon']) && !empty($provider_data['use-favicon']['enabled'])) {
        $favicon = !empty($provider_data['use-favicon']['favicon']) ? sanitize_html_class($provider_data['use-favicon']['favicon']) : 'fa-user';
        $color = !empty($provider_data['use-favicon']['color']) ? sanitize_hex_color('#' . ltrim($provider_data['use-favicon']['color'], '#')) : '';
        $style = $color ? 'color:' . $color . '; margin-right:8px;' : 'margin-right:8px;';
        $icon = '<i class="fab ' . esc_attr($favicon) . '" style="' . esc_attr($style) . '" aria-hidden="true"></i>';
    }

    $icon = apply_filters('os_provider_icon_html', $icon, $provider, $provider_data);

    return wp_kses($icon, onlinesched_get_provider_icon_allowed_html());
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
    $year = trim((string) get_option('onlinesched_year', wp_date('Y')));

    if ('' === $year || 'Event Schedule Year' === $year) {
        $year = wp_date('Y');
    }

    $calendar_name = sanitize_text_field(onlinesched_get_config('calendar_name', trim($site_name . ' ' . $year)));

    return apply_filters('os_calendar_name', $calendar_name);
}

function onlinesched_get_ical_prodid()
{
    $site_name = sanitize_text_field(get_bloginfo('name'));
    $prodid = '-//OnlineSched//' . ($site_name ? $site_name : 'Event Schedule') . '//EN';

    return apply_filters('os_ical_prodid', onlinesched_get_config('ical_prodid', $prodid));
}

function onlinesched_get_ical_filename_prefix()
{
    $prefix = sanitize_title(onlinesched_get_config('ical_filename_prefix', 'onlinesched'));
    $prefix = $prefix ? $prefix : 'onlinesched';

    return apply_filters('os_ical_filename_prefix', $prefix);
}

function onlinesched_get_room_sort_priority()
{
    $raw = onlinesched_get_config('room_sort_priority', '');
    if (is_string($raw)) {
        $rooms = array_map('trim', explode(',', $raw));
    } elseif (is_array($raw)) {
        $rooms = $raw;
    } else {
        $rooms = array();
    }

    $rooms = array_values(array_filter(array_map('sanitize_text_field', $rooms)));

    return apply_filters('os_room_sort_priority', $rooms);
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
