<?php
/**
 * Request-scoped calendar subscription override for the disposable Vanilla test site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscription_override = isset( $_GET['onlinesched_test_calendar_subscriptions'] )
	? sanitize_key( wp_unslash( $_GET['onlinesched_test_calendar_subscriptions'] ) )
	: '';

if ( 'disabled' === $subscription_override ) {
	add_filter(
		'os_config_calendar_subscriptions_enabled',
		static function () {
			return false;
		}
	);
}

$query_guard = isset( $_GET['onlinesched_test_calendar_query_guard'] )
	? sanitize_key( wp_unslash( $_GET['onlinesched_test_calendar_query_guard'] ) )
	: '';

if ( 'armed' === $query_guard ) {
	header( 'X-OnlineSched-Test-Query-Guard: armed' );

	add_action(
		'pre_get_posts',
		static function ( $query ) {
			$post_type = $query->get( 'post_type' );
			$post_types = is_array( $post_type ) ? $post_type : array( $post_type );

			if ( in_array( 'os_event', $post_types, true ) ) {
				throw new RuntimeException( 'OnlineSched calendar query guard observed an os_event WP_Query.' );
			}
		},
		PHP_INT_MIN
	);
}
