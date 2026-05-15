<?php
/**
 * Privacy policy, exporter, and eraser integration.
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

function onlinesched_add_privacy_policy_content()
{
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }

    $content = '<p>OnlineSched can store schedule favorites in a browser cookie for visitors who are not logged in. If social login is enabled and a visitor logs in, OnlineSched may store the login provider name, provider identifier or email address, selected favorite event IDs, last favorite update time, and last login time so favorites can sync across devices.</p>';
    $content .= '<p>Social login credentials are configured by the site owner. OnlineSched does not enable social login providers by default and does not store OAuth access tokens in its favorites table.</p>';

    wp_add_privacy_policy_content('OnlineSched', wp_kses_post(wpautop($content)));
}
add_action('admin_init', 'onlinesched_add_privacy_policy_content');

function onlinesched_register_privacy_exporter($exporters)
{
    $exporters['onlinesched-favorites'] = array(
        'exporter_friendly_name' => 'OnlineSched Favorites',
        'callback' => 'onlinesched_favorites_data_exporter',
    );

    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'onlinesched_register_privacy_exporter');

function onlinesched_register_privacy_eraser($erasers)
{
    $erasers['onlinesched-favorites'] = array(
        'eraser_friendly_name' => 'OnlineSched Favorites',
        'callback' => 'onlinesched_favorites_data_eraser',
    );

    return $erasers;
}
add_filter('wp_privacy_personal_data_erasers', 'onlinesched_register_privacy_eraser');

function onlinesched_get_favorite_rows_by_email($email_address)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'onlinesched_favorites';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE identifier = %s",
        sanitize_email($email_address)
    ));
}

function onlinesched_favorites_data_exporter($email_address, $page = 1)
{
    $rows = onlinesched_get_favorite_rows_by_email($email_address);
    $data = array();

    foreach ($rows as $row) {
        $data[] = array(
            'group_id' => 'onlinesched-favorites',
            'group_label' => 'OnlineSched Favorites',
            'item_id' => 'onlinesched-favorites-' . absint($row->id),
            'data' => array(
                array('name' => 'Provider', 'value' => $row->provider),
                array('name' => 'Identifier', 'value' => $row->identifier),
                array('name' => 'Favorite event IDs', 'value' => implode(', ', onlinesched_sanitize_favorites($row->favorites))),
                array('name' => 'Last updated', 'value' => $row->last_updated),
                array('name' => 'Last logged in', 'value' => $row->last_logged_in),
            ),
        );
    }

    return array(
        'data' => $data,
        'done' => true,
    );
}

function onlinesched_favorites_data_eraser($email_address, $page = 1)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'onlinesched_favorites';

    $deleted = $wpdb->delete(
        $table_name,
        array('identifier' => sanitize_email($email_address)),
        array('%s')
    );

    return array(
        'items_removed' => (bool) $deleted,
        'items_retained' => false,
        'messages' => array(),
        'done' => true,
    );
}
