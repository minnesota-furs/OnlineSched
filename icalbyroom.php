<?php
/*
 * TODO:
 * - Location/Room Name is broke
 * - support "room_all"
 * - support "room=slug,slug,slug"
 * - Don't show old dates.
 * - Clean code
 */
require_once('../../../wp-load.php');

/**
 * Full Content Template
 *
   Template Name:  Panel Grid - iCal page
 *
 * @file           icalbyroom.php
 * @package        FM-2018 
 * @author         Ben Lindstrom, Brian Mogged
 * @copyright      2014, 2016, 2018
 * @license        license.txt
 * @version        Release: 2.0
 * @filesource     wp-content/themes/fm-2018/icalbyroom.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */

define('DATE_ICAL', 'Ymd\THis\Z');
define('EOL', "\r\n");
date_default_timezone_set('America/Chicago');

class iCalGen {
	private $output;
	public $prodid = "-//Furry Migration//Programing Grid 2022//EN";

	private function escapeString($string) {
		return preg_replace('/([\,;])/','\\\$1', $string);
	}

	function add($uid,
		     $startTime,
		     $endTime,
		     $location,
		     $title,
		     $desc) {
		$start = new DateTime();
		$start->setTimestamp(strtotime($startTime));
		$utc = new DateTimeZone('UTC');
		$start->setTimezone($utc);

		$end = new DateTime();
		$end->setTimestamp(strtotime($endTime));
		$utc = new DateTimeZone('UTC');
		$end->setTimezone($utc);

		$this->output .= 'BEGIN:VEVENT' . EOL .
		    'DTSTAMP:' . $start->format(DATE_ICAL) . EOL .
		    'DTSTART:' . $start->format(DATE_ICAL) . EOL . 
		    'DTEND:' . $end->format(DATE_ICAL) . EOL .
		    'SUMMARY:' . $this->escapeString($title) . EOL .
		    'DESCRIPTION:' . str_replace(array("\n", "\r"), '', $this->escapeString($desc)) . EOL .
		    'LOCATION:' . $this->escapeString($location) . EOL . 
		    'UID:' . $uid  . EOL . 
		    'END:VEVENT' . EOL;
	}

	function display() {
		return 'BEGIN:VCALENDAR' . EOL .
		    'VERSION:2.0'. EOL .
		    'CALSCALE:GREGORIAN' . EOL .
		    'METHOD:PUBLISH' . EOL .
		    'PRODID:' . $this->prodid . EOL .
		    'X-WR-TIMEZONE:GMT' . EOL .
		    $this->output . 
		    'END:VCALENDAR'. EOL;
	}
}

function limit($string,$len, $more) 
{
	if ($len > 0 and $len < strlen($string)) {
		$more_len = strlen($more) + 1;
		$stringCut = substr($string, 0, $len - $more_len);
		$endPoint = strrpos($stringCut, ' ');

		$string = $endPoint? substr($stringCut, 0, $endPoint) : 
			     	substr($stringCut, 0);
		return $string ." " . $more;
	} else {
		return $string;
	}
}


$slug = $_REQUEST['room'];
$textlen = 0;
$textmore = "[..]";
if (isset($_REQUEST['textlen'])) {
	$textlen = $_REQUEST['textlen'];
}
if (isset($_REQUEST['textmore'])) {
	$textmore = $_REQUEST['textmore'];
}

$args = array( 
	'post_type' => 'event_schedule',
	'tax_query' => array(
			array(
				'taxonomy' => 'event_schedule_room_type',
				'field' => 'slug',
				'terms' => $slug,
			)
			),
	'orderby' => 'title',
	'order' => 'ASC',
	'nopaging' => true
);

if (isset($_REQUEST['limit'])) {
	$limit = intval($_REQUEST['limit']);
	if ($limit > 0) {
		$args['posts_per_page'] = $limit;
		$args['nopaging'] = false;
	}
}
$loop = new WP_Query($args);
if (empty($loop->posts)) {
	exit();
}

$postsArr = $loop->posts;

$iCal = new iCalGen();
foreach ($postsArr as $item) {
	$postId = $item->ID;
	$year = get_post_meta( $postId, 'onlinesched_year', true );
	if ( $year != get_option( 'event_schedule_year' ) ) {
		continue;
	}

	## Figure out Times
	$startTime = get_post_meta($postId, 'onlinesched_sorttime', true);
	$endTime = $startTime + (get_post_meta($postId, 'onlinesched_timelen', true)*60);

	$dst = new DateTime('@'.$startTime);
	$dst->setTimeZone(new DateTimeZone(date_default_timezone_get()));
	$dst->setTimeZone(new DateTimeZone('UTC'));

	$det = new DateTime('@'.$endTime);
	$det->setTimeZone(new DateTimeZone(date_default_timezone_get()));
	$det->setTimeZone(new DateTimeZone('UTC'));

	## Room
	$rooms = OnlineSched_terms_list('event_schedule_room_type');
	$iCal->add('cal-fm-'.$postId,
		   $dst->format("m/d/Y H:i"),
		   $det->format("m/d/Y H:i"),
		   $rooms,
		   $item->post_title,
		   limit(strip_tags($item->post_content),$textlen, $textmore)
	);
}

header('Content-type: text/calendar');
header('Content-Disposition: attachment; filename="mnfm-'.$slug.'.ics"');
echo $iCal->display();
?>
