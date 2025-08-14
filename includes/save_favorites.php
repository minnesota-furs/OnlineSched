<?php
// save_favorites.php: Save or update favorites for a user/provider

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
$favorites = isset($_POST['favorites']) ? stripslashes(trim($_POST['favorites'])) : '';

// Validate favorites format
$valid_favorites = '';
if ($favorites) {
    $decoded = json_decode($favorites, true);
    if (is_array($decoded)) {
        // Only allow up to 200 items
        if (count($decoded) > 200) {
            $decoded = array_slice($decoded, 0, 200);
        }
        // Only allow numeric strings or integers
        $filtered = array_filter($decoded, function($v) {
            return is_numeric($v) && preg_match('/^\d+$/', strval($v));
        });
        if (count($filtered) === count($decoded)) {
            $valid_favorites = json_encode(array_values($filtered));
        }
    }
}
// If not valid, silently discard
if (!$valid_favorites) {
    $valid_favorites = '';
}

if (!$provider || !$identifier) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing provider or identifier']);
    exit;
}

// Save or update the favorites in the DB
global $wpdb;
$table_name = $wpdb->prefix . 'onlinesched_favorites';
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $table_name WHERE provider = %s AND identifier = %s",
    strtolower($provider),
    $identifier
));

if ($existing) {
    $wpdb->update(
        $table_name,
        [
            'favorites' => $valid_favorites,
            'last_updated' => current_time('mysql', 1)
        ],
        ['id' => $existing->id],
        ['%s', '%s'],
        ['%d']
    );
} else {
    $wpdb->insert(
        $table_name,
        [
            'provider' => strtolower($provider),
            'identifier' => $identifier,
            'favorites' => $favorites,
            'last_updated' => current_time('mysql', 1)
        ],
        ['%s', '%s', '%s', '%s']
    );
}

echo json_encode(['success' => true]);
