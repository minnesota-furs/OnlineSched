<?php
/**
 * Disposable Vanilla-only integration checks for schedule page introductions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$original_post         = $GLOBALS['post'] ?? null;
$original_wp_query     = $GLOBALS['wp_query'] ?? null;
$original_wp_the_query = $GLOBALS['wp_the_query'] ?? null;
$page_id               = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Schedule Intro Test',
		'post_content' => '<p class="hide-standalone">Schedule intro <strong>from page content</strong>.</p>',
		'post_excerpt' => 'Legacy excerpt marker.',
	),
	true
);

if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( 'Could not create the schedule intro test page.' );
}

try {
	$query                        = new WP_Query( array( 'page_id' => $page_id ) );
	$GLOBALS['wp_query']          = $query;
	$GLOBALS['wp_the_query']      = $query;
	$query->the_post();

	$assert( is_page( $page_id ), 'The test page must be the active page query.' );

	$html = onlinesched_render_schedule(
		array(
			'mode' => 'standard',
			'tabs' => array( 'programming' ),
		)
	);

	$assert( false !== strpos( $html, '<div class="os-lead">' ), 'The ordinary schedule must render an intro region.' );
	$assert( false !== strpos( $html, 'from page content' ), 'The ordinary schedule intro must render page content.' );
	$assert( false !== strpos( $html, 'class="hide-standalone"' ), 'The PWA visibility class must survive intro rendering.' );
	$assert( false === strpos( $html, 'Legacy excerpt marker.' ), 'Page content must replace the legacy excerpt when both exist.' );
	$assert( 1 === substr_count( $html, 'from page content' ), 'The ordinary schedule intro must render once.' );

	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => '',
			'post_excerpt' => '<span class="hide-standalone">Legacy excerpt fallback.</span>',
		)
	);
	$fallback = onlinesched_schedule_intro_html( get_post( $page_id ), true );
	$assert( false !== strpos( $fallback, 'Legacy excerpt fallback.' ), 'An empty page must retain the excerpt fallback.' );
	$assert( false !== strpos( $fallback, 'class="hide-standalone"' ), 'The excerpt fallback must retain the PWA visibility class.' );

	echo "OnlineSched schedule intro integration tests passed.\n";
} finally {
	wp_delete_post( $page_id, true );
	wp_reset_postdata();
	$GLOBALS['post']         = $original_post;
	$GLOBALS['wp_query']     = $original_wp_query;
	$GLOBALS['wp_the_query'] = $original_wp_the_query;
}
