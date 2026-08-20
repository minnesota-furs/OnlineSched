<?php
/**
 * App feed builders (json.php sections).
 *
 * Produces the sectioned, schema-versioned JSON feed consumed by the mobile
 * companion app and other structured clients: meta, schedule, hours, info.
 * See README "JSON Feed" for the public contract.
 *
 * @package OnlineSched
 */

if (!defined('ABSPATH')) {
	exit;
}

define('ONLINESCHED_APP_FEED_SCHEMA_VERSION', 1);

// ---------------------------------------------------------------------------
// Settings readers.
// ---------------------------------------------------------------------------

/**
 * Whether the schedule section is published to app clients.
 *
 * Independent of the calendar-subscription (ICS) toggle by design.
 *
 * @return bool
 */
function onlinesched_app_schedule_published() {
	$value = get_option('onlinesched_app_schedule_published', '1');
	$enabled = in_array($value, array(true, 1, '1', 'true', 'yes', 'on'), true);
	return (bool) apply_filters('os_app_schedule_published', $enabled);
}

/**
 * Ordered page IDs configured for the app info section.
 *
 * @return int[]
 */
function onlinesched_app_info_page_ids() {
	// The seam: a theme supplies the ordered ids, so the choosing UI can
	// live wherever the site keeps its own admin. No selection, no section.
	$ids = apply_filters('os_app_info_page_ids', array());
	return is_array($ids) ? array_values(array_map('absint', $ids)) : array();
}

/**
 * Operational convention window and public datetimes from settings.
 *
 * @return array{con_start:string, con_end:string, public_start:string, public_end:string, public_start_at:string, public_end_at:string}
 */
function onlinesched_app_con_dates() {
	$public_start = onlinesched_app_sanitize_local_datetime(get_option('onlinesched_public_date_start', ''));
	$public_end   = onlinesched_app_sanitize_local_datetime(get_option('onlinesched_public_date_end', ''));

	return array(
		'con_start'      => onlinesched_app_sanitize_date(get_option('onlinesched_con_start', '')),
		'con_end'        => onlinesched_app_sanitize_date(get_option('onlinesched_con_end', '')),
		'public_start'   => '' === $public_start ? '' : substr($public_start, 0, 10),
		'public_end'     => '' === $public_end ? '' : substr($public_end, 0, 10),
		'public_start_at' => onlinesched_app_local_datetime_iso8601($public_start),
		'public_end_at'   => onlinesched_app_local_datetime_iso8601($public_end),
	);
}

/**
 * Accept only real YYYY-MM-DD calendar dates; anything else becomes ''.
 *
 * @param mixed $value Raw option/POST value.
 * @return string
 */
function onlinesched_app_sanitize_date($value) {
	$value = trim((string) $value);
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
		return '';
	}
	if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	return $value;
}

/**
 * Accept a WordPress-local datetime. Legacy date-only values become midnight.
 *
 * @param mixed $value Raw option/POST value.
 * @return string
 */
function onlinesched_app_sanitize_local_datetime($value) {
	$value = trim((string) $value);
	$date  = onlinesched_app_sanitize_date($value);
	if ('' !== $date) {
		return $date . 'T00:00';
	}
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/', $value, $m)) {
		return '';
	}
	if (
		!checkdate((int) $m[2], (int) $m[3], (int) $m[1])
		|| (int) $m[4] > 23
		|| (int) $m[5] > 59
	) {
		return '';
	}

	$datetime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
	if (!$datetime || $datetime->format('Y-m-d\TH:i') !== $value) {
		return '';
	}

	return $value;
}

/**
 * Convert a WordPress-local datetime to an offset-bearing instant.
 *
 * @param mixed $value Sanitized or raw local datetime.
 * @return string
 */
function onlinesched_app_local_datetime_iso8601($value) {
	$value = onlinesched_app_sanitize_local_datetime($value);
	if ('' === $value) {
		return '';
	}

	$datetime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
	return $datetime ? $datetime->format('Y-m-d\TH:i:sP') : '';
}

// ---------------------------------------------------------------------------
// Durable event identity.
// ---------------------------------------------------------------------------

/**
 * Durable event UID for app-side identity (favorites, reminders).
 *
 * Imported events derive it from the exact year + external event id, so a
 * delete-year followed by reimport yields the same UID. Components are
 * rawurlencoded and joined with ':' (always percent-encoded inside a
 * component), so distinct ids can never collide ("A B" vs "A-B", ids that
 * differ only in punctuation, or ids containing the delimiter).
 *
 * Manually created events use a UUID persisted in post meta - assigned at
 * save time and by the versioned upgrade backfill, never here: this read
 * path performs zero writes. A manual event with no persisted UUID yet
 * (created before the upgrade ran, never re-saved, upgrade not yet run)
 * returns '' and the schedule builder OMITS it - the feed never serves a
 * provisional UID that a later backfill would change out from under stored
 * favorites/reminders. Fail closed, stay durable.
 *
 * @param int    $post_id Event post ID.
 * @param string $year    Event year (already read from meta by callers).
 * @return string Durable UID, or '' when none exists yet.
 */
function onlinesched_get_event_uid($post_id, $year) {
	$year_part = rawurlencode((string) $year);

	// A persisted UUID always wins, so the external id CSV export backfills
	// cannot change a uid clients already stored.
	$uuid = trim((string) get_post_meta($post_id, 'onlinesched_event_uid', true));
	if ('' !== $uuid) {
		$uid = $year_part . ':' . $uuid;
	} else {
		$external = trim((string) get_post_meta($post_id, 'onlinesched_external_event_id', true));
		$uid = ('' !== $external) ? $year_part . ':' . rawurlencode($external) : '';
	}

	return (string) apply_filters('os_event_uid', $uid, $post_id, $year);
}

/**
 * Assign the persisted UUID for a manually created event if missing.
 *
 * Called from save-time hooks and the versioned upgrade - the feed's read
 * path never writes.
 *
 * @param int $post_id Event post ID.
 */
function onlinesched_ensure_event_uid_meta($post_id) {
	$external = trim((string) get_post_meta($post_id, 'onlinesched_external_event_id', true));
	if ('' !== $external) {
		return;
	}
	$uuid = trim((string) get_post_meta($post_id, 'onlinesched_event_uid', true));
	if ('' === $uuid) {
		update_post_meta($post_id, 'onlinesched_event_uid', wp_generate_uuid4());
	}
}

// ---------------------------------------------------------------------------
// Section builders.
// ---------------------------------------------------------------------------

/**
 * Admin-selected Essentials configuration for app surfaces.
 *
 * @return array{label:string, tags:string[]}
 */
function onlinesched_app_feed_essentials() {
	$tags = get_option('onlinesched_essentials_tags', array());
	$tags = is_array($tags) ? array_map('sanitize_title', array_filter(array_map('strval', $tags))) : array();
	$tags = array_values(array_unique(array_filter($tags)));
	sort($tags);
	$label = trim((string) get_option('onlinesched_essentials_tab_name', ''));
	return array(
		'label' => '' === $label ? 'Essentials' : $label,
		'tags'  => $tags,
	);
}

/**
 * Meta section: the app handshake.
 *
 * @param array|null $revisions Revision snapshot for request coherence.
 * @return array
 */
function onlinesched_app_feed_meta($revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$dates = onlinesched_app_con_dates();

	$rev_numbers = array();
	$stamp_parts = array();
	foreach (onlinesched_feed_sections() as $section) {
		$rev_numbers[$section] = $revisions[$section]['rev'];
		$stamp_parts[] = $revisions[$section]['rev'];
	}

	// Established config rail (option -> constant -> filter), then site title,
	// then a neutral default - never blank.
	$con_name = function_exists('onlinesched_get_calendar_name')
		? trim((string) onlinesched_get_calendar_name())
		: trim((string) get_option('onlinesched_calendar_name', ''));
	if ('' === $con_name) {
		$con_name = trim((string) get_bloginfo('name'));
	}
	if ('' === $con_name) {
		$con_name = 'Convention Schedule';
	}

	return array(
		'schema_version'     => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
		'con_name'           => $con_name,
		'year'               => (string) get_option('onlinesched_year', ''),
		'timezone'           => wp_timezone_string(),
		// Composed from the supplied snapshot, never a live read - the body
		// must agree with the snapshot it was built from.
		'revisions'          => $rev_numbers,
		'change_stamp'       => implode('.', $stamp_parts),
		'con_start'          => $dates['con_start'],
		'con_end'            => $dates['con_end'],
		'public_dates'       => array(
			'start'    => $dates['public_start'],
			'end'      => $dates['public_end'],
			'start_at' => $dates['public_start_at'],
			'end_at'   => $dates['public_end_at'],
		),
		'schedule_published' => onlinesched_app_schedule_published(),
		// The website's admin-selected Essential tags and tab label, so app
		// surfaces agree with the schedule page instead of hardcoding a slug.
		'essentials'         => onlinesched_app_feed_essentials(),
		'sections'           => onlinesched_feed_sections(),
		// Public schedule page URL, empty when none is configured. The schedule
		// front end owns the '#evt={id}' hash clients append to it.
		'schedule_url'       => onlinesched_app_feed_schedule_url(),
		'info_pages'         => onlinesched_app_feed_info_index(),
		'resources'          => (object) onlinesched_app_feed_resources( $revisions ),
		// Endpoint map that themes and plugins extend through the filter. Cast to
		// an object so an empty map serializes as {} rather than [].
		'links'              => (object) apply_filters('onlinesched_app_feed_meta_links', array()),
		'social_links'       => (object) apply_filters('onlinesched_app_feed_meta_social_links', array()),
	);
}

/**
 * Resource manifest for one Meta snapshot.
 *
 * Site plugins may add independently published resources through
 * onlinesched_app_feed_meta_resources. Reading that filter must be bounded and
 * side-effect free.
 *
 * @param array $revisions Revision snapshot for request coherence.
 * @return array
 */
function onlinesched_app_feed_resources( $revisions ) {
	$base = ONLINESCHED_PLUGIN_URL . 'json.php';
	$resources = array(
		'schedule' => array(
			'url'            => add_query_arg(
				array(
					'section' => 'schedule',
					'rev'     => $revisions['schedule']['rev'],
				),
				$base
			),
			'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
			'enabled'        => true,
			'revision'       => (string) $revisions['schedule']['rev'],
		),
		'hours'    => array(
			'url'            => add_query_arg(
				array(
					'section' => 'hours',
					'rev'     => $revisions['hours']['rev'],
				),
				$base
			),
			'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
			'enabled'        => true,
			'revision'       => (string) $revisions['hours']['rev'],
		),
		'info'     => array(
			'url'            => add_query_arg(
				array(
					'section' => 'info',
					'rev'     => $revisions['info']['rev'],
				),
				$base
			),
			'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
			'enabled'        => true,
			'revision'       => (string) $revisions['info']['rev'],
		),
	);

	$resources = apply_filters(
		'onlinesched_app_feed_meta_resources',
		$resources,
		$revisions
	);
	if ( ! is_array( $resources ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $resources as $key => $resource ) {
		$name = sanitize_key( $key );
		if ( '' === $name || ! is_array( $resource ) ) {
			continue;
		}

		$enabled  = ! empty( $resource['enabled'] );
		$schema   = max( 1, (int) ( $resource['schema_version'] ?? 1 ) );
		$revision = is_scalar( $resource['revision'] ?? null )
			? trim( (string) $resource['revision'] )
			: '';
		$url = is_string( $resource['url'] ?? null )
			? esc_url_raw( $resource['url'], array( 'http', 'https' ) )
			: '';

		if ( $enabled && ( '' === $url || '' === $revision ) ) {
			continue;
		}

		$normalized[ $name ] = array(
			'url'            => $url,
			'schema_version' => $schema,
			'enabled'        => $enabled,
			'revision'       => $revision,
		);
	}
	ksort( $normalized );
	return $normalized;
}

/**
 * Permalink of the configured public schedule page, or ''.
 *
 * @return string
 */
function onlinesched_app_feed_schedule_url() {
	$page_id = function_exists('onlinesched_get_page_id')
		? (int) onlinesched_get_page_id('schedule', 'schedule')
		: (int) get_option('onlinesched_schedule_page_id', 0);
	if ($page_id <= 0) {
		return '';
	}
	$permalink = get_permalink($page_id);
	return is_string($permalink) ? $permalink : '';
}

/**
 * Schedule section: full active-year schedule, optionally filtered.
 *
 * @param array $filters Optional room/tag/group filters (term slugs).
 * @return array
 */
function onlinesched_app_feed_schedule(array $filters = array(), $revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$payload = array(
		'schema_version'     => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
		'generated'          => onlinesched_app_feed_generated($revisions['schedule']),
		'timezone'           => wp_timezone_string(),
		'year'               => (string) get_option('onlinesched_year', ''),
		'schedule_published' => onlinesched_app_schedule_published(),
		'rooms'              => array(),
		'tags'               => array(),
		'events'             => array(),
	);

	if (!$payload['schedule_published']) {
		return $payload;
	}

	$args = array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'meta_key'    => 'onlinesched_sorttime',
		'orderby'     => 'meta_value_num',
		'order'       => 'ASC',
		'nopaging'    => true,
	);

	$tax_query = onlinesched_app_feed_tax_query($filters);
	if (count($tax_query) > 1) {
		$args['tax_query'] = $tax_query;
	}

	$active_year = $payload['year'];
	$loop = new WP_Query($args);
	$posts = empty($loop->posts) ? array() : $loop->posts;

	foreach ($posts as $item) {
		$post_id = $item->ID;
		$year = get_post_meta($post_id, 'onlinesched_year', true);
		if ((string) $year !== $active_year) {
			continue;
		}

		$start_time = get_post_meta($post_id, 'onlinesched_sorttime', true);
		if (!is_numeric($start_time) || (int) $start_time <= 0) {
			// Unset/zero sorttime would otherwise export as a 1970 event.
			continue;
		}
		$start_time = (int) $start_time;

		$duration = get_post_meta($post_id, 'onlinesched_timelen', true);
		$duration = (is_numeric($duration) && (int) $duration >= 0) ? (int) $duration : 0;
		$end_time = $start_time + ($duration * 60);

		$event_uid = onlinesched_get_event_uid($post_id, $active_year);
		if ('' === $event_uid) {
			// No durable identity yet (pre-upgrade manual event): omit rather
			// than serve a UID that the backfill would later change.
			continue;
		}

		$rooms = onlinesched_app_feed_event_terms($post_id, 'os_room');
		$tags = onlinesched_app_feed_event_terms($post_id, 'os_tag');
		$panelists = onlinesched_app_feed_event_terms($post_id, 'os_panelist');

		$tag_names = array_map('strtolower', wp_list_pluck($tags, 'name'));
		$cancelled = in_array('canceled', $tag_names, true) || in_array('cancelled', $tag_names, true);
		$adult = in_array('restricted', $tag_names, true);

		$payload['events'][] = array(
			'event_uid'        => $event_uid,
			'wp_post_id'       => $post_id,
			'title'            => html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
			'start'            => wp_date('c', $start_time),
			'end'              => wp_date('c', $end_time),
			'rooms'            => wp_list_pluck($rooms, 'slug'),
			'tags'             => wp_list_pluck($tags, 'slug'),
			'panelists'        => wp_list_pluck($panelists, 'name'),
			'description_html' => wp_kses_post($item->post_content),
			'cancelled'        => $cancelled,
			'adult'            => $adult,
			'modified'         => get_post_modified_time('c', true, $item),
		);

		foreach ($rooms as $room) {
			$payload['rooms'][$room['slug']] = $room;
		}
		foreach ($tags as $tag) {
			$payload['tags'][$tag['slug']] = $tag;
		}
	}

	ksort($payload['rooms']);
	ksort($payload['tags']);
	$payload['rooms'] = array_values($payload['rooms']);
	$payload['tags'] = array_values($payload['tags']);

	return $payload;
}

/**
 * @param int    $post_id  Event post ID.
 * @param string $taxonomy Taxonomy name.
 * @return array<int, array{slug:string, name:string}>
 */
function onlinesched_app_feed_event_terms($post_id, $taxonomy) {
	$terms = get_the_terms($post_id, $taxonomy);
	if (!is_array($terms)) {
		return array();
	}

	$out = array();
	foreach ($terms as $term) {
		$out[] = array(
			'slug' => $term->slug,
			'name' => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
		);
	}

	return $out;
}

/**
 * One clock time in house style: bare hours lose ":00", noon and midnight are
 * named.
 *
 * @param string $clock HH:MM.
 * @return string
 */
function onlinesched_hours_format_clock($clock) {
	$clock = onlinesched_hours_clock($clock);
	if ('' === $clock) {
		return '';
	}
	list($hour, $minute) = array_map('intval', explode(':', $clock));
	if (0 === $minute && 12 === $hour) {
		return 'Noon';
	}
	if (0 === $minute && 0 === $hour) {
		return 'Midnight';
	}
	$suffix  = $hour < 12 ? 'am' : 'pm';
	$display = $hour % 12;
	$display = 0 === $display ? 12 : $display;
	return $display . (0 === $minute ? '' : sprintf(':%02d', $minute)) . $suffix;
}

/**
 * The displayed hours line for a structured entry, or '' when it has none.
 *
 * @param string $start   HH:MM.
 * @param string $end     HH:MM.
 * @param bool   $all_day Open around the clock.
 * @return string
 */
function onlinesched_hours_format_range($start, $end, $all_day = false) {
	if ($all_day) {
		return '24 hours';
	}
	$start = onlinesched_hours_clock($start);
	$end   = onlinesched_hours_clock($end);
	if ('' === $start || '' === $end) {
		return '';
	}
	// Same instant both ends is a full day, not a zero-length one.
	if ($start === $end) {
		return '24 hours';
	}
	return onlinesched_hours_format_clock($start) . ' - ' . onlinesched_hours_format_clock($end);
}

/**
 * A 24-hour clock string, or '' for anything that is not one.
 *
 * @param mixed $value Authored attribute.
 * @return string HH:MM or ''.
 */
function onlinesched_hours_clock($value) {
	$value = is_string($value) ? trim($value) : '';
	return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
}

/**
 * Normalize authored hours text for the app feed.
 *
 * @param mixed $value Authored attribute.
 * @return string
 */
function onlinesched_hours_text($value) {
	if (!is_string($value)) {
		return '';
	}
	return sanitize_text_field(
		html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_option('blog_charset'))
	);
}

/**
 * Hours section: lossless export of the configured Hours page blocks.
 *
 * Free-form values are preserved and never parsed. Open/close status uses only
 * the structured fields.
 *
 * @return array
 */
function onlinesched_app_feed_hours($revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$payload = array(
		'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
		'generated'      => onlinesched_app_feed_generated($revisions['hours']),
		'departments'    => array(),
	);

	$page_id = (int) get_option('onlinesched_hours_page_id', 0);
	if ($page_id <= 0) {
		return $payload;
	}

	$page = get_post($page_id);
	if (!$page || 'publish' !== $page->post_status) {
		return $payload;
	}

	$blocks = parse_blocks($page->post_content);
	$payload['departments'] = onlinesched_app_feed_collect_hours_departments($blocks);

	return $payload;
}

/**
 * Recursively collect hours-department blocks from parsed block trees.
 *
 * @param array $blocks parse_blocks() output.
 * @return array
 */
function onlinesched_app_feed_collect_hours_departments(array $blocks) {
	$departments = array();

	foreach ($blocks as $block) {
		if (!is_array($block)) {
			continue;
		}

		if ('onlinesched/hours-department' === ($block['blockName'] ?? '')) {
			$attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : array();
			$department = array(
				'name'     => onlinesched_hours_text($attrs['department'] ?? ''),
				'location' => onlinesched_hours_text($attrs['location'] ?? ''),
				'days'     => array(),
			);

			foreach (($block['innerBlocks'] ?? array()) as $day_block) {
				if ('onlinesched/hours-day' !== ($day_block['blockName'] ?? '')) {
					continue;
				}
				$day_attrs = is_array($day_block['attrs'] ?? null) ? $day_block['attrs'] : array();
				$day = array(
					'day'     => onlinesched_hours_text($day_attrs['day'] ?? 'Friday'),
					'entries' => array(),
				);

				foreach (($day_block['innerBlocks'] ?? array()) as $time_block) {
					if ('onlinesched/hours-time' !== ($time_block['blockName'] ?? '')) {
						continue;
					}
					$time_attrs = is_array($time_block['attrs'] ?? null) ? $time_block['attrs'] : array();
					$hours_text = onlinesched_hours_text($time_attrs['hours'] ?? '');
					$note = onlinesched_hours_text($time_attrs['smallText'] ?? '');
					$start = onlinesched_hours_clock($time_attrs['start'] ?? '');
					$end   = onlinesched_hours_clock($time_attrs['end'] ?? '');
					$all_day   = ! empty($time_attrs['allDay']);
					$access    = onlinesched_hours_text($time_attrs['access'] ?? 'public');
					if (!in_array($access, array('public', 'sponsor_and_super_sponsor', 'super_sponsor'), true)) {
						$access = 'public';
					}
					$formatted = onlinesched_hours_format_range($start, $end, $all_day);
					if ('' === $formatted && '' === $hours_text && '' === $note) {
						continue;
					}
					$entry = array(
						'hours_text' => '' !== $formatted ? $formatted : $hours_text,
						'note'       => $note,
						'start'      => $all_day ? '00:00' : $start,
						'end'        => $all_day ? '24:00' : $end,
						'all_day'    => $all_day,
						'closed'     => ! empty($time_attrs['closed']),
					);
					if ('public' !== $access) {
						$entry['access'] = $access;
					}
					$day['entries'][] = $entry;
				}

				if (!empty($day['entries'])) {
					$department['days'][] = $day;
				}
			}

			if ('' !== $department['name'] || !empty($department['days'])) {
				$departments[] = $department;
			}
			continue;
		}

		if (!empty($block['innerBlocks'])) {
			$departments = array_merge(
				$departments,
				onlinesched_app_feed_collect_hours_departments($block['innerBlocks'])
			);
		}
	}

	return $departments;
}

/**
 * Info index (slug/title/updated per configured page).
 *
 * @return array
 */
function onlinesched_app_feed_info_index() {
	$pages = array();
	foreach (onlinesched_app_info_page_ids() as $page_id) {
		$page = get_post($page_id);
		if (!$page || 'publish' !== $page->post_status) {
			continue;
		}
		$pages[] = array(
			'slug'    => $page->post_name,
			'title'   => html_entity_decode(get_the_title($page), ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
			'updated' => get_post_modified_time('c', true, $page),
		);
	}

	return $pages;
}

/**
 * Info section: index, or a single page with content when $slug given.
 *
 * @param string $slug Page slug ('' for the index).
 * @return array|null Null when the slug does not resolve to a configured page.
 */
function onlinesched_app_feed_info($slug = '', $revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$base = array(
		'schema_version' => ONLINESCHED_APP_FEED_SCHEMA_VERSION,
		'generated'      => onlinesched_app_feed_generated($revisions['info']),
	);

	if ('' === $slug) {
		$base['pages'] = onlinesched_app_feed_info_index();
		return $base;
	}

	foreach (onlinesched_app_info_page_ids() as $page_id) {
		$page = get_post($page_id);
		if (!$page || 'publish' !== $page->post_status || $page->post_name !== $slug) {
			continue;
		}

		$content = apply_filters('the_content', $page->post_content);
		$content = wp_kses_post($content);

		$images = array();
		if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
			$images = array_values(array_unique($matches[1]));
		}

		$base['page'] = array(
			'slug'         => $page->post_name,
			'title'        => html_entity_decode(get_the_title($page), ENT_QUOTES | ENT_HTML5, get_option('blog_charset')),
			'updated'      => get_post_modified_time('c', true, $page),
			'content_html' => $content,
			'images'       => $images,
		);
		return $base;
	}

	return null;
}

/**
 * Build a payload whose revision snapshot is consistent with its content.
 *
 * A mutation landing between the snapshot read and the content queries would
 * otherwise produce a body newer (or older) than the revisions/`generated`
 * values it carries. Re-read the snapshot after building; if any revision
 * moved mid-build, rebuild against the fresh snapshot (bounded retries -
 * under pathological churn the last build is accepted, and the payload-hash
 * ETag keeps conditional requests correct regardless).
 *
 * @param callable $build Receives the revision snapshot, returns the payload
 *                        (null allowed, e.g. unknown info page).
 * @return array{0: array|null, 1: array} [payload, revisions]
 */
function onlinesched_app_feed_build_consistent(callable $build) {
	$revisions = onlinesched_get_feed_revisions();
	$payload = $build($revisions);
	for ($attempt = 0; $attempt < 2; $attempt++) {
		$after = onlinesched_get_feed_revisions();
		if ($after === $revisions) {
			break;
		}
		$revisions = $after;
		$payload = $build($revisions);
	}
	// Always the snapshot this payload was built from: a coherent stale pair
	// beats fresh metadata describing someone else's body.
	return array($payload, $revisions);
}

// ---------------------------------------------------------------------------
// HTTP plumbing (ETag / Last-Modified / 304).
// ---------------------------------------------------------------------------

/**
 * ISO 8601 generated time for a section revision entry.
 *
 * Never request time: a section whose revision has not moved must keep an
 * identical body between requests (matching its stable ETag). Time 0 only
 * occurs before the versioned upgrade has run; the epoch value it renders is
 * stable, and the upgrade replaces it on the next admin/CLI request.
 *
 * @param array{rev:int, time:int} $revision Revision entry.
 * @return string
 */
function onlinesched_app_feed_generated(array $revision) {
	return gmdate('c', max(0, $revision['time']));
}

/**
 * Strong ETag for a section response variant.
 *
 * Derived from the schema version, the section revision, and the request
 * variant (filters/slug) - never from response bytes, so identical
 * representations keep identical ETags across requests.
 *
 * @param string $section Section name ('meta' uses the composite stamp).
 * @param string $variant Request variant discriminator.
 * @return string
 */
function onlinesched_app_feed_etag($section, $variant = '', $revisions = null, $content_hash = '') {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	if ('meta' === $section) {
		// The public stamp plus the internal meta revision, so a meta-only change
		// moves the meta ETag without touching the public contract.
		$parts = array();
		foreach (onlinesched_feed_sections() as $fetchable) {
			$parts[] = $revisions[$fetchable]['rev'];
		}
		$rev = implode('.', $parts) . '+' . $revisions['meta']['rev'];
	} else {
		$rev = isset($revisions[$section]) ? (string) $revisions[$section]['rev'] : '0';
	}

	return '"os-' . ONLINESCHED_APP_FEED_SCHEMA_VERSION . '-' . $section . '-' . $rev
		. ('' !== $variant ? '-' . md5($variant) : '')
		. ('' !== $content_hash ? '-' . substr($content_hash, 0, 20) : '') . '"';
}

/**
 * Fingerprint of the current Meta representation: the exact ETag a meta
 * request would receive, covering every section revision, resource revision,
 * and link. This is the completed-publication identity; the public change
 * stamp alone misses resource-only and meta-only movement.
 *
 * @param array|null $revisions Optional revision snapshot.
 * @return string
 */
function onlinesched_app_feed_meta_fingerprint($revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$payload = onlinesched_app_feed_meta($revisions);
	return onlinesched_app_feed_etag('meta', '', $revisions, md5((string) wp_json_encode($payload)));
}

/**
 * Send a section payload with conditional-request support, then exit.
 *
 * Body, ETag, and Last-Modified all derive from one immutable revision
 * snapshot taken per request (a concurrent touch can never produce headers
 * from one revision and a body from another), and the strong ETag includes a
 * hash of the exact bytes sent - the ETag changes if and only if the
 * representation changes, whatever the dependency (filters included).
 *
 * @param array      $payload   Response body.
 * @param string     $section   Section name.
 * @param string     $variant   Request variant discriminator for the ETag.
 * @param array|null $revisions The request's revision snapshot.
 */
function onlinesched_app_feed_send(array $payload, $section, $variant = '', $revisions = null) {
	$revisions = is_array($revisions) ? $revisions : onlinesched_get_feed_revisions();
	$body = wp_json_encode($payload);
	$etag = onlinesched_app_feed_etag($section, $variant, $revisions, md5($body));

	if ('meta' === $section) {
		$last_modified_time = 0;
		foreach ($revisions as $entry) {
			$last_modified_time = max($last_modified_time, $entry['time']);
		}
	} else {
		$last_modified_time = isset($revisions[$section]) ? $revisions[$section]['time'] : 0;
	}

	header('Cache-Control: public, max-age=60');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');
	header('ETag: ' . $etag);
	if ($last_modified_time > 0) {
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified_time) . ' GMT');
	}

	$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH'])
		? trim(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']))
		: '';
	// Compared on the unquoted core token, because proxies rewrap the ETag and
	// an exact match would disable every 304 behind them.
	if ('' !== $if_none_match && false !== strpos($if_none_match, trim($etag, '"'))) {
		status_header(304);
		exit;
	}

	// Emit the exact hashed bytes (wp_send_json would re-encode).
	header('Content-Type: application/json; charset=' . get_option('blog_charset'));
	echo $body;
	exit;
}

// ---------------------------------------------------------------------------
// Schedule filter parsing (room/tag/group request parameters).
// ---------------------------------------------------------------------------

/**
 * Read room/tag/group filters from the request.
 *
 * @return array{rooms:string[], exclude_rooms:string[], tags:string[], exclude_tags:string[], empty_group:bool}
 */
function onlinesched_app_feed_request_filters() {
	$filters = array(
		'rooms'         => array(),
		'exclude_rooms' => array(),
		'tags'          => array(),
		'exclude_tags'  => array(),
		'empty_group'   => false,
	);

	$group_key = onlinesched_app_feed_request_value(array('group'));
	if ('' !== $group_key) {
		$group_key = sanitize_key($group_key);
		$groups = onlinesched_app_feed_room_groups();
		$group = onlinesched_app_feed_normalize_group($groups[$group_key] ?? array());
		$has_filters = !empty($group['rooms']) || !empty($group['exclude_rooms'])
			|| !empty($group['tags']) || !empty($group['exclude_tags']);
		if (!$has_filters) {
			$filters['empty_group'] = true;
			return $filters;
		}
		return array_merge($filters, $group);
	}

	$room_slugs = onlinesched_app_feed_request_slugs(array('room', 'rooms'));
	if (!in_array('all', array_map('strtolower', $room_slugs), true)) {
		$filters['rooms'] = $room_slugs;
	}

	$tag_slugs = onlinesched_app_feed_request_slugs(array('tag', 'tags'));
	if (!empty($tag_slugs) && !in_array('all', array_map('strtolower', $tag_slugs), true)) {
		$filters['tags'] = $tag_slugs;
	}

	return $filters;
}

/**
 * Build a tax_query from parsed filters.
 *
 * @param array $filters onlinesched_app_feed_request_filters() output.
 * @return array
 */
function onlinesched_app_feed_tax_query(array $filters) {
	$tax_query = array('relation' => 'AND');

	if (!empty($filters['empty_group'])) {
		// Unknown group: force an empty result rather than guessing.
		$tax_query[] = array(
			'taxonomy' => 'os_room',
			'field'    => 'slug',
			'terms'    => array('onlinesched-nonexistent-group'),
		);
		return $tax_query;
	}

	$clauses = array(
		array('os_room', $filters['rooms'] ?? array(), 'IN'),
		array('os_room', $filters['exclude_rooms'] ?? array(), 'NOT IN'),
		array('os_tag', $filters['tags'] ?? array(), 'IN'),
		array('os_tag', $filters['exclude_tags'] ?? array(), 'NOT IN'),
	);
	foreach ($clauses as $clause) {
		if (empty($clause[1])) {
			continue;
		}
		$tax_query[] = array(
			'taxonomy' => $clause[0],
			'field'    => 'slug',
			'terms'    => $clause[1],
			'operator' => $clause[2],
		);
	}

	return $tax_query;
}

/**
 * @param string[] $keys Accepted request parameter names.
 * @return string First non-empty scalar value, sanitized.
 */
function onlinesched_app_feed_request_value(array $keys) {
	foreach ($keys as $key) {
		if (!isset($_REQUEST[$key]) || '' === $_REQUEST[$key] || is_array($_REQUEST[$key])) {
			continue;
		}
		return sanitize_text_field(wp_unslash($_REQUEST[$key]));
	}
	return '';
}

/**
 * @param string[] $keys Accepted request parameter names.
 * @return string[] Sanitized slug list.
 */
function onlinesched_app_feed_request_slugs(array $keys) {
	$value = onlinesched_app_feed_request_value($keys);
	if ('' === $value) {
		return array();
	}
	return array_values(array_filter(array_map('sanitize_title', explode(',', $value))));
}

/**
 * Named room/tag groups (option + filter, unchanged from the legacy feed).
 *
 * @return array
 */
function onlinesched_app_feed_room_groups() {
	$groups = get_option('onlinesched_json_room_groups', array());
	if (is_string($groups) && '' !== trim($groups)) {
		$decoded = json_decode($groups, true);
		$groups = is_array($decoded) ? $decoded : array();
	}
	if (!is_array($groups)) {
		$groups = array();
	}

	$groups = apply_filters('os_json_room_groups', $groups);
	return is_array($groups) ? $groups : array();
}

/**
 * Normalize one group definition.
 *
 * @param mixed $group Raw group config.
 * @return array{rooms:string[], exclude_rooms:string[], tags:string[], exclude_tags:string[]}
 */
function onlinesched_app_feed_normalize_group($group) {
	$sanitize = static function ($slugs) {
		if (!is_array($slugs)) {
			$slugs = array($slugs);
		}
		return array_values(array_filter(array_map('sanitize_title', $slugs)));
	};

	if (!is_array($group)) {
		return array(
			'rooms'         => $sanitize($group),
			'exclude_rooms' => array(),
			'tags'          => array(),
			'exclude_tags'  => array(),
		);
	}

	if (array_keys($group) === range(0, count($group) - 1)) {
		$group = array('rooms' => $group);
	}

	return array(
		'rooms'         => $sanitize($group['rooms'] ?? array()),
		'exclude_rooms' => $sanitize($group['exclude_rooms'] ?? array()),
		'tags'          => $sanitize($group['tags'] ?? array()),
		'exclude_tags'  => $sanitize($group['exclude_tags'] ?? array()),
	);
}
