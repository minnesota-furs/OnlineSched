<?php
// get_favorites.php: Returns the current user's favorites from the database as JSON
require_once dirname(__DIR__, 4) . '/wp-load.php';
header('Content-Type: application/json');

$provider = isset($_GET['provider']) ? trim($_GET['provider']) : '';
$identifier = isset($_GET['identifier']) ? trim($_GET['identifier']) : '';

if (!$provider || !$identifier) {
    echo json_encode(['favorites' => null]);
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'onlinesched_favorites';
$row = $wpdb->get_row($wpdb->prepare(
    "SELECT favorites FROM $table_name WHERE provider = %s AND identifier = %s",
    strtolower($provider),
    $identifier
));

if ($row && $row->favorites) {
    // Try to decode and re-encode as a JSON array
    $decoded = json_decode($row->favorites, true);
    if (is_array($decoded)) {
        // If it's an associative array (object), convert to array of keys
        if (array_keys($decoded) !== range(0, count($decoded) - 1)) {
            $decoded = array_keys($decoded);
        }
        echo json_encode(['favorites' => json_encode($decoded)]);
    } else {
        echo json_encode(['favorites' => '[]']);
    }
} else {
    echo json_encode(['favorites' => '[]']);
}
