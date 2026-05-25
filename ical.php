<?php

// Exit if accessed directly
//if ( !defined('ABSPATH')) exit;

require_once('../../../wp-load.php');
require_once('lib/ical.php');

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


class iCalGen {

	private $output;
	public $prodid;

    public function __construct()
    {
        $this->prodid = function_exists('onlinesched_get_ical_prodid') ? onlinesched_get_ical_prodid() : '-//OnlineSched//Event Schedule//EN';
    }


	function add($uid,
		     $startTime,
		     $endTime,
		     $location,
		     $title,
		     $desc,
			 $categories,
	         $cancelled = false) {
		$start = new DateTime('@' . absint($startTime));
		$utc = new DateTimeZone('UTC');
		$start->setTimezone($utc);

		$end = new DateTime('@' . absint($endTime));
		$end->setTimezone($utc);

		$this->output .= "BEGIN:VEVENT\r\n" .
onlinesched_ical_line('DTSTAMP', gmdate(ONLINESCHED_ICAL_DATE_FORMAT), false) .
onlinesched_ical_line('DTSTART', $start->format(ONLINESCHED_ICAL_DATE_FORMAT), false) .
onlinesched_ical_line('DTEND', $end->format(ONLINESCHED_ICAL_DATE_FORMAT), false) .
onlinesched_ical_line('SUMMARY', $title) .
onlinesched_ical_line('DESCRIPTION', $desc) .
onlinesched_ical_line('LOCATION', $location) .
onlinesched_ical_line('CATEGORIES', $categories, false) .
onlinesched_ical_line('STATUS', ($cancelled ? 'CANCELLED' : 'CONFIRMED'), false) .
"SEQUENCE:0\r\n" .
onlinesched_ical_line('UID', $uid, false) .
"END:VEVENT\r\n";
	}

	function display() {
		return onlinesched_ical_calendar_header() . $this->output . onlinesched_ical_calendar_footer();
	}
}

function onlinesched_ical_send_response($body, $filename)
{
	onlinesched_ical_send_headers($filename);
	echo $body;
	exit;
}

$id = isset($_REQUEST['cal-id']) && !is_array($_REQUEST['cal-id'])
	? absint(wp_unslash($_REQUEST['cal-id']))
	: 0;

$_post = get_post($id);
$filename_prefix = function_exists('onlinesched_get_ical_filename_prefix') ? onlinesched_get_ical_filename_prefix() : 'onlinesched';
$filename = $filename_prefix . '-' . ($id > 0 ? $id : 'event') . '.ics';

if (
	$id <= 0 ||
	empty($_post) ||
	'os_event' !== $_post->post_type ||
	'publish' !== $_post->post_status
) {
	onlinesched_ical_send_response(onlinesched_ical_empty_calendar(), $filename);
}

$startTime = get_post_meta($id, 'onlinesched_sorttime', true);
if (!is_numeric($startTime)) {
	onlinesched_ical_send_response(onlinesched_ical_empty_calendar(), $filename);
}
$startTime = intval($startTime);

$duration = get_post_meta($id, 'onlinesched_timelen', true);
if (!is_numeric($duration) || intval($duration) < 0) {
    $duration = 0;
}
$duration = intval($duration);
$endTime = $startTime + ($duration * 60);

$iCal = new iCalGen();

$rooms = OnlineSched_terms_list2('os_room', $_post->ID);
$rooms = html_entity_decode($rooms);

$tags = OnlineSched_terms_list2('os_tag', $id);
$tagsArray   = array_map( 'trim', explode( ",", $tags ) );
$eventCancelled = array_reduce($tagsArray, function($carry, $item) {
	$lowercaseItem = strtolower($item);
	return $carry || $lowercaseItem === 'cancelled' || $lowercaseItem === 'canceled';
}, false);

if ($eventCancelled) {
	$rooms = "Canceled";
}

$content = onlinesched_ical_html_to_text($_post->post_content);

$iCal->add(onlinesched_ical_uid($id),
	   $startTime,
	   $endTime,
	   //	   date("m/d/Y H:i", $endTime),
		   $rooms,
	       html_entity_decode($_post->post_title),
		   $content,
		   onlinesched_ical_categories($id, 'os_tag'),
			$eventCancelled
		  );

onlinesched_ical_send_response($iCal->display(), $filename);
