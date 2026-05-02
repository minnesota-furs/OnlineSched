<?php
// OnlineSchedSettings.php

if (!defined('ABSPATH')) {
    exit;
}

function onlinesched_settings_capability()
{
    return 'edit_onlinesched_event_schedules';
}

add_filter('option_page_capability_onlinesched_option_group', 'onlinesched_settings_capability');
add_filter('option_page_capability_onlinesched_social_login_group', 'onlinesched_settings_capability');
add_filter('option_page_capability_event_schedule_option_group', 'onlinesched_settings_capability');
add_filter('option_page_capability_event_schedule_social_login_group', 'onlinesched_settings_capability');

function onlinesched_register_setting_alias($primary_group, $legacy_group, $option_name, $sanitize_callback = null)
{
    if ($sanitize_callback) {
        register_setting($primary_group, $option_name, $sanitize_callback);
        register_setting($legacy_group, $option_name, $sanitize_callback);
        return;
    }

    register_setting($primary_group, $option_name);
    register_setting($legacy_group, $option_name);
}

function onlinesched_register_main_setting($option_name, $sanitize_callback = null)
{
    onlinesched_register_setting_alias('onlinesched_option_group', 'event_schedule_option_group', $option_name, $sanitize_callback);
}

function onlinesched_register_social_login_setting($option_name, $sanitize_callback = null)
{
    onlinesched_register_setting_alias('onlinesched_social_login_group', 'event_schedule_social_login_group', $option_name, $sanitize_callback);
}

function onlinesched_settings_admin_styles()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'event_schedule_page_onlinesched-settings' !== $screen->id) {
        return;
    }
    ?>
    <style>
        .onlinesched-page-select {
            display: inline-block !important;
            min-width: 25em;
            max-width: 100%;
        }
    </style>
    <?php
}

function onlinesched_get_page_selector_pages()
{
    $pages = get_pages(array(
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
        'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
    ));

    if (!empty($pages)) {
        return $pages;
    }

    global $wpdb;

    return $wpdb->get_results(
        "SELECT ID, post_title, post_name, post_status
         FROM {$wpdb->posts}
         WHERE post_type = 'page'
           AND post_status NOT IN ('trash', 'auto-draft')
         ORDER BY post_title ASC, ID ASC"
    );
}

function OnlineSched_admin_init()
{
    add_meta_box(
        'OnlineSched_timeslot',
        'Event Information',
        'OnlineSched_timeslot_metabox',
        'event_schedule',
        'normal',
        'high'
    );

    add_option('event_schedule_year', 'Event Schedule Year');

    onlinesched_register_main_setting('event_schedule_year', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_schedule_page_id', 'absint');
    onlinesched_register_main_setting('onlinesched_kiosk_page_id', 'absint');
    onlinesched_register_main_setting('onlinesched_live_page_id', 'absint');
    onlinesched_register_main_setting('onlinesched_hours_page_id', 'absint');
    onlinesched_register_main_setting('onlinesched_map_page_id', 'absint');
    onlinesched_register_main_setting('onlinesched_tab_programming_label', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_tab_programming_mobile_label', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_tab_hours_label', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_tab_map_label', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_sticky_offset_desktop', 'absint');
    onlinesched_register_main_setting('onlinesched_sticky_offset_mobile', 'absint');
}

function OnlineSched_register_options_page()
{
    add_submenu_page(
        'edit.php?post_type=event_schedule',
        'Event Schedule Settings',
        'Event Settings',
        'edit_onlinesched_event_schedules',
        'onlinesched-settings',
        'OnlineSched_options_page'
    );
}

function OnlineSched_register_config_status_page()
{
    add_submenu_page(
        'edit.php?post_type=event_schedule',
        'Configuration Status',
        'Configuration Status',
        'edit_onlinesched_event_schedules',
        'onlinesched-config-status',
        'OnlineSched_config_status_page'
    );
}

function onlinesched_page_dropdown_row($option_name, $label, $description)
{
    $selected = absint(get_option($option_name, 0));
    $pages = onlinesched_get_page_selector_pages();

    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <select
                name="<?php echo esc_attr($option_name); ?>"
                id="<?php echo esc_attr($option_name); ?>"
                class="regular-text onlinesched-page-select"
                style="display:inline-block !important; min-width:25em; max-width:100%; visibility:visible !important; opacity:1 !important;"
            >
                <option value="0"><?php echo esc_html('-- Select a Page --'); ?></option>
                <?php foreach ($pages as $page) : ?>
                    <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected, $page->ID); ?>>
                        <?php echo esc_html(sprintf('%s%s', $page->post_title ? $page->post_title : '(no title)', 'publish' !== $page->post_status ? ' [' . $page->post_status . ']' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($pages)) : ?>
                <p class="description">No pages found. Create a WordPress page first, then return here.</p>
            <?php endif; ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        </td>
    </tr>
    <?php
}

function onlinesched_text_input_row($option_name, $label, $default, $description)
{
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="text" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>"
                   value="<?php echo esc_attr(get_option($option_name, $default)); ?>" class="regular-text" />
            <p class="description"><?php echo esc_html($description); ?></p>
        </td>
    </tr>
    <?php
}

function onlinesched_number_input_row($option_name, $label, $default, $description)
{
    $constant_name = onlinesched_get_constant_name(str_replace('onlinesched_', '', $option_name));
    $managed_in_code = defined($constant_name);
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="number" min="0" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>"
                   value="<?php echo esc_attr(absint(get_option($option_name, $default))); ?>" class="small-text"
                <?php disabled($managed_in_code); ?> />
            <?php if ($managed_in_code) : ?>
                <span class="description">Managed in code by <code><?php echo esc_html($constant_name); ?></code>.</span>
            <?php endif; ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        </td>
    </tr>
    <?php
}

function OnlineSched_options_page()
{
    ?>
    <div class="wrap">
        <h1>Event Schedule Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('onlinesched_option_group'); ?>

            <h2>Basic Setup</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="event_schedule_year">Event Schedule Year</label></th>
                    <td>
                        <input type="text" id="event_schedule_year" name="event_schedule_year"
                               value="<?php echo esc_attr(get_option('event_schedule_year')); ?>" class="regular-text" />
                        <p class="description">Only events matching this year are shown on the public schedule.</p>
                    </td>
                </tr>
                <?php
                onlinesched_page_dropdown_row('onlinesched_schedule_page_id', 'Main Schedule Page', 'The page that displays the standard public schedule.');
                onlinesched_page_dropdown_row('onlinesched_kiosk_page_id', 'Kiosk Schedule Page', 'The page that displays kiosk mode.');
                onlinesched_page_dropdown_row('onlinesched_live_page_id', 'Live Streaming Page', 'The page that displays live streaming mode.');
                onlinesched_page_dropdown_row('onlinesched_hours_page_id', 'Hours Tab Content Page', 'Page content rendered inside the Hours tab.');
                onlinesched_page_dropdown_row('onlinesched_map_page_id', 'Map Tab Content Page', 'Page content rendered inside the kiosk Map tab.');
                ?>
            </table>

            <h2>Advanced Display</h2>
            <p>These defaults are fine for most sites. Keep them boring unless your theme needs a custom nudge.</p>
            <table class="form-table" role="presentation">
                <?php
                onlinesched_text_input_row('onlinesched_tab_programming_label', 'Programming Tab Label', 'Programming', 'Desktop label for the main schedule tab.');
                onlinesched_text_input_row('onlinesched_tab_programming_mobile_label', 'Programming Mobile Label', 'Events', 'Short mobile label for the main schedule tab.');
                onlinesched_text_input_row('onlinesched_tab_hours_label', 'Hours Tab Label', 'Hours', 'Label for the Hours tab.');
                onlinesched_text_input_row('onlinesched_tab_map_label', 'Map Tab Label', 'Map', 'Label for the kiosk Map tab.');
                onlinesched_number_input_row('onlinesched_sticky_offset_desktop', 'Desktop Sticky Offset', 0, 'Height in pixels of any fixed theme header above schedule tabs on desktop.');
                onlinesched_number_input_row('onlinesched_sticky_offset_mobile', 'Mobile Sticky Offset', 0, 'Height in pixels of any fixed theme header above schedule tabs on mobile.');
                ?>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function onlinesched_config_status_row($label, $status, $editable = 'Yes', $secret = false)
{
    $value = $status['value'];
    if ($secret) {
        $value_preview = empty($value) ? 'not configured' : 'configured';
    } elseif (is_scalar($value)) {
        $value_preview = (string) $value;
    } else {
        $value_preview = wp_json_encode($value);
    }

    ?>
    <tr>
        <td><?php echo esc_html($label); ?></td>
        <td><code><?php echo esc_html($status['option']); ?></code></td>
        <td><?php echo esc_html($status['source']); ?></td>
        <td><?php echo esc_html($value_preview); ?></td>
        <td><?php echo esc_html($editable); ?></td>
        <td>
            <code><?php echo esc_html($status['constant']); ?></code><br>
            <code><?php echo esc_html($status['filter']); ?></code>
        </td>
    </tr>
    <?php
}

function OnlineSched_config_status_page()
{
    $rows = array(
        'schedule_page_id' => 'Main Schedule Page',
        'kiosk_page_id' => 'Kiosk Schedule Page',
        'live_page_id' => 'Live Streaming Page',
        'hours_page_id' => 'Hours Tab Content Page',
        'map_page_id' => 'Map Tab Content Page',
        'tab_programming_label' => 'Programming Tab Label',
        'tab_programming_mobile_label' => 'Programming Mobile Label',
        'tab_hours_label' => 'Hours Tab Label',
        'tab_map_label' => 'Map Tab Label',
        'sticky_offset_desktop' => 'Desktop Sticky Offset',
        'sticky_offset_mobile' => 'Mobile Sticky Offset',
    );
    ?>
    <div class="wrap">
        <h1>OnlineSched Configuration Status</h1>
        <p>This read-only view shows where important OnlineSched values come from. Secrets are never printed here.</p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Setting</th>
                    <th>Key</th>
                    <th>Source</th>
                    <th>Value Preview</th>
                    <th>Editable Here</th>
                    <th>Code Hooks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $key => $label) : ?>
                    <?php onlinesched_config_status_row($label, onlinesched_get_config_status($key, '')); ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Social Login Providers</h2>
        <p>SSO credentials are edited on the Social Login page. This table only shows whether each provider is configured.</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Key</th>
                    <th>Status</th>
                    <th>Editable Here</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $social_config = require dirname(__FILE__) . '/includes/social_providers_config.php';
                foreach ($social_config['providers'] as $provider => $provider_data) :
                    if (!empty($provider_data['no_keys'])) :
                        $configured = function_exists('onlinesched_social_provider_is_enabled')
                            ? onlinesched_social_provider_is_enabled($provider, $provider_data)
                            : !empty($provider_data['enabled']);
                        ?>
                        <tr>
                            <td><?php echo esc_html($provider); ?></td>
                            <td>none required</td>
                            <td><?php echo esc_html($configured ? 'enabled' : 'disabled'); ?></td>
                            <td>Social Login page</td>
                        </tr>
                        <?php
                        continue;
                    endif;

                    foreach ((array) ($provider_data['keys'] ?? array()) as $key => $unused) :
                        $option_name = 'onlinesched_social_' . strtolower($provider) . '_' . strtolower($key);
                        $configured = get_option($option_name, '');
                        $enabled = function_exists('onlinesched_social_provider_is_enabled')
                            ? onlinesched_social_provider_is_enabled($provider, $provider_data)
                            : false;
                        ?>
                        <tr>
                            <td><?php echo esc_html($provider); ?></td>
                            <td><code><?php echo esc_html($option_name); ?></code></td>
                            <td><?php echo esc_html($enabled ? 'enabled' : ($configured ? 'configured but disabled' : 'not configured')); ?></td>
                            <td>Social Login page</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
