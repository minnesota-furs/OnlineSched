<?php
// OnlineSchedEssentials.php
// Admin page for managing Essentials tags

function onlinesched_essentials_page() {
	if (!current_user_can('manage_event_schedule_tags_type')) {
		wp_die('You do not have permission to manage Essentials settings.');
	}
	$option_name = 'onlinesched_essentials_tags';
	$essentials_tags = get_option($option_name, array());
	$tags = get_terms([
		'taxonomy' => 'event_schedule_tags_type',
		'hide_empty' => false,
	]);
	$option_tab_name = 'onlinesched_essentials_tab_name';
	$essentials_tab_name = get_option($option_tab_name, 'Essentials');

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (isset($_POST['essentials_tags'])) {
			$new_tags = array_map('sanitize_text_field', (array)$_POST['essentials_tags']);
			update_option($option_name, $new_tags);
			$essentials_tags = $new_tags;
			$message = 'Essentials tags updated.';
		}
		if (isset($_POST['essentials_tab_name'])) {
			$new_tab_name = sanitize_text_field($_POST['essentials_tab_name']);
			update_option($option_tab_name, $new_tab_name);
			$essentials_tab_name = $new_tab_name;
			$message = ($message ? $message . ' ' : '') . 'Tab name updated.';
		}
	}
	?>
	<style>
	.schedule-updated {
		background: #f6ffed;
		border-left: 4px solid #46b450;
		margin: 20px 0 20px 0;
		padding: 12px 12px 12px 16px;
		box-shadow: 0 1px 1px 0 rgba(0,0,0,.04);
		color: #1a531b;
		font-size: 14px;
		line-height: 1.5;
		border-radius: 2px;
		position: relative;
	}
	.schedule-updated p { margin: 0; }
	.schedule-updated .close-message {
		position: absolute;
		top: 8px;
		right: 12px;
		background: none;
		border: none;
		font-size: 18px;
		color: #888;
		cursor: pointer;
	}
	</style>
	<div class="wrap">
		<h2>Essential Tab Tag Settings</h2>
		<p style="margin-bottom:16px; color:#444; background:#f7fbff; border-left:4px solid #1890ff; padding:12px 18px; border-radius:4px; max-width:700px;">
			<strong>Instructions:</strong> Select which tags should be considered <strong>Essential</strong> for the schedule filter.<br>
			In previous years, the following tags were typically marked as Essential:
			<ul style="margin-top:8px; margin-bottom:8px;">
				<li><strong>Guest Of Honor</strong></li>
				<li><strong>Special Guest</strong></li>
				<li><strong>VIP</strong></li>
				<li><strong>Essentials</strong></li>
			</ul>
			You may select any tags below to customize the Essential Tab filter for this year.
		</p>
		<?php if (!empty($message)) {
			 echo '<div class="schedule-updated"><button class="close-message" onclick="this.parentNode.style.display=\'none\';">&times;</button><p>' . esc_html($message) . '</p></div>';
		} ?>
		<form method="post">
			<table class="form-table">
				<tr><th>Tab Name for Essential Tab:</th><td>
					<input type="text" name="essentials_tab_name" value="<?php echo esc_attr($essentials_tab_name); ?>" style="width:220px;" placeholder="Essentials" />
					<p class="description">This will be the name of the tab on the schedule page. Example: "Essentials", "Must See", "Featured", etc.</p>
				</td></tr>
				<tr><th>Select tags for Essential Tab:</th><td>
				<?php foreach ($tags as $tag) : ?>
					<label style="display:block;margin-bottom:8px;">
						<input type="checkbox" name="essentials_tags[]" value="<?php echo esc_attr($tag->slug); ?>" <?php checked(in_array($tag->slug, $essentials_tags)); ?>>
						<?php echo esc_html($tag->name); ?>
					</label>
				<?php endforeach; ?>
				</td></tr>
			</table>
			<?php submit_button('Save Essential Tab Settings'); ?>
		</form>
	</div>
	<?php
}
