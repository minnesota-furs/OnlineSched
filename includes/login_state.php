<?php
// login_state.php: no-store bootstrap for social login state and favorites sync.
//
// This is a standalone direct-hit endpoint, not an include-only file.
// It loads WordPress itself via wp-load.php, so it intentionally does NOT
// have the `if (!defined('ABSPATH')) exit;` guard that library files use.
require_once __DIR__ . '/../../../../wp-load.php';

if (function_exists('onlinesched_send_private_no_store_headers')) {
    onlinesched_send_private_no_store_headers();
} else if (!headers_sent()) {
    nocache_headers();
    header('Cache-Control: no-store, private, max-age=0, must-revalidate', true);
    header('Vary: Cookie', false);
}

$identity = function_exists('onlinesched_get_favorites_identity')
    ? onlinesched_get_favorites_identity()
    : new WP_Error('onlinesched_no_identity', 'No OnlineSched social-login identity is active.');

$response = array(
    'loggedIn' => false,
    'provider' => '',
    'favorites' => null,
    'favoritesToken' => '',
);

if (!is_wp_error($identity)) {
    $response['loggedIn'] = true;
    $response['provider'] = $identity['provider'];
    $response['favorites'] = function_exists('onlinesched_get_favorites_for_identity')
        ? onlinesched_get_favorites_for_identity($identity['provider'], $identity['identifier'])
        : array();
    $response['favoritesToken'] = function_exists('onlinesched_get_favorites_session_token')
        ? onlinesched_get_favorites_session_token()
        : '';
}

header('Content-Type: application/json; charset=' . get_option('blog_charset'));
echo wp_json_encode($response);
