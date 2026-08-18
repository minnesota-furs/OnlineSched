<?php
/**
 * A dateless event (sorttime 0) must not publish a 1970 entry into
 * subscribed calendars.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-ical-dateless.php \
 *     --path=/var/www/html --allow-root
 */

if (!defined('WP_CLI') || !WP_CLI) {
	echo "This test must run through WP-CLI.\n";
	exit(1);
}

$assert = static function ($condition, $message) {
	if (!$condition) {
		WP_CLI::error('FAIL: ' . $message);
	}
	WP_CLI::log('PASS: ' . $message);
};

$post_id = wp_insert_post(array(
	'post_title'  => 'Dateless Calendar Probe',
	'post_status' => 'publish',
	'post_type'   => 'os_event',
	'meta_input'  => array(
		'onlinesched_sorttime' => 0,
		'onlinesched_timelen'  => 60,
	),
));

try {
	$assert(!is_wp_error($post_id) && $post_id > 0, 'probe event created');

	## The site name resolves on the host, not in the container: fetch through
	## the web server container and present the site host by header.
	$web_host = getenv('ONLINESCHED_TEST_WEB_HOST');
	$response = wp_remote_get(
		'https://' . ($web_host ? $web_host : 'fm-httpd')
			. '/wp-content/plugins/OnlineSched/icalby.php',
		array(
			'sslverify' => false,
			'timeout'   => 30,
			'headers'   => array('Host' => wp_parse_url(home_url(), PHP_URL_HOST)),
		)
	);
	$assert(!is_wp_error($response), 'calendar feed fetched');
	$body = wp_remote_retrieve_body($response);

	$assert(false !== strpos($body, 'BEGIN:VEVENT'), 'feed still carries events');
	$assert(false === strpos($body, 'DTSTART:19700101'), 'no epoch-zero event published');
	$assert(false === strpos($body, 'Dateless Calendar Probe'), 'the dateless event is absent');
} finally {
	if (!is_wp_error($post_id) && $post_id > 0) {
		wp_delete_post($post_id, true);
	}
}

WP_CLI::success('Dateless calendar checks passed.');
