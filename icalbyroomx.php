<?php
/*
 * TODO:
 * - Location/Room Name is broke
 * - support "room=slug,slug,slug"
 * - Clean code
 */
require_once('../../../wp-load.php');
require_once('html2text/html2text.php');
/**
 * Full Content Template
 *
   Template Name:  Panel Grid - iCal page
 *
 * @file           icalbyroom.php
 * @package        OnlineSched
 * @author         Ben Lindstrom, Brian Mogged
 * @copyright      2014, 2016, 2018, 2020
 * @license        GPL-2.0-or-later
 * @version        Release: 2.0
 * @filesource     wp-content/plugins/OnlineSched/icalbyroom.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */

/* Usage 
romm=paanel room name
room=all all roms
limit = 2
*/

define('DATE_ICAL', 'Ymd\THis\Z');
define('EOL', "\r\n");

class iCalGen {
	private $output;
	public $prodid;

    public function __construct()
    {
        $this->prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';
    }

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

$slug = $_REQUEST['room'];

$args = array( 
	'post_type' => 'os_event',
	'tax_query' => array(
			array(
				'taxonomy' => 'os_room',
				'field' => 'slug',
				'terms' => $slug,
			)
			),
#	'orderby' => 'title',		## XX Think this is wrong
	'meta_key' => 'onlinesched_sorttime',
	'orderby' => 'meta_value',
	'order' => 'ASC',
	'nopaging' => true
);

if (strtolower($slug) == 'all') {

  unset ($args['tax_query']);
}

$limit = -1;
if (isset($_REQUEST['limit'])) {
	$limit = intval($_REQUEST['limit']);
}
$loop = new WP_Query($args);
if (empty($loop->posts)) {
	exit();
}

$postsArr = $loop->posts;

$dnt = new DateTime();
$dnt->setTimestamp(current_time('timestamp', true));
$dnt->setTimeZone(wp_timezone());
$dnt->setTimeZone(new DateTimeZone('UTC'));
#$dnt->add(new DateInterval('P10D'));

$iCal = new iCalGen();
foreach ($postsArr as $item) {
	$postId = $item->ID;
	$year = get_post_meta( $postId, 'onlinesched_year', true );

	## If we are limited ($limit != -1), if we hit 0, skip remaining posts.
	if ( $limit == 0) {		
		break;
	}

	## If the current onlinesched_year is not our current year, skip event
	if ( $year != get_option( 'onlinesched_year' ) ) {
		continue;
	}

	## Figure out Times
	$startTime = get_post_meta($postId, 'onlinesched_sorttime', true);
	$endTime = $startTime + (get_post_meta($postId, 'onlinesched_timelen', true)*60);

	$dst = new DateTime('@'.$startTime);
	$dst->setTimeZone(wp_timezone());
	$dst->setTimeZone(new DateTimeZone('UTC'));

	$det = new DateTime('@'.$endTime);
	$det->setTimeZone(wp_timezone());
	$det->setTimeZone(new DateTimeZone('UTC'));

	//	var_dump(array( $endTime, $det, $dnt));


	## If the limiting, skip any events clearly in the past
	if ($limit > 0 && $det < $dnt) {
		continue;
	}
	$limit--;


	$rooms = OnlineSched_terms_list2('os_room', $postId);

	$tags = OnlineSched_terms_list2('os_tag', $postId);
	$tagsArray   = array_map( 'trim', explode( ",", $tags ) );
	$eventCancelled  = in_array( "canceled", array_map( 'strtolower', $tagsArray ) ) ? true : false;
	if ($eventCancelled) {
		$rooms = "Canceled";
	} 

	$addAdultTag = in_array( "restricted", array_map( 'strtolower', $tagsArray ) ) ? " [Adult]" : "";

	$content = convert_html_to_text($item->post_content);

	if (strlen($content) > 250) {
	  $content = substr($content, 0, 250).'&#8230;';
	} else {
	  $content = $item->post_content;
	}

	$iCal->add('onlinesched-'.$postId,
		   $dst->format("m/d/Y H:i"),
		   $det->format("m/d/Y H:i"),
		   $rooms,
		   $item->post_title . $addAdultTag,
		   $content
	);
}

header('Content-type: text/calendar');
$filename_prefix = function_exists('onlinesched_get_ical_filename_prefix') ? onlinesched_get_ical_filename_prefix() : 'onlinesched';
header('Content-Disposition: attachment; filename="' . $filename_prefix . '-' . sanitize_title($slug) . '.ics"');
echo $iCal->display();
?>
