<?php
/**
 * Disposable Vanilla-only integration checks for the calendar subscription setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$option_name = 'onlinesched_calendar_subscriptions_enabled';
$missing = '__onlinesched_calendar_subscriptions_missing__';
$original_value = get_option( $option_name, $missing );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$render_setting = static function () {
	ob_start();
	onlinesched_calendar_subscriptions_setting_row();
	return ob_get_clean();
};

$has_attribute = static function ( $html, $attribute ) {
	return 1 === preg_match( '/\s' . preg_quote( $attribute, '/' ) . "=(?:'|\")" . preg_quote( $attribute, '/' ) . "(?:'|\")/", $html );
};

$restore = static function () use ( $option_name, $missing, $original_value ) {
	if ( $missing === $original_value ) {
		delete_option( $option_name );
		return;
	}

	update_option( $option_name, $original_value );
};

try {
	delete_option( $option_name );
	$assert( onlinesched_calendar_subscriptions_enabled(), 'An unset option must default to enabled.' );
	$default_status = onlinesched_get_config_status( 'calendar_subscriptions_enabled', '1' );
	$assert( 'default' === $default_status['source'], 'The unset option must report the default source.' );
	$assert( '1' === (string) $default_status['value'], 'The unset option must report enabled.' );
	$default_html = $render_setting();
	$assert( $has_attribute( $default_html, 'checked' ), 'The default admin checkbox must be checked.' );
	$assert( ! $has_attribute( $default_html, 'disabled' ), 'The default admin checkbox must be editable.' );
	$assert( '1' === onlinesched_sanitize_checkbox( '1' ), 'The checkbox sanitizer must retain an enabled save.' );
	$assert( '0' === onlinesched_sanitize_checkbox( '0' ), 'The checkbox sanitizer must retain a disabled save.' );
	$assert( '0' === onlinesched_sanitize_checkbox( 'true' ), 'The checkbox sanitizer must reject non-checkbox truthy text.' );

	update_option( $option_name, '0' );
	$assert( ! onlinesched_calendar_subscriptions_enabled(), 'A saved zero must disable subscriptions.' );
	$disabled_status = onlinesched_get_config_status( 'calendar_subscriptions_enabled', '1' );
	$assert( 'saved option' === $disabled_status['source'], 'A saved zero must report the saved-option source.' );
	$disabled_html = $render_setting();
	$assert( ! $has_attribute( $disabled_html, 'checked' ), 'The disabled admin checkbox must be unchecked.' );
	$assert( ! $has_attribute( $disabled_html, 'disabled' ), 'The saved-option admin checkbox must remain editable.' );
	$assert( false !== strpos( $disabled_html, 'Individual event calendar buttons remain available' ), 'The admin row must retain the individual-event boundary.' );

	$schedule_page_id = onlinesched_get_page_id( 'schedule', 'schedule' );
	$cleaned_post_ids = array();
	$cache_observer = static function ( $post_id ) use ( &$cleaned_post_ids ) {
		$cleaned_post_ids[] = (int) $post_id;
	};
	add_action( 'clean_post_cache', $cache_observer );
	update_option( $option_name, '1' );
	remove_action( 'clean_post_cache', $cache_observer );
	$assert( $schedule_page_id > 0, 'The Vanilla fixture must configure a schedule page.' );
	$assert( in_array( $schedule_page_id, $cleaned_post_ids, true ), 'Changing the option must clean the configured schedule page cache.' );

	$filter = static function () {
		return false;
	};
	add_filter( 'os_config_calendar_subscriptions_enabled', $filter, 20 );
	$assert( ! onlinesched_calendar_subscriptions_enabled(), 'The code filter must override the saved option.' );
	$filter_status = onlinesched_get_config_status( 'calendar_subscriptions_enabled', '1' );
	$assert( 'filter' === $filter_status['source'], 'The code filter must report the filter source.' );
	$filter_html = $render_setting();
	$assert( $has_attribute( $filter_html, 'disabled' ), 'A filter-managed checkbox must be disabled.' );
	$assert( ! $has_attribute( $filter_html, 'checked' ), 'A false filter-managed checkbox must be unchecked.' );
	$assert( false !== strpos( $filter_html, 'Managed in code by <code>os_config_calendar_subscriptions_enabled</code>' ), 'The filter-managed state must be explained.' );
	$assert( 1 === preg_match( '/type=(?:\'|")hidden(?:\'|")[^>]*value=(?:\'|")1(?:\'|")/', $filter_html ), 'The managed row must preserve the saved option value.' );
	remove_filter( 'os_config_calendar_subscriptions_enabled', $filter, 20 );

	define( 'ONLINESCHED_CALENDAR_SUBSCRIPTIONS_ENABLED', false );
	$assert( ! onlinesched_calendar_subscriptions_enabled(), 'The code constant must override the saved option.' );
	$constant_status = onlinesched_get_config_status( 'calendar_subscriptions_enabled', '1' );
	$assert( 'constant' === $constant_status['source'], 'The code constant must report the constant source.' );
	$constant_html = $render_setting();
	$assert( $has_attribute( $constant_html, 'disabled' ), 'A constant-managed checkbox must be disabled.' );
	$assert( ! $has_attribute( $constant_html, 'checked' ), 'A false constant-managed checkbox must be unchecked.' );
	$assert( false !== strpos( $constant_html, 'Managed in code by <code>ONLINESCHED_CALENDAR_SUBSCRIPTIONS_ENABLED</code>' ), 'The constant-managed state must be explained.' );

	echo "OnlineSched calendar subscription setting integration tests passed.\n";
} finally {
	$restore();
}
