<?php
// OnlineSchedSettings.php

// Admin settings for OnlineSched plugin

function OnlineSched_admin_init()
{
	add_meta_box(
		'OnlineSched_timeslot',
		'Event Information',
		'OnlineSched_timeslot_metabox',
		'event_schedule',
		'normal',
		'high');
	add_option('event_schedule_year', 'Event Schedule Year');
	register_setting('event_schedule_option_group', 'event_schedule_year', 'OnlineSched_callback');

	// Dynamically register social provider settings
	$social_config = require dirname(__FILE__) . '/includes/social_providers_config.php';
	if (isset($social_config['providers']) && is_array($social_config['providers'])) {
		foreach ($social_config['providers'] as $provider => $providerData) {
			if (isset($providerData['keys']) && is_array($providerData['keys'])) {
				foreach ($providerData['keys'] as $key => $val) {
					$option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
					add_option($option_name, '');
					register_setting('event_schedule_option_group', $option_name);
				}
			}
		}
	}
}

function OnlineSched_register_options_page()
{
	add_options_page('Online Sched', 'OnlineSched ', 'edit_onlinesched_event_schedules', 'event_schedule', 'OnlineSched_options_page');
}

function OnlineSched_options_page()
{
	$social_config = require dirname(__FILE__) . '/includes/social_providers_config.php';
	?>
    <div>
        <h2>OnlineSched Options</h2>
        <form method="post" action="options.php">
			<?php settings_fields('event_schedule_option_group'); ?>
            <table>
                <tr valign="top">
                    <th scope="row"><label for="event_schedule_year">OnlineSched Year</label></th>
                    <td><input type="text" id="event_schedule_year" name="event_schedule_year"
                               value="<?php echo esc_attr(get_option('event_schedule_year')); ?>"/></td>
                </tr>
            </table>
            <h3>Social Login Providers</h3>
            <table>
			<?php
			if (isset($social_config['providers']) && is_array($social_config['providers'])) {
				foreach ($social_config['providers'] as $provider => $providerData) {
					echo '<tr><th colspan="2" style="padding-top:20px;"><strong>' . esc_html($provider) . '</strong></th></tr>';
					if (!empty($providerData['no_keys'])) {
						echo '<tr><td colspan="2" style="color: #666; padding-bottom: 10px;">No settings are needed for this provider.</td></tr>';
					} else if (isset($providerData['keys']) && is_array($providerData['keys'])) {
						foreach ($providerData['keys'] as $key => $val) {
							$option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
							echo '<tr valign="top">';
							echo '<th scope="row"><label for="' . esc_attr($option_name) . '">' . esc_html(ucfirst($provider) . ' ' . ucfirst($key)) . '</label></th>';
							echo '<td><input type="text" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr(get_option($option_name)) . '" size="50"/></td>';
							echo '</tr>';
						}
					}
				}
			}
			?>
            </table>
			<?php submit_button(); ?>
        </form>
    </div>
	<?PHP
}
