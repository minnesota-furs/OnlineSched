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

wp_enqueue_style('online-schedule-css', plugin_dir_url(dirname(__FILE__)) . "build/main.css", array(), filemtime(plugin_dir_path(dirname(__FILE__)) . "build/main.css"));
wp_enqueue_script('online-schedule-js', plugin_dir_url(dirname(__FILE__)) . "build/bundle.js", array(), filemtime(plugin_dir_path(dirname(__FILE__)) . 'build/bundle.js'));
wp_localize_script('online-schedule-js', 'OnlineSchedPublic', array(
    'nonce' => wp_create_nonce('onlinesched_public'),
    'saveFavoritesUrl' => plugin_dir_url(dirname(__FILE__)) . 'includes/save_favorites.php',
    'loginStateUrl' => plugin_dir_url(dirname(__FILE__)) . 'includes/login_state.php',
    'getFavoritesUrl' => plugin_dir_url(dirname(__FILE__)) . 'includes/get_favorites.php',
));


$theming_filename = $theming = "";
$badge_type_meta_cache = array();
$gmt_offset = floatval(get_option('gmt_offset'));
$event_schedule_year = get_option('event_schedule_year');
$essentials_tab_name = get_option('onlinesched_essentials_tab_name', 'Essentials');

$cssClass = 'standard-schedule';
$filterLINKS = false;

global $post;
if (!isset($post) || !is_object($post)) {
    $post = get_post();
}
if ($post->post_name === "kiosk-schedule") {
    $theming = "schedule";
    $cssClass = 'kiosk-schedule';
    $filterLINKS = true;
    $theming_filename = 'header-schedule.php';
}

$liveStreaming = false;
if ($post->post_name == 'live') {
    $liveStreaming = true;
    $cssClass = 'live-schedule';
}

$masterTags = array();
$masterRooms = array();

if (!empty($theming)) {
    include plugin_dir_path(__FILE__) . $theming_filename;
} else {
    get_header();
}

$start = microtime(true);
?>

    <!-- Login Modal -->
    <dialog id="login-modal" class="os-modal login-modal" aria-modal="true" aria-label="Login">
        <div class="os-modal__header">
            <h3>Login</h3>
            <button type="button" class="os-close" id="login-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p>You can keep track of your schedule by logging in. If you choose not to log in, any events you favorite will be saved locally on your device.</p>
            <p>Login with your account:</p>
            <div class="login-provider-list">
            <?php
            $social_config = require dirname(__DIR__) . '/includes/social_providers_config.php';
            if (isset($social_config['providers']) && is_array($social_config['providers'])) {
                foreach ($social_config['providers'] as $provider => $providerData) {
                    $showProvider = false;
                    if (!empty($providerData['no_keys'])) {
                        $showProvider = !empty($providerData['enabled']);
                    } else if (isset($providerData['keys']) && is_array($providerData['keys'])) {
                        foreach ($providerData['keys'] as $key => $val) {
                            $option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
                            $option_val = get_option($option_name);
                            if (!empty($option_val)) {
                                $showProvider = true;
                                break;
                            }
                        }
                    }
                    if ($showProvider) {
                        $icon = '';
                        if (isset($providerData['use-favicon']) && !empty($providerData['use-favicon']['enabled'])) {
                            $favicon = !empty($providerData['use-favicon']['favicon']) ? $providerData['use-favicon']['favicon'] : 'fa-user';
                            $color = !empty($providerData['use-favicon']['color']) ? '#' . ltrim($providerData['use-favicon']['color'], '#') : '';
                            $icon = '<i class="fab ' . esc_attr($favicon) . '" style="color:' . esc_attr($color) . '; margin-right:8px;"></i>';
                        } else {
                            switch (strtolower($provider)) {
                                case 'facebook':
                                    $icon = '<i class="fab fa-facebook" style="color:#4267B2; margin-right:8px;"></i>';
                                    break;
                                case 'twitter':
                                    $icon = '<i class="fab fa-twitter" style="color:#1DA1F2; margin-right:8px;"></i>';
                                    break;
                                case 'discord':
                                    $icon = '<i class="fab fa-discord" style="color:#7289DA; margin-right:8px;"></i>';
                                    break;
                                default:
                                    $icon = '<i class="fas fa-user" style="margin-right:8px;"></i>';
                            }
                        }
                        echo '<div class="login-provider-item"><button onclick="openLoginWithProvider(\'' . esc_js($provider) . '\', event)" class="os-btn os-btn--default">' . $icon . 'Login with ' . esc_html($provider) . '</button></div>';
                    }
                }
            }
            ?>
            </div>
        </div>
    </dialog>
    <div class="os-container">
        <div class="os-row">
            <div class="os-schedule-main">
                <h1><?php the_title(); ?></h1>
                <?php if (!empty($post->post_excerpt)) : ?><p
                        class="os-lead"><?php echo get_the_excerpt(); ?></p><?php endif; ?>
                <?php edit_post_link(__('Edit', 'mnfm'), '<div class="edit-link">', '</div>'); ?>

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
                        <div id="schedule" style="display:none" class="<?php echo $cssClass; ?>">
                            <ul class="os-tabs schedule-tabs" role="tablist">
                                <li role="presentation" class="os-tabs__item os-tabs__item--active"><a href="#programming"
                                                                          aria-controls="programming"
                                                                          role="tab" data-os-tab="programming"
                                                                          data-os-pane="programming"
                                                                          onclick="setFilterEvents(true);"><span
                                                class="os-hide-mobile">Programming</span><span
                                                class="os-show-mobile">Events</span></a>
                                </li>
                                <li role="presentation" class="os-tabs__item"><a href="#essentials" aria-controls="programming" role="tab"
                                                           data-os-tab="essentials"
                                                           data-os-pane="programming"
                                                           onclick="setFilterEvents(false);"><?php echo esc_html($essentials_tab_name); ?></a>
                                </li>
                                <?php if ($theming != "schedule") { ?>
                                    <li role="presentation" class="os-tabs__item"><a href="#hours" aria-controls="hours" role="tab"
                                                                data-os-tab="hours"
                                                                id="hours-tab" onclick="scrollTopMenu()">Hours</a></li>
                                <?php } else { ?>
                                    <li role="presentation" class="os-tabs__item"><a href="#map" aria-controls="map" role="tab"
                                                                data-os-tab="map"
                                                                id="map-tab" onclick="scrollTopMenu()">Map</a></li>
                                    <?php
                                } ?>
                            </ul>

                            <div class="os-tab-content">
                                <div role="tabpanel" class="os-tab-pane os-tab-pane--active" id="programming">                                    <div class="schedule-sort os-well">
                                        <div class="os-row">
                                            <div class="os-col-sm-3">
                                                <div class="schedule-search">
                                                    <input class="os-form-control" type="text"
                                                           placeholder="Type to search..."
                                                           id="schedule-search-text" value=""
                                                           autocomplete='off' <?php if ($theming == 'schedule') {
                                                        echo " style='display:none;'";
                                                    } ?>>
                                                </div>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-tags">
                                                    <option selected value="all">All Tags</option>
                                                </select>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-days">
                                                    <option value="all">All Days</option>
                                                    <option selected value="Current">Now and Future</option>
                                                </select>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-rooms">
                                                    <option selected value="all">All Rooms</option>
                                                </select>
                                            </div>
                                            <?php if (!$liveStreaming && $theming != "schedule") { ?>
                                                <div class="os-col-sm-1 schedule-favorites-filter"
                                                     style="display: flex; align-items: center;">
                                                    <button class="os-btn os-btn--default os-btn--sm os-btn--block schedule-favorites-toggle"
                                                            id="schedule-favorites-toggle" title="Show Favorites Only"
                                                            aria-pressed="false"
                                                            style="display: flex; align-items: center; justify-content: center; height: 34px;">
                                                        <span class="favorite-label-mobile"
                                                              style="margin-right: 4px; display: none;">Favorite</span>
                                                        <i class="far fa-star" aria-hidden="true"
                                                           style="color: #f6c700;"></i>
                                                        <span class="os-sr-only">Show Favorites Only</span>
                                                    </button>
                                                </div>
                                            <?php } ?>
                                            <div class="os-col-sm-2 schedule-reset">
                                                <button class="os-btn os-btn--primary os-btn--sm os-btn--block" disabled
                                                        id="schedule-reset"><i
                                                            class="fa fa-refresh" aria-hidden="true"></i> Reset
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php

                                    $dayofweek = 'none';
                                    $hour = 'none';

                                    $args = array(
                                            'post_type' => 'event_schedule',
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
                                                        'taxonomy' => 'event_schedule_tags_type',
                                                        'field' => 'slug',
                                                        'terms' => 'streaming',
                                                );
                                    }

                                    // THis allows us to sort more effectively so we don't need multiple loops
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
                                                $rooms = OnlineSched_terms_list('event_schedule_room_type', $masterRooms);
                                                $tags = OnlineSched_terms_list('event_schedule_tags_type', $masterTags);
                                                $tagsArray = array_map('trim', explode(",", $tags));
                                                $roomClassMarker = 'fa-map-marker';
                                                $panelists = OnlineSched_terms_list('event_schedule_panelist_type');
                                                $hideTime = '';

                                                // Use cached $event_schedule_year and $gmt_offset
                                                $year = get_post_meta(get_the_ID(), 'onlinesched_year', true);
                                                if ($year != $event_schedule_year) {
                                                    continue;
                                                }

                                                $sorttime = get_post_meta(get_the_ID(), 'onlinesched_sorttime', true);
                                                if ($sorttime <= 0) {
                                                    continue;
                                                }

                                                $tags_slugs = OnlineSched_terms_slug_array('event_schedule_tags_type');
                                                $tag_terms = wp_get_post_terms(get_the_ID(), 'event_schedule_tags_type');
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

                                                // Build the row container
                                                $row_style = $row_highlight_color ? ' style="background-color: ' . esc_attr($row_highlight_color) . ';"' : '';
                                                echo '<div id="onlineevt-' . get_the_ID() . '" class="os-row schedule-item' . $addVIPClass . $addGOHClass . $addSpecialGuestClass . $addCanceledClass . $addScheduleRoom . $addScheduleTags . '" data-end-time="' . $sortEndTimeGMT . '"' . $row_style . '>';

                                                $hiddenLg = $liveStreaming ? ' os-hide-desktop' : '';
                                                $titleLg = $liveStreaming ? ' os-col-lg-7' : '';

                                                // Build badges using the exact case type
                                                $badgeSpans = '';
                                                foreach ($badge_types_present as $type => $terms) {
                                                    $type_lc = strtolower($type);
                                                    $show_badge = true;
                                                    if (isset($badge_types_display[$type])) {
                                                        $show_badge = $badge_types_display[$type];
                                                    }
                                                    $color = isset($badge_types_colors[$type]) ? $badge_types_colors[$type] : '';
                                                    $style = ($color !== '') ? "background-color: $color;" : '';
                                                    $fg_color = isset($badge_types_fg_colors[$type]) && $badge_types_fg_colors[$type] ? $badge_types_fg_colors[$type] : '';
                                                    $fg_style = ($fg_color !== '') ? "color: $fg_color;" : '';

                                                    if ($show_badge) {
                                                        if (!empty($badge_types_icons[$type])) {
                                                            $icon_class_raw = $badge_types_icons[$type];
                                                            $icon_class = esc_attr($icon_class_raw);
                                                            $label = esc_html(ucwords($type));
                                                            // If the icon class contains 'fa-', use it as-is. Otherwise, prepend 'fa-classic fa-'
                                                            if (strpos($icon_class_raw, 'fa-') !== false) {
                                                                $badgeSpans .= " <span class='os-badge os-badge--icon os-badge--" . sanitize_title_with_dashes($type) . "'" . ($style ? " style='" . esc_attr($style) . "'" : '') . "><i class='" . $icon_class . "'" . ($fg_style ? " style='" . esc_attr($fg_style) . "'" : '') . " aria-hidden='true'></i> <span class='os-sr-only'>" . $label . "</span></span>";
                                                            } else {
                                                                $badgeSpans .= " <span class='os-badge os-badge--icon os-badge--" . sanitize_title_with_dashes($type) . "'" . ($style ? " style='" . esc_attr($style) . "'" : '') . "><i class='fa-classic fa-" . $icon_class . "'" . ($fg_style ? " style='" . esc_attr($fg_style) . "'" : '') . " aria-hidden='true'></i> <span class='os-sr-only'>" . $label . "</span></span>";
                                                            }
                                                        } elseif (isset($canonical_badges[$type_lc])) {
                                                            $span_style = ($style || $fg_style) ? " style='" . esc_attr($style . $fg_style) . "'" : '';
                                                            $badgeSpans .= str_replace("'>", "'" . $span_style . ">", $canonical_badges[$type_lc]);
                                                        } else {
                                                            $class = 'os-badge--' . sanitize_title_with_dashes($type);
                                                            $label = esc_html(ucwords($type));
                                                            $span_style = ($style || $fg_style) ? " style='" . esc_attr($style . $fg_style) . "'" : '';
                                                            $badgeSpans .= " <span class='os-badge $class'$span_style>$label</span>";
                                                        }
                                                    }
                                                }

                                                echo '<div class="os-col-md-3 os-col-xs-9 schedule-title' . $titleLg . '"><a href="#" data-target="#modal-schedule">' . get_the_title(get_the_ID()) . '</a>' . $badgeSpans . '</div>';
                                                echo '<hr class="visible-sm">';
                                                $filterLinkClass = ($theming != 'schedule') ? ' schedule-filter-link' : '';
                                                echo '<dl class="os-col-md-2 os-col-sm-3' . $hiddenLg . '">';
                                                echo '<dt><i class="fa ' . $roomClassMarker . '" aria-hidden="true"></i></dt>';
                                                echo '<dd class="schedule-room' . $filterLinkClass . '">' . $rooms . '</dd>';
                                                echo '</dl>';
                                                echo '<dl class="os-col-md-2 os-col-sm-3' . $hideTime . '">';
                                                echo '<dt><i class="far fa-clock" aria-hidden="true"></i></dt>';
                                                echo '<dd class="schedule-time"><span class="os-sr-only">' . date('g:i A', $sorttime) . '</span>' . esc_html($hourduration) . '</dd>';
                                                echo '</dl>';
                                                echo '<dl class="os-col-md-2 os-col-sm-3' . $hiddenLg . ' os-hide-mobile">';
                                                if ($tags != 'None') {
                                                    echo '<dt><i class="fa fa-tags" aria-hidden="true"></i></dt>';
                                                    echo '<dd class="schedule-tags' . $filterLinkClass . '">' . $tags . '</dd>';
                                                }
                                                echo '</dl>';
                                                echo '<dl class="os-col-md-2 os-col-sm-3 os-hide-mobile">';
                                                if ($panelists != 'None') {
                                                    echo '<dt><i class="fa fa-user" aria-hidden="true"></i></dt>';
                                                    echo '<dd class="schedule-panelists">' . $panelists . '</dd>';
                                                }
                                                echo '</dl>';
                                                echo '<div class="schedule-calendar' . $hiddenLg . '">';
                                                if (!$eventCancelled) {
                                                    if ($theming != "schedule") {
                                                        // Star toggle button (favorite)
                                                        echo '<button class="schedule-favorite-toggle" title="Mark as favorite" data-event-id="' . get_the_ID() . '"><i class="far fa-star" aria-hidden="true"></i></button>';
                                                        $ical_base_url = plugin_dir_url(dirname(__FILE__));
                                                        $ical_base_url = preg_replace('/^https?:\/\//', '', $ical_base_url); // Remove http:// or https://
                                                        $ical_link = 'webcal://' . $ical_base_url . 'ical.php?cal-id=' . get_the_ID();
                                                        echo '<button title="copy to clipboard" class="schedule-clipboard"><i class="fas fa-copy" aria-hidden="true"></i></button>';
                                                        echo '<a href="' . $ical_link . '" title="Add to Apple Calendar" class="schedule-ical" target="_blank" onclick="return confirmCalendarAppleSubscription(this);"><i class="fab fa-apple" aria-hidden="true"></i></a>';
                                                        $googleLink = 'https://calendar.google.com/calendar/r?cid=' . urlencode($ical_link);
                                                        echo '<a href="' . $googleLink . '" title="Add to Google Calendar" class="schedule-google" target="_blank" onclick="return confirmCalendarGoogleSubscription(this);"><i class="fab fa-google" aria-hidden="true"></i></a>';
                                                    }
                                                }
                                                echo '</div>';
                                                if (!$eventCancelled) {

                                                    $eventDescription = get_the_content();
                                                    if ($filterLINKS) {
                                                        $eventDescription = strip_tags(preg_replace('/<a href="(.*)">/', '$1', $eventDescription));
                                                    }


                                                    echo '<div class="schedule-description">' . $eventDescription . '</div>';
                                                }

                                                echo '</div>';
                                            endwhile;
                                            $html = ob_get_clean();
                                            echo $html;
                                            ?>
                                        </div>
                                    </div>
                                    <?php if ($theming != "schedule" && !$liveStreaming) {

                                        ?>
                                        <script>
                                            var scheduleMasterRooms = <?php echo json_encode(decode_array_keys($masterRooms));?>;
                                            var scheduleMasterTags = <?php echo json_encode(decode_array_keys($masterTags));?>;
                                        </script>
                                        <div id="schedule-add-to-calendar">
                                            <div class="os-row" id="schedule-add-to-calendar-div">
                                                <div class="os-col-xs-12 os-col-md-7 schedule-add-to-calendar-blurb d-flex align-items-center">
                                                    Do you like what you see?<br/><span
                                                            id="schedule-add-to-calendar-message">Add this filtered list to your calendar!</span>
                                                </div>
                                                <div class="os-col-xs-12 os-col-md-5 schedule-add-to-calendar-buttons">
                                                    <button onclick="open_calendar_google()"
                                                            aria-label="Add subscription"><i class="fab fa-google"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add to Google
                                                    </button>
                                                    <button onclick="open_calendar_apple()"
                                                            aria-label="Add subscription"><i class="fab fa-apple"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add To Apple<br/>(WebCal)
                                                    </button>
                                                    <button onclick="open_calendar_outlook()"
                                                            aria-label="Add subscription"><i class="fas fa-calendar-alt"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add To Outlook
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="schedule-key">


                                            <div class="os-row">
                                                <div class="os-col-xs-12">
                                                    <h3>Key</h3>
                                                    <p>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fab fa-apple"
                                                           aria-label="Apple Symbol used to represent Apple's Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Webcal/ICS file and feeds that work with all iCal-compatible and
                                                        web calendars. If your browser isn't set up with application
                                                        shortcuts for webcal://, it will download an ICS file that can
                                                            be used by any calendar app.<br/>
                                                        </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fab fa-google"
                                                           aria-label="Google Symbol used to represent Google's Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Native support for adding event entries directly to Google
                                                            Calendar.<br/>
                                                            </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fas fa-calendar-alt"
                                                           aria-label="Calendar Symbol used to represent Outlook Web Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Native support for adding events to the Outlook web calendar
                                                            feed.<br/>
                                                        </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fas fa-copy"
                                                           aria-label="Copy symbol to represent copy to clipboard"
                                                           style="margin-right:10px;"></i>
                                                        Copies the specific programming event URL to the clipboard. It
                                                        can be used to copy and paste through social media, email, and
                                                            any other direct linking.</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="os-row">
                                                <div class="os-col-xs-12">
                                                    <h2>Important Notes About Calendar Feeds</h2>
                                                    <p>Please be aware that calendar feeds may not always reflect
                                                        real-time updates and are controlled by calendar client. The
                                                        website will always have the most up-to-date information. Update
                                                        frequencies can vary:</p>
                                                    <ul>
                                                        <li>Apple updates upon app/program startup and every 1-3 hours.
                                                            (I&rsquo;ve seen some default to as much as 1 week on my
                                                            Mac)
                                                        </li>
                                                        <li>Google normally updates every 24 hours.</li>
                                                        <li>Outlook updates upon app/program startup &amp; every 1-3
                                                            hours.
                                                        </li>
                                                        <li>Outlook.com updates every 3 hours.</li>
                                                        <li>Yahoo updates every 8-12 hours.</li>
                                                    </ul>
                                                    <p>Once the convention is over, please consider removing the feed
                                                        from your calendar.</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div><!-- end of tab -->

                                <div role="tabpanel" class="os-tab-pane" id="hours">


                                    <?php
                                    $hours = new WP_Query(array(
                                            'posts_per_page' => 1,
                                            'name' => 'schedule',
                                            'post_type' => 'page'
                                    ));

                                    if ($hours->have_posts()) {
                                        while ($hours->have_posts()) : $hours->the_post();

                                            echo apply_filters('the_content', get_the_content());

                                        endwhile;

                                        if (!empty($loop)) {
                                            $loop->reset_postdata();
                                        }
                                    }

                                    ?>

                                </div>

                                <!-- map for kiosk -->
                                <div role="tabpanel" class="os-tab-pane" id="map">
                                    <?php
                                    $hours = new WP_Query(array(
                                            'posts_per_page' => 1,
                                            'name' => 'kiosk-schedule',
                                            'post_type' => 'page'
                                    ));

                                    if ($hours->have_posts()) {
                                        while ($hours->have_posts()) : $hours->the_post();

                                            echo apply_filters('the_content', get_the_content());

                                        endwhile;

                                        if (!empty($loop)) {
                                            $loop->reset_postdata();
                                        }
                                    }

                                    ?>
                                </div>

                            </div><!-- end of tab container -->

                        </div>
                    </div>
                </div>
                <?php if ($liveStreaming) {
                ?></div>
        </div>
        <?php } ?>
    </div>
    <dialog id="modal-schedule" class="os-modal" aria-modal="true">
        <div class="os-modal__header">
            <h3 id="modal-schedule-title"></h3>
            <button type="button" class="os-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p id="modal-schedule-description">&nbsp;</p>
            <hr>
            <div class="os-row">
                <div class="os-col-sm-6">
                    <dl class="schedule-meta">
                        <dt><i class="fa fa-calendar" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-date">&nbsp;</dd>
                        <dt><i class="far fa-clock" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-time">&nbsp;</dd>
                        <dt><i class="fa fa-map-marker" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-room">&nbsp;</dd>
                    </dl>
                </div>
                <div class="os-col-sm-6">
                    <dl class="schedule-meta">
                        <dt><i class="fa fa-tags" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-tags">&nbsp;</dd>
                        <dt><i class="fa fa-user" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-panelists">&nbsp;</dd>
                    </dl>
                </div>
            </div>
        </div>
        <?php if ($theming != "schedule") { ?>
            <div class="os-modal__footer"><a href="#" class="os-btn os-btn--default" id="modal-schedule-ical"
                                             target="_blank"><i class="fab fa-apple" aria-hidden="true"></i> Apple
                    Calendar</a> <a href="#" class="os-btn os-btn--default" id="modal-schedule-google" target="_blank"><i
                            class="fab fa-google" aria-hidden="true"></i> Google Calendar</a>
                <button href="#" class="os-btn os-btn--default" id="modal-copy-url">
                    <i class="fas fa-copy" aria-hidden="true"></i> Copy
                </button>
            </div>
        <?php } ?>
    </dialog>
    <!-- Info Modal -->
    <dialog id="info-modal" class="os-modal info-modal" aria-modal="true">
        <div class="os-modal__header">
            <h3>How Favorites, Login, Calendar, and Sharing Work</h3>
            <button type="button" class="os-close" id="info-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <div style="font-size:1.1em;">
                <p><strong><i class="far fa-star" aria-hidden="true"></i> Favorites:</strong> Mark events as favorites to keep track of your schedule. If you're not logged in, your favorites are saved only on this device. If you log in, your favorites are saved to your account and sync across devices.</p>
                <p><strong><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login:</strong> Logging in lets you save your schedule and favorites to your account, so you can access them from any device. Your login info is only used to identify you, nothing more! And won't be kept past the convention.</p>
                <p><strong><i class="far fa-calendar-alt" aria-hidden="true"></i> Calendar:</strong> Add events or your whole schedule to your calendar (Google, Apple, Outlook). You can add individual events by tapping the calendar icons, or add everything at once from the bottom of the page. Calendar feeds update periodically, but may not always reflect real-time changes. For the latest info, check this website.</p>
                <p><strong><i class="fas fa-copy" aria-hidden="true"></i> Share:</strong> Want to share an event with a friend? Tap the copy icon to grab the event link and paste it anywhere like social media, email, chat, on side of your car, Rico's hand, or stiched on your fursuit. No tech wizardry required!</p>
            </div>
        </div>
    </dialog>

    <!-- Android Google Calendar Modal -->
    <dialog id="android-google-calendar-modal" class="os-modal android-gcal-options-four" aria-modal="true">
        <div class="os-modal__header">
            <h3>Google Calendar on Android</h3>
            <button type="button" class="os-close" id="android-google-calendar-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p><strong>Google Calendar on Android does not support direct calendar subscriptions via webcal/ics links.</strong></p>
            <div class="android-gcal-apology">
                <i class="fa fa-exclamation-triangle" aria-hidden="true" style="margin-right:6px;"></i>
                We apologize for the inconvenience.... Google Calendar on Android does not support direct calendar subscriptions. We hope Google or our team can improve this in the future!
            </div>
            <p class="android-gcal-options-text">You have these options below:</p>
            <ol class="android-gcal-options-list">
                <li class="android-gcal-onetime-section">
                    <span class="android-gcal-option-icon"><i class="fa fa-calendar-plus" aria-hidden="true"></i></span>
                    <strong>One Time Google Event:</strong>
                    <span class="android-gcal-onetime-desc">Create a single event in your Google Calendar for this session. This does not subscribe you to future updates, changes, or cancellations.</span>
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--default os-btn--block android-gcal-onetime-btn"><i class="fab fa-google"></i> <i class="fa fa-calendar"></i> One-Time Google Event</button>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fab fa-google" aria-hidden="true"></i></span>
                    <strong>Try the official Google Calendar link:</strong> This may not work on Android, but you can try. It's been spotty for 15+ years.
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--primary os-btn--block" id="android-gcal-try-link"><i class="fab fa-google"></i> Try Google Calendar (may not work)</button>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-download" aria-hidden="true"></i></span>
                    <strong>Download the calendar file (.ics):</strong> You can manually import this file into Google Calendar by double clicking it. Those will not sync from the web.
                    <div class="android-gcal-buttons">
                        <a class="os-btn os-btn--default os-btn--block" id="android-gcal-download" href="#" download>
                            <i class="fa fa-download"></i> Download calendar file (.ics)
                        </a>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-copy" aria-hidden="true"></i></span>
                    <strong>Copy the calendar subscription link:</strong> You can add this link manually in Google Calendar settings.
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--default os-btn--block" id="android-gcal-copy"><i class="fa fa-copy"></i> Copy calendar link</button>
                    </div>
                    <div id="android-gcal-copy-confirm" style="display:none;"><i class="fa fa-check"></i> Link copied!</div>
                    <div class="android-gcal-manual-instructions">
                        <strong>Manual Add Instructions:</strong>
                        <ol>
                            <li>Click the <b>Copy calendar link</b> button above.</li>
                            <li>Go to <a href="https://calendar.google.com" target="_blank" rel="noopener">Google Calendar</a> on a computer.</li>
                            <li>In the left sidebar, click the <b>+</b> next to <b>Other calendars</b> and choose <b>From URL</b>.</li>
                            <li>Paste the copied link and click <b>Add calendar</b>.</li>
                            <li>Your calendar will appear under "Other calendars" and update automatically.</li>
                        </ol>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-desktop" aria-hidden="true"></i></span>
                    <strong>Subscribe on a computer:</strong> Google recommends this, <a href="https://support.google.com/calendar/answer/37118?hl=en&co=GENIE.Platform%3DAndroid&oco=1" target="_blank" rel="noopener">Seriously</a>. If you are on the computer and you hit the icon and it will subscribe to Google calendar.
                </li>
            </ol>
        </div>
    </dialog>
<?php
// Only use custom provider/cookie/session for login state
$social_config = require dirname(__DIR__) . '/includes/social_providers_config.php';
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
        $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS t ON (tt.term_id = t.term_id AND tt.taxonomy = 'event_schedule_room_type')";

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
