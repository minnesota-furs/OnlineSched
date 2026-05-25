<?php
require_once('../../../wp-load.php');

require_once('html2text/html2text.php');
require_once('lib/ical.php');
/**
 * Full Content Template
 *
   Template Name:  Panel Grid - iCal page
 *
 * @file           icalby.php
 * @package        OnlineSched
 * @author         Ben Lindstrom, Brian Mogged
 * @copyright      2014, 2016, 2018, 2020, 2021, 2022, 2023, 2024
 * @license        GPL-2.0-or-later
 * @version        Release: 2.0
 * @filesource     wp-content/plugins/OnlineSched/icalby.php
 * @link           http://codex.wordpress.org/Theme_Development#Pages_.28page.php.29
 * @since          available since Release 1.0
 */

/* Usage
room=<panels> room name, can be comma separated
room=all all rooms
tag=<tags> by tag, can be comma separated
tag=all all tags
limit = 2 limits to the newest 2.
textlen = <number> limits description length (default 250). If textlen is 0 or negative, shows full description.
*/

define('EOL', "\r\n");

function onlinesched_get_request_value(array $keys) {
	foreach ($keys as $key) {
		if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
			if (is_array($_REQUEST[$key])) {
				continue;
			}

			return sanitize_text_field(wp_unslash($_REQUEST[$key]));
		}
	}

	return '';
}

function onlinesched_get_request_slugs(array $keys) {
	$value = onlinesched_get_request_value($keys);
	if ($value === '') {
		return array();
	}

	return array_values(array_filter(array_map('sanitize_title', explode(',', $value))));
}

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
			     $cancelled) {
		$start = new DateTime('@' . absint($startTime));
		$utc = new DateTimeZone('UTC');
		$start->setTimezone($utc);

		$end = new DateTime('@' . absint($endTime));
		$end->setTimezone($utc);

		$this->output .= 'BEGIN:VEVENT' . EOL .
			onlinesched_ical_line('DTSTAMP', gmdate(ONLINESCHED_ICAL_DATE_FORMAT), false) .
		    onlinesched_ical_line('DTSTART', $start->format(ONLINESCHED_ICAL_DATE_FORMAT), false) .
		    onlinesched_ical_line('DTEND', $end->format(ONLINESCHED_ICAL_DATE_FORMAT), false) .
		    onlinesched_ical_line('SUMMARY', $title) .
		    onlinesched_ical_line('DESCRIPTION', $desc) .
		    onlinesched_ical_line('LOCATION', $location) .
			onlinesched_ical_line('CATEGORIES', $categories, false) .
			onlinesched_ical_line('STATUS', ($cancelled ? 'CANCELLED' : 'CONFIRMED'), false) .
		    onlinesched_ical_line('UID', $uid, false) .
		    'END:VEVENT' . EOL;
	}

	function display() {
		return onlinesched_ical_calendar_header() . $this->output . onlinesched_ical_calendar_footer();
	}
}

$filename='-all';
$args = array(
	'post_type' => 'os_event',
#	'orderby' => 'title',		## XX Think this is wrong
	'meta_key' => 'onlinesched_sorttime',
	'orderby' => 'meta_value',
	'order' => 'ASC',
	'nopaging' => true
);

$sanitized_slugs = onlinesched_get_request_slugs(array('room', 'rooms'));
if (!empty($sanitized_slugs)) {
	$clean_slug = implode(',', array_filter($sanitized_slugs));


	if (strtolower($clean_slug) !== 'all') {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'os_room',
				'field' => 'slug',
				'terms' => $sanitized_slugs,
			)
		);
		$filename = '-room-'. preg_replace('/[^a-z0-9_]/', '_', $clean_slug);
	}
}

$sanitized_slugs = onlinesched_get_request_slugs(array('tag', 'tags'));
if (!empty($sanitized_slugs)) {
	$clean_slug = implode(',', array_filter($sanitized_slugs));

	if (strtolower($clean_slug) !== 'all') {
		if (!isset($args['tax_query'])) {
			$args['tax_query'] = array();
		}
		$args['tax_query'][] =
			array(
				'taxonomy' => 'os_tag',
				'field' => 'slug',
				'terms' => $sanitized_slugs,
			);


		if ($filename == '-all') {
			$filename = '';
		}
		$filename .= '-tag-'. preg_replace('/[^a-z0-9_]/', '_', $clean_slug);
	}
}

$limit = -1;
if (isset($_REQUEST['limit']) && !is_array($_REQUEST['limit'])) {
	$limit = intval(wp_unslash($_REQUEST['limit']));
}
$loop = new WP_Query($args);
$postsArr = empty($loop->posts) ? array() : $loop->posts;

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
	$startTimeRaw = get_post_meta($postId, 'onlinesched_sorttime', true);
	if (!is_numeric($startTimeRaw)) {
		continue;
	}

	$durationRaw = get_post_meta($postId, 'onlinesched_timelen', true);
	$startTime = intval($startTimeRaw);
	$duration = (is_numeric($durationRaw) && intval($durationRaw) >= 0) ? intval($durationRaw) : 0;
	$endTime = $startTime + ($duration * 60);

	## If the limiting, skip any events clearly in the past
	$det = new DateTime('@'.$endTime);
	$det->setTimeZone(new DateTimeZone('UTC'));
	if ($limit > 0 && $det < $dnt) {
		continue;
	}
	$limit--;


	$rooms = OnlineSched_terms_list2('os_room', $postId);
	$rooms = html_entity_decode($rooms);

	$tags = OnlineSched_terms_list2('os_tag', $postId);
	$tagsArray   = array_map( 'trim', explode( ",", $tags ) );
	$eventCancelled = array_reduce($tagsArray, function($carry, $item) {
		$lowercaseItem = strtolower($item);
		return $carry || $lowercaseItem === 'cancelled' || $lowercaseItem === 'canceled';
	}, false);

	if ($eventCancelled) {
		$rooms = "Canceled";
	}

	$addAdultTag = in_array( "restricted", array_map( 'strtolower', $tagsArray ) ) ? " [Adult]" : "";

	$textlen = 250;
	if (isset($_REQUEST['textlen'])) {
		$textlen = intval($_REQUEST['textlen']);
		if ($textlen < 1) {
			$textlen = -1; // Show full description
		}
	}

	$content = convert_html_to_text($item->post_content);

	if ($textlen > 0 && strlen($content) > $textlen) {
		$content = substr($content, 0, $textlen).'&#8230;';
	} // If textlen is -1, show full description

	$iCal->add(onlinesched_ical_uid($postId),
		   $startTime,
		   $endTime,
		   $rooms,
		   html_entity_decode($item->post_title . $addAdultTag),
		   $content,
		   onlinesched_ical_categories($postId, 'os_tag'),
		$eventCancelled
	);
}

$filename_prefix = function_exists('onlinesched_get_ical_filename_prefix') ? onlinesched_get_ical_filename_prefix() : 'onlinesched';
onlinesched_ical_send_headers($filename_prefix . $filename . '.ics');
echo $iCal->display();
