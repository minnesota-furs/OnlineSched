<?php
if (!defined('ABSPATH')) {
	exit;
}

$row_style = $row_highlight_color ? ' style="background-color: ' . esc_attr($row_highlight_color) . ';"' : '';
do_action('os_before_schedule_item', get_the_ID());
echo '<div id="onlineevt-' . get_the_ID() . '" class="os-row schedule-item' . $addVIPClass . $addGOHClass . $addSpecialGuestClass . $addCanceledClass . $addScheduleRoom . $addScheduleTags . '" data-os-event-id="' . esc_attr(get_the_ID()) . '" data-end-time="' . $sortEndTimeGMT . '"' . $addScheduleRoomData . $addScheduleTagsData . $row_style . '>';

$hiddenLg = $liveStreaming ? ' os-hide-desktop' : '';
$titleLg = $liveStreaming ? ' os-col-lg-7' : '';

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

$badgeSpans = apply_filters('os_event_badge_html', $badgeSpans, get_the_ID());

echo '<div class="os-col-md-3 os-col-xs-9 schedule-title' . $titleLg . '"><a href="#" data-target="#modal-schedule">' . esc_html(get_the_title(get_the_ID())) . '</a>' . $badgeSpans . '</div>';
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
echo '<dl class="os-col-md-2 os-col-sm-3 os-hide-mobile' . $hiddenLg . '">';
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
        $fav_icon_class = onlinesched_get_favorite_icon_classes(false);
        echo '<button class="schedule-favorite-toggle" title="Mark as favorite" data-os-event-id="' . esc_attr(get_the_ID()) . '"><i class="' . esc_attr($fav_icon_class) . '" aria-hidden="true"></i></button>';
        $ical_base_url = ONLINESCHED_PLUGIN_URL;
        $ical_base_url = preg_replace('/^https?:\/\//', '', $ical_base_url);
        $ical_link = 'webcal://' . $ical_base_url . 'ical.php?cal-id=' . get_the_ID();
        echo '<button title="copy to clipboard" class="schedule-clipboard"><i class="fas fa-copy" aria-hidden="true"></i></button>';
        echo '<a href="' . esc_url($ical_link) . '" title="Add to Apple Calendar" class="schedule-ical" target="_blank" onclick="return confirmCalendarAppleSubscription(this);"><i class="fab fa-apple" aria-hidden="true"></i></a>';
        $googleLink = 'https://calendar.google.com/calendar/r?cid=' . urlencode($ical_link);
        echo '<a href="' . esc_url($googleLink) . '" title="Add to Google Calendar" class="schedule-google" target="_blank" onclick="return confirmCalendarGoogleSubscription(this);"><i class="fab fa-google" aria-hidden="true"></i></a>';
    }
}
echo '</div>';
if (!$eventCancelled) {

    $eventDescription = apply_filters('os_event_description', get_the_content(), get_the_ID());
    if ($filterLINKS) {
        $eventDescription = strip_tags(preg_replace('/<a href="(.*)">/', '$1', $eventDescription));
    }


    echo '<div class="schedule-description">' . wp_kses_post($eventDescription) . '</div>';
}

echo '</div>';
do_action('os_after_schedule_item', get_the_ID());
