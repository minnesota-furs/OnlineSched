<?php
require_once( '../../../wp-load.php' );

/*
room=<room name>
programming=all all programming
gaming=shows all room not programming, consuite, etc

*/

/**
 * Full Content Template
 *
 * Template Name:  Panel Grid - iCal page
 *
 * @file           icalbyroom.php
 * @package        OnlineSched
 * @author         Ben Lindstrom, Brian Mogged
 * @copyright      2014, 2016, 2018
 * @license        GPL-2.0-or-later
 * @version        Release: 3.0
 * @filesource     wp-content/themes/fm-2018/icalbyroom.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */


function onlinesched_json_sanitize_slugs($slugs) {
	if (!is_array($slugs)) {
		$slugs = array($slugs);
	}

	return array_values(array_filter(array_map('sanitize_title', $slugs)));
}

$slug = isset($_REQUEST['room']) && $_REQUEST['room'] !== '' && !is_array($_REQUEST['room'])
	? sanitize_title(wp_unslash($_REQUEST['room']))
	: 'main-stage';
$request = '';
if (!empty($_REQUEST['programming'] )) {
	$request = 'programming';
} else if (!empty($_REQUEST['gaming'] )) {
	$request = 'gaming';
}

$args = array(
	'post_type' => 'os_event',
	'meta_key'  => 'onlinesched_sorttime',
	'orderby'   => 'meta_value',
	'order'     => 'ASC',
	'nopaging'  => true
);

if (strtolower($slug) !== 'all' && empty($request)) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'os_room',
            'field'    => 'slug',
            'terms'    => $slug,
        )
    );
}

if ( $request == 'programming') {
	unset ( $args['tax_query'] );
	$groups = apply_filters('os_json_room_groups', array(
		'programming' => array(
			'mainstage',
			'panel-room-a',
			'panel-room-b',
			'regency',
			'special-events',
			'workshop-room',
			'youth-programming',
			'flex-space',
			'main-stage',
			'greenway-aandb',
			'greenway-f',
			'greenway-g',
			'greenway-h',
			'greenway-iandj',
			'lakeshore',
			'flex-space',
		)
	));

	$slug = $groups['programming'] ?? array();
	$slug = onlinesched_json_sanitize_slugs($slug);

	$args['tax_query'] = array(
		array(
			'taxonomy' => 'os_room',
			'field'    => 'slug',
			'terms'    => $slug,
			'operator' => 'IN'
		)
	);
}

if ( $request == 'gaming') {
	unset ( $args['tax_query'] );
	$groups = apply_filters('os_json_room_groups', array(
		'gaming_exclude' => array(
			'mainstage',
			'panel-room-a',
			'panel-room-b',
			'regency',
			'special-events',
			'workshop-room',
			'youth-programming',
			'main-stage',
			'greenway-aandb',
			'greenway-f',
			'greenway-g',
			'greenway-h',
			'greenway-iandj',
			'lakeshore',
			'flex-space',
			'art-jam',
			'consuite',
			'room-party',
			'registration'
		)
	));

	$slug = $groups['gaming_exclude'] ?? array();
	$slug = onlinesched_json_sanitize_slugs($slug);

	$args['tax_query'] = array(
		'relation' => 'AND',
		array(
			'taxonomy' => 'os_room',
			'field'    => 'slug',
			'terms'    => $slug,
			'operator' => 'NOT IN'
		),
		array(
			'taxonomy' => 'os_tag',
			'field'    => 'slug',
			'terms'    => 'open-gaming',
			'operator' => 'NOT IN',
		)
	);
}

$limit = -1;
if ( isset( $_REQUEST['limit'] ) && ! is_array( $_REQUEST['limit'] ) ) {
	$limit = intval( wp_unslash( $_REQUEST['limit'] ) );
}
$loop = new WP_Query( $args );
$postsArr = empty( $loop->posts ) ? array() : $loop->posts;

$dnt = new DateTime();
$dnt->setTimestamp(current_time('timestamp', true));
$dnt->setTimeZone(wp_timezone());
$dnt->setTimeZone( new DateTimeZone( 'UTC' ) );
#$dnt->add(new DateInterval('P10D'));
$json_out = array();

foreach ( $postsArr as $item ) {
	$postId = $item->ID;
	$year   = get_post_meta( $postId, 'onlinesched_year', true );

	## If we are limited ($limit != -1), if we hit 0, skip remaining posts.
	if ( $limit == 0 ) {
		break;
	}

	## If the current onlinesched_year is not our current year, skip event
	if ( $year != get_option( 'onlinesched_year' ) ) {
		continue;
	}

	## Figure out Times
	$startTime = get_post_meta( $postId, 'onlinesched_sorttime', true );
	$endTime   = $startTime + ( get_post_meta( $postId, 'onlinesched_timelen', true ) * 60 );

	$dst = new DateTime('@'.$startTime);
	$dst->setTimeZone(wp_timezone());
	$dst->setTimeZone(new DateTimeZone('UTC'));

	$det = new DateTime('@'.$endTime);
	$det->setTimeZone(wp_timezone());
	$det->setTimeZone(new DateTimeZone('UTC'));




	//	$det = new DateTime();
	//$det->setTimestamp($endTime);

	## If the limiting, skip any events clearly in the past
	if ( $limit > 0 && $det < $dnt ) {
		continue;
	}
	$limit --;

	$rooms = OnlineSched_terms_list2( 'os_room', $postId );

	$tags           = OnlineSched_terms_list2( 'os_tag', $postId );
	$tagsArray      = array_map( 'trim', explode( ",", $tags ) );
	$eventCancelled = in_array( "canceled", array_map( 'strtolower', $tagsArray ) ) ? true : false;
	if ( $eventCancelled ) {
		$rooms = "Canceled";
	}

	$addAdultTag = in_array( "restricted", array_map( 'strtolower', $tagsArray ) ) ? " [Adult]" : "";


	/*	$iCal->add('onlinesched-'.$postId,
		   $dst->format("m/d/Y H:i"),
		   $det->format("m/d/Y H:i"),
		   $rooms,
		   $item->post_title . $addAdultTag,
		   $item->post_content
	);
	*/
	$json_out[] = array(
		'room'        => $rooms,
		'title'       => $item->post_title . $addAdultTag,
		'startTime'   => $dst->format( "g:i A" ),
		'description' => $item->post_content
	);

}

header( 'Content-type: application/json' );
echo json_encode( $json_out );
