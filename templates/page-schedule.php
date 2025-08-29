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
 * @version        Release: 3.1
 * @filesource     wp-content/plugins/OnlineSched/grid.php
 */

wp_enqueue_script('jquery');
wp_enqueue_style( 'online-schedule-css', plugin_dir_url(dirname(__FILE__))."build/main.css", array(),   filemtime(plugin_dir_path(dirname(__FILE__))."build/main.css"));
wp_enqueue_script( 'online-schedule-js', plugin_dir_url(dirname(__FILE__)) . "build/bundle.js", array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__)) . 'build/bundle.js'));


$theming_filename = $theming = "";
$badge_type_meta_cache = array();
$gmt_offset = floatval(get_option('gmt_offset'));
$event_schedule_year = get_option('event_schedule_year');

$cssClass = 'standard-schedule';
$filterLINKS = false;

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

if (!empty($theming)){
	include plugin_dir_path(__FILE__) . $theming_filename;
} else {
	get_header();
}

$start = microtime(true);
?>

    <!-- Login Modal -->
    <div id="login-modal" class="modal" tabindex="-1" role="dialog" style="display:none;">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" id="login-modal-close" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h3 class="modal-title">Login</h3>
          </div>
          <div class="modal-body">
            <p>Login with your account:</p>
            <?php
            $social_config = require dirname(__DIR__) . '/includes/social_providers_config.php';
            if (isset($social_config['providers']) && is_array($social_config['providers'])) {
                foreach ($social_config['providers'] as $provider => $providerData) {
                    $showProvider = false;
                    if (isset($providerData['keys']) && is_array($providerData['keys'])) {
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
                        echo '<button onclick="openLoginWithProvider(\'' . esc_js($provider) . '\', event)" class="btn btn-default">' . $icon . 'Login with ' . esc_html($provider) . '</button>';
                    }
                }
            }
            ?>
          </div>
        </div>
      </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <h1><?php the_title(); ?></h1>
				<?php if (!empty($post->post_excerpt)) : ?><p
                        class="lead"><?php echo get_the_excerpt(); ?></p><?php endif; ?>
				<?php edit_post_link(__('Edit', 'mnfm'), '<div class="edit-link">', '</div>'); ?>

				<?php if ($liveStreaming) {
				?>
                <div class="row">
                    <div class="col-lg-6 schedule-live-left"><?php
						the_content();
						?>
                    </div>
                    <div class="col-lg-6 schedule-live-right">
						<?php
						}
                        if (!$liveStreaming && $theming != "schedule") { ?>
                        <div style="text-align:right;width:100%;clear:both;margin-bottom:10px;"><button id="login-modal-btn" class="btn btn-primary">Login</button>
                            <button id="logout-modal-btn" class="btn btn-danger" style="display:none;" onclick="openLogoutProvider('Google', event)">Logout</button></div>
                        <?php } ?>
                        <div id="schedule" style="display:none" class="<?php echo $cssClass; ?>">
                            <ul class="nav nav-tabs schedule-tabs" role="tablist">
                                <li role="presentation" class="active"><a href="#programming"
                                                                          aria-controls="programming"
                                                                          role="tab" data-toggle="tab"
                                                                          onclick="setFilterEvents(true);"><span
                                                class="hidden-xs">Programming</span><span
                                                class="visible-xs">Events</span></a>
                                </li>
                                <li role="presentation"><a href="#programming" aria-controls="programming" role="tab"
                                                           data-toggle="tab" onclick="setFilterEvents(false);">Essentials</a>
                                </li>
								<?php if ($theming != "schedule") { ?>
                                    <li role="presentation"><a href="#hours" aria-controls="hours" role="tab"
                                                               data-toggle="tab"
                                                               id="hours-tab" onclick="scrollTopMenu()">Hours</a></li>
								<?php } else { ?>
                                    <li role="presentation"><a href="#map" aria-controls="map" role="tab"
                                                               data-toggle="tab"
                                                               id="map-tab" onclick="scrollTopMenu()">Map</a></li>
									<?php
								} ?>
                            </ul>

                            <div class="tab-content">
                                <div role="tabpanel" class="tab-pane active" id="programming">
                                    <div class="schedule-sort well">
                                        <div class="row">
                                            <div class="col-sm-3">
                                                <div class="schedule-search">
                                                    <input class="form-control" type="text"
                                                           placeholder="Type to search..."
                                                           id="schedule-search-text" value=""
                                                           autocomplete='off' <?php if ($theming == 'schedule') {
														echo " style='display:none;'";
													} ?>>
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <select class="form-control" id="schedule-select-tags">
                                                    <option selected value="all">All Tags</option>
													<?php /*                        <option>Fursuiting</option>
                        <option>Music &amp; Performance</option>
                        <option>Art</option>
                        <option>Writing</option>
                        <option>(etc)</option> */ ?>
                                                </select>
                                            </div>
                                            <div class="col-sm-2">
                                                <select class="form-control" id="schedule-select-days">
                                                    <option value="all">All Days</option>
                                                    <option selected value="Current">Now and Future</option>
													<?php /* <option>Thursday, September 8</option>
                        <option>Friday, September 9</option>
                        <option>Saturday, September 10</option>
                        <option>Sunday, September 11</option> */ ?>
                                                </select>
                                            </div>
                                            <div class="col-sm-2">
                                                <select class="form-control" id="schedule-select-rooms">
                                                    <option selected value="all">All Rooms</option>
													<?php /* <option>Great Lakes A</option>
                        <option>Great Lakes B</option>
                        <option>Great Lakes C</option>
                        <option>(etc)</option> */ ?>
                                                </select>
                                            </div>
                                           <?php if (!$liveStreaming && $theming != "schedule") { ?>
                                            <div class="col-sm-1 schedule-favorites-filter" style="display: flex; align-items: center;">
                                                <button class="btn btn-default btn-sm btn-block schedule-favorites-toggle" id="schedule-favorites-toggle" title="Show Favorites Only" aria-pressed="false" style="display: flex; align-items: center; justify-content: center;">
                                                    <span class="favorite-label-mobile visible-xs-inline" style="margin-right: 4px;">Favorite</span>
                                                    <i class="far fa-star" aria-hidden="true" style="color: #f6c700;"></i>
                                                    <span class="sr-only">Show Favorites Only</span>
                                                </button>
                                            </div>
                                            <?php } ?>
                                            <div class="col-sm-2 schedule-reset">
                                                <button class="btn btn-primary btn-sm btn-block" disabled
                                                        id="schedule-reset"><i
                                                            class="fa fa-refresh" aria-hidden="true"></i> Reset
                                                </button>
                                                <!-- remove 'disabled' attribute when search/filters have been activated -->
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
									// hard code no result

									if (!$loop->have_posts()){
									?>
                                    <div class="schedule-day" data-schedule-num-day="1725580800"
                                         data-schedule-day="Friday, September 6"><h2>No date in past or future</h2>
                                        <div class="schedule-hour"><h3>Out of time</h3>
                                            <div id="onlineevt-17175"
                                                 class="row schedule-item schedule-room-main-stage schedule-tag-essential schedule-tag-streaming"
                                                 data-end-time="1725645600" data-schedule-tag0="0"
                                                 data-schedule-tag1="1"
                                                 data-schedule-room0="0">
                                                <div class="col-xs-12 schedule-title">Nothing happening. No valid
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
												$tagsArray = array_map('trim', explode(",", OnlineSched_terms_list('event_schedule_tags_type', $masterTags)));
												$tags = OnlineSched_terms_list('event_schedule_tags_type', $masterTags);
												$rooms = OnlineSched_terms_list('event_schedule_room_type', $masterRooms);
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

												$rooms = OnlineSched_terms_list('event_schedule_room_type', $masterRooms);
												$tags = OnlineSched_terms_list('event_schedule_tags_type', $masterTags);

												$tags_slugs = OnlineSched_terms_slug_array('event_schedule_tags_type');

                                                // New logic using term meta for badge types
                                                $tag_terms = wp_get_post_terms(get_the_ID(), 'event_schedule_tags_type');
                                                $badge_types_present = [];
                                                foreach ($tag_terms as $term) {
                                                    $badge_type = get_term_meta($term->term_id, 'badge_type', true);
                                                    if ($badge_type) {
                                                        $badge_types_present[strtolower($badge_type)][] = $term;
                                                    }
                                                }

                                                // Use badge_types_present for output logic
                                                $addVIPClass = !empty($badge_types_present['vip']) ? " vip" : "";
                                                $addGOHClass = !empty($badge_types_present['guest of honor']) ? " goh" : "";
                                                $addSpecialGuestClass = !empty($badge_types_present['special guest']) ? " specialguest" : "";
                                                $addCanceledClass = !empty($badge_types_present['cancelled']) ? " canceled" : "";
                                                $addAdultTag = !empty($badge_types_present['adult']) ? " <span class='badge badge-danger'>Adult</span>" : "";
                                                $addSensorTag = !empty($badge_types_present['sensory']) ? " <span class='badge badge-sensory'>Sensory</span>" : "";

                                                // For output, keep the same logic for tags, but highlight Essentials tag if present
                                                $tagsEssentialArray = $tagsArray;
                                                $setStrong = false;
                                                foreach ($tag_terms as $term) {
                                                    $badge_type = get_term_meta($term->term_id, 'badge_type', true);
                                                    if (strtolower($badge_type) === 'essentials') {
                                                        foreach ($tagsEssentialArray as &$tag) {
                                                            if (strtolower($tag) === strtolower($term->name)) {
                                                                $tag = '<strong>' . $tag . '</strong>';
                                                                $setStrong = true;
                                                            }
                                                        }
                                                        unset($tag);
                                                    }
                                                }
                                                if ($setStrong) {
                                                    $tags = implode(', ', $tagsEssentialArray);
                                                }

                                                // Cancelled logic
                                                $eventCancelled = !empty($badge_types_present['cancelled']);
                                                if ($eventCancelled) {
                                                    $tagsArray = array('Cancelled');
                                                    $tags = 'Cancelled';
                                                    $rooms = '';
                                                    $roomClassMarker = '';
                                                    $panelists = 'None';
                                                    $hideTime = ' hide-cancelled';
                                                } else {
                                                    $roomClassMarker = 'fa-map-marker';
                                                    $hideTime = '';
                                                }

												$panelists = OnlineSched_terms_list('event_schedule_panelist_type');
												$duration = intval(get_post_meta(get_the_ID(), 'onlinesched_timelen', true));
												$sorttime = intval($sorttime);
												$sortEndtime = $sorttime + ($duration * 60);
												$sortEndTimeGMT = $sortEndtime - (60 * 60 * $gmt_offset);
												$googleStart = date('Ymd\THis\Z', $sorttime - (60 * 60 * $gmt_offset));
												$googleEnd = date('Ymd\THis\Z', $sortEndtime - (60 * 60 * $gmt_offset));

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


												// Only show events who have a valid UNIX Timestamp.  Otherwise, they are unscheduled.
												if ($sorttime > 0) {
													$newdayofweek = date('l, F j', $sorttime);
													if ($dayofweek != $newdayofweek) {

														if ($dayofweek != "none" && $dayofweek != "") {
															echo "</div></div>";
														}

														$newTimestamp = strtotime(date("Y-m-d 00:00:00", $sorttime)); // This is GMT unmodified
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
												$sorttime = intval($sorttime);

												$newhour = date('g:i A', $sorttime);
												if ($sorttime == 0) {
													$newhour = "Unscheduled";
													$hourduration = $newhour;
												}

												if ($hour != $newhour) {
													if ($hour != 'none') {
														echo "</div>";
													}

													$hour = $newhour;

													echo '<div class="schedule-hour"><h3>' . esc_html($hour) . '</h3>';

												}

												if ($sorttime != 0) {
													// $hourduration = date('g:i A', $sorttime).' - '.date('g:i A', $sortEndtime);
													$hourduration = $prettyDuration;
												}
												$lowerRooms = str_replace(' ', '-', strtolower($rooms));

												// Event was canncelled :(
												$eventCancelled = array_reduce($tagsArray, function ($carry, $item) {
													$lowercaseItem = strtolower($item);
													return $carry || $lowercaseItem === 'cancelled' || $lowercaseItem === 'canceled';
												}, false);

												$roomClassMarker = 'fa-map-marker';
												$hideTime = '';

												if ($eventCancelled) {
													$tagsArray = array('Cancelled');
													$tags = 'Cancelled';
													$rooms = '';
													$roomClassMarker = '';
													$panelists = 'None';
													$hideTime = ' hide-cancelled';
												}

												$addScheduleRoom = " schedule-room-" . $lowerRooms;

												$addScheduleTags = "";
												foreach ($tags_slugs as $slug) {
													$addScheduleTags .= " schedule-tag-" . $slug;
												}

												echo '<div id="onlineevt-' . get_the_ID() . '" class="row schedule-item' . $addVIPClass . $addGOHClass . $addSpecialGuestClass . $addCanceledClass . $addScheduleRoom . $addScheduleTags . '" data-end-time="' . $sortEndTimeGMT . '">';
												// remove  data-toggle="modal"
												$hiddenLg = '';
												$titleLg = '';

												if ($liveStreaming) {
													$hiddenLg = ' hidden-lg';
													$titleLg = ' col-lg-7';
												}

                                                // Done as col-xs-9 for the favorite

												$canonical_badges = [
    'adult' => " <span class='badge badge-danger'>Adult</span>",
    'sensory' => " <span class='badge badge-sensory'>Sensory</span>",
    'vip' => " <span class='badge badge-vip'>VIP</span>",
    'essentials' => " <span class='badge badge-essentials'>Essentials</span>",
    'guest of honor' => " <span class='badge badge-goh'>Guest Of Honor</span>",
    'special guest' => " <span class='badge badge-specialguest'>Special Guest</span>",
    'streaming' => " <span class='badge badge-streaming'>Streaming</span>",
    'cancelled' => " <span class='badge badge-cancelled'>Cancelled</span>",
];
$badge_types_display = get_option('onlinesched_badge_types_display', array());
// Build badge_types_present using original badge type string
$badge_types_present = array();
foreach ($tag_terms as $term) {
    if (!isset($badge_type_meta_cache[$term->term_id])) {
        $badge_type_meta_cache[$term->term_id] = get_term_meta($term->term_id, 'badge_type', true);
    }
    $badge_type = $badge_type_meta_cache[$term->term_id];
    if ($badge_type) {
        $badge_types_present[$badge_type][] = $term;
    }
}

                                                $badgeSpans = '';
foreach ($badge_types_present as $type => $terms) {
    $type_lc = strtolower($type);
    $show_badge = true;
    // Use the original badge type string for lookup
    if (isset($badge_types_display[$type])) {
        $show_badge = $badge_types_display[$type];
    }
    // Only show badge if display is enabled
    if ($show_badge) {
        if (isset($canonical_badges[$type_lc])) {
            $badgeSpans .= $canonical_badges[$type_lc];
        } else {
            $class = 'badge-' . sanitize_title_with_dashes($type);
            $label = esc_html(ucwords($type));
            $badgeSpans .= " <span class='badge $class'>$label</span>";
        }
    }
}

												echo '<div class="col-md-3 col-xs-9 schedule-title' . $titleLg . '"><a href="#" data-target="#modal-schedule" data-dismiss="modal">' . get_the_title(get_the_ID()) . '</a>' . $badgeSpans . '</div>';
												echo '<hr class="visible-sm">';
												echo '<dl class="col-md-2 col-sm-3' . $hiddenLg . '">';
												echo '<dt><i class="fa ' . $roomClassMarker . '" aria-hidden="true"></i></dt>';
												echo '<dd class="schedule-room">' . $rooms . '</dd>';
												echo '</dl>';
												echo '<dl class="col-md-2 col-sm-3' . $hideTime . '">';
												echo '<dt><i class="far fa-clock" aria-hidden="true"></i></dt>';
												echo '<dd class="schedule-time"><span class="sr-only">' . date('g:i A', $sorttime) . '</span>' . esc_html($hourduration) . '</dd>';
												echo '</dl>';
												echo '<dl class="col-md-2 col-sm-3' . $hiddenLg . ' hidden-xs">';
												if ($tags != 'None') {
													echo '<dt><i class="fa fa-tags" aria-hidden="true"></i></dt>';
													echo '<dd class="schedule-tags">' . $tags . '</dd>';
												}
												echo '</dl>';
												echo '<dl class="col-md-2 col-sm-3 hidden-xs">';
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
														echo '<a href="'.$ical_link.'" title="Add to Apple Calendar" class="schedule-ical" target="_blank" onclick="return confirmCalendarAppleSubscription(this);"><i class="fab fa-apple" aria-hidden="true"></i></a>';
														$googleLink  ='https://calendar.google.com/calendar/r?cid=' . urlencode($ical_link);
														echo '<a href="' . $googleLink . '" title="Add to Google Calendar" class="schedule-google" target="_blank" onclick="return confirmCalendarGoogleSubscription(this);"><i class="fab fa-google" aria-hidden="true"></i></a>';
													}
												}
												echo '</div>';
												if (!$eventCancelled) {

													$eventDescription = get_the_content();
													if ($filterLINKS) {
														$eventDescription = $message = strip_tags(preg_replace('/<a href="(.*)">/', '$1', $eventDescription));
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
                                            <div class="row" id="schedule-add-to-calendar-div">
                                                <div class="col-xs-12 col-md-7 schedule-add-to-calendar-blurb d-flex align-items-center">
                                                    Do you like what you see?<br/><span
                                                            id="schedule-add-to-calendar-message">Add this filtered list to your calendar!</span>
                                                </div>
                                                <div class="col-xs-12 col-md-5 schedule-add-to-calendar-buttons">
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


                                            <div class="row">
                                                <div class="col-xs-12">
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
                                                        Copies the specific programming event URL to the clipborad. It
                                                        can be used to copy and paste through social media, email, and
                                                            any other direct linking.</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    <h2>Important Notes About Calendar Feeds</h2>
                                                    <p>Please be aware that calendar feeds may not always reflect real-time updates and are controlled by calendar client. The website will always have the most up-to-date information. Update frequencies can vary:</p>
                                                    <ul>
                                                        <li>Apple updates upon app/program startup and every 1-3 hours. (I&rsquo;ve seen some default to as much as 1 week on my Mac)</li>
                                                        <li>Google normally updates every 24 hours.</li>
                                                        <li>Outlook updates upon app/program startup &amp; every 1-3 hours.</li>
                                                        <li>Outlook.com updates every 3 hours.</li>
                                                        <li>Yahoo updates every 8-12 hours.</li>
                                                    </ul>
                                                    <p>Once the convention is over, please consider removing the feed from your calendar.</p>
                                                </div>
                                            </div>
                                        </div>
									<?php } ?>
                                </div><!-- end of tab -->

                                <div role="tabpanel" class="tab-pane" id="hours">


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
                                <div role="tabpanel" class="tab-pane" id="map">
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
    <div class="modal fade" tabindex="-1" role="dialog" id="modal-schedule">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    <h3 class="modal-title" id="modal-schedule-title"></h3>
                </div>
                <div class="modal-body">
                    <p id="modal-schedule-description">&nbsp;</p>
                    <hr>
                    <div class="row">
                        <div class="col-sm-6">
                            <dl class="schedule-meta">
                                <dt><i class="fa fa-calendar" aria-hidden="true"></i></dt>
                                <dd id="modal-schedule-date">&nbsp;</dd>
                                <dt><i class="far fa-clock" aria-hidden="true"></i></dt>
                                <dd id="modal-schedule-time">&nbsp;</dd>
                                <dt><i class="fa fa-map-marker" aria-hidden="true"></i></dt>
                                <dd id="modal-schedule-room">&nbsp;</dd>
                            </dl>
                        </div>
                        <div class="col-sm-6">
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

                    <div class="modal-footer"><a href="#" class="btn btn-default" id="modal-schedule-ical"
                                                 target="_blank"><i class="fab fa-apple" aria-hidden="true"></i> Apple
                            Calendar</a> <a href="#" class="btn btn-default" id="modal-schedule-google" target="_blank"><i
                                    class="fab fa-google" aria-hidden="true"></i> Google Calendar</a>
                        <button href="#" class="btn btn-default" id="modal-copy-url">
                            <i class="fas fa-copy" aria-hidden="true"></i> Copy
                        </button>
                    </div>
				<?php } ?>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->
	<script>
function openLoginWithProvider(provider, event) {
    if (event) event.preventDefault();
    var rand = (window.crypto && window.crypto.getRandomValues) ?
        Array.from(window.crypto.getRandomValues(new Uint32Array(2)), x => x.toString(16)).join('') :
        Math.random().toString(36).substring(2) + Date.now();
    var url = '/wp-content/plugins/OnlineSched/includes/login.php?provider=' + encodeURIComponent(provider) + '&cachebreak=' + rand;
    var w = 500, h = 600;
    var left = (screen.width/2)-(w/2), top = (screen.height/2)-(h/2);
    var win = window.open(url, 'onlinesched_login', 'width='+w+',height='+h+',top='+top+',left='+left+',resizable,scrollbars');
    if (!win) {
        alert('Popup blocked! Please allow popups for this site to log in.');
    }
    return false;
}

function openLogoutProvider(provider, event) {
    if (event) event.preventDefault();
    var rand = (window.crypto && window.crypto.getRandomValues) ?
        Array.from(window.crypto.getRandomValues(new Uint32Array(2)), x => x.toString(16)).join('') :
        Math.random().toString(36).substring(2) + Date.now();
    var url = '/wp-content/plugins/OnlineSched/includes/login.php?logout=' + encodeURIComponent(provider) + '&cachebreak=' + rand;
    var w = 500, h = 400;
    var left = (screen.width/2)-(w/2), top = (screen.height/2)-(h/2);
    var win = window.open(url, 'onlinesched_logout', 'width='+w+',height='+h+',top='+top+',left='+left+',resizable,scrollbars');
    if (!win) {
        alert('Popup blocked! Please allow popups for this site to log out.');
        // Clear login state if popup blocked
        if (window.ONLINESCHED_USER) {
            window.ONLINESCHED_USER.loggedIn = false;
            window.ONLINESCHED_USER.provider = '';
            window.ONLINESCHED_USER.identifier = '';
        }
        if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
        window.location.reload();
    } else {
        // Poll for window close, then reload
        var pollTimer = window.setInterval(function() {
            if (win.closed) {
                window.clearInterval(pollTimer);
                // Clear login state after logout popup closes
                if (window.ONLINESCHED_USER) {
                    window.ONLINESCHED_USER.loggedIn = false;
                    window.ONLINESCHED_USER.provider = '';
                    window.ONLINESCHED_USER.identifier = '';
                }
                if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
                window.location.reload();
            }
        }, 500);
    }
    return false;
}

// Show/hide login/logout buttons based on login state
function updateLoginLogoutUI() {
    var loggedIn = window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn;
    var loginBtn = document.getElementById('login-modal-btn');
    var logoutBtn = document.getElementById('logout-modal-btn');
    if (loginBtn) loginBtn.style.display = loggedIn ? 'none' : '';
    if (logoutBtn) logoutBtn.style.display = loggedIn ? '' : 'none';
}

// Fetch login state via AJAX and update UI
window.ONLINESCHED_USER = { loggedIn: false, provider: '', identifier: '' };
// Hide login/logout buttons until state is loaded
function hideLoginButtons() {
    var loginBtn = document.getElementById('login-modal-btn');
    var logoutBtn = document.getElementById('logout-modal-btn');
    if (loginBtn) loginBtn.style.display = 'none';
    if (logoutBtn) logoutBtn.style.display = 'none';
}
hideLoginButtons();

function fetchLoginStateAndInit() {
    fetch('/wp-content/plugins/OnlineSched/includes/login_state.php', { credentials: 'same-origin' })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            window.ONLINESCHED_USER = data;
            if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
            // If logged in, fetch favorites from DB and sync cookie/UI
            if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
                fetch('/wp-content/plugins/OnlineSched/includes/get_favorites.php?provider=' + encodeURIComponent(window.ONLINESCHED_USER.provider) + '&identifier=' + encodeURIComponent(window.ONLINESCHED_USER.identifier), { credentials: 'same-origin' })
                    .then(function(resp) { return resp.json(); })
                    .then(function(favData) {
                        if (favData.favorites) {
                            // Set cookie as raw JSON string, not encodeURIComponent
                            document.cookie = 'schedule_favorites=' + favData.favorites + ';path=/;max-age=' + (60*60*24*30);
                        }
                        // Always call restoreFavoritesFromCookie after updating the cookie
                        if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
                        if (window.scheduleFavorites) window.scheduleFavorites();
                    });
            } else {
                if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
                if (window.scheduleFavorites) window.scheduleFavorites();
            }
        });
}
document.addEventListener('DOMContentLoaded', fetchLoginStateAndInit);
</script>

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
