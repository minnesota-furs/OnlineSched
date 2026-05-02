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
 * @package        FM-2023
 * @author         BL, BM, AL
 * @copyright      2016-current Furry Migration
 * @license        BSD 2-Clause
 * @version        Release: 4.0
 * @filesource     wp-content/plugins/OnlineSched/grid.php
 */

wp_enqueue_style('online-schedule-css', ONLINESCHED_PLUGIN_URL . "build/main.css", array(), filemtime(ONLINESCHED_PLUGIN_DIR . "build/main.css"));
onlinesched_add_color_inline_style('online-schedule-css');
wp_enqueue_script('online-schedule-js', ONLINESCHED_PLUGIN_URL . "build/bundle.js", array(), filemtime(ONLINESCHED_PLUGIN_DIR . 'build/bundle.js'));
wp_localize_script('online-schedule-js', 'OnlineSchedPublic', array(
    'nonce' => wp_create_nonce('onlinesched_public'),
    'saveFavoritesUrl' => ONLINESCHED_PLUGIN_URL . 'includes/save_favorites.php',
    'loginStateUrl' => ONLINESCHED_PLUGIN_URL . 'includes/login_state.php',
    'getFavoritesUrl' => ONLINESCHED_PLUGIN_URL . 'includes/get_favorites.php',
));


$theming_filename = $theming = "";
$badge_type_meta_cache = array();
$gmt_offset = floatval(get_option('gmt_offset'));
$onlinesched_year = get_option('onlinesched_year');
$essentials_tab_name = get_option('onlinesched_essentials_tab_name', 'Essentials');
$programming_tab_label = onlinesched_get_config('tab_programming_label', 'Programming');
$programming_mobile_label = onlinesched_get_config('tab_programming_mobile_label', 'Events');
$hours_tab_label = onlinesched_get_config('tab_hours_label', 'Hours');
$map_tab_label = onlinesched_get_config('tab_map_label', 'Map');
$programming_tab_label = $programming_tab_label !== '' ? $programming_tab_label : 'Programming';
$programming_mobile_label = $programming_mobile_label !== '' ? $programming_mobile_label : 'Events';
$hours_tab_label = $hours_tab_label !== '' ? $hours_tab_label : 'Hours';
$map_tab_label = $map_tab_label !== '' ? $map_tab_label : 'Map';
$sticky_offsets = onlinesched_get_sticky_offsets();

$cssClass = 'standard-schedule';
$filterLINKS = false;

global $post;
if (!isset($post) || !is_object($post)) {
    $post = get_post();
}

$is_kiosk_schedule = onlinesched_is_configured_page($post, 'kiosk', 'kiosk-schedule');
$is_live_schedule = onlinesched_is_configured_page($post, 'live', 'live');

wp_add_inline_script(
    'online-schedule-js',
    'window.OS_SCHEDULE_CONFIG = ' . wp_json_encode(array(
        'stickyOffsetDesktop' => $sticky_offsets['desktop'],
        'stickyOffsetMobile' => $sticky_offsets['mobile'],
        'stickyBreakpoint' => 991,
        'fixedTabsHeight' => 40,
        'isKiosk' => $is_kiosk_schedule,
        'isLive' => $is_live_schedule,
        'calendarName' => onlinesched_get_calendar_name(),
    )) . ';',
    'before'
);

if ($is_kiosk_schedule) {
    $theming = "schedule";
    $cssClass = 'kiosk-schedule';
    $filterLINKS = true;
    $theming_filename = 'header-schedule.php';
}

$liveStreaming = false;
if ($is_live_schedule) {
    $liveStreaming = true;
    $cssClass = 'live-schedule';
}

$masterTags = array();
$masterRooms = array();

if (!empty($theming)) {
    include ONLINESCHED_PLUGIN_DIR . 'templates/' . $theming_filename;
} else {
    get_header();
}

$start = microtime(true);
?>

    <?php onlinesched_get_template_part('login-modal'); ?>
    <div class="os-container">
        <div class="os-row">
            <div class="os-schedule-main">
                <h1><?php the_title(); ?></h1>
                <?php if (!empty($post->post_excerpt)) : ?><p
                        class="os-lead"><?php echo get_the_excerpt(); ?></p><?php endif; ?>
                <?php edit_post_link(__('Edit', 'onlinesched'), '<div class="edit-link">', '</div>'); ?>

                <?php if ($liveStreaming) {
                ?>
                <div class="os-row">
                    <div class="os-col-lg-6 schedule-live-left"><?php
                        the_content();
                        ?>
                    </div>
                    <div class="os-col-lg-6 schedule-live-right">
                        <?php
                        }
                        if (!$liveStreaming && $theming != "schedule") { ?>
                            <div style="text-align:right;width:100%;clear:both;margin-bottom:10px;">
                                <button id="login-modal-btn" class="os-btn os-btn--primary">
                                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login
                                </button>
                                <button id="logout-modal-btn" class="os-btn os-btn--danger" style="display:none;"
                                        onclick="openLogoutProvider(window.ONLINESCHED_USER.provider, event)">
                                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
                                </button>
                                <button id="info-modal-btn" class="os-btn os-btn--primary" style="margin-left:8px;" title="How favorites, login, and calendar work">
                                    <i class="fa fa-question-circle" aria-hidden="true"></i>
                                </button>
                            </div>
                        <?php } ?>
                        <?php do_action('os_before_schedule'); ?>
                        <div id="schedule"
                             style="display:none; --os-sticky-top-offset:<?php echo esc_attr($sticky_offsets['desktop']); ?>px; --os-sticky-mobile-top-offset:<?php echo esc_attr($sticky_offsets['mobile']); ?>px;"
                             class="<?php echo esc_attr($cssClass); ?>">
                            <?php onlinesched_get_template_part('schedule-tabs', compact('theming', 'programming_tab_label', 'programming_mobile_label', 'essentials_tab_name', 'hours_tab_label', 'map_tab_label')); ?>

                            <div class="os-tab-content">
                                <div role="tabpanel" class="os-tab-pane os-tab-pane--active" id="programming">
                                    <?php onlinesched_get_template_part('schedule-filters', compact('theming', 'liveStreaming')); ?>
                                    <?php

                                    $dayofweek = 'none';
                                    $hour = 'none';

                                    $args = array(
                                            'post_type' => 'os_event',
                                            'meta_key' => 'onlinesched_sorttime',
                                            'orderby' => array(
                                                    'onlinesched_sorttime' => 'ASC', // Order by meta_time first
                                                    'title' => 'ASC'       // Then order by title
                                            ),
                                            'order' => 'ASC', // Change to 'DESC' if you want descending order
                                            'nopaging' => true
                                    );

                                    if ($liveStreaming) {
                                        $args['tax_query']['relation'] = 'AND';
                                        $args['tax_query'][] =
                                                array(
                                                        'taxonomy' => 'os_tag',
                                                        'field' => 'slug',
                                                        'terms' => 'streaming',
                                                );
                                    }

                                    // THis allows us to sort more effectively so we don't need multiple loops
                                    $filtered_args = apply_filters('os_schedule_query_args', $args);
                                    if (is_array($filtered_args)) {
                                        $args = $filtered_args;
                                    } else {
                                        _doing_it_wrong('os_schedule_query_args', 'The os_schedule_query_args filter must return an array.', '1.0.0');
                                    }

                                    add_filter('posts_clauses', 'modify_wp_query_clauses', 10, 2);
                                    $loop = new WP_Query($args);
                                    // Bulk prefetch post meta for all queried posts
                                    update_meta_cache('post', wp_list_pluck($loop->posts, 'ID'));
                                    // remove the filter that does the sort just incase.
                                    remove_filter('posts_clauses', 'modify_wp_query_clauses', 10);
                                    
                                    // Prefetch options outside the loop
                                    $badge_types_display = get_option('onlinesched_badge_types_display', array());
                                    $badge_types_icons = get_option('onlinesched_badge_types_icons', array());
                                    $badge_types_colors = get_option('onlinesched_badge_types_colors', array());
                                    $badge_types_fg_colors = get_option('onlinesched_badge_types_fg_colors', array());
                                    $badge_types_row_colors = get_option('onlinesched_badge_types_row_colors', array());

                                    // Canonical badges fallback
                                    $canonical_badges = [
                                            'adult' => " <span class='os-badge os-badge--danger'>Adult</span>",
                                            'sensory' => " <span class='os-badge os-badge--sensory'>Sensory</span>",
                                            'vip' => " <span class='os-badge os-badge--vip'>VIP</span>",
                                            'essentials' => " <span class='os-badge os-badge--essentials'>Essentials</span>",
                                            'guest of honor' => " <span class='os-badge os-badge--goh'>Guest Of Honor</span>",
                                            'special guest' => " <span class='os-badge os-badge--specialguest'>Special Guest</span>",
                                            'streaming' => " <span class='os-badge os-badge--streaming'>Streaming</span>",
                                            'cancelled' => " <span class='os-badge os-badge--cancelled'>Cancelled</span>",
                                    ];

                                    if (!$loop->have_posts()){
                                    ?>
                                    <div class="schedule-day" data-schedule-num-day="1725580800"
                                         data-schedule-day="Friday, September 6"><h2>No date in past or future</h2>
                                        <div class="schedule-hour"><h3>Out of time</h3>
                                            <div id="onlineevt-17175"
                                                 class="os-row schedule-item schedule-room-main-stage schedule-tag-essential schedule-tag-streaming"
                                                 data-end-time="1725645600" data-schedule-tag0="0"
                                                 data-schedule-tag1="1"
                                                 data-schedule-room0="0">
                                                <div class="os-col-xs-12 schedule-title">Nothing happening. No valid
                                                    entries in past or future
                                                </div>
                                            </div>
                                            <?php
                                            }
                                            // Output buffering for event loop
                                            ob_start();
                                            while ($loop->have_posts()) : $loop->the_post();
                                                // Reset per-event variables to defaults
                                                $eventCancelled = false;
                                                $rooms = OnlineSched_terms_list('os_room', $masterRooms);
                                                $tags = OnlineSched_terms_list('os_tag', $masterTags);
                                                $tagsArray = array_map('trim', explode(",", $tags));
                                                $roomClassMarker = 'fa-map-marker';
                                                $panelists = OnlineSched_terms_list('os_panelist');
                                                $hideTime = '';

                                                // Use cached $onlinesched_year and $gmt_offset
                                                $year = get_post_meta(get_the_ID(), 'onlinesched_year', true);
                                                if ($year != $onlinesched_year) {
                                                    continue;
                                                }

                                                $sorttime = get_post_meta(get_the_ID(), 'onlinesched_sorttime', true);
                                                if ($sorttime <= 0) {
                                                    continue;
                                                }

                                                $tags_slugs = OnlineSched_terms_slug_array('os_tag');
                                                $tag_terms = wp_get_post_terms(get_the_ID(), 'os_tag');
                                                $badge_types_present = [];
                                                $row_highlight_color = '';
                                                
                                                foreach ($tag_terms as $term) {
                                                    if (!isset($badge_type_meta_cache[$term->term_id])) {
                                                        $meta_badge = get_term_meta($term->term_id, 'badge_type', true);
                                                        // Fallback to tag name if meta is empty, but only if that badge type actually exists
                                                        if (empty($meta_badge) && isset($badge_types_display[$term->name])) {
                                                            $meta_badge = $term->name;
                                                        }
                                                        $badge_type_meta_cache[$term->term_id] = $meta_badge;
                                                    }
                                                    $badge_type = $badge_type_meta_cache[$term->term_id];
                                                    
                                                    if ($badge_type) {
                                                        // Group by exact case badge type string
                                                        $badge_types_present[$badge_type][] = $term;
                                                        
                                                        if (isset($badge_types_row_colors[$badge_type]) && $badge_types_row_colors[$badge_type] && $row_highlight_color == '') {
                                                            $row_highlight_color = $badge_types_row_colors[$badge_type];
                                                        }
                                                    }
                                                }

                                                // Build classes for the schedule item row
                                                $addScheduleRoom = " schedule-room-" . str_replace(' ', '-', strtolower($rooms));
                                                $addScheduleTags = "";
                                                foreach ($tags_slugs as $slug) {
                                                    $addScheduleTags .= " schedule-tag-" . $slug;
                                                }
                                                
                                                // Keep legacy hardcoded classes for JS filters just in case
                                                $addVIPClass = ''; $addGOHClass = ''; $addSpecialGuestClass = ''; $addCanceledClass = '';
                                                foreach ($badge_types_present as $type => $terms) {
                                                    $lc_type = strtolower($type);
                                                    if ($lc_type === 'vip') $addVIPClass = ' vip';
                                                    if ($lc_type === 'guest of honor') $addGOHClass = ' goh';
                                                    if ($lc_type === 'special guest') $addSpecialGuestClass = ' specialguest';
                                                    if ($lc_type === 'cancelled' || $lc_type === 'canceled') $addCanceledClass = ' canceled';
                                                }

                                                // Essentials bold logic
                                                $tagsEssentialArray = $tagsArray;
                                                $setStrong = false;
                                                foreach ($badge_types_present as $type => $terms) {
                                                    if (strtolower($type) === 'essentials') {
                                                        foreach ($terms as $term) {
                                                            foreach ($tagsEssentialArray as &$tag) {
                                                                if (strtolower($tag) === strtolower($term->name)) {
                                                                    $tag = '<strong>' . $tag . '</strong>';
                                                                    $setStrong = true;
                                                                }
                                                            }
                                                            unset($tag);
                                                        }
                                                    }
                                                }
                                                if ($setStrong) {
                                                    $tags = implode(', ', $tagsEssentialArray);
                                                }

                                                // Cancelled logic overrides
                                                $eventCancelled = ($addCanceledClass !== '');
                                                if ($eventCancelled) {
                                                    $tagsArray = array('Cancelled');
                                                    $tags = 'Cancelled';
                                                    $rooms = '';
                                                    $roomClassMarker = '';
                                                    $panelists = 'None';
                                                    $hideTime = ' hide-cancelled';
                                                }

                                                $duration = intval(get_post_meta(get_the_ID(), 'onlinesched_timelen', true));
                                                $sorttime = intval($sorttime);
                                                $sortEndtime = $sorttime + ($duration * 60);
                                                $sortEndTimeGMT = $sortEndtime - (60 * 60 * $gmt_offset);

                                                // Pretty Hour
                                                $minutes = $duration % 60;
                                                $hours = ($duration - $minutes) / 60;
                                                $prettyDuration = "";
                                                if ($hours > 0) {
                                                    $prettyDuration = $hours . ' hr';
                                                    $prettyDuration .= ($hours > 1) ? "s" : "";
                                                }
                                                if ($minutes > 0) {
                                                    $prettyDuration .= empty($prettyDuration) ? "" : " ";
                                                    $prettyDuration .= $minutes . " min";
                                                }

                                                // Output Day Header
                                                if ($sorttime > 0) {
                                                    $newdayofweek = date('l, F j', $sorttime);
                                                    if ($dayofweek != $newdayofweek) {
                                                        if ($dayofweek != "none" && $dayofweek != "") {
                                                            echo "</div></div>";
                                                        }
                                                        $newTimestamp = strtotime(date("Y-m-d 00:00:00", $sorttime)); 
                                                        $dayofweek = $newdayofweek;
                                                        $hour = "none";
                                                        echo '<div class="schedule-day" data-schedule-num-day="' . $newTimestamp . '" data-schedule-day="' . $dayofweek . '"><h2>' . $dayofweek . '</h2>';
                                                    }
                                                } else {
                                                    if ($dayofweek == "none") {
                                                        $dayofweek = "Unscheduled";
                                                        echo '<div class="schedule-day" data-schedule-day="Unscheduled"><h2>Unscheduled</h2>';
                                                    }
                                                }

                                                // Output Hour Header
                                                $newhour = ($sorttime == 0) ? "Unscheduled" : date('g:i A', $sorttime);
                                                if ($hour != $newhour) {
                                                    if ($hour != 'none') {
                                                        echo "</div>";
                                                    }
                                                    $hour = $newhour;
                                                    echo '<div class="schedule-hour"><h3>' . esc_html($hour) . '</h3>';
                                                }

                                                $hourduration = ($sorttime != 0) ? $prettyDuration : "Unscheduled";

                                                onlinesched_get_template_part('schedule-event-row', compact(
                                                    'addCanceledClass',
                                                    'addGOHClass',
                                                    'addScheduleRoom',
                                                    'addScheduleTags',
                                                    'addSpecialGuestClass',
                                                    'addVIPClass',
                                                    'badge_types_colors',
                                                    'badge_types_display',
                                                    'badge_types_fg_colors',
                                                    'badge_types_icons',
                                                    'badge_types_present',
                                                    'canonical_badges',
                                                    'eventCancelled',
                                                    'filterLINKS',
                                                    'hideTime',
                                                    'hourduration',
                                                    'liveStreaming',
                                                    'panelists',
                                                    'roomClassMarker',
                                                    'rooms',
                                                    'row_highlight_color',
                                                    'sortEndTimeGMT',
                                                    'sorttime',
                                                    'tags',
                                                    'theming'
                                                ));
                                            endwhile;
                                            $html = ob_get_clean();
                                            echo $html;
                                            ?>
                                        </div>
                                    </div>
                                    <?php if ($theming != "schedule" && !$liveStreaming) {

                                        ?>
                                        <?php onlinesched_get_template_part('schedule-calendar-actions', compact('masterRooms', 'masterTags')); ?>
                                    <?php } ?>
                                </div><!-- end of tab -->

                                <div role="tabpanel" class="os-tab-pane" id="hours">
                                    <?php echo apply_filters('os_tab_hours_content', onlinesched_get_page_content('hours', 'schedule')); ?>
                                </div>

                                <!-- map for kiosk -->
                                <div role="tabpanel" class="os-tab-pane" id="map">
                                    <?php echo apply_filters('os_tab_map_content', onlinesched_get_page_content('map', 'kiosk-schedule')); ?>
                                </div>

                            </div><!-- end of tab container -->

                        </div>
                        <?php do_action('os_after_schedule'); ?>
                    </div>
                </div>
                <?php if ($liveStreaming) {
                ?></div>
        </div>
        <?php } ?>
    </div>
    <?php
    onlinesched_get_template_part('schedule-event-modal', compact('theming'));
    onlinesched_get_template_part('info-modal');
    onlinesched_get_template_part('android-google-calendar-modal');
    ?>
<?php
// Only use custom provider/cookie/session for login state
$social_config = require ONLINESCHED_PLUGIN_DIR . 'includes/social_providers_config.php';
$valid_providers = array_keys($social_config['providers']);
$provider = isset($_SESSION['provider']) ? $_SESSION['provider'] : '';
$identifier = isset($_COOKIE['onlinesched_identifier']) ? $_COOKIE['onlinesched_identifier'] : '';
$is_logged_in = in_array($provider, $valid_providers) && !empty($identifier);
?>
<?php

$end = microtime(true);
$creationtime = ($end - $start);
// printf("Page created in %.6f seconds.", $creationtime);
get_footer($theming);


// This modifies the query dynamically so we can sort on taxonomy making the results more efficent reducing the loops.
function modify_wp_query_clauses($clauses, $wp_query)
{
    global $wpdb;

    // Check if the query is the one you want to modify
    if (isset($wp_query->query_vars['meta_key']) && $wp_query->query_vars['meta_key'] === 'onlinesched_sorttime') {
        // Join the term relationships and taxonomy tables
        $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->posts}.ID = tr.object_id)";
        $clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
        $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS t ON (tt.term_id = t.term_id AND tt.taxonomy = 'os_room')";

        // Add the taxonomy term to the ORDER BY clause
        $clauses['orderby'] = "{$wpdb->postmeta}.meta_value ASC, t.name ASC, {$wpdb->posts}.post_title ASC";
    }

    return $clauses;
}

function decode_array_keys($array)
{
    $decoded_array = [];
    foreach ($array as $key => $value) {
        $decoded_key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded_array[$decoded_key] = $value;
    }

    return $decoded_array;
}
$essentials_tags = get_option('onlinesched_essentials_tags', array());
echo '<script>window.essentialsTags = ' . json_encode($essentials_tags) . ';</script>';
