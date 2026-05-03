<?php

if (!defined('ABSPATH')) {
    exit;
}

function onlinesched_render_schedule($args = array()) {
    static $depth = 0;
    if ($depth > 0) {
        return '<div class="os-notice os-notice--recursion">'
            . esc_html__('Schedule cannot be embedded inside its own Hours tab content.', 'onlinesched')
            . '</div>';
    }
    $depth++;

    try {
        $defaults = array(
            'mode'          => 'standard', // 'standard', 'kiosk', 'live'
            'tabs'          => array('programming', 'essentials', 'hours', 'map'),
            'hours_page_id' => (int) get_option('onlinesched_hours_page_id', 0),
            'map_page_id'   => (int) get_option('onlinesched_map_page_id', 0),
            'tag'           => '',
            'room'          => '',
        );
        $args = wp_parse_args($args, $defaults);
        $args = apply_filters('os_render_schedule_args', $args);

        $theming = '';
        $cssClass = 'standard-schedule';
        $filterLINKS = false;
        $liveStreaming = false;

        if ($args['mode'] === 'kiosk') {
            $theming = 'schedule';
            $cssClass = 'kiosk-schedule';
            $filterLINKS = true;
        } elseif ($args['mode'] === 'live') {
            $liveStreaming = true;
            $cssClass = 'live-schedule';
        }

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

        // Ensure essentialsTags is output
        $essentials_tags = get_option('onlinesched_essentials_tags', array());
        $essentials_script = '<script>window.essentialsTags = ' . wp_json_encode($essentials_tags) . ';</script>';

        // Set up the schedule config object
        $schedule_config = '<script>window.OS_SCHEDULE_CONFIG = ' . wp_json_encode(array(
            'stickyOffsetDesktop' => $sticky_offsets['desktop'],
            'stickyOffsetMobile' => $sticky_offsets['mobile'],
            'stickyBreakpoint' => 991,
            'fixedTabsHeight' => 40,
            'isKiosk' => ($args['mode'] === 'kiosk'),
            'isLive' => $args['mode'] === 'live',
            'calendarName' => onlinesched_get_calendar_name(),
        )) . ';</script>';

        ob_start();

        echo $essentials_script;
        echo $schedule_config;

        onlinesched_get_template_part('login-modal');

        echo '<div class="os-container' . ($args['mode'] === 'kiosk' ? ' os-container--kiosk' : '') . '"><div class="os-row"><div class="os-schedule-main">';

        // If it's not the dedicated page, we might want to skip printing h1/content here
        // But for now we stick to the structure that was in page-schedule.php
        if (is_page() && !has_shortcode(get_post()->post_content, 'onlinesched_schedule')) {
            echo '<h1>' . get_the_title() . '</h1>';
            if (has_excerpt()) {
                echo '<p class="os-lead">' . get_the_excerpt() . '</p>';
            }
            edit_post_link(__('Edit', 'onlinesched'), '<div class="edit-link">', '</div>');
        }

        if ($liveStreaming) {
            echo '<div class="os-row"><div class="os-col-lg-6 schedule-live-left">';
            // In live mode, if it's the page, we output content
            if (is_page() && !has_shortcode(get_post()->post_content, 'onlinesched_schedule')) {
                echo apply_filters('the_content', get_post()->post_content);
            }
            echo '</div><div class="os-col-lg-6 schedule-live-right">';
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
        <?php }

        do_action('os_before_schedule'); ?>

        <div id="schedule"
             style="display:none; --os-sticky-top-offset:<?php echo esc_attr($sticky_offsets['desktop']); ?>px; --os-sticky-mobile-top-offset:<?php echo esc_attr($sticky_offsets['mobile']); ?>px;"
             class="<?php echo esc_attr($cssClass); ?>">
            
            <?php onlinesched_get_template_part('schedule-tabs', compact(
                'theming', 'programming_tab_label', 'programming_mobile_label',
                'essentials_tab_name', 'hours_tab_label', 'map_tab_label'
            )); ?>

            <div class="os-tab-content">
                <div role="tabpanel" class="os-tab-pane os-tab-pane--active" id="programming">
                    <?php onlinesched_get_template_part('schedule-filters', compact('theming', 'liveStreaming')); ?>
                    
                    <?php
                    $dayofweek = 'none';
                    $hour = 'none';

                    $query_args = array(
                        'post_type' => 'os_event',
                        'meta_key' => 'onlinesched_sorttime',
                        'orderby' => array(
                            'onlinesched_sorttime' => 'ASC',
                            'title' => 'ASC'
                        ),
                        'order' => 'ASC',
                        'nopaging' => true
                    );

                    if ($liveStreaming) {
                        $query_args['tax_query']['relation'] = 'AND';
                        $query_args['tax_query'][] = array(
                            'taxonomy' => 'os_tag',
                            'field' => 'slug',
                            'terms' => 'streaming',
                        );
                    }

                    if (!empty($args['tag'])) {
                        $query_args['tax_query'][] = array(
                            'taxonomy' => 'os_tag',
                            'field' => 'slug',
                            'terms' => $args['tag'],
                        );
                    }
                    if (!empty($args['room'])) {
                        $query_args['tax_query'][] = array(
                            'taxonomy' => 'os_room',
                            'field' => 'slug',
                            'terms' => $args['room'],
                        );
                    }

                    $filtered_args = apply_filters('os_schedule_query_args', $query_args);
                    if (is_array($filtered_args)) {
                        $query_args = $filtered_args;
                    } else {
                        _doing_it_wrong('os_schedule_query_args', 'The os_schedule_query_args filter must return an array.', '1.0.0');
                    }

                    add_filter('posts_clauses', 'onlinesched_modify_wp_query_clauses', 10, 2);
                    $loop = new WP_Query($query_args);
                    update_meta_cache('post', wp_list_pluck($loop->posts, 'ID'));
                    remove_filter('posts_clauses', 'onlinesched_modify_wp_query_clauses', 10);
                    
                    $badge_types_display = get_option('onlinesched_badge_types_display', array());
                    $badge_types_icons = get_option('onlinesched_badge_types_icons', array());
                    $badge_types_colors = get_option('onlinesched_badge_types_colors', array());
                    $badge_types_fg_colors = get_option('onlinesched_badge_types_fg_colors', array());
                    $badge_types_row_colors = get_option('onlinesched_badge_types_row_colors', array());

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

                    $masterTags = array();
                    $masterRooms = array();

                    if (!$loop->have_posts()){
                        ?>
                        <div class="schedule-day" data-schedule-num-day="1725580800" data-schedule-day="Friday, September 6">
                            <h2>No date in past or future</h2>
                            <div class="schedule-hour">
                                <h3>Out of time</h3>
                                <div class="os-row schedule-item schedule-room-main-stage schedule-tag-essential schedule-tag-streaming"
                                     data-end-time="1725645600" data-schedule-tag0="0" data-schedule-tag1="1" data-schedule-room0="0">
                                    <div class="os-col-xs-12 schedule-title">Nothing happening. No valid entries in past or future</div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }

                    while ($loop->have_posts()) : $loop->the_post();
                        $eventCancelled = false;
                        $rooms = OnlineSched_terms_list('os_room', $masterRooms);
                        $tags = OnlineSched_terms_list('os_tag', $masterTags);
                        $tagsArray = array_map('trim', explode(",", $tags));
                        $roomClassMarker = 'fa-map-marker';
                        $panelists = OnlineSched_terms_list('os_panelist');
                        $hideTime = '';

                        $year = get_post_meta(get_the_ID(), 'onlinesched_year', true);
                        if ($year != $onlinesched_year) continue;

                        $sorttime = get_post_meta(get_the_ID(), 'onlinesched_sorttime', true);
                        if ($sorttime <= 0) continue;

                        $tags_slugs = OnlineSched_terms_slug_array('os_tag');
                        $tag_terms = wp_get_post_terms(get_the_ID(), 'os_tag');
                        $badge_types_present = [];
                        $row_highlight_color = '';
                        
                        foreach ($tag_terms as $term) {
                            if (!isset($badge_type_meta_cache[$term->term_id])) {
                                $meta_badge = get_term_meta($term->term_id, 'badge_type', true);
                                if (empty($meta_badge) && isset($badge_types_display[$term->name])) {
                                    $meta_badge = $term->name;
                                }
                                $badge_type_meta_cache[$term->term_id] = $meta_badge;
                            }
                            $badge_type = $badge_type_meta_cache[$term->term_id];
                            
                            if ($badge_type) {
                                $badge_types_present[$badge_type][] = $term;
                                if (isset($badge_types_row_colors[$badge_type]) && $badge_types_row_colors[$badge_type] && $row_highlight_color == '') {
                                    $row_highlight_color = $badge_types_row_colors[$badge_type];
                                }
                            }
                        }

                        $addScheduleRoom = " schedule-room-" . str_replace(' ', '-', strtolower($rooms));
                        $addScheduleTags = "";
                        foreach ($tags_slugs as $slug) {
                            $addScheduleTags .= " schedule-tag-" . $slug;
                        }
                        
                        $addVIPClass = ''; $addGOHClass = ''; $addSpecialGuestClass = ''; $addCanceledClass = '';
                        foreach ($badge_types_present as $type => $terms) {
                            $lc_type = strtolower($type);
                            if ($lc_type === 'vip') $addVIPClass = ' vip';
                            if ($lc_type === 'guest of honor') $addGOHClass = ' goh';
                            if ($lc_type === 'special guest') $addSpecialGuestClass = ' specialguest';
                            if ($lc_type === 'cancelled' || $lc_type === 'canceled') $addCanceledClass = ' canceled';
                        }

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
                            'addCanceledClass', 'addGOHClass', 'addScheduleRoom', 'addScheduleTags',
                            'addSpecialGuestClass', 'addVIPClass', 'badge_types_colors', 'badge_types_display',
                            'badge_types_fg_colors', 'badge_types_icons', 'badge_types_present', 'canonical_badges',
                            'eventCancelled', 'filterLINKS', 'hideTime', 'hourduration', 'liveStreaming',
                            'panelists', 'roomClassMarker', 'rooms', 'row_highlight_color', 'sortEndTimeGMT',
                            'sorttime', 'tags', 'theming'
                        ));
                    endwhile;
                    wp_reset_postdata();
                    
                    if ($dayofweek != 'none') {
                        echo "</div></div>";
                    }
                    ?>

                <?php if ($theming != "schedule" && !$liveStreaming) { ?>
                    <?php onlinesched_get_template_part('schedule-calendar-actions', compact('masterRooms', 'masterTags')); ?>
                <?php } ?>
            </div>

            <?php if (in_array('hours', $args['tabs'])) { ?>
            <div role="tabpanel" class="os-tab-pane" id="hours">
                <?php
                if ($args['hours_page_id']) {
                    $hours_post = get_post($args['hours_page_id']);
                    if ($hours_post && $hours_post->post_status === 'publish') {
                        $hours_content = $hours_post->post_content;
                        // Defensive recursion guard for the content filter
                        $hours_content = preg_replace('/\[onlinesched_schedule[^\]]*\]/', '', $hours_content);
                        echo apply_filters('os_tab_hours_content', apply_filters('the_content', $hours_content));
                    }
                }
                ?>
            </div>
            <?php } ?>

            <?php if (in_array('map', $args['tabs'])) { ?>
            <div role="tabpanel" class="os-tab-pane" id="map">
                <?php
                if ($args['map_page_id']) {
                    $map_post = get_post($args['map_page_id']);
                    if ($map_post && $map_post->post_status === 'publish') {
                        $map_content = $map_post->post_content;
                        $map_content = preg_replace('/\[onlinesched_schedule[^\]]*\]/', '', $map_content);
                        echo apply_filters('os_tab_map_content', apply_filters('the_content', $map_content));
                    }
                }
                ?>
            </div>
            <?php } ?>

        </div>
        
        <?php do_action('os_after_schedule'); ?>

        <?php if ($liveStreaming) { echo '</div></div>'; } ?>

        </div></div></div></div>

        <?php
        onlinesched_get_template_part('schedule-event-modal', compact('theming'));
        onlinesched_get_template_part('info-modal');
        onlinesched_get_template_part('android-google-calendar-modal');

        return ob_get_clean();

    } finally {
        $depth--;
    }
}

function onlinesched_modify_wp_query_clauses($clauses, $wp_query) {
    global $wpdb;
    if (isset($wp_query->query_vars['meta_key']) && $wp_query->query_vars['meta_key'] === 'onlinesched_sorttime') {
        $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->posts}.ID = tr.object_id)";
        $clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
        $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS t ON (tt.term_id = t.term_id AND tt.taxonomy = 'os_room')";

        $room_priority = function_exists('onlinesched_get_room_sort_priority') ? onlinesched_get_room_sort_priority() : array();
        $room_order = '';
        if (!empty($room_priority)) {
            $quoted_rooms = array();
            foreach ($room_priority as $room_name) {
                $quoted_rooms[] = "'" . esc_sql($room_name) . "'";
            }
            $field_sql = 'FIELD(t.name, ' . implode(',', $quoted_rooms) . ')';
            $room_order = "({$field_sql} = 0) ASC, {$field_sql} ASC, ";
        }
        $clauses['orderby'] = "{$wpdb->postmeta}.meta_value ASC, {$room_order}t.name ASC, {$wpdb->posts}.post_title ASC";
    }
    return $clauses;
}

function decode_array_keys($array) {
    $decoded_array = [];
    foreach ($array as $key => $value) {
        $decoded_key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded_array[$decoded_key] = $value;
    }
    return $decoded_array;
}
