<?php
// login_state.php: Returns the current social login state as JSON for AJAX use
session_start();
$social_config = require __DIR__ . '/social_providers_config.php';
$valid_providers = array_keys($social_config['providers']);
$provider = isset($_SESSION['provider']) ? $_SESSION['provider'] : '';
$identifier = isset($_COOKIE['onlinesched_identifier']) ? $_COOKIE['onlinesched_identifier'] : '';
$is_logged_in = in_array($provider, $valid_providers) && !empty($identifier);

require_once __DIR__ . '/../../../../wp-load.php';
$favorites = null;
if ($is_logged_in && !empty($provider) && !empty($identifier)) {
    global $wpdb;
    $table = $wpdb->prefix . 'onlinesched_favorites';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT favorites FROM $table WHERE provider = %s AND identifier = %s LIMIT 1",
        $provider,
        $identifier
    ));
    if ($row && isset($row->favorites)) {
        $favorites = json_decode($row->favorites,);
    }
}

header('Content-Type: application/json');
echo json_encode([
    'loggedIn' => $is_logged_in,
    'provider' => $provider,
    'identifier' => $identifier,
    'favorites' => $favorites
]);

