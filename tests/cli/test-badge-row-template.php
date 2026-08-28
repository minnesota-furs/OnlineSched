<?php
/**
 * An icon-configured badge renders its label visibly beside the icon.
 *
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/cli/test-badge-row-template.php \
 *     --path=/var/www/html --allow-root
 */

if (!defined('WP_CLI') || !WP_CLI) {
	echo "This test must run through WP-CLI.\n";
	exit(1);
}

$failures = 0;
$check = static function ($label, $ok, $detail = '') use (&$failures) {
	if ($ok) {
		WP_CLI::log('PASS: ' . $label);
		return;
	}
	$failures++;
	WP_CLI::warning("FAIL: $label" . ($detail !== '' ? "\n  $detail" : ''));
};

$post_id = wp_insert_post(array(
	'post_type'   => 'os_event',
	'post_title'  => 'AFT Badge Row Fixture',
	'post_status' => 'publish',
));
if (is_wp_error($post_id) || !$post_id) {
	WP_CLI::error('Could not create the fixture event.');
}

global $post;
$post = get_post($post_id);
setup_postdata($post);

// The locals the partial reads from its including scope.
$badge_types_present  = array('ASL' => array());
$badge_types_display  = array('ASL' => true);
$badge_types_colors   = array('ASL' => '#5e35b1');
$badge_types_fg_colors = array('ASL' => '#ffffff');
$badge_types_icons    = array('ASL' => 'fa-solid fa-hands-asl-interpreting');
$canonical_badges     = array();
$row_highlight_color  = '';
$addVIPClass = $addGOHClass = $addSpecialGuestClass = $addCanceledClass = '';
$addScheduleRoom = $addScheduleTags = $addScheduleRoomData = $addScheduleTagsData = '';
$sortEndTime = 0;
$sorttime = time();
$liveStreaming = false;
$theming = 'schedule';
$roomClassMarker = 'fa-map-marker';
$rooms = 'Main Stage';
$hourduration = '1 hour';
$hideTime = false;
$eventCancelled = false;
$eventDescription = '';
$panelists = '';
$popupExtra = '';
$tags = '';
$filterLINKS = '';
$fav_icon_class = '';
$ical_base_url = '';
$ical_link = '';
$googleLink = '';

ob_start();
include __DIR__ . '/../../templates/partials/schedule-event-row.php';
$html = ob_get_clean();

$badge = '';
if (preg_match("/<span class='os-badge os-badge--icon os-badge--asl'[^>]*>.*?<\\/span>/s", $html, $m)) {
	$badge = $m[0];
}
$check('the ASL badge renders', $badge !== '', 'no os-badge--asl span in output');
$check(
	'the icon carries the configured class and stays decorative',
	strpos($badge, 'fa-hands-asl-interpreting') !== false
		&& strpos($badge, "aria-hidden='true'") !== false,
	$badge
);
$stripped = trim(wp_strip_all_tags($badge));
$check('the label is visible text, not screen-reader only', $stripped === 'ASL', 'visible text: ' . var_export($stripped, true));
$check('nothing in the badge hides in os-sr-only', strpos($badge, 'os-sr-only') === false, $badge);

wp_reset_postdata();
wp_delete_post($post_id, true);

if ($failures > 0) {
	WP_CLI::error($failures . ' badge row template check(s) failed.');
}
WP_CLI::success('Badge row template checks passed.');
