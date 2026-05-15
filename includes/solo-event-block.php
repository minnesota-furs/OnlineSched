<?php
/**
 * Solo Event block registration and rendering.
 *
 * @package OnlineSched
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ONLINESCHED_PLUGIN_DIR . 'lib/OnlineSchedEventViewModel.php';

use OnlineSched\Lib\OnlineSchedEventViewModel;

add_action( 'init', 'onlinesched_register_solo_event_block' );

/**
 * Register the Solo Event block.
 */
function onlinesched_register_solo_event_block() {
	$script_path = ONLINESCHED_PLUGIN_DIR . 'build/solo-event-block.bundle.js';
	$view_script_path = ONLINESCHED_PLUGIN_DIR . 'build/solo-event-view.bundle.js';
	$style_path = ONLINESCHED_PLUGIN_DIR . 'build/main.css';

	$script_handle = 'onlinesched-solo-event-block';
	$view_script_handle = 'onlinesched-solo-event-view';
	$style_handle = 'online-schedule-css';

	if ( file_exists( $script_path ) ) {
		wp_register_script(
			$script_handle,
			ONLINESCHED_PLUGIN_URL . 'build/solo-event-block.bundle.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data' ),
			filemtime( $script_path ),
			true
		);
	}

	if ( file_exists( $view_script_path ) ) {
		wp_register_script(
			$view_script_handle,
			ONLINESCHED_PLUGIN_URL . 'build/solo-event-view.bundle.js',
			array( 'onlinesched-calendar-helpers' ),
			filemtime( $view_script_path ),
			true
		);
	}

	$args = array(
		'render_callback' => 'onlinesched_render_solo_event_block',
	);

	if ( wp_script_is( $script_handle, 'registered' ) ) {
		$args['editor_script'] = $script_handle;
	}

	if ( wp_script_is( $view_script_handle, 'registered' ) ) {
		$args['view_script'] = $view_script_handle;
	}

	if ( wp_style_is( $style_handle, 'registered' ) ) {
		$args['style'] = $style_handle;
		$args['editor_style'] = $style_handle;
	}

	register_block_type( ONLINESCHED_PLUGIN_DIR . 'includes/blocks/solo-event', $args );
}

/**
 * Render the Solo Event block on the frontend.
 */
function onlinesched_render_solo_event_block( $attributes ) {
	// Ensure core schedule assets are available (CSS, FA, Fonts)
	if ( function_exists( 'onlinesched_enqueue_schedule_assets' ) ) {
		onlinesched_enqueue_schedule_assets();
	}

	// Ensure modals are printed in the footer if not already present
	add_action( 'wp_footer', 'onlinesched_print_solo_event_modals', 20 );

	$event_id = ! empty( $attributes['eventId'] ) ? absint( $attributes['eventId'] ) : 0;
	$is_full_width = ! empty( $attributes['fullWidth'] ) ? (bool) $attributes['fullWidth'] : false;

	if ( ! $event_id ) {
		if ( is_admin() ) {
			return '<div class="os-solo-event-placeholder">' . esc_html__( 'No event selected.', 'onlinesched' ) . '</div>';
		}
		return '';
	}

	$view_model = new OnlineSchedEventViewModel( $event_id );
	$data = $view_model->get_data();

	if ( empty( $data ) ) {
		if ( is_admin() ) {
			return '<div class="os-solo-event-placeholder">' . esc_html__( 'Event not found or invalid.', 'onlinesched' ) . '</div>';
		}
		return '';
	}

	$wrapper_id = 'onlineevt-' . $event_id . '-' . $view_model->get_unique_id();
	$highlight_style = ! empty( $data['highlight_color'] ) ? ' style="border-left: 5px solid ' . esc_attr( $data['highlight_color'] ) . ';"' : '';
	$cancelled_class = $data['is_cancelled'] ? ' os-cancelled' : '';
	$full_width_class = $is_full_width ? ' is-full-width' : '';
	$links = $view_model->get_action_links();
	$end_time = ! empty( $data['end_ts'] ) ? $data['end_ts'] : 0;

	ob_start();
	?>
	<div id="<?php echo esc_attr( $wrapper_id ); ?>"
		 class="os-solo-event-card<?php echo esc_attr( $cancelled_class . $full_width_class ); ?> schedule-item"
		 data-os-event-id="<?php echo esc_attr( $event_id ); ?>"
		 data-end-time="<?php echo esc_attr( $end_time ); ?>"
		 data-os-event-date="<?php echo esc_attr( $data['formatted_date'] ); ?>"
		 data-os-event-time="<?php echo esc_attr( $data['hour_duration'] ); ?>"
		 <?php echo $highlight_style; ?>>

		<div class="os-solo-event-card__header">
			<div class="os-solo-event-card__title-row">
				<?php echo $view_model->get_favorite_button_html(); ?>
				<h3 class="os-solo-event-card__title schedule-title">
					<?php echo esc_html( $data['title'] ); ?>
				</h3>
				<?php echo $view_model->get_badge_html(); ?>
			</div>
		</div>

		<div class="os-solo-event-card__body">
			<?php if ( ! $data['is_cancelled'] && ! empty( $data['description'] ) ) : ?>
				<div class="os-solo-event-card__description schedule-description">
					<?php echo $data['description']; ?>
				</div>
			<?php endif; ?>

			<div class="os-solo-event-card__meta">
				<div class="os-solo-event-card__meta-item">
					<i class="far fa-calendar-alt" aria-hidden="true"></i>
					<span><?php echo esc_html( $data['formatted_date'] ); ?></span>
				</div>
				<div class="os-solo-event-card__meta-item">
					<i class="far fa-clock" aria-hidden="true"></i>
					<span class="schedule-time"><?php echo esc_html( $data['formatted_time'] ); ?> (<?php echo esc_html( $data['hour_duration'] ); ?>)</span>
				</div>
				<?php if ( ! empty( $data['rooms'] ) ) : ?>
					<div class="os-solo-event-card__meta-item">
						<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
						<span class="schedule-room"><?php echo $data['rooms']; ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $data['panelists'] ) ) : ?>
					<div class="os-solo-event-card__meta-item">
						<i class="fas fa-users" aria-hidden="true"></i>
						<span class="schedule-panelists"><?php echo ! is_wp_error( $data['panelists'] ) ? $data['panelists'] : ''; ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $data['tags'] ) ) : ?>
					<div class="os-solo-event-card__meta-item">
						<i class="fas fa-tags" aria-hidden="true"></i>
						<span class="schedule-tags"><?php echo $data['tags']; ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="os-solo-event-card__footer" style="text-align: center;">
			<?php
			$links = $view_model->get_action_links();
			if ( ! empty( $links ) ) :
				?>
				<div class="os-solo-event-card__actions" style="justify-content: center;">
					<a href="<?php echo esc_url( $links['ical'] ); ?>" class="os-btn os-btn--small schedule-ical" title="<?php esc_attr_e( 'Add to Apple Calendar', 'onlinesched' ); ?>" onclick="return confirmCalendarAppleSubscription(this);">
						<i class="fab fa-apple" aria-hidden="true"></i> <?php esc_html_e( 'APPLE', 'onlinesched' ); ?>
					</a>
					<a href="<?php echo esc_url( $links['google'] ); ?>" class="os-btn os-btn--small schedule-google" title="<?php esc_attr_e( 'Add to Google Calendar', 'onlinesched' ); ?>" onclick="return confirmCalendarGoogleSubscription(this);">
						<i class="fab fa-google" aria-hidden="true"></i> <?php esc_html_e( 'GOOGLE', 'onlinesched' ); ?>
					</a>
					<button type="button" class="os-btn os-btn--small schedule-clipboard" title="<?php esc_attr_e( 'Copy link', 'onlinesched' ); ?>" data-url="<?php echo esc_url( $links['share'] ); ?>">
						<i class="fas fa-copy" aria-hidden="true"></i> <?php esc_html_e( 'COPY', 'onlinesched' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Print required modals for Solo Event block hydration.
 */
function onlinesched_print_solo_event_modals() {
	static $printed = false;
	if ( $printed ) return;
	$printed = true;

	$theming = ''; // Default theming for standalone cards
	onlinesched_get_template_part( 'schedule-event-modal', compact( 'theming' ) );
	onlinesched_get_template_part( 'info-modal' );
	onlinesched_get_template_part( 'android-google-calendar-modal' );
}
