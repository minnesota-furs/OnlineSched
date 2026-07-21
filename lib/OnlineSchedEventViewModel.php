<?php
/**
 * OnlineSched Event View Model
 *
 * A single source of truth for event data and rendering logic.
 *
 * @package OnlineSched
 */

namespace OnlineSched\Lib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnlineSchedEventViewModel {
	/** @var int Event ID */
	public $id;

	/** @var \WP_Post The event post object */
	protected $post;

	/** @var array Raw event data */
	protected $data = [];

	/**
	 * Constructor
	 *
	 * @param int $event_id The ID of the os_event post.
	 */
	public function __construct( $event_id ) {
		$this->id = absint( $event_id );
		$this->post = get_post( $this->id );

		if ( $this->post && 'os_event' === $this->post->post_type ) {
			$this->load_metadata();
		}
	}

	/**
	 * Load the event metadata from WordPress storage.
	 */
	protected function load_metadata() {
		$this->data['id'] = $this->id;
		$this->data['dom_id'] = 'onlineevt-' . $this->id;
		$this->data['title'] = get_the_title( $this->post );
		$this->data['content'] = $this->post->post_content;
		$this->data['description'] = apply_filters( 'os_event_description', $this->data['content'], $this->id );

		// Metadata extraction uses corrected event timing keys.
		$sort_ts = get_post_meta( $this->id, 'onlinesched_sorttime', true );
		$duration = get_post_meta( $this->id, 'onlinesched_timelen', true );

		$this->data['start_ts'] = ! empty( $sort_ts ) ? (int) $sort_ts : 0;
		$this->data['duration'] = ! empty( $duration ) ? (int) $duration : 0;
		$this->data['end_ts']   = $this->data['start_ts'] + ( $this->data['duration'] * 60 );

		$this->data['formatted_date'] = $this->data['start_ts'] ? wp_date( 'l, F j, Y', $this->data['start_ts'] ) : '';
		$this->data['formatted_time'] = $this->data['start_ts'] ? wp_date( 'g:i A', $this->data['start_ts'] ) : '';
		$this->data['hour_duration']  = $this->calculate_duration( $this->data['start_ts'], $this->data['end_ts'] );

		// Get schedule page URL for filtering
		$schedule_page_id = get_option( 'onlinesched_schedule_page_id' );
		$schedule_url = $schedule_page_id ? get_permalink( $schedule_page_id ) : home_url( '/' );

		// Taxonomy extraction - Generate links to main schedule with filter hash
		$this->data['rooms'] = $this->get_linked_terms( 'os_room', $schedule_url, '#room-' );
		$this->data['tags'] = $this->get_linked_terms( 'os_tag', $schedule_url, '#tag-' );

		// Panelists: Plain text to avoid 404 links to non-existent archive pages.
		$panelist_terms = wp_get_post_terms( $this->id, 'os_panelist' );
		$this->data['panelists'] = $this->format_term_list( $panelist_terms );

		// Status checks from event tags to avoid false positives.
		$tag_objects = wp_get_post_terms( $this->id, 'os_tag' );
		$tag_slugs = ! is_wp_error( $tag_objects ) ? wp_list_pluck( $tag_objects, 'slug' ) : [];

		$this->data['is_cancelled']     = in_array( 'cancelled', $tag_slugs ) || in_array( 'canceled', $tag_slugs );
		$this->data['is_vip']           = in_array( 'vip', $tag_slugs );
		$this->data['is_goh']           = in_array( 'guest-of-honor', $tag_slugs ) || in_array( 'goh', $tag_slugs );
		$this->data['is_special_guest'] = in_array( 'special-guest', $tag_slugs );

		// Row highlight color (if any)
		$this->data['highlight_color'] = get_post_meta( $this->id, 'onlinesched_row_color', true );
	}

	/**
	 * Helper: Get linked terms for filtering.
	 */
	protected function get_linked_terms( $taxonomy, $base_url, $hash_prefix ) {
		$terms = wp_get_post_terms( $this->id, $taxonomy );
		return $this->format_term_list( $terms, $base_url, $hash_prefix );
	}

	/**
	 * Format terms as inline units so separators stay attached when text wraps.
	 */
	protected function format_term_list( $terms, $base_url = '', $hash_prefix = '' ) {
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$items = [];
		$term_count = count( $terms );
		foreach ( $terms as $index => $term ) {
			$attrs = ' data-os-term-label="' . esc_attr( $term->name ) . '"';
			if ( 'os_tag' === $term->taxonomy ) {
				$attrs .= ' data-os-tag-route="' . esc_attr( preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( $term->name ) ) ) ) . '"';
			}

			$label = esc_html( $term->name );
			if ( '' !== $base_url ) {
				$label = sprintf(
					'<a href="%s" class="os-filter-link">%s</a>',
					esc_url( $base_url . $hash_prefix . $term->slug ),
					$label
				);
			}

			$separator = ( $index < $term_count - 1 ) ? '<span class="os-term-separator" aria-hidden="true">,</span>' : '';
			$items[] = '<span class="os-term-item"' . $attrs . '>' . $label . $separator . '</span>';
		}

		return implode( ' ', $items );
	}

	/**
	 * Calculate duration string between two timestamps.
	 */
	protected function calculate_duration( $start, $end ) {
		if ( ! $start || ! $end ) {
			return '';
		}
		return wp_date( 'g:i A', $start ) . ' - ' . wp_date( 'g:i A', $end );
	}

	/**
	 * Get the raw data array for flexibility.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Get the HTML for event badges.
	 * Consolidated and hardened from schedule-event-row.php.
	 */
	public function get_badge_html() {
		if ( empty( $this->data ) ) {
			return '';
		}

		// Re-fetch badge logic from options/filters to ensure consistency
		$badge_types_present = array(); // Fetch the active term types once and cache in metadata.
		// For now, we'll implement the core VIP/GOH/Cancelled badges as a baseline.

		$badges = '';

		if ( $this->data['is_cancelled'] ) {
			$badges .= ' <span class="os-badge os-badge--canceled">' . esc_html__( 'Canceled', 'onlinesched' ) . '</span>';
		}
		if ( $this->data['is_vip'] ) {
			$badges .= ' <span class="os-badge os-badge--vip">' . esc_html__( 'VIP', 'onlinesched' ) . '</span>';
		}
		if ( $this->data['is_goh'] ) {
			$badges .= ' <span class="os-badge os-badge--goh">' . esc_html__( 'GOH', 'onlinesched' ) . '</span>';
		}
		if ( $this->data['is_special_guest'] ) {
			$badges .= ' <span class="os-badge os-badge--special-guest">' . esc_html__( 'Special Guest', 'onlinesched' ) . '</span>';
		}

		return apply_filters( 'os_event_badge_html', $badges, $this->id );
	}

	/**
	 * Get the HTML for the favorite toggle button.
	 */
	public function get_favorite_button_html() {
		if ( empty( $this->data ) ) {
			return '';
		}

		$fav_icon_class = function_exists( 'onlinesched_get_favorite_icon_classes' )
			? onlinesched_get_favorite_icon_classes( false )
			: 'far fa-star';

		return sprintf(
			'<button type="button" class="schedule-favorite-toggle" title="%s" data-os-event-id="%s" aria-pressed="false"><i class="%s" aria-hidden="true"></i></button>',
			esc_attr__( 'Mark as favorite', 'onlinesched' ),
			esc_attr( $this->id ),
			esc_attr( $fav_icon_class )
		);
	}

	/**
	 * Get calendar and share action links.
	 */
	public function get_action_links() {
		if ( empty( $this->data ) || $this->data['is_cancelled'] ) {
			return [];
		}

		$ical_base_url = ONLINESCHED_PLUGIN_URL;
		$ical_base_url = preg_replace( '/^https?:\/\//', '', $ical_base_url );
		$ical_link = 'webcal://' . $ical_base_url . 'ical.php?cal-id=' . $this->id;
		$google_link = 'https://calendar.google.com/calendar/r?cid=' . urlencode( $ical_link );

		// Get schedule page URL.
		$schedule_page_id = get_option( 'onlinesched_schedule_page_id' );
		$base_url = $schedule_page_id ? get_permalink( $schedule_page_id ) : home_url( '/' );

		return [
			'ical' => $ical_link,
			'google' => $google_link,
			'share' => $base_url . '#evt=' . $this->id,
		];
	}

	/**
	 * Helper for unique wrapper IDs.
	 */
	public function get_unique_id( $prefix = 'os-solo-event-' ) {
		return wp_unique_id( $prefix );
	}
}
