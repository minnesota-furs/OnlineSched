<?php
// OnlineSchedSocialLogin.php
// Social Login settings page for OnlineSched plugin

function onlinesched_social_provider_option_prefix($provider)
{
    return 'onlinesched_social_' . strtolower($provider);
}

function onlinesched_social_provider_enabled_option($provider)
{
    return onlinesched_social_provider_option_prefix($provider) . '_enabled';
}

function onlinesched_social_provider_has_required_keys($provider, $providerData)
{
    if (!empty($providerData['no_keys'])) {
        return true;
    }

    foreach ((array) ($providerData['keys'] ?? array()) as $key => $unused) {
        $option_name = onlinesched_social_provider_option_prefix($provider) . '_' . strtolower($key);
        if ('' === trim((string) get_option($option_name, ''))) {
            return false;
        }
    }

    return true;
}

function onlinesched_social_provider_is_enabled($provider, $providerData)
{
    $option_name = onlinesched_social_provider_enabled_option($provider);
    $missing = '__onlinesched_missing__';
    $enabled_option = get_option($option_name, $missing);
    $has_keys = onlinesched_social_provider_has_required_keys($provider, $providerData);

    if ($missing === $enabled_option) {
        return !empty($providerData['enabled']) && $has_keys;
    }

    return (bool) absint($enabled_option) && $has_keys;
}

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
            <?php settings_fields('onlinesched_social_login_group'); ?>
            <table>
            <?php
            if (isset($social_config['providers']) && is_array($social_config['providers'])) {
                foreach ($social_config['providers'] as $provider => $providerData) {
                    $enabled_option = onlinesched_social_provider_enabled_option($provider);
                    echo '<tr><th colspan="2" style="padding-top:20px;"><strong>' . esc_html($provider) . '</strong></th></tr>';
                    echo '<tr>';
                    echo '<th scope="row"><label for="' . esc_attr($enabled_option) . '">Enable ' . esc_html($provider) . '</label></th>';
                    echo '<td><input type="hidden" name="' . esc_attr($enabled_option) . '" value="0" /><label><input type="checkbox" id="' . esc_attr($enabled_option) . '" name="' . esc_attr($enabled_option) . '" value="1" ' . checked(onlinesched_social_provider_is_enabled($provider, $providerData), true, false) . ' /> Allow visitors to log in with ' . esc_html($provider) . '</label></td>';
                    echo '</tr>';
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
            onlinesched_register_social_login_setting(onlinesched_social_provider_enabled_option($provider), 'absint');
            if (isset($providerData['keys']) && is_array($providerData['keys'])) {
                foreach ($providerData['keys'] as $key => $val) {
                    $option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
                    onlinesched_register_social_login_setting($option_name);
                }
            }
        }
    }
}
