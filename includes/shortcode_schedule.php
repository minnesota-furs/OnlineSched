<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('onlinesched_schedule', 'onlinesched_schedule_shortcode');

function onlinesched_schedule_shortcode($atts) {
    $atts = shortcode_atts(array(
        'mode'          => 'standard',
        'tabs'          => 'programming,essentials,hours',
        'hours_page_id' => 0,
        'map_page_id'   => 0,
        'tag'           => '',
        'room'          => '',
    ), $atts, 'onlinesched_schedule');

    // Sanitize
    $atts['mode']  = in_array($atts['mode'], array('standard','kiosk','live'), true)
        ? $atts['mode']
        : 'standard';
    $atts['tabs']  = array_map('sanitize_key', explode(',', $atts['tabs']));
    $atts['tag']   = sanitize_title($atts['tag']);
    $atts['room']  = sanitize_title($atts['room']);
    $atts['hours_page_id'] = absint($atts['hours_page_id']);
    $atts['map_page_id']   = absint($atts['map_page_id']);

    if (!$atts['hours_page_id']) {
        $atts['hours_page_id'] = (int) get_option('onlinesched_hours_page_id', 0);
    }
    if (!$atts['map_page_id']) {
        $atts['map_page_id'] = (int) get_option('onlinesched_map_page_id', 0);
    }

    return onlinesched_render_schedule($atts);
}

// Auto-enqueue assets when shortcode is on the page
add_action('wp_enqueue_scripts', 'onlinesched_enqueue_shortcode_assets');
function onlinesched_enqueue_shortcode_assets() {
    if (is_singular()) {
        global $post;
        if ($post && has_shortcode($post->post_content, 'onlinesched_schedule')) {
            onlinesched_enqueue_schedule_assets();
        }
    }
}

function onlinesched_enqueue_schedule_assets() {
    $plugin_url = ONLINESCHED_PLUGIN_URL;
    $plugin_path = ONLINESCHED_PLUGIN_DIR;

    // Main plugin CSS (no FA, no font-faces — those are separate handles below).
    wp_enqueue_style('online-schedule-css',
        $plugin_url . 'build/main.css',
        array(),
        filemtime($plugin_path . 'build/main.css')
    );

    if (function_exists('onlinesched_add_color_inline_style')) {
        onlinesched_add_color_inline_style('online-schedule-css');
    }

    // Font Awesome — separate handle so sites that already load FA can opt out:
    //   add_filter( 'onlinesched_load_fontawesome', '__return_false' );
    if (apply_filters('onlinesched_load_fontawesome', true)) {
        wp_enqueue_style(
            'onlinesched-fa',
            $plugin_url . 'build/fontawesome.css',
            array(),
            filemtime($plugin_path . 'build/fontawesome.css')
        );
    }

    // Metropolis font — separate handle so themes supplying their own font can opt out:
    //   add_filter( 'onlinesched_load_fonts', '__return_false' );
    // Override the typeface entirely via CSS variable instead of disabling:
    //   add_action( 'wp_head', fn() => print ':root{--os-font-family:"YourFont",sans-serif;}' );
    if (apply_filters('onlinesched_load_fonts', true)) {
        wp_enqueue_style(
            'onlinesched-fonts',
            $plugin_url . 'build/fonts.css',
            array(),
            filemtime($plugin_path . 'build/fonts.css')
        );
    }

    wp_enqueue_script('online-schedule-js',
        $plugin_url . 'build/bundle.js',
        array(),
        filemtime($plugin_path . 'build/bundle.js'),
        true
    );

    wp_localize_script('online-schedule-js', 'OnlineSchedPublic', array(
        'loginStateUrl'    => $plugin_url . 'includes/login_state.php',
        'saveFavoritesUrl' => admin_url('admin-ajax.php?action=onlinesched_save_favorites'),
    ));
}
