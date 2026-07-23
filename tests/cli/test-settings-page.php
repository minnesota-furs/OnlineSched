<?php
/**
 * Disposable Vanilla-only integration checks for the Event Settings page:
 *
 * - managed-in-code number/color rows render a hidden fallback input alongside
 *   their disabled visible control (markup precondition), AND — separately, as a
 *   real end-to-end check, not just a markup assertion — a full simulation of the
 *   actual wp-admin/options.php 'update' save loop (real update_option() calls
 *   through the real registered sanitize callbacks, reached through the real
 *   'allowed_options' whitelist register_setting() wires up) proves a
 *   constant-managed option survives a save instead of being blanked;
 * - con_start/con_end and public_date_start/public_date_end must never persist an
 *   inverted pair;
 * - App Info Pages reorder controls must disable Up on the first row and Down on
 *   the last row, label each control with the page title, and the hidden CSV field
 *   must pre-populate from the saved option;
 * - the tab panels/nav must resolve their active tab from $_GET['tab'] server-side.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$has_attribute = static function ( $html, $attribute ) {
	return 1 === preg_match( '/\s' . preg_quote( $attribute, '/' ) . "=(?:'|\")" . preg_quote( $attribute, '/' ) . "(?:'|\")/", $html );
};

// Finds the FIRST <input type="hidden" ... name="$name" ... value="..."> (attribute
// order tolerant) and returns its value, or null if no such hidden input exists.
$hidden_input_value = static function ( $html, $name ) {
	$pattern = '/<input\b(?=[^>]*type=(?:\'|")hidden(?:\'|"))(?=[^>]*name=(?:\'|")' . preg_quote( $name, '/' ) . '(?:\'|"))[^>]*value=(?:\'|")([^\'"]*)(?:\'|")[^>]*>/';
	return 1 === preg_match( $pattern, $html, $m ) ? $m[1] : null;
};

// Finds the reorder/remove <button class="...$class..."> tag within one row's
// HTML and returns its disabled state and aria-label text. Matched by CSS
// class (stable identity) rather than aria-label text, since the aria-label
// now embeds the page title and varies per row.
$find_row_button = static function ( $li_html, $class ) {
	if ( 1 !== preg_match( '/<button[^>]*class="[^"]*' . preg_quote( $class, '/' ) . '[^"]*"[^>]*>/', $li_html, $m ) ) {
		throw new RuntimeException( "No button with class \"$class\" found in row markup." );
	}
	$tag = $m[0];
	$aria_label = null;
	if ( 1 === preg_match( '/aria-label="([^"]*)"/', $tag, $lm ) ) {
		$aria_label = $lm[1];
	}
	return array(
		'disabled' => false !== strpos( $tag, "disabled='disabled'" ) || false !== strpos( $tag, 'disabled="disabled"' ),
		'aria_label' => $aria_label,
	);
};

$missing = '__onlinesched_settings_page_test_missing__';

// register_setting() only populates the real 'allowed_options' whitelist once
// admin_init has run. Call the plugin's own admin_init handler directly — what
// WordPress calls on every real wp-admin page load — rather than firing the
// whole admin_init action, to avoid unrelated admin_init hooks from other
// handlers in this fixture (e.g. CSV export headers) making noise here.
OnlineSched_admin_init();
$allowed_options = apply_filters( 'allowed_options', array() );
$assert(
	isset( $allowed_options['onlinesched_option_group'] ) && is_array( $allowed_options['onlinesched_option_group'] ),
	'onlinesched_option_group must be present in the real WordPress allowed_options whitelist (proves register_setting() actually wired the group in).'
);
$group_options = $allowed_options['onlinesched_option_group'];
$assert(
	in_array( 'onlinesched_sticky_offset_desktop', $group_options, true ),
	'onlinesched_sticky_offset_desktop must be in the real allowed_options whitelist for onlinesched_option_group.'
);

// Snapshot every option in the real group (not just the handful this file
// touches directly) so the end-to-end save-loop simulation below — which
// resubmits the whole group, exactly like the single always-rendered form
// does — can restore the database to its pre-test state regardless of outcome.
$originals = array();
foreach ( $group_options as $group_option_name ) {
	$originals[ $group_option_name ] = get_option( $group_option_name, $missing );
}

// This is the literal per-option loop from wp-admin/options.php's 'update'
// action handler (trim -> wp_unslash -> update_option), copied verbatim and
// scoped to whichever $options list is passed in. Calling the real
// update_option() means the real registered sanitize_callback for each
// option — the one register_setting() wired up above — actually runs, the
// same as it would on a genuine Save click.
$run_options_php_update_loop = static function ( array $options, array $post ) {
	foreach ( $options as $option ) {
		$option = trim( $option );
		$value = null;
		if ( array_key_exists( $option, $post ) ) {
			$value = $post[ $option ];
			if ( ! is_array( $value ) ) {
				$value = trim( $value );
			}
			$value = wp_unslash( $value );
		}
		update_option( $option, $value );
	}
};

// Mirrors real browser form serialization for one field name: a disabled
// input never submits, and when several non-disabled inputs share a name
// (the hidden-fallback pattern), the later one in DOM order is what
// PHP/$_POST ends up with.
$browser_submitted_value = static function ( $html, $name ) {
	preg_match_all( '/<input\b[^>]*>/', $html, $tags );
	$value = null;
	foreach ( $tags[0] as $tag ) {
		$has_name = false !== strpos( $tag, "name='" . $name . "'" ) || false !== strpos( $tag, 'name="' . $name . '"' );
		if ( ! $has_name || false !== strpos( $tag, 'disabled' ) ) {
			continue;
		}
		if ( 1 === preg_match( '/value=(?:\'|")([^\'"]*)(?:\'|")/', $tag, $m ) ) {
			$value = $m[1];
		}
	}
	return $value;
};

$post_backup = $_POST;
$get_backup = $_GET;

$restore = static function () use ( $originals, $missing, $post_backup, $get_backup ) {
	foreach ( $originals as $option_name => $original_value ) {
		if ( $missing === $original_value ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $original_value );
		}
	}
	$_POST = $post_backup;
	$_GET = $get_backup;
};

try {
	// -----------------------------------------------------------------
	// P1 markup precondition: managed-in-code number/color rows render a
	// hidden fallback carrying the saved value alongside the disabled
	// visible control. This section only asserts what got RENDERED — it
	// does not submit anything or call update_option(). The real save-loop
	// proof that this markup actually prevents data loss is the next
	// section, "REAL end-to-end options.php save-loop simulation" below.
	// -----------------------------------------------------------------

	update_option( 'onlinesched_sticky_offset_desktop', '42' );
	define( 'ONLINESCHED_STICKY_OFFSET_DESKTOP', 999 ); // Simulates constant-based management.
	ob_start();
	onlinesched_number_input_row( 'onlinesched_sticky_offset_desktop', 'Desktop Sticky Offset', 0, 'Test description.' );
	$number_html = ob_get_clean();
	$assert( $has_attribute( $number_html, 'disabled' ), 'MARKUP: a constant-managed number row must render its visible input disabled.' );
	$assert(
		'42' === $hidden_input_value( $number_html, 'onlinesched_sticky_offset_desktop' ),
		'MARKUP: a constant-managed number row must render a hidden fallback input carrying the saved value alongside the disabled visible control.'
	);

	update_option( 'onlinesched_color_primary', '#017940' );
	$color_filter = static function () {
		return '#abcdef';
	};
	add_filter( 'os_config_color_primary', $color_filter, 20 ); // Simulates filter-based management.
	ob_start();
	onlinesched_color_input_row( 'color_primary', 'Primary Color', 'Test description.' );
	$color_html = ob_get_clean();
	remove_filter( 'os_config_color_primary', $color_filter, 20 );
	$assert( $has_attribute( $color_html, 'disabled' ), 'MARKUP: a filter-managed color row must render its visible input disabled.' );
	$assert(
		'#017940' === $hidden_input_value( $color_html, 'onlinesched_color_primary' ),
		'MARKUP: a filter-managed color row must render a hidden fallback carrying the RAW saved option (not the filter-derived color).'
	);

	// -----------------------------------------------------------------
	// REAL end-to-end options.php save-loop simulation. Not a markup
	// assertion: this actually runs the literal wp-admin/options.php
	// 'update' loop — real update_option() calls, dispatching through the
	// real registered sanitize_callback via sanitize_option() — against a
	// full "Save with nothing else changed" $_POST built from the whole
	// real allowed_options whitelist for this group, with the
	// constant-managed field's submitted value derived by parsing the
	// actual rendered row markup the way a browser's form serialization
	// would. Proves the fix holds under the real save mechanism, not just
	// that the right tags are present in the HTML.
	// -----------------------------------------------------------------

	update_option( 'onlinesched_sticky_offset_desktop', '55' );
	// (ONLINESCHED_STICKY_OFFSET_DESKTOP is already defined from the markup
	// precondition section above — constants can't be undefined mid-process,
	// and that's fine: it's exactly the "managed in code" state under test.)

	ob_start();
	onlinesched_number_input_row( 'onlinesched_sticky_offset_desktop', 'Desktop Sticky Offset', 0, 'Test description.' );
	$row_html = ob_get_clean();
	$submitted_value = $browser_submitted_value( $row_html, 'onlinesched_sticky_offset_desktop' );
	$assert(
		'55' === $submitted_value,
		'Precondition: a browser submitting the real rendered row must send the hidden fallback\'s saved value for this field name.'
	);

	// A full resubmit: every other option in the group keeps its own current
	// value (a real "Save" with nothing else touched), and this one field
	// gets whatever a browser would actually submit for it.
	$post_payload = array();
	foreach ( $group_options as $group_option_name ) {
		$post_payload[ $group_option_name ] = (string) get_option( $group_option_name, '' );
	}
	$post_payload['onlinesched_sticky_offset_desktop'] = $submitted_value;

	$run_options_php_update_loop( $group_options, $post_payload );

	$assert(
		'55' === get_option( 'onlinesched_sticky_offset_desktop' ),
		'REAL SAVE-LOOP: after running the actual update_option()/sanitize_option() pipeline for the whole onlinesched_option_group with this row disabled by a constant, the option must read back as the previously saved 55 — not blanked or zeroed.'
	);

	// Counterfactual, to prove this simulation would have caught the
	// original bug: reproduce the PRE-FIX markup contract — a disabled
	// input with no hidden fallback submits nothing for that field name.
	$post_payload_without_fallback = $post_payload;
	unset( $post_payload_without_fallback['onlinesched_sticky_offset_desktop'] );
	$run_options_php_update_loop( $group_options, $post_payload_without_fallback );
	$blanked_value = get_option( 'onlinesched_sticky_offset_desktop' );
	$assert(
		0 === (int) $blanked_value,
		'COUNTERFACTUAL: without a hidden fallback input, the real save-loop blanks/zeroes a constant-managed option (absint(null) === 0) — confirming this simulation actually exercises the bug the P1 fix closes, not a tautology.'
	);

	// Put the fixed value back before the group-wide restore in `finally`.
	update_option( 'onlinesched_sticky_offset_desktop', '55' );

	// -----------------------------------------------------------------
	// Cross-field date validation: con_start/con_end must never invert
	// -----------------------------------------------------------------

	update_option( 'onlinesched_con_start', '2026-08-01' );
	update_option( 'onlinesched_con_end', '2026-08-05' );
	$_POST = array(
		'onlinesched_con_start' => '2026-08-10',
		'onlinesched_con_end' => '2026-08-05',
	);
	$assert(
		'2026-08-01' === onlinesched_sanitize_con_start( $_POST['onlinesched_con_start'] ),
		'An inverted con_start/con_end submission must keep the previously saved con_start.'
	);
	$assert(
		'2026-08-05' === onlinesched_sanitize_con_end( $_POST['onlinesched_con_end'] ),
		'An inverted con_start/con_end submission must keep the previously saved con_end.'
	);
	$assert(
		1 === count( get_settings_errors( 'onlinesched_con_start' ) ),
		'Exactly one settings error must be added (from the start-field callback) for an inverted con_start/con_end pair.'
	);
	$assert(
		0 === count( get_settings_errors( 'onlinesched_con_end' ) ),
		'The end-field sanitizer must not add its own duplicate settings error for the same inverted pair.'
	);

	// A valid (non-inverted) pair must save the submitted value and add no error.
	update_option( 'onlinesched_con_start', '2026-08-01' );
	update_option( 'onlinesched_con_end', '2026-08-05' );
	$_POST = array(
		'onlinesched_con_start' => '2026-08-02',
		'onlinesched_con_end' => '2026-08-05',
	);
	$assert(
		'2026-08-02' === onlinesched_sanitize_con_start( $_POST['onlinesched_con_start'] ),
		'A valid con_start/con_end pair must save the submitted con_start unchanged.'
	);

	// public_date_start/public_date_end get the same treatment.
	update_option( 'onlinesched_public_date_start', '2026-08-01' );
	update_option( 'onlinesched_public_date_end', '2026-08-05' );
	$_POST = array(
		'onlinesched_public_date_start' => '2026-08-10',
		'onlinesched_public_date_end' => '2026-08-05',
	);
	$assert(
		'2026-08-01' === onlinesched_sanitize_public_date_start( $_POST['onlinesched_public_date_start'] ),
		'An inverted public_date_start/public_date_end submission must keep the previously saved public_date_start.'
	);
	$assert(
		'2026-08-05' === onlinesched_sanitize_public_date_end( $_POST['onlinesched_public_date_end'] ),
		'An inverted public_date_start/public_date_end submission must keep the previously saved public_date_end.'
	);

	// -----------------------------------------------------------------
	// App Info Pages: Up disabled on the first row, Down disabled on the
	// last row, every control's aria-label includes the page title, and
	// the hidden CSV field pre-populates from the option.
	// -----------------------------------------------------------------

	$pages = get_pages( array( 'number' => 3 ) );
	$assert( count( $pages ) >= 2, 'The Vanilla fixture must have at least two pages to exercise the reorder controls.' );
	$ids = array_slice( array_map( static function ( $page ) { return $page->ID; }, $pages ), 0, min( 3, count( $pages ) ) );
	update_option( 'onlinesched_app_info_page_ids', implode( ',', $ids ) );

	ob_start();
	onlinesched_app_feed_settings_rows();
	$app_feed_html = ob_get_clean();

	$assert(
		implode( ',', $ids ) === $hidden_input_value( $app_feed_html, 'onlinesched_app_info_page_ids' ),
		'The App Info Pages hidden CSV field must pre-populate from the saved option.'
	);

	$row_count = preg_match_all( '/<li data-page-id="\d+">.*?<\/li>/s', $app_feed_html, $row_matches );
	$assert( $row_count === count( $ids ), 'The App Info Pages list must render exactly one row per configured page id.' );
	$rows = $row_matches[0];

	$up_first = $find_row_button( $rows[0], 'onlinesched-app-info-page-up' );
	$down_first = $find_row_button( $rows[0], 'onlinesched-app-info-page-down' );
	$assert( $up_first['disabled'], 'The first App Info Pages row must have its Move Up control disabled.' );
	$assert( ! $down_first['disabled'], 'The first App Info Pages row must NOT have its Move Down control disabled.' );

	$last_row = $rows[ count( $rows ) - 1 ];
	$up_last = $find_row_button( $last_row, 'onlinesched-app-info-page-up' );
	$down_last = $find_row_button( $last_row, 'onlinesched-app-info-page-down' );
	$assert( ! $up_last['disabled'], 'The last App Info Pages row must NOT have its Move Up control disabled.' );
	$assert( $down_last['disabled'], 'The last App Info Pages row must have its Move Down control disabled.' );

	if ( count( $rows ) > 2 ) {
		$up_mid = $find_row_button( $rows[1], 'onlinesched-app-info-page-up' );
		$down_mid = $find_row_button( $rows[1], 'onlinesched-app-info-page-down' );
		$assert( ! $up_mid['disabled'], 'A middle App Info Pages row must NOT have its Move Up control disabled.' );
		$assert( ! $down_mid['disabled'], 'A middle App Info Pages row must NOT have its Move Down control disabled.' );
	}

	// Every reorder/remove control's aria-label must name the row's page, so
	// screen reader users hear "Move Hotel up" / "Remove Parking", not a bare
	// "Move up" that gives no clue which row a control belongs to.
	foreach ( $ids as $row_index => $page_id ) {
		$expected_title = get_the_title( $page_id );
		$assert( '' !== trim( (string) $expected_title ), 'Test fixture sanity: every configured page id must resolve to a non-empty title.' );

		$app_info_row_html = $rows[ $row_index ];
		$up = $find_row_button( $app_info_row_html, 'onlinesched-app-info-page-up' );
		$down = $find_row_button( $app_info_row_html, 'onlinesched-app-info-page-down' );
		$remove = $find_row_button( $app_info_row_html, 'onlinesched-app-info-page-remove' );

		$assert(
			"Move {$expected_title} up" === $up['aria_label'],
			"Row {$row_index} Move Up aria-label must read \"Move {$expected_title} up\", got \"{$up['aria_label']}\"."
		);
		$assert(
			"Move {$expected_title} down" === $down['aria_label'],
			"Row {$row_index} Move Down aria-label must read \"Move {$expected_title} down\", got \"{$down['aria_label']}\"."
		);
		$assert(
			"Remove {$expected_title}" === $remove['aria_label'],
			"Row {$row_index} Remove aria-label must read \"Remove {$expected_title}\", got \"{$remove['aria_label']}\"."
		);
	}

	// -----------------------------------------------------------------
	// Tabs: the active tab (and which panel is visible) resolves from
	// $_GET['tab'] server-side, so it works before any JS runs.
	// -----------------------------------------------------------------

	$_GET = array();
	$assert( 'basic-setup' === onlinesched_active_settings_tab(), 'With no ?tab= param, the active tab must default to the first tab.' );

	$_GET = array( 'tab' => 'appearance' );
	$assert( 'appearance' === onlinesched_active_settings_tab(), '?tab=appearance must resolve to the appearance tab.' );

	$_GET = array( 'tab' => 'not-a-real-tab' );
	$assert( 'basic-setup' === onlinesched_active_settings_tab(), 'An unknown ?tab= value must fall back to the first tab.' );

	$_GET = array();
	ob_start();
	OnlineSched_options_page();
	$default_page_html = ob_get_clean();
	$assert(
		1 === preg_match( '/id="onlinesched-tab-panel-appearance"[^>]*style="display:none;"/', $default_page_html ),
		'With no ?tab= param, the Appearance panel must render with display:none.'
	);
	$assert(
		0 === preg_match( '/id="onlinesched-tab-panel-basic-setup"[^>]*style="display:none;"/', $default_page_html ),
		'With no ?tab= param, the Basic Setup panel must render visible (no inline display:none).'
	);
	$assert(
		1 === preg_match( '/id="onlinesched-tab-basic-setup"[^>]*aria-selected="true"/', $default_page_html ),
		'With no ?tab= param, the Basic Setup nav-tab must be aria-selected="true".'
	);
	$assert(
		false !== strpos( $default_page_html, 'name="_wp_http_referer"' ) || false !== strpos( $default_page_html, "name='_wp_http_referer'" ),
		'The form must render the _wp_http_referer hidden field the tab-persistence JS depends on.'
	);

	$_GET = array( 'tab' => 'appearance' );
	ob_start();
	OnlineSched_options_page();
	$appearance_page_html = ob_get_clean();
	$assert(
		0 === preg_match( '/id="onlinesched-tab-panel-appearance"[^>]*style="display:none;"/', $appearance_page_html ),
		'With ?tab=appearance, the Appearance panel must render visible (no inline display:none).'
	);
	$assert(
		1 === preg_match( '/id="onlinesched-tab-panel-basic-setup"[^>]*style="display:none;"/', $appearance_page_html ),
		'With ?tab=appearance, the Basic Setup panel must render with display:none.'
	);
	$assert(
		1 === preg_match( '/id="onlinesched-tab-appearance"[^>]*aria-selected="true"/', $appearance_page_html ),
		'With ?tab=appearance, the Appearance nav-tab must be aria-selected="true".'
	);

	echo "OnlineSched settings-page integration tests passed.\n";
} finally {
	$restore();
}
