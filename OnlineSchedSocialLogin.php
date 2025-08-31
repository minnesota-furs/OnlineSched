<?php
// OnlineSchedSocialLogin.php
// Social Login settings page for OnlineSched plugin

function OnlineSched_register_social_login_page()
{
    add_submenu_page(
        'edit.php?post_type=event_schedule',
        'Social Login Settings',
        'Social Login',
        'edit_onlinesched_event_schedules',
        'onlinesched-social-login',
        'OnlineSched_social_login_page'
    );
}

function OnlineSched_social_login_page()
{
    $social_config = require dirname(__FILE__) . '/includes/social_providers_config.php';
    ?>
    <div>
        <h2>Social Login Providers</h2>
        <form method="post" action="options.php">
            <?php settings_fields('event_schedule_social_login_group'); ?>
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
                            echo '<tr>';
                            echo '<th scope="row"><label for="' . esc_attr($option_name) . '">' . esc_html(ucfirst($provider) . ' ' . ucfirst($key)) . '</label></th>';
                            echo '<td><input type="text" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr(get_option($option_name)) . '" size="50"/>'.$option_name.'</td>';
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
    <?php
}

add_action('admin_init', 'OnlineSched_social_login_save_settings');

// This function should only register settings for the Social Login form.
// Saving/updating happens only via options.php when the form is submitted.
function OnlineSched_social_login_save_settings() {
    $social_config = require dirname(__FILE__) . '/includes/social_providers_config.php';
    if (isset($social_config['providers']) && is_array($social_config['providers'])) {
        foreach ($social_config['providers'] as $provider => $providerData) {
            if (isset($providerData['keys']) && is_array($providerData['keys'])) {
                foreach ($providerData['keys'] as $key => $val) {
                    $option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
                    register_setting('event_schedule_social_login_group', $option_name);
                }
            }
        }
    }
}
