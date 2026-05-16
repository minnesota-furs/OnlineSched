<?php
/**
 * Social Login settings page for OnlineSched.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

function onlinesched_social_provider_option_prefix($provider)
{
    return 'onlinesched_social_' . strtolower(sanitize_key($provider));
}

function onlinesched_social_provider_enabled_option($provider)
{
    return onlinesched_social_provider_option_prefix($provider) . '_enabled';
}

function onlinesched_social_provider_key_option($provider, $key)
{
    return onlinesched_social_provider_option_prefix($provider) . '_' . strtolower(sanitize_key($key));
}

function onlinesched_social_provider_key_constant($provider, $key)
{
    $provider = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $provider));
    $key = strtolower($key) === 'id' ? 'CLIENT_ID' : strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $key));
    $key = 'SECRET' === $key ? 'CLIENT_SECRET' : $key;

    return 'ONLINESCHED_' . $provider . '_' . $key;
}

function onlinesched_social_provider_key_is_code_managed($provider, $key)
{
    return defined(onlinesched_social_provider_key_constant($provider, $key));
}

function onlinesched_social_provider_get_key($provider, $key)
{
    $constant = onlinesched_social_provider_key_constant($provider, $key);
    if (defined($constant)) {
        return (string) constant($constant);
    }

    return (string) get_option(onlinesched_social_provider_key_option($provider, $key), '');
}

function onlinesched_social_provider_has_required_keys($provider, $providerData)
{
    if (!empty($providerData['no_keys'])) {
        return true;
    }

    foreach ((array) ($providerData['keys'] ?? array()) as $key => $unused) {
        if ('' === trim(onlinesched_social_provider_get_key($provider, $key))) {
            return false;
        }
    }

    return true;
}

function onlinesched_social_provider_is_enabled($provider, $providerData)
{
    $enabled = (bool) absint(get_option(onlinesched_social_provider_enabled_option($provider), 0));

    return $enabled && onlinesched_social_provider_has_required_keys($provider, $providerData);
}

function onlinesched_social_sanitize_text($value)
{
    return sanitize_text_field($value);
}

function onlinesched_social_sanitize_secret($value, $option_name)
{
    $value = trim(sanitize_text_field($value));
    if ('' === $value) {
        return (string) get_option($option_name, '');
    }

    return $value;
}

function OnlineSched_register_social_login_page()
{
    add_submenu_page(
        'edit.php?post_type=os_event',
        'Social Login Settings',
        'Social Login',
        'edit_onlinesched_event_schedules',
        'onlinesched-social-login',
        'OnlineSched_social_login_page'
    );
}

function OnlineSched_social_login_page()
{
    $social_config = require ONLINESCHED_PLUGIN_DIR . 'includes/social_providers_config.php';
    ?>
    <div class="wrap">
        <h1>Social Login Providers</h1>
        <p>Providers appear in the public login modal only when enabled here and configured with required credentials.</p>
        <form method="post" action="options.php">
            <?php settings_fields('onlinesched_social_login_group'); ?>
            <table class="form-table" role="presentation">
            <?php foreach ((array) ($social_config['providers'] ?? array()) as $provider => $providerData) : ?>
                <?php $enabled_option = onlinesched_social_provider_enabled_option($provider); ?>
                <tr>
                    <th colspan="2"><h2><?php echo esc_html($provider); ?></h2></th>
                </tr>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr($enabled_option); ?>">Enable <?php echo esc_html($provider); ?></label></th>
                    <td>
                        <input type="hidden" name="<?php echo esc_attr($enabled_option); ?>" value="0" />
                        <label>
                            <input type="checkbox" id="<?php echo esc_attr($enabled_option); ?>" name="<?php echo esc_attr($enabled_option); ?>" value="1" <?php checked((bool) absint(get_option($enabled_option, 0))); ?> />
                            Allow visitors to log in with <?php echo esc_html($provider); ?>
                        </label>
                        <p class="description">
                            Status:
                            <?php echo onlinesched_social_provider_is_enabled($provider, $providerData) ? 'configured and enabled' : 'not active'; ?>
                        </p>
                    </td>
                </tr>
                <?php if (!empty($providerData['no_keys'])) : ?>
                    <tr>
                        <td colspan="2"><p class="description">No credentials are needed for this provider.</p></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ((array) ($providerData['keys'] ?? array()) as $key => $unused) : ?>
                        <?php
                        $option_name = onlinesched_social_provider_key_option($provider, $key);
                        $constant = onlinesched_social_provider_key_constant($provider, $key);
                        $managed_in_code = defined($constant);
                        $saved_value = (string) get_option($option_name, '');
                        $is_secret = strtolower($key) === 'secret';
                        ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($provider . ' ' . ucfirst($key)); ?></label></th>
                            <td>
                                <?php if ($managed_in_code) : ?>
                                    <input type="text" id="<?php echo esc_attr($option_name); ?>" class="regular-text" value="Managed in code" disabled />
                                    <p class="description">Managed by <code><?php echo esc_html($constant); ?></code>.</p>
                                <?php else : ?>
                                    <input
                                        type="<?php echo $is_secret ? 'password' : 'text'; ?>"
                                        id="<?php echo esc_attr($option_name); ?>"
                                        name="<?php echo esc_attr($option_name); ?>"
                                        value="<?php echo $is_secret ? '' : esc_attr($saved_value); ?>"
                                        class="regular-text"
                                        autocomplete="off"
                                        placeholder="<?php echo $is_secret && $saved_value ? esc_attr('Saved secret hidden; enter a new value to replace it') : ''; ?>"
                                    />
                                    <?php if ($is_secret && $saved_value) : ?>
                                        <p class="description">A secret is saved but hidden. Leave blank to keep it.</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'OnlineSched_social_login_save_settings');

function OnlineSched_social_login_save_settings()
{
    $social_config = require ONLINESCHED_PLUGIN_DIR . 'includes/social_providers_config.php';
    foreach ((array) ($social_config['providers'] ?? array()) as $provider => $providerData) {
        onlinesched_register_social_login_setting(onlinesched_social_provider_enabled_option($provider), 'absint');
        foreach ((array) ($providerData['keys'] ?? array()) as $key => $unused) {
            $option_name = onlinesched_social_provider_key_option($provider, $key);
            if (strtolower($key) === 'secret') {
                onlinesched_register_social_login_setting($option_name, function ($value) use ($option_name) {
                    return onlinesched_social_sanitize_secret($value, $option_name);
                });
            } else {
                onlinesched_register_social_login_setting($option_name, 'onlinesched_social_sanitize_text');
            }
        }
    }
}
