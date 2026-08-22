<?php
/**
 * Disposable Vanilla-only integration checks for the live favorites feed key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;
$table_name = $wpdb->prefix . 'onlinesched_favorites';

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$identifier_a = 'feed-token-test-a@example.test';
$identifier_b = 'feed-token-test-b@example.test';
$created_posts = array();

$cleanup = static function () use ( $wpdb, $table_name, $identifier_a, $identifier_b, &$created_posts ) {
	$wpdb->delete( $table_name, array( 'identifier' => $identifier_a ), array( '%s' ) );
	$wpdb->delete( $table_name, array( 'identifier' => $identifier_b ), array( '%s' ) );
	foreach ( $created_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
};

try {
	$assert( false !== has_action( 'init', 'onlinesched_upgrade_favorites_table' ), 'The schema upgrade must run before public feed requests.' );
	onlinesched_create_favorites_table();

	$assert( '' === onlinesched_sanitize_feed_token( 'nope!' ), 'Non-hex input must sanitize to empty.' );
	$assert( '' === onlinesched_sanitize_feed_token( 'abc' ), 'Too-short input must sanitize to empty.' );
	$assert( '' === onlinesched_sanitize_feed_token( array( 'x' ) ), 'Array input must sanitize to empty.' );
	$assert( str_repeat( 'ab', 16 ) === onlinesched_sanitize_feed_token( strtoupper( str_repeat( 'ab', 16 ) ) ), 'Uppercase hex must sanitize to lowercase.' );

	onlinesched_save_favorites_for_identity( 'telegram', $identifier_a, array() );
	$assert( '' === onlinesched_get_feed_token_for_identity( 'telegram', $identifier_a ), 'A fresh row must hold no key.' );

	$token_a = onlinesched_get_or_mint_feed_token( 'telegram', $identifier_a );
	$assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $token_a ), 'Minting must return a 32-char hex key.' );
	$assert( $token_a === onlinesched_get_or_mint_feed_token( 'telegram', $identifier_a ), 'A second mint must return the same key.' );

	$token_b = onlinesched_get_or_mint_feed_token( 'telegram', $identifier_b );
	$assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $token_b ), 'Minting with no prior row must create one and key it.' );
	$assert( $token_a !== $token_b, 'Two identities must hold distinct keys.' );

	$row = onlinesched_get_feed_row_by_token( $token_a );
	$assert( $row && $identifier_a === $row->identifier, 'A valid key must resolve to its row.' );
	$assert( null === onlinesched_get_feed_row_by_token( str_repeat( '0', 32 ) ), 'An unknown key must resolve to null.' );
	$assert( null === onlinesched_get_feed_row_by_token( 'not-hex' ), 'A malformed key must resolve to null.' );

	$post_fav = wp_insert_post( array( 'post_type' => 'os_event', 'post_status' => 'publish', 'post_title' => 'Feed Token Test Favorite' ) );
	$created_posts = array( $post_fav );
	$assert( $post_fav > 0, 'Fixture event must insert.' );
	onlinesched_save_favorites_for_identity( 'telegram', $identifier_a, array( $post_fav ) );

	$fallback = onlinesched_fallback_feed_token();
	$assert( $fallback === onlinesched_sanitize_feed_token( $fallback ), 'The fallback key must survive the sanitizer.' );
	$assert( $fallback !== onlinesched_fallback_feed_token(), 'Two fallback draws must differ.' );

	$row = onlinesched_get_feed_row_by_token( $token_a );
	$claimed = onlinesched_claim_feed_token( $row->id );
	$assert( $token_a === $claimed, 'A claim on a keyed row must return the standing key, not overwrite it.' );

	$body_one = "BEGIN:VCALENDAR\r\nDTSTAMP:20260822T140000Z\r\nSUMMARY:Alpha\r\nEND:VCALENDAR\r\n";
	$body_two = "BEGIN:VCALENDAR\r\nDTSTAMP:20260822T150000Z\r\nSUMMARY:Alpha\r\nEND:VCALENDAR\r\n";
	$body_changed = "BEGIN:VCALENDAR\r\nDTSTAMP:20260822T140000Z\r\nSUMMARY:Beta\r\nEND:VCALENDAR\r\n";
	$assert( onlinesched_feed_body_validator( $body_one ) === onlinesched_feed_body_validator( $body_two ), 'Only DTSTAMP differing must not alter the validator.' );
	$assert( onlinesched_feed_body_validator( $body_one ) !== onlinesched_feed_body_validator( $body_changed ), 'A rendered content change must alter the validator.' );

	$rotated = onlinesched_rotate_feed_token( 'telegram', $identifier_a );
	$assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $rotated ) && $rotated !== $token_a, 'Rotation must mint a fresh key.' );
	$assert( null === onlinesched_get_feed_row_by_token( $token_a ), 'The old key must stop resolving after rotation.' );
	$new_row = onlinesched_get_feed_row_by_token( $rotated );
	$assert( $new_row && $identifier_a === $new_row->identifier, 'The rotated key must resolve to the same row.' );
	$assert( onlinesched_sanitize_favorites( $new_row->favorites ) === array( (string) $post_fav ), 'Rotation must leave the favorites untouched.' );

	$assert( '' === onlinesched_rotate_feed_token( 'telegram', 'feed-token-test-none@example.test' ), 'Rotation without a row must return empty.' );

	$erased = onlinesched_favorites_data_eraser( $identifier_a );
	$assert( $erased['items_removed'], 'Privacy erasure must remove the row.' );
	$assert( null === onlinesched_get_feed_row_by_token( $rotated ), 'Privacy erasure must kill the key.' );

	$url = onlinesched_feed_url( $token_b );
	$assert( false !== strpos( $url, 'icalby.php?feed=' . $token_b ), 'The feed URL must target icalby.php with the key.' );

	echo "feed token checks passed\n";
} finally {
	$cleanup();
}
