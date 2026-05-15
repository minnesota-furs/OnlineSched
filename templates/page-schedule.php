<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Full Content Template
 *
 * Template Name:  Online Schedule
 *
 * @file           page-schedule.php
 * @package        OnlineSched
 * @author         BL, BM, AL & Contributors
 * @copyright      2016-2026 Original Authors
 * @license        GPL-2.0-or-later
 * @version        Release: 4.0
 * @filesource     wp-content/plugins/OnlineSched/grid.php
 */

global $post;
if (!isset($post) || !is_object($post)) {
    $post = get_post();
}

$is_kiosk_schedule = onlinesched_is_configured_page($post, 'kiosk', 'kiosk-schedule');
$is_live_schedule = onlinesched_is_configured_page($post, 'live', 'live');

$mode = 'standard';
$theming_filename = '';
$theming = '';
$cssClass = 'standard-schedule';

if ($is_kiosk_schedule) {
    $mode = 'kiosk';
    $theming = "schedule";
    $theming_filename = 'header-schedule.php';
    $cssClass = 'kiosk-schedule';
} elseif ($is_live_schedule) {
    $mode = 'live';
    $cssClass = 'live-schedule';
}

add_action('wp_enqueue_scripts', 'onlinesched_enqueue_schedule_assets');

add_filter('body_class', function ($classes) use ($cssClass) {
    return array_merge($classes, array($cssClass));
});

if (!empty($theming)) {
    include ONLINESCHED_PLUGIN_DIR . 'templates/' . $theming_filename;
} else {
    get_header();
}

$start = microtime(true);

echo onlinesched_render_schedule(array(
    'mode' => $mode
));

$end = microtime(true);
$creationtime = ($end - $start);
// printf("Page created in %.6f seconds.", $creationtime);
get_footer($theming);
