<?php

// Exit if accessed directly
//if ( !defined('ABSPATH')) exit;

require_once('../../../wp-load.php');

/**
 * Full Content Template
 *
   Template Name:  Panel Grid - iCal page
 *
 * @file           grid2.php
 * @package        FM-2104 
 * @author         Ben Lindstrom
 * @copyright      2014 - Ben Lidnstrom , 2016 Brian Mogged
 * @license        license.txt
 * @version        Release: 2.0
 * @filesource     wp-content/themes/fm-2014/grid.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */


define('DATE_ICAL', 'Ymd\THis\Z');
date_default_timezone_set('America/Chicago');


//date_default_timezone_set('UTC');


function escapeString($string) {
return preg_replace('/([\,;])/','\\\$1', $string);
}

class iCalGen {

	private $output;
	public $prodid = "-//Furry Migration//Programing Grid 2017//EN";
	private $calname = "furrymigration2017";

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

		$this->output .= "BEGIN:VEVENT\r\n" .
"DTSTART:" . $start->format(DATE_ICAL) . "\r\n" .
"DTEND:" . $end->format(DATE_ICAL) . "\r\n"  .
"SUMMARY:" . escapeString($title) . "\r\n" .
"DESCRIPTION:" . escapeString($desc) . "\r\n" .
"LOCATION:" . escapeString($location) . "\r\n" .
"UID:$uid\r\n".
"END:VEVENT\r\n";
	}

	function display() {
		return "BEGIN:VCALENDAR\nVERSION:2.0\n".
	/* Disabled for one event */
			/* "X-WR-CALNAME:" . 
		    $this->calname . "\nX-WR-CALDESC:Event Calendar\n" . */
		    "CALSCALE:GREGORIAN\nMETHOD:PUBLISH\nPRODID:" . 
		    $this->prodid . "\nX-WR-TIMEZONE:GMT\n" . 
		    $this->output . "END:VCALENDAR\n";
	}
}

//$table = TablePress::$controller->model_table->load( 3 );

//$list = explode(",", $_GET['uuid']);

// XXX - No validation check on $_GET/$list
$id = intval($_REQUEST['cal-id']);
if ($id <= 0 ) {
	exit();
}

$_post = get_post($id);
if (empty($_post)) {
	exit();
}

$iCal = new iCalGen();
$startTime = get_post_meta($id, 'onlinesched_sorttime', true);
$endTime = $startTime + (get_post_meta($id, 'onlinesched_timelen', true)*60);
$rooms = OnlineSched_terms_list('event_schedule_room_type', $id);

$dst = new DateTime('@'.$startTime);
$dst->setTimeZone(new DateTimeZone(date_default_timezone_get()));
$dst->setTimeZone(new DateTimeZone('UTC'));

$det = new DateTime('@'.$endTime);
$det->setTimeZone(new DateTimeZone(date_default_timezone_get()));
$det->setTimeZone(new DateTimeZone('UTC'));

 print "ack $startTime $endTime x".date("m/d/Y H:i",$startTime)."x".date("c",$startTime)."x".$dst->format("c")."\r\n";

$iCal->add('cal-fm-'.$id,
	   $dst->format("m/d/Y H:i"),
	   $det->format("m/d/Y H:i"),
	   //	   date("m/d/Y H:i", $endTime),
		   $rooms,
		   $_post->post_title,
		   $_post->post_content
		  );

		  
/*$iCal->add()

foreach ($list as $item) {
	$found = 0;
	foreach ($table['data'] as $id => $db) {
		if ($id != 0) {
			if ($db[6] === $item) {
				$found = 1;
				break;
			}
		}
	}

	if ($found == 0) {
		break;	// Not found, skip
	}
	$iCal->add(
	    $item . "fmorg",
	    $db[0], 
	    date("m/d/Y H:i", strtotime($db[0])+ ($db[7] * 60)),   // Calculate Ending based on "Minutes"
	    $db[1],
	    $db[3],
	    $db[4]
	);
}
*/
header('Content-type: text/calendar');
header('Content-Disposition: attachment; filename="mnfm'.$id.'.ics"');
echo $iCal->display();
?>
