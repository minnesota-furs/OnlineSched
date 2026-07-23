<?php
/**
 * Public app feed: sectioned, schema-versioned JSON.
 *
 * GET json.php?section=schedule   (default — bare json.php returns the schedule)
 * GET json.php?section=meta
 * GET json.php?section=hours
 * GET json.php?section=info[&page={slug}]
 *
 * Schedule filters: room/rooms, tag/tags, group (term slugs; 'all' accepted).
 * All responses send ETag/Last-Modified and honor If-None-Match with 304.
 *
 * 3.0.0 replaced the former signage-oriented output with this contract; see
 * README "JSON Feed" and CHANGELOG.
 *
 * @package OnlineSched
 */

require_once('../../../wp-load.php');

if (!function_exists('onlinesched_app_feed_send')) {
	// Plugin inactive: the feed has no data source.
	status_header(404);
	exit;
}

$section = isset($_REQUEST['section']) && !is_array($_REQUEST['section'])
	? sanitize_key(wp_unslash($_REQUEST['section']))
	: 'schedule';

// Body, ETag, Last-Modified, and embedded revision values all derive from one
// snapshot, verified unchanged across the build (see
// onlinesched_app_feed_build_consistent) so a mutation landing mid-request
// can never tear content apart from its revision metadata.
switch ($section) {
	case 'meta':
		list($payload, $revisions) = onlinesched_app_feed_build_consistent('onlinesched_app_feed_meta');
		onlinesched_app_feed_send($payload, 'meta', '', $revisions);
		break;

	case 'hours':
		list($payload, $revisions) = onlinesched_app_feed_build_consistent('onlinesched_app_feed_hours');
		onlinesched_app_feed_send($payload, 'hours', '', $revisions);
		break;

	case 'info':
		$slug = isset($_REQUEST['page']) && !is_array($_REQUEST['page'])
			? sanitize_title(wp_unslash($_REQUEST['page']))
			: '';
		list($payload, $revisions) = onlinesched_app_feed_build_consistent(
			static function ($revisions) use ($slug) {
				return onlinesched_app_feed_info($slug, $revisions);
			}
		);
		if (null === $payload) {
			status_header(404);
			wp_send_json(array(
				'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
				'error'          => 'unknown_info_page',
			));
		}
		onlinesched_app_feed_send($payload, 'info', $slug, $revisions);
		break;

	case 'schedule':
	default:
		$filters = onlinesched_app_feed_request_filters();
		// Resolved values that filters can change without an option write
		// (group definitions are already resolved inside $filters; the
		// publication flag can be filtered) belong in the ETag variant; the
		// payload hash in the ETag covers every remaining dependency.
		$variant = wp_json_encode($filters) . '|pub:' . (int) onlinesched_app_schedule_published();
		list($payload, $revisions) = onlinesched_app_feed_build_consistent(
			static function ($revisions) use ($filters) {
				return onlinesched_app_feed_schedule($filters, $revisions);
			}
		);
		onlinesched_app_feed_send($payload, 'schedule', $variant, $revisions);
		break;
}
