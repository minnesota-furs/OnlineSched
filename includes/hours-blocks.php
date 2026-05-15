<?php
/**
 * Native Hours of Operations blocks.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
 * Revised by: Kurst Hyperyote for Furry Migration
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once ONLINESCHED_PLUGIN_DIR . 'lib/OnlineSchedHoursRenderer.php';

add_action('init', 'onlinesched_register_hours_blocks');
add_shortcode('onlinesched_hours', 'onlinesched_hours_shortcode');
add_action('wp_enqueue_scripts', 'onlinesched_enqueue_hours_assets_if_needed');
add_action('admin_post_onlinesched_migrate_hours_from_acf', 'onlinesched_handle_hours_admin_migration');

if (defined('WP_CLI') && WP_CLI) {
    require_once ONLINESCHED_PLUGIN_DIR . 'includes/cli/MigrateHoursCommand.php';
}

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

function onlinesched_page_has_native_hours($page_id)
{
    $post = get_post($page_id);

    return $post && has_block('onlinesched/hours-of-operations', $post);
}

function onlinesched_page_has_acf_hours($page_id)
{
    if (!$page_id || !function_exists('get_field')) {
        return false;
    }

    $enabled = get_field('enable_hours', $page_id);
    $departments = get_field('departments', $page_id);

    return !empty($enabled) && is_array($departments) && !empty($departments);
}

function onlinesched_migrate_hours_from_acf($page_id, $backup = true, $force = false)
{
    $page_id = absint($page_id);
    if (!$page_id) {
        return new WP_Error('missing_page', 'No Hours page is configured.');
    }

    $post = get_post($page_id);
    if (!$post || $post->post_type !== 'page') {
        return new WP_Error('invalid_page', 'The selected Hours page could not be found.');
    }

    if (!$force && onlinesched_page_has_native_hours($page_id)) {
        return new WP_Error('already_migrated', 'This page already contains OnlineSched hours blocks.');
    }

    if (!function_exists('get_field')) {
        return new WP_Error('acf_missing', 'ACF is not active, so there is no legacy data to migrate.');
    }

    $enabled = get_field('enable_hours', $page_id);
    $departments = get_field('departments', $page_id);
    if (empty($enabled) || !is_array($departments) || empty($departments)) {
        return new WP_Error('missing_data', 'The selected page has no enabled ACF hours data.');
    }

    $blocks = onlinesched_build_migrated_hours_content($post->post_content, $departments);
    if ($backup) {
        update_post_meta($page_id, '_onlinesched_hours_premigration', $post->post_content);
    }

    $updated = wp_update_post(array(
        'ID'           => $page_id,
        'post_content' => $blocks,
    ), true);

    if (is_wp_error($updated)) {
        return $updated;
    }

    return count($departments);
}

function onlinesched_build_migrated_hours_content($existing_content, array $departments)
{
    $content = preg_replace('/\[hours_of_operations[^\]]*\]/', '', (string) $existing_content);
    $content = preg_replace('/\[onlinesched_hours[^\]]*\]/', '', $content);
    $content = onlinesched_remove_hours_blocks_from_content($content);
    $hours_block = OnlineSchedHoursRenderer::build_block_markup_from_acf($departments);

    return trim($content) !== '' ? rtrim($content) . "\n\n" . $hours_block : $hours_block;
}

function onlinesched_remove_hours_blocks_from_content($content)
{
    if (!has_blocks($content)) {
        return $content;
    }

    $blocks = array_filter(parse_blocks($content), function ($block) {
        return ($block['blockName'] ?? '') !== 'onlinesched/hours-of-operations';
    });

    return implode('', array_map('serialize_block', $blocks));
}

function onlinesched_handle_hours_admin_migration()
{
    if (!current_user_can(onlinesched_settings_capability())) {
        wp_die(esc_html__('You do not have permission to migrate Hours content.', 'onlinesched'));
    }

    check_admin_referer('onlinesched_migrate_hours_from_acf', 'onlinesched_hours_migration_nonce');

    $page_id = absint($_POST['onlinesched_hours_page_id'] ?? $_POST['page_id'] ?? get_option('onlinesched_hours_page_id', 0));
    $result = onlinesched_migrate_hours_from_acf($page_id, true, false);
    $redirect = add_query_arg(array(
        'post_type' => 'os_event',
        'page'      => 'onlinesched-settings',
    ), admin_url('edit.php'));

    if (is_wp_error($result)) {
        $redirect = add_query_arg('onlinesched_hours_migration_error', rawurlencode($result->get_error_message()), $redirect);
    } else {
        $redirect = add_query_arg('onlinesched_hours_migrated', absint($result), $redirect);
    }

    wp_safe_redirect($redirect);
    exit;
}
