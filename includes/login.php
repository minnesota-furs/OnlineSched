<?php

if (!defined('DONOTCACHEPAGE')) {
	define('DONOTCACHEPAGE', true);
}

require_once dirname(__DIR__, 4) . '/wp-load.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * A simple example that shows how to use multiple providers, opening provider authentication in a pop-up.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$plugin_url = plugins_url( 'includes/login.php', dirname(__FILE__, 1) );
$schedule_url = function_exists('onlinesched_get_schedule_page_url') ? onlinesched_get_schedule_page_url() : home_url('/schedule/');

// Load shared social providers config
$social_config = require __DIR__ . '/social_providers_config.php';
// --- Load provider keys from WordPress options ---
if (isset($social_config['providers']) && is_array($social_config['providers'])) {
    foreach ($social_config['providers'] as $provider => &$providerData) {
        if (!empty($providerData['no_keys'])) {
            // For providers with no_keys, allow usage even if no key is set
        } else if (isset($providerData['keys']) && is_array($providerData['keys'])) {
            foreach ($providerData['keys'] as $key => $val) {
                $wp_val = function_exists('onlinesched_social_provider_get_key')
                    ? onlinesched_social_provider_get_key($provider, $key)
                    : get_option('onlinesched_social_' . strtolower($provider) . '_' . strtolower($key), '');
                if (!empty($wp_val)) {
                    $providerData['keys'][$key] = $wp_val;
                }
            }
        }

        $provider_enabled = function_exists('onlinesched_social_provider_is_enabled')
            ? onlinesched_social_provider_is_enabled($provider, $providerData)
            : !empty($providerData['enabled']);

        if (!$provider_enabled) {
            unset($social_config['providers'][$provider]);
            continue;
        }

        $providerData['enabled'] = true;
    }
    unset($providerData);
}

if (!function_exists('onlinesched_login_resolve_provider_name')) {
	function onlinesched_login_resolve_provider_name($requested_provider, $available_providers)
	{
		foreach ((array) $available_providers as $provider) {
			if (strtolower($provider) === strtolower($requested_provider)) {
				return $provider;
			}
		}

		return '';
	}
}

if (!function_exists('onlinesched_login_render_popup_response')) {
	function onlinesched_login_render_popup_response($title, $message, $schedule_url, $before_finish_js = '')
	{
		$schedule_url = esc_url_raw($schedule_url);
		if (!headers_sent()) {
			nocache_headers();
			header('Cache-Control: no-store, private, max-age=0, must-revalidate', true);
			header('X-Robots-Tag: noindex, nofollow', true);
		}
		?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html($title); ?></title>
	<style>
		body {
			box-sizing: border-box;
			display: grid;
			min-height: 100vh;
			margin: 0;
			padding: 24px;
			place-items: center;
			background: #f6f7f7;
			color: #1d2327;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}

		main {
			max-width: 420px;
			padding: 24px;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			background: #fff;
			text-align: center;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
		}

		a {
			color: #135e96;
		}
	</style>
</head>
<body>
	<main>
		<h1><?php echo esc_html($title); ?></h1>
		<p id="onlinesched-auth-status"><?php echo esc_html($message); ?></p>
		<p><a href="<?php echo esc_url($schedule_url); ?>">Return to schedule</a></p>
	</main>
	<script>
	(function () {
		var scheduleUrl = <?php echo wp_json_encode($schedule_url); ?>;
		var status = document.getElementById('onlinesched-auth-status');

		function setStatus(message) {
			if (status) {
				status.textContent = message;
			}
		}

		function refreshOpener() {
			try {
				if (window.opener && !window.opener.closed) {
					window.opener.location.reload();
					return true;
				}
			} catch (e) {}

			return false;
		}

		function closeOrFallback() {
			var hadOpener = refreshOpener();
			window.setTimeout(function () {
				window.close();
			}, 100);
			window.setTimeout(function () {
				setStatus(hadOpener ? 'Done. You can close this window if it did not close automatically.' : 'Done. Use the link below to return to the schedule.');
				if (!hadOpener) {
					window.location.replace(scheduleUrl);
				}
			}, 1200);
		}

		<?php echo $before_finish_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		closeOrFallback();
	}());
	</script>
</body>
</html>
		<?php
	}
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
		$requested_provider = sanitize_text_field(wp_unslash($_GET['provider']));
		$resolved_provider = onlinesched_login_resolve_provider_name($requested_provider, $hybridauth->getProviders());
		if ($resolved_provider) {
			// Store the provider for the callback event
			$storage->set('provider', $resolved_provider);
		} else {
			$error = $requested_provider;
		}
	}

	//
	// Event 2: User clicked LOGOUT link
	//
	if (isset($_GET['logout'])) {
		$requested_logout = sanitize_text_field(wp_unslash($_GET['logout']));
		$resolved_logout = onlinesched_login_resolve_provider_name($requested_logout, $hybridauth->getProviders());
		if ($resolved_logout) {
			// Disconnect the adapter
			$adapter = $hybridauth->getAdapter($resolved_logout);
			$adapter->disconnect();

			global $wpdb;
			$table_name = $wpdb->prefix . 'onlinesched_favorites';
			// Try to get identifier from session or cookiee
			$provider_db = strtolower($resolved_logout);
			$identifier_db = isset($_COOKIE['onlinesched_identifier']) ? sanitize_text_field(wp_unslash($_COOKIE['onlinesched_identifier'])) : '';
			// don't delete the entry in db for logout
			//		if ($identifier_db) {
			//		$wpdb->delete($table_name, array('provider' => $provider_db, 'identifier' => $identifier_db));
			//	}

			// --- Clear session and identifier cookie ---
			if (isset($_SESSION['provider'])) unset($_SESSION['provider']);
			if (function_exists('onlinesched_clear_favorites_session_token')) {
				onlinesched_clear_favorites_session_token();
			}
			setcookie('onlinesched_identifier', '', time() - 3600, '/');

			$logout_script = "document.cookie = 'schedule_favorites=; Max-Age=0; path=/; SameSite=Lax';\n";
			$logout_script .= "document.cookie = 'onlinesched_identifier=; Max-Age=0; path=/; SameSite=Lax';\n";
			onlinesched_login_render_popup_response(
				'Logged out',
				'Logout complete. This window should close automatically.',
				$schedule_url,
				$logout_script
			);
			exit;
		} else {
			$error = $requested_logout;
		}
	}

	//
	// Handle invalid provider errors
	//
	if ($error) {
		error_log('Hybridauth Error: Provider ' . json_encode($error) . ' not found or not enabled in $config');
		onlinesched_login_render_popup_response(
			'Login provider unavailable',
			'That login provider is not enabled or configured.',
			$schedule_url
		);
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
		if (function_exists('onlinesched_rotate_favorites_session_token')) {
			onlinesched_rotate_favorites_session_token();
		}
		$identifier_db = sanitize_text_field($userProfile->email ? $userProfile->email : $userProfile->identifier);
		setcookie('onlinesched_identifier', $identifier_db, time() + 60*60*24*30, '/');

		// --- FAVORITES SYNC LOGIC ---
		global $wpdb;
		$table_name = $wpdb->prefix . 'onlinesched_favorites';
		$provider_db = strtolower($provider);
		$identifier_db = sanitize_text_field($userProfile->email ? $userProfile->email : $userProfile->identifier);
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

		$favorites = $row && function_exists('onlinesched_sanitize_favorites')
			? onlinesched_sanitize_favorites($row->favorites)
			: array();
		$success_script = 'var serverFavorites = ' . wp_json_encode($favorites) . ";\n";
		$success_script .= "try {\n";
		$success_script .= "  if (serverFavorites.length && window.opener && !window.opener.closed && window.opener.setFavoritesCookie) {\n";
		$success_script .= "    window.opener.setFavoritesCookie(serverFavorites);\n";
		$success_script .= "  }\n";
		$success_script .= "} catch (e) {}\n";
		onlinesched_login_render_popup_response(
			'Login complete',
			'Login complete. This window should close automatically.',
			$schedule_url,
			$success_script
		);
		exit;
	}

} catch (Exception $e) {
	error_log($e->getMessage());
	onlinesched_login_render_popup_response(
		'Login failed',
		'Login did not complete. Please return to the schedule and try again.',
		$schedule_url
	);
}
