<?php

require_once dirname(__DIR__, 4) . '/wp-load.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * A simple example that shows how to use multiple providers, opening provider authentication in a pop-up.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$plugin_url = plugins_url( 'includes/login.php', dirname(__FILE__, 1) );

// Load shared social providers config
$social_config = require __DIR__ . '/social_providers_config.php';
// --- Load provider keys from WordPress options ---
if (isset($social_config['providers']) && is_array($social_config['providers'])) {
    foreach ($social_config['providers'] as $provider => &$providerData) {
        if (isset($providerData['keys']) && is_array($providerData['keys'])) {
            foreach ($providerData['keys'] as $key => $val) {
                $option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
                $wp_val = get_option($option_name);
                if (!empty($wp_val)) {
                    $providerData['keys'][$key] = $wp_val;
                }
            }
        }
    }
    unset($providerData);
}

$config = [
    'callback' =>  $plugin_url,
    'providers' => $social_config['providers'],
];

use Hybridauth\Exception\Exception;
use Hybridauth\Hybridauth;
use Hybridauth\HttpClient;
use Hybridauth\Storage\Session;

try {

	$hybridauth = new Hybridauth($config);
	$storage = new Session();
	$error = false;

	//
	// Event 1: User clicked SIGN-IN link
	//
	if (isset($_GET['provider'])) {
		if (in_array($_GET['provider'], $hybridauth->getProviders())) {
			// Store the provider for the callback event
			$storage->set('provider', $_GET['provider']);
		} else {
			$error = $_GET['provider'];
		}
	}

	//
	// Event 2: User clicked LOGOUT link
	//
	if (isset($_GET['logout'])) {
		if (in_array($_GET['logout'], $hybridauth->getProviders())) {
			// Disconnect the adapter
			$adapter = $hybridauth->getAdapter($_GET['logout']);
			$adapter->disconnect();

			// Try to get identifier from session or cookiee
			$provider_db = strtolower($_GET['logout']);
			$identifier_db = isset($_COOKIE['onlinesched_identifier']) ? $_COOKIE['onlinesched_identifier'] : '';
			if ($identifier_db) {
				$wpdb->delete($table_name, array('provider' => $provider_db, 'identifier' => $identifier_db));
			}

			// --- Clear session and identifier cookie ---
			if (isset($_SESSION['provider'])) unset($_SESSION['provider']);
			setcookie('onlinesched_identifier', '', time() - 3600, '/');

			// Output JS to clear cookie and close popup
			echo "<script>\n";
			echo "document.cookie = 'schedule_favorites=; Max-Age=0; path=/;';\n";
			echo "document.cookie = 'onlinesched_identifier=; Max-Age=0; path=/;';\n";
			echo "if (window.opener && !window.opener.closed) { window.opener.location.reload(); window.close(); } else { window.location.href = '/schedule'; }\n";
			echo "</script>\n";
			exit;
		} else {
			$error = $_GET['logout'];
		}
	}

	//
	// Handle invalid provider errors
	//
	if ($error) {
		echo "ERRO!";
		error_log('Hybridauth Error: Provider ' . json_encode($error) . ' not found or not enabled in $config');
		// Close the pop-up window
		/*echo "
            <script>
                if (window.opener.closeAuthWindow) {
                    window.opener.closeAuthWindow();
                }
            </script>"; */
		exit;
	}

	//
	// Event 3: Provider returns via CALLBACK
	//
	if ($provider = $storage->get('provider')) {

		$hybridauth->authenticate($provider);
		$storage->set('provider', null);

		// Retrieve the provider record
		$adapter = $hybridauth->getAdapter($provider);
		$userProfile = $adapter->getUserProfile();
		$accessToken = $adapter->getAccessToken();

		// add your custom AUTH functions (if any) here
		// ...
		$data = [
			'token' => $accessToken,
			'identifier' => $userProfile->identifier,
			'email' => $userProfile->email,
			'first_name' => $userProfile->firstName,
			'last_name' => $userProfile->lastName,
			'photoURL' => strtok($userProfile->photoURL, '?'),
		];

		// --- Set session and cookie for AJAX login state ---
		$_SESSION['provider'] = $provider;
		$identifier_db = $userProfile->email ? $userProfile->email : $userProfile->identifier;
		setcookie('onlinesched_identifier', $identifier_db, time() + 60*60*24*30, '/');

		// --- FAVORITES SYNC LOGIC ---
		global $wpdb;
		$table_name = $wpdb->prefix . 'onlinesched_favorites';
		$provider_db = strtolower($provider);
		$identifier_db = $userProfile->email ? $userProfile->email : $userProfile->identifier;
		// Update last_logged_in timestamp
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE provider = %s AND identifier = %s",
			$provider_db,
			$identifier_db
		));
		if ($row) {
			$wpdb->update(
				$table_name,
				['last_logged_in' => current_time('mysql', 1)],
				['provider' => $provider_db, 'identifier' => $identifier_db],
				['%s'],
				['%s', '%s']
			);
		} else {
			// Insert a new row with last_logged_in if not present
			$wpdb->insert(
				$table_name,
				[
					'provider' => $provider_db,
					'identifier' => $identifier_db,
					'favorites' => '',
					'last_logged_in' => current_time('mysql', 1)
				],
				['%s', '%s', '%s', '%s']
			);
		}

		$favorites = $row ? json_decode($row->favorites) : null;
		// Output JS to sync cookie/database
		echo "<script>\n";
		echo "function setFavoritesCookie(val) { document.cookie = 'schedule_favorites=' + encodeURIComponent(val) + ';path=/;max-age=' + (60*60*24*30); }\n";

		if ($favorites) {
			// Set cookie from DB
			echo "if (window.opener) { window.opener.setFavoritesCookie && window.opener.setFavoritesCookie('" . addslashes($favorites) . "'); }\n";
		} else {
			// No DB record: get cookie from opener and send to server
			echo "if (window.opener) {\n";
			echo "  var fav = window.opener.getCookie ? window.opener.getCookie('schedule_favorites') : null;\n";
			echo "  if (fav) {\n";
			echo "    var xhr = new XMLHttpRequest();\n";
			echo "    xhr.open('POST', 'save_favorites.php', true);\n";
			echo "    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');\n";
			echo "    xhr.send('provider=" . urlencode($provider_db) . "&identifier=" . urlencode($identifier_db) . "&favorites=' + encodeURIComponent(fav));\n";
			echo "  }\n";
			echo "}\n";
		}
		echo "</script>\n";
		// --- END FAVORITES SYNC LOGIC ---

		// Improved: Close pop-up and reload opener, fallback for non-popup
		echo "<script>\n";
		echo "if (window.opener && !window.opener.closed) {\n";
		echo "  if (window.opener.closeAuthWindow) window.opener.closeAuthWindow();\n";
		echo "  if (window.opener.location) window.opener.location.reload();\n";
		echo "  window.close();\n";
		echo "} else {\n";
		echo "  document.body.innerHTML = '<div style=\'text-align:center;margin-top:2em;font-size:1.2em\'>Login successful. <a href=\'/schedule\'>Return to schedule</a>.</div>';\n";
		echo "}\n";
		echo "</script>\n";
		exit;
	}

} catch (Exception $e) {
	error_log($e->getMessage());
	echo $e->getMessage();
}
