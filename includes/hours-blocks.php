<?php
/**
 * Native Hours of Operations blocks.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once ONLINESCHED_PLUGIN_DIR . 'lib/OnlineSchedHoursRenderer.php';

add_action('init', 'onlinesched_register_hours_blocks');
add_shortcode('onlinesched_hours', 'onlinesched_hours_shortcode');
add_action('wp_enqueue_scripts', 'onlinesched_enqueue_hours_assets_if_needed');

function onlinesched_get_hours_day_choices()
{
    return apply_filters('os_hours_day_choices', array(
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
        'Monday',
    ));
}

function onlinesched_register_hours_blocks()
{
    $script_path = ONLINESCHED_PLUGIN_DIR . 'build/hours-blocks.bundle.js';
    $style_path = ONLINESCHED_PLUGIN_DIR . 'build/main.css';
    $script_handle = null;
    $style_handle = null;

    if (file_exists($style_path)) {
        $style_handle = 'online-schedule-css';
        if (!wp_style_is($style_handle, 'registered')) {
            wp_register_style(
                $style_handle,
                ONLINESCHED_PLUGIN_URL . 'build/main.css',
                array(),
                filemtime($style_path)
            );
        }
    }

    if (file_exists($script_path)) {
        $script_handle = 'onlinesched-hours-blocks';
        wp_register_script(
            $script_handle,
            ONLINESCHED_PLUGIN_URL . 'build/hours-blocks.bundle.js',
            array('wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'),
            filemtime($script_path),
            true
        );
        wp_localize_script($script_handle, 'OnlineSchedHoursBlocks', array(
            'dayChoices' => array_values(onlinesched_get_hours_day_choices()),
        ));
    }

    $blocks = array(
        'hours-of-operations' => array('OnlineSchedHoursRenderer', 'render_wrapper'),
        'hours-department'    => array('OnlineSchedHoursRenderer', 'render_department'),
        'hours-day'           => array('OnlineSchedHoursRenderer', 'render_day'),
        'hours-time'          => array('OnlineSchedHoursRenderer', 'render_time'),
    );

    foreach ($blocks as $block => $render_callback) {
        $args = array(
            'render_callback' => $render_callback,
        );
        if ($script_handle) {
            $args['editor_script'] = $script_handle;
        }
        if ($style_handle) {
            $args['style'] = $style_handle;
            $args['editor_style'] = $style_handle;
        }

        register_block_type(ONLINESCHED_PLUGIN_DIR . 'includes/blocks/hours/' . $block, $args);
    }
}

function onlinesched_enqueue_hours_assets_if_needed()
{
    if (!is_singular()) {
        return;
    }

    $post = get_post();
    if (!$post) {
        return;
    }

    if (!has_shortcode($post->post_content, 'onlinesched_hours') && !has_block('onlinesched/hours-of-operations', $post)) {
        return;
    }

    wp_enqueue_style(
        'online-schedule-css',
        ONLINESCHED_PLUGIN_URL . 'build/main.css',
        array(),
        filemtime(ONLINESCHED_PLUGIN_DIR . 'build/main.css')
    );

    if (function_exists('onlinesched_add_color_inline_style')) {
        onlinesched_add_color_inline_style('online-schedule-css');
    }
}

function onlinesched_hours_shortcode($atts = array())
{
    $atts = shortcode_atts(array(
        'page_id' => 0,
    ), $atts, 'onlinesched_hours');

    $page_id = absint($atts['page_id']);
    if (!$page_id) {
        $page_id = (int) get_option('onlinesched_hours_page_id', 0);
    }

    if (!$page_id) {
        return '';
    }

    $hours_post = get_post($page_id);
    if (!$hours_post || $hours_post->post_status !== 'publish') {
        return '';
    }

    $content = preg_replace('/\[onlinesched_schedule[^\]]*\]/', '', $hours_post->post_content);
    $content = preg_replace('/\[onlinesched_hours[^\]]*\]/', '', $content);

    return do_blocks($content);
}
