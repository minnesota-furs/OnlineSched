<?php

// Exit if accessed directly
//if ( !defined('ABSPATH')) exit;

require_once('../../../wp-load.php');
require_once('html2text/html2text.php');

/**
 * Full Content Template
 *
   Template Name:  Panel Grid - iCal page
 *
 * @file           ical.php
 * @package        OnlineSched
 * @author         BL, BM, AL & Contributors
 * @copyright      2016-2026 Original Authors
 * @license        GPL-2.0-or-later
 * @version        Release: 2.0
 * @filesource     wp-content/ical.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */


define('DATE_ICAL', 'Ymd\THis\Z');


function escapeString($string) {
return preg_replace('/([\,;])/','\\\$1', $string);
}

class iCalGen {

	private $output;
	public $prodid;

    public function __construct()
    {
        $this->prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';
    }


	// categories need to be pre-escaped otherwise could be double escpaed wrong
	function add($uid,
		     $startTime,
		     $endTime,
		     $location,
		     $title,
		     $desc,
			 $categories,
	         $cancelled = false) {
		$start = new DateTime();
		$start->setTimestamp(strtotime($startTime));
		$utc = new DateTimeZone('UTC');
		$start->setTimezone($utc);

		$end = new DateTime();
		$end->setTimestamp(strtotime($endTime));
		$utc = new DateTimeZone('UTC');
		$end->setTimezone($utc);

		$this->output .= "BEGIN:VEVENT\r\n" .
"DTSTAMP:" . gmdate(DATE_ICAL) . "\r\n" .
"DTSTART:" . $start->format(DATE_ICAL) . "\r\n" .
"DTEND:" . $end->format(DATE_ICAL) . "\r\n"  .
"SUMMARY:" . escapeString($title) . "\r\n" .
"DESCRIPTION:" . escapeString($desc) . "\r\n" .
"LOCATION:" . escapeString($location) . "\r\n" .
'CATEGORIES:'. $categories. "\r\n" .
"STATUS:" . ($cancelled ? 'CANCELLED' : 'CONFIRMED') . "\r\n" .
"SEQUENCE:0\r\n" .
"UID:$uid\r\n".
"END:VEVENT\r\n";
	}

	function display() {
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".
	/* Disabled for one event */
			/* "X-WR-CALNAME:" . 
		    $this->calname . "\nX-WR-CALDESC:Event Calendar\n" . */
		    "CALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nPRODID:" .
		    $this->prodid . "\r\nX-WR-TIMEZONE:GMT\r\n" .
		    $this->output . "END:VCALENDAR\r\n";
	}
}

$id = isset($_REQUEST['cal-id']) && !is_array($_REQUEST['cal-id'])
	? absint(wp_unslash($_REQUEST['cal-id']))
	: 0;
if ($id <= 0 ) {
	exit();
}

$_post = get_post($id);
if (empty($_post)) {
	exit();
}

$iCal = new iCalGen();
$startTime = get_post_meta($id, 'onlinesched_sorttime', true);
$duration = get_post_meta($id, 'onlinesched_timelen', true);
if (!is_numeric($duration) || $duration < 0) {
    $duration = 0;
}
$endTime = $startTime + ($duration * 60);

$rooms = OnlineSched_terms_list2('os_room', $_post->ID);
$rooms = html_entity_decode($rooms);

$dst = new DateTime('@'.$startTime);
$dst->setTimeZone(wp_timezone());
$dst->setTimeZone(new DateTimeZone('UTC'));

$det = new DateTime('@'.$endTime);
$det->setTimeZone(wp_timezone());
$det->setTimeZone(new DateTimeZone('UTC'));

$tags = OnlineSched_terms_list2('os_tag', $id);
$tagsArray   = array_map( 'trim', explode( ",", $tags ) );
$eventCancelled = array_reduce($tagsArray, function($carry, $item) {
	$lowercaseItem = strtolower($item);
	return $carry || $lowercaseItem === 'cancelled' || $lowercaseItem === 'canceled';
}, false);

if ($eventCancelled) {
	$rooms = "Canceled";
}

$content = convert_html_to_text($_post->post_content);

$iCal->add('onlinesched-'.$id,
	   $dst->format("m/d/Y H:i"),
	   $det->format("m/d/Y H:i"),
	   //	   date("m/d/Y H:i", $endTime),
		   $rooms,
	       html_entity_decode($_post->post_title),
		   $content,
		   getEscapedCategoriesForICal($id, 'os_tag'),
			$eventCancelled
		  );

header('Content-type: text/calendar');
$filename_prefix = function_exists('onlinesched_get_ical_filename_prefix') ? onlinesched_get_ical_filename_prefix() : 'onlinesched';
header('Content-Disposition: attachment; filename="' . $filename_prefix . '-' . $id . '.ics"');
echo $iCal->display();


function getEscapedCategoriesForICal($post_id, $tag = 'os_tag') {
	// Get the terms for the specified custom taxonomy
	$terms = wp_get_post_terms($post_id, $tag, array('fields' => 'names'));

	// Escape commas in each term name
	$escapedCategories = array_map(function($term) {
		preg_replace('/([\,;])/','\\\$1', $term);
		$term = html_entity_decode($term);
		return str_replace(',', '\,', $term);
	}, $terms);

	// Join categories into a single string separated by commas
	return implode(',', $escapedCategories);
}
