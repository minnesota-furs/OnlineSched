<?php
/**
 * Favorites storage and privacy helpers.
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

function onlinesched_send_private_no_store_headers()
{
    if (headers_sent()) {
        return;
    }

    nocache_headers();
    header('Cache-Control: no-store, private, max-age=0, must-revalidate', true);
    header('Pragma: no-cache', true);
    header('Vary: Cookie', false);
}

function onlinesched_maybe_start_session()
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
}

function onlinesched_new_favorites_session_token()
{
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return wp_generate_password(64, false, false);
    }
}

function onlinesched_get_favorites_session_token()
{
    onlinesched_maybe_start_session();

    if (empty($_SESSION['onlinesched_favorites_token'])) {
        $_SESSION['onlinesched_favorites_token'] = onlinesched_new_favorites_session_token();
    }

    return $_SESSION['onlinesched_favorites_token'];
}

function onlinesched_rotate_favorites_session_token()
{
    onlinesched_maybe_start_session();
    $_SESSION['onlinesched_favorites_token'] = onlinesched_new_favorites_session_token();

    return $_SESSION['onlinesched_favorites_token'];
}

function onlinesched_clear_favorites_session_token()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['onlinesched_favorites_token']);
    }
}

function onlinesched_verify_favorites_session_token($token = null)
{
    onlinesched_maybe_start_session();

    if (null === $token) {
        $token = isset($_REQUEST['favorites_token'])
            ? sanitize_text_field(wp_unslash($_REQUEST['favorites_token']))
            : '';
    }

    $expected = isset($_SESSION['onlinesched_favorites_token'])
        ? (string) $_SESSION['onlinesched_favorites_token']
        : '';

    return '' !== $token && '' !== $expected && hash_equals($expected, (string) $token);
}

function onlinesched_get_favorites_identity()
{
    $social_config = require ONLINESCHED_PLUGIN_DIR . 'includes/social_providers_config.php';
    $valid_providers = array_map('strtolower', array_keys((array) ($social_config['providers'] ?? array())));

    $identifier = isset($_COOKIE['onlinesched_identifier'])
        ? sanitize_text_field(wp_unslash($_COOKIE['onlinesched_identifier']))
        : '';

    if (!$identifier) {
        return new WP_Error('onlinesched_no_identity', 'No OnlineSched social-login identity is active.');
    }

    onlinesched_maybe_start_session();

    $provider = isset($_SESSION['provider']) ? strtolower(sanitize_key(wp_unslash($_SESSION['provider']))) : '';

    if (!$provider || !in_array($provider, $valid_providers, true)) {
        return new WP_Error('onlinesched_no_identity', 'No OnlineSched social-login identity is active.');
    }

    return array(
        'provider' => $provider,
        'identifier' => $identifier,
    );
}

function onlinesched_sanitize_favorites($favorites)
{
    if (is_string($favorites)) {
        $decoded = json_decode(wp_unslash($favorites), true);
    } else {
        $decoded = $favorites;
    }

    if (!is_array($decoded)) {
        return array();
    }

    if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
        $decoded = array_keys($decoded);
    }

    $ids = array();
    foreach ($decoded as $favorite_id) {
        $favorite_id = absint($favorite_id);
        if ($favorite_id > 0) {
            $ids[] = (string) $favorite_id;
        }
    }

    return array_slice(array_values(array_unique($ids)), 0, 200);
}

function onlinesched_get_favorites_for_identity($provider, $identifier)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'onlinesched_favorites';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT favorites FROM {$table_name} WHERE provider = %s AND identifier = %s",
        strtolower($provider),
        $identifier
    ));

    if (!$row || !$row->favorites) {
        return array();
    }

    return onlinesched_sanitize_favorites($row->favorites);
}

function onlinesched_save_favorites_for_identity($provider, $identifier, $favorites)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'onlinesched_favorites';
    $provider = strtolower(sanitize_key($provider));
    $identifier = sanitize_text_field($identifier);
    $favorites_json = wp_json_encode(onlinesched_sanitize_favorites($favorites));

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE provider = %s AND identifier = %s",
        $provider,
        $identifier
    ));

    $data = array(
        'provider' => $provider,
        'identifier' => $identifier,
        'favorites' => $favorites_json,
        'last_updated' => current_time('mysql', true),
    );

    if ($existing) {
        return false !== $wpdb->update(
            $table_name,
            array(
                'favorites' => $favorites_json,
                'last_updated' => $data['last_updated'],
            ),
            array('id' => absint($existing->id)),
            array('%s', '%s'),
            array('%d')
        );
    }

    return false !== $wpdb->insert(
        $table_name,
        $data,
        array('%s', '%s', '%s', '%s')
    );
}

function onlinesched_ajax_get_favorites()
{
    onlinesched_send_private_no_store_headers();

    $identity = onlinesched_get_favorites_identity();
    if (is_wp_error($identity)) {
        wp_send_json_success(array('favorites' => array()));
    }

    if (!onlinesched_verify_favorites_session_token()) {
        wp_send_json_error(array('message' => 'Invalid favorites token.'), 403);
    }

    wp_send_json_success(array(
        'favorites' => onlinesched_get_favorites_for_identity($identity['provider'], $identity['identifier']),
    ));
}

function onlinesched_ajax_save_favorites()
{
    onlinesched_send_private_no_store_headers();

    $identity = onlinesched_get_favorites_identity();
    if (is_wp_error($identity)) {
        wp_send_json_error(array('message' => 'Not logged in.'), 403);
    }

    if (!onlinesched_verify_favorites_session_token()) {
        wp_send_json_error(array('message' => 'Invalid favorites token.'), 403);
    }

    $favorites = isset($_POST['favorites']) ? wp_unslash($_POST['favorites']) : '[]';
    $saved = onlinesched_save_favorites_for_identity($identity['provider'], $identity['identifier'], $favorites);

    if (!$saved) {
        wp_send_json_error(array('message' => 'Could not save favorites.'), 500);
    }

    wp_send_json_success(array('favorites' => onlinesched_sanitize_favorites($favorites)));
}

function onlinesched_direct_get_favorites()
{
    onlinesched_send_private_no_store_headers();

    $identity = onlinesched_get_favorites_identity();
    if (is_wp_error($identity)) {
        wp_send_json_success(array('favorites' => array()));
    }

    if (!onlinesched_verify_favorites_session_token()) {
        wp_send_json_error(array('message' => 'Invalid favorites token.'), 403);
    }

    wp_send_json_success(array(
        'favorites' => onlinesched_get_favorites_for_identity($identity['provider'], $identity['identifier']),
    ));
}

function onlinesched_direct_save_favorites()
{
    if ('POST' !== $_SERVER['REQUEST_METHOD']) {
        wp_send_json_error(array('message' => 'Method not allowed.'), 405);
    }

    onlinesched_send_private_no_store_headers();

    $identity = onlinesched_get_favorites_identity();
    if (is_wp_error($identity)) {
        wp_send_json_error(array('message' => 'Not logged in.'), 403);
    }

    if (!onlinesched_verify_favorites_session_token()) {
        wp_send_json_error(array('message' => 'Invalid favorites token.'), 403);
    }

    $favorites = isset($_POST['favorites']) ? wp_unslash($_POST['favorites']) : '[]';
    $saved = onlinesched_save_favorites_for_identity($identity['provider'], $identity['identifier'], $favorites);

    if (!$saved) {
        wp_send_json_error(array('message' => 'Could not save favorites.'), 500);
    }

    wp_send_json_success(array('favorites' => onlinesched_sanitize_favorites($favorites)));
}

add_action('wp_ajax_onlinesched_get_favorites', 'onlinesched_ajax_get_favorites');
add_action('wp_ajax_nopriv_onlinesched_get_favorites', 'onlinesched_ajax_get_favorites');
add_action('wp_ajax_onlinesched_save_favorites', 'onlinesched_ajax_save_favorites');
add_action('wp_ajax_nopriv_onlinesched_save_favorites', 'onlinesched_ajax_save_favorites');
