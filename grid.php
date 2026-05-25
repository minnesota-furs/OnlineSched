<?php

// Exit if accessed directly
if ( !defined('ABSPATH')) exit;

/**
 * Full Content Template
 *
 * Template Name:  Online Schedule
 *
 * @file           grid.php
 * @package        OnlineSched
 * @author         BL, BM, AL & Contributors
 * @copyright      2016-2026 Original Authors
 * @license        GPL-2.0-or-later
 * @version        Release: 1.0
 * @filesource     wp-content/plugins/OnlineSched/grid.php
 */

get_header();
?>

<div class="container pt30">
<h1>Online Schedule</h1>

<style>
.alignright	{ text-align: right; }
.aligntop	{ vertical-align: top; }
div.os_time 	{ color: white; font-weight: bold; text-align: center; font-size: 20px; background-color: #f1592a; }
div.os_dow 	{ color: black; font-weight: bold; text-align: center; font-size: 20px; }
div.os_room 	{ color: white; font-weight: bold; text-align: center; font-size: 20px; background-color: grey; }
div.os_title 	{ color: black; font-weight: bold; font-size: 14px; }
div.os_desc 	{ color: black; font-size: 12px; }
div.os_panelist { color: black; font-size: 12px; font-style: italic; }
span.os-term-item { display: inline; }
span.os-term-separator { white-space: nowrap; }
span.os_tag_label { color: black; font-size: 12px; font-style: bold; }
div.os_tag	 { color: black; font-size: 12px; font-style: italic; }
span.os_timelen_label { color: black; font-size: 12px; font-style: bold; }
div.os_timelen	 { color: black; font-size: 12px; font-style: italic; }
</style>

<?php
$args = array( 
	'post_type' => 'os_event',
	'meta_key' => 'onlinesched_sorttime',
	'orderby' => 'meta_value_num',
	'order' => 'ASC',
	'nopaging' => true
);
$loop = new WP_Query($args);

$dayofweek = 'none';
$hour = 'none';
while ( $loop->have_posts() ) : $loop->the_post();
	$rooms = OnlineSched_terms_list('os_room');
	$tags = OnlineSched_terms_list('os_tag');
	$panelists = OnlineSched_terms_list('os_panelist');
	$sorttime = get_post_meta(get_the_ID(), 'onlinesched_sorttime', true);
	$room = get_terms('os_room', array('search' => $rooms));
	$roomsort = 0;
	if (count($room) == 1) {
		$roomsort = $room[0]->description;
	}



	// Only show events who have a valid UNIX Timestamp.  Otherwise, they are unscheduled.
	if ($sorttime > 0) { 
		$newdayofweek = date('l', $sorttime);
		if ($dayofweek != $newdayofweek) {
			$dayofweek = $newdayofweek;
			$hour = "none";

			echo '<div class="os_dow">Events & Panels: ' . esc_html($dayofweek)  . '</div>';
		}

		$newhour = date('h:i A', $sorttime);
		if ($hour != $newhour) {
			$hour = $newhour;
			echo '<div class="os_time">'. esc_html($hour) . '</div>';
		}
		

		echo '<div class="os_room">' . $rooms . '</div>';
		echo '<div class="os_title">' . esc_html(get_the_title(get_the_ID())) . '</div>';
		echo '<div class="os_timelen"><span class="os_timelen_label">Length:</span> ' . esc_html(get_post_meta(get_the_ID(), 'onlinesched_timelen', true)) . ' Min(s)</div>';
		if ($tags != "None") {
			echo '<div class="os_tag"><span class="os_tag_label">Tag(s):</span> ' . $tags . '</div>';
		}
		if ($panelists != "None") {
			echo '<div class="os_panelist">' . $panelists . '</div>';
		}
		echo '<div class="os_desc">' . wp_kses_post(apply_filters('the_content', get_the_content())) . '</div>';
		echo '<br/>';
	}
endwhile;
?>
</tbody>
</table>

</div><!-- end of #content-full -->
<?php get_footer(); ?>
