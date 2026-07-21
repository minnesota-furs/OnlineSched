<?php
/**
 * OnlineSched REST API Endpoints.
 *
 * @package OnlineSched
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'onlinesched_register_rest_routes' );

/**
 * Register custom REST API routes.
 */
function onlinesched_register_rest_routes() {
	register_rest_route( 'onlinesched/v1', '/event-search', array(
		'methods'             => \WP_REST_Server::READABLE,
		'callback'            => 'onlinesched_rest_event_search',
		'permission_callback' => 'onlinesched_rest_event_search_permissions',
		'args'                => array(
			's' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
	) );
}

/**
 * Permission callback for event search.
 */
function onlinesched_rest_event_search_permissions() {
	return current_user_can( 'edit_posts' );
}

/**
 * REST callback for event search.
 */
function onlinesched_rest_event_search( $request ) {
	$search_term = $request->get_param( 's' );

	$args = array(
		'post_type'      => 'os_event',
		'post_status'    => 'publish',
		's'              => $search_term,
		'posts_per_page' => 20,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$query = new \WP_Query( $args );
	$results = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$event_id = get_the_ID();

			// Simple list for the picker
			$sort_time = (int) get_post_meta($event_id, 'onlinesched_sorttime', true);
			$results[] = array(
				'id'    => $event_id,
				'title' => get_the_title(),
				'date'  => $sort_time ? wp_date('D m/d', $sort_time) : '',
			);
		}
		wp_reset_postdata();
	}

	return rest_ensure_response( $results );
}
