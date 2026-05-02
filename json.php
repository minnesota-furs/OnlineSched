<?php
/*
 * TODO:
 * - Location/Room Name is broke
  * - support "room=slug,slug,slug"
 * - Clean code
 * merge with ical code
 */
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


$slug = empty($_REQUEST['room']) ? 'main-stage' : $_REQUEST['room'];
$request = '';
if (!empty($_REQUEST['programming'] )) {
	$request = 'programming';
} else if (!empty($_REQUEST['gaming'] )) {
	$request = 'gaming';
}

$args = array(
	'post_type' => 'os_event',
	'tax_query' => array(
		array(
			'taxonomy' => 'os_room',
			'field'    => 'slug',
			'terms'    => $slug,
		)
	),
#	'orderby' => 'title',		## XX Think this is wrong
	'meta_key'  => 'onlinesched_sorttime',
	'orderby'   => 'meta_value',
	'order'     => 'ASC',
	'nopaging'  => true
);

if ( $request == 'programming') {

	unset ( $args['tax_query'] );
	$slug              = array(
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

	);
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
	$slug              = array(
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
	);
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

$limit = - 1;
$limit = 1; // One back now
if ( isset( $_REQUEST['limit'] ) ) {
	$limit = intval( $_REQUEST['limit'] );
}
$loop = new WP_Query( $args );
if ( empty( $loop->posts ) ) {
	exit();
}

$postsArr = $loop->posts;

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
	//	print_r(array($startTime, $endTime, $det, $dnt));

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
