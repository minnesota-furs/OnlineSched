<?php
// OnlineSchedSettings.php

if (!defined('ABSPATH')) {
    exit;
}

function onlinesched_settings_capability()
{
    return 'edit_onlinesched_event_schedules';
}

// Settings groups are admin-form internals: only settings_fields($group) and options.php
// see them. Nothing outside this plugin's own code references them, so they need no legacy
// aliases. If we ever rename a group again, add a one-line migration that maps any saved
// state; do not introduce dual-group registration.
add_filter('option_page_capability_onlinesched_option_group', 'onlinesched_settings_capability');
add_filter('option_page_capability_onlinesched_social_login_group', 'onlinesched_settings_capability');

/**
 * Register an OnlineSched plugin option in the main settings group.
 *
 * Thin wrapper around register_setting() that routes every option through one chokepoint
 * with a sanitize callback. Use this instead of calling register_setting() directly so
 * the group name lives in exactly one place.
 */
function onlinesched_register_main_setting($option_name, $sanitize_callback = null)
{
    if ($sanitize_callback) {
        register_setting('onlinesched_option_group', $option_name, $sanitize_callback);
    } else {
        register_setting('onlinesched_option_group', $option_name);
    }
}

/**
 * Register an OnlineSched social-login option in the social-login settings group.
 * Same rationale as onlinesched_register_main_setting() but for the SSO admin page.
 */
function onlinesched_register_social_login_setting($option_name, $sanitize_callback = null)
{
    if ($sanitize_callback) {
        register_setting('onlinesched_social_login_group', $option_name, $sanitize_callback);
    } else {
        register_setting('onlinesched_social_login_group', $option_name);
    }
}

function onlinesched_settings_admin_styles()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'os_event_page_onlinesched-settings' !== $screen->id) {
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
        'os_event',
        'normal',
        'high'
    );

    add_option('onlinesched_year', 'Event Schedule Year');
    onlinesched_auto_detect_pages();

    onlinesched_register_main_setting('onlinesched_year', 'sanitize_text_field');
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
    onlinesched_register_main_setting('onlinesched_calendar_name', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_ical_filename_prefix', 'sanitize_title');
    onlinesched_register_main_setting('onlinesched_calendar_subscriptions_enabled', 'onlinesched_sanitize_checkbox');
    onlinesched_register_main_setting('onlinesched_room_sort_priority', 'sanitize_text_field');
    foreach (onlinesched_get_color_defaults() as $key => $unused) {
        onlinesched_register_main_setting(onlinesched_get_option_name($key), 'onlinesched_sanitize_color_option');
    }

    onlinesched_register_main_setting('onlinesched_enable_header_flare', 'onlinesched_sanitize_checkbox');
    onlinesched_register_main_setting('onlinesched_header_flare_icon', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_header_flare_custom_class', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_header_flare_image', 'esc_url_raw');

    onlinesched_register_main_setting('onlinesched_icon_fav_inactive', 'onlinesched_sanitize_icon_classes');
    onlinesched_register_main_setting('onlinesched_icon_fav_active', 'onlinesched_sanitize_icon_classes');
}

/**
 * Sanitizer to ensure clean class strings for dynamic icons.
 */
function onlinesched_sanitize_icon_classes($value)
{
    // Trim and allow only alphanumeric, spaces, and dashes.
    return sanitize_text_field(preg_replace('/[^a-z0-9\s-]/i', '', (string)$value));
}

function onlinesched_sanitize_checkbox($value)
{
    return ($value == '1' ? '1' : '0');
}

function onlinesched_calendar_subscriptions_setting_row()
{
    $option_name = 'onlinesched_calendar_subscriptions_enabled';
    $constant_name = 'ONLINESCHED_CALENDAR_SUBSCRIPTIONS_ENABLED';
    $filter_name = 'os_config_calendar_subscriptions_enabled';
    $managed_by_constant = defined($constant_name);
    $managed_by_filter = false !== has_filter($filter_name);
    $managed_in_code = $managed_by_constant || $managed_by_filter;
    $saved_value = get_option($option_name, '1');
    $checked_value = $managed_in_code
        ? onlinesched_calendar_subscriptions_enabled()
        : '1' === (string) $saved_value;
    ?>
    <tr>
        <th scope="row">Schedule Calendar Subscriptions</th>
        <td>
            <input type="hidden" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($managed_in_code ? onlinesched_sanitize_checkbox($saved_value) : '0'); ?>" />
            <label for="<?php echo esc_attr($option_name); ?>">
                <input type="checkbox" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="1"
                    <?php checked($checked_value); ?> <?php disabled($managed_in_code); ?> />
                Publish full-schedule calendar subscriptions
            </label>
            <?php if ($managed_by_constant) : ?>
                <p class="description">Managed in code by <code><?php echo esc_html($constant_name); ?></code>.</p>
            <?php elseif ($managed_by_filter) : ?>
                <p class="description">Managed in code by <code><?php echo esc_html($filter_name); ?></code>.</p>
            <?php endif; ?>
            <p class="description">When disabled, full and filtered schedule subscriptions return an empty calendar and the full-schedule subscription buttons are hidden. Individual event calendar buttons remain available because those events are already visible on the schedule page. Existing subscribers stay connected and will receive events again when this setting is enabled.</p>
            <p class="description"><strong>This setting does not hide the public schedule, individual events, or the JSON feed.</strong></p>
        </td>
    </tr>
    <?php
}

function onlinesched_flush_calendar_subscription_caches()
{
    $schedule_page_id = onlinesched_get_page_id('schedule', 'schedule');
    if ($schedule_page_id) {
        clean_post_cache($schedule_page_id);
    }

    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
}

add_action('add_option_onlinesched_calendar_subscriptions_enabled', 'onlinesched_flush_calendar_subscription_caches');
add_action('update_option_onlinesched_calendar_subscriptions_enabled', 'onlinesched_flush_calendar_subscription_caches');

function OnlineSched_register_options_page()
{
    add_submenu_page(
        'edit.php?post_type=os_event',
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
        'edit.php?post_type=os_event',
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
    $key = str_replace('onlinesched_', '', $option_name);
    $constant_name = onlinesched_get_constant_name($key);
    $filter_name = 'os_config_' . sanitize_key($key);
    $managed_by_constant = defined($constant_name);
    $managed_by_filter = has_filter($filter_name);
    $managed_in_code = $managed_by_constant || $managed_by_filter;
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="number" min="0" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>"
                   value="<?php echo esc_attr(absint(get_option($option_name, $default))); ?>" class="small-text"
                <?php disabled($managed_in_code); ?> />
            <?php if ($managed_by_constant) : ?>
                <span class="description">Managed in code by <code><?php echo esc_html($constant_name); ?></code>.</span>
            <?php elseif ($managed_by_filter) : ?>
                <span class="description">Managed in code by <code><?php echo esc_html($filter_name); ?></code>.</span>
            <?php endif; ?>
            <p class="description"><?php echo esc_html($description); ?></p>
        </td>
    </tr>
    <?php
}

function onlinesched_color_input_row($key, $label, $description)
{
    $defaults = onlinesched_get_color_defaults();
    $option_name = onlinesched_get_option_name($key);
    $default = $defaults[$key];
    $value = onlinesched_get_colors()[$key];
    $constant_name = onlinesched_get_constant_name($key);
    $filter_name = 'os_config_' . sanitize_key($key);
    $managed_by_constant = defined($constant_name);
    $managed_by_filter = has_filter($filter_name);
    $managed_in_code = $managed_by_constant || $managed_by_filter;
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input
                type="color"
                id="<?php echo esc_attr($option_name); ?>"
                name="<?php echo esc_attr($option_name); ?>"
                value="<?php echo esc_attr($value); ?>"
                data-default-color="<?php echo esc_attr($default); ?>"
                style="width:60px; height:34px;"
                <?php disabled($managed_in_code); ?>
            />
            <span class="description">Default: <code><?php echo esc_html($default); ?></code></span>
            <?php if ($managed_by_constant) : ?>
                <span class="description">Managed in code by <code><?php echo esc_html($constant_name); ?></code>.</span>
            <?php elseif ($managed_by_filter) : ?>
                <span class="description">Managed in code by <code><?php echo esc_html($filter_name); ?></code>.</span>
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
                    <th scope="row"><label for="onlinesched_year">Event Schedule Year</label></th>
                    <td>
                        <input type="text" id="onlinesched_year" name="onlinesched_year"
                               value="<?php echo esc_attr(get_option('onlinesched_year')); ?>" class="regular-text" />
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

            <h2>Schedule Calendar Subscriptions</h2>
            <table class="form-table" role="presentation">
                <?php onlinesched_calendar_subscriptions_setting_row(); ?>
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
                onlinesched_text_input_row('onlinesched_calendar_name', 'Calendar Name', onlinesched_get_calendar_name(), 'Name shown by calendar clients for full-schedule subscriptions.');
                onlinesched_text_input_row('onlinesched_ical_filename_prefix', 'iCal Filename Prefix', 'onlinesched', 'Short lowercase prefix for downloaded .ics files.');
                onlinesched_text_input_row('onlinesched_room_sort_priority', 'Room Sort Priority', '', 'Optional comma-separated room names that should sort before the normal alphabetical room order.');
                ?>
            </table>

            <h2>Appearance</h2>
            <p>FM colors are the defaults. Change these only when a site needs its own palette.</p>
            <table class="form-table" role="presentation">
                <?php
                onlinesched_color_input_row('color_primary', 'Primary Color', 'Used for primary highlights, tab hover states, and login button hover states.');
                onlinesched_color_input_row('color_secondary', 'Secondary Color', 'Used for schedule day headers, modal headings, and calendar panels.');
                onlinesched_color_input_row('color_accent', 'Accent Color', 'Used for calendar and copy action icons.');
                onlinesched_color_input_row('color_fav_inactive', 'Favorite Icon Inactive Color', 'Used for the color of unselected/inactive favorites.');
                onlinesched_color_input_row('color_fav_active', 'Favorite Icon Active Color', 'Used for the color of selected/active favorites.');
                onlinesched_color_input_row('color_danger', 'Danger Color', 'Used for cancelled events and destructive buttons.');
                ?>
                <tr>
                    <th scope="row">Header Flare</th>
                    <td>
                        <label>
                            <input type="checkbox" name="onlinesched_enable_header_flare" value="1" <?php checked('1', get_option('onlinesched_enable_header_flare', '1')); ?> />
                            Enable Header Flare (e.g. Paw Prints)
                        </label>
                        <p class="description">Adds a subtle icon to the right side of schedule day headers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="onlinesched_header_flare_icon">Header Flare Icon</label></th>
                    <td>
                        <?php
                        $current_icon        = get_option('onlinesched_header_flare_icon', 'fa-paw');
                        $current_custom      = get_option('onlinesched_header_flare_custom_class', '');
                        $preset_icons = [
                            'fa-paw'                 => 'Paw',
                            'fa-dog'                 => 'Dog',
                            'fa-wolf-pack-battalion' => 'Wolf',
                            'fa-cat'                 => 'Cat',
                            'fa-crow'   => 'Crow',
                            'fa-horse'  => 'Horse',
                            'fa-dragon' => 'Dragon',
                            'fa-otter'  => 'Otter',
                            'fa-hippo'  => 'Hippo',
                            'fa-frog'   => 'Frog',
                            'fa-fish'   => 'Fish',
                            'fa-none'   => 'None (no icon)',
                            'fa-custom' => 'Custom icon class...',
                        ];
                        ?>
                        <div style="display:flex; align-items:center;">
                            <select name="onlinesched_header_flare_icon" id="onlinesched_header_flare_icon">
                                <?php foreach ($preset_icons as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_icon, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="os-flare-icon-preview-wrap" style="margin-left:12px; font-size:1.5em; width:30px; text-align:center;">
                                <?php
                                $preview_key  = ($current_icon === 'fa-custom') ? $current_custom : $current_icon;
                                $preview_slug = preg_replace('/^fa\-/', '', preg_replace('/[^a-z0-9\-]/', '', $preview_key));
                                $preview_pfx  = in_array($preview_slug, onlinesched_brands_icons(), true) ? 'fab' : 'fas';
                                echo '<i id="os-flare-icon-preview" class="' . esc_attr($preview_pfx . ' fa-' . $preview_slug) . '"></i>';
                                ?>
                            </span>
                        </div>
                        <div id="os-flare-custom-wrap" style="margin-top:8px;<?php echo ($current_icon !== 'fa-custom') ? 'display:none;' : ''; ?>">
                            <input type="text"
                                   name="onlinesched_header_flare_custom_class"
                                   id="onlinesched_header_flare_custom_class"
                                   value="<?php echo esc_attr($current_custom); ?>"
                                   placeholder="e.g. fa-ice-cream, fa-star, fa-dragon"
                                   class="regular-text" />
                            <p class="description">
                                Any <a href="https://fontawesome.com/icons?s=solid&m=free" target="_blank">Font Awesome Free solid icon</a> class name.
                                The icon doesn't have to be an animal — sky's the limit.
                            </p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="onlinesched_header_flare_image">Header Flare Image/SVG URL</label></th>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <input type="text" name="onlinesched_header_flare_image" id="onlinesched_header_flare_image" value="<?php echo esc_attr(get_option('onlinesched_header_flare_image', '')); ?>" class="regular-text" />
                            <div id="os-flare-image-preview-wrap" style="margin-left:12px; display:none;">
                                <img id="os-flare-image-preview" src="" style="max-height:32px; display:block; border:none; outline:none; background:transparent;" />
                            </div>
                        </div>
                        <p class="description">Optional: URL to an image or SVG file. If provided, this will be used instead of the Font Awesome icon.</p>
                    </td>
                </tr>
                <script>
                    (function () {
                        var sel        = document.getElementById('onlinesched_header_flare_icon');
                        var customWrap = document.getElementById('os-flare-custom-wrap');
                        var customInp  = document.getElementById('onlinesched_header_flare_custom_class');
                        var imgInp     = document.getElementById('onlinesched_header_flare_image');
                        var iconWrap   = document.getElementById('os-flare-icon-preview-wrap');
                        var imgPrev    = document.getElementById('os-flare-image-preview');
                        var imgWrap    = document.getElementById('os-flare-image-preview-wrap');
                        var brandsIcons = <?php echo wp_json_encode( onlinesched_brands_icons() ); ?>;

                        if (!sel || !customWrap) return;

                        function faClass(key) {
                            var slug   = key.replace(/^fa-/, '');
                            var prefix = brandsIcons.indexOf(slug) !== -1 ? 'fab' : 'fas';
                            return prefix + ' fa-' + slug;
                        }

                        function updateFlarePreview() {
                            var imgUrl = imgInp.value.trim();
                            if (imgUrl) {
                                iconWrap.style.display = 'none';
                                imgWrap.style.display = '';
                                imgPrev.src = imgUrl;
                                return;
                            }
                            imgWrap.style.display = 'none';
                            iconWrap.style.display = '';

                            var key  = sel.value === 'fa-custom' ? (customInp.value.trim() || 'fa-question') : sel.value;
                            var prev = document.getElementById('os-flare-icon-preview');
                            var cls  = key === 'fa-none' ? '' : faClass(key);
                            if (prev) {
                                prev.className = cls;
                            } else {
                                iconWrap.innerHTML = '<i id="os-flare-icon-preview" class="' + cls + '"></i>';
                            }
                        }

                        sel.addEventListener('change', function () {
                            customWrap.style.display = (sel.value === 'fa-custom') ? '' : 'none';
                            updateFlarePreview();
                        });
                        customInp.addEventListener('input', updateFlarePreview);
                        imgInp.addEventListener('input', updateFlarePreview);
                        updateFlarePreview();
                    })();
                </script>
                <tr>
                    <th scope="row"><label for="onlinesched_icon_fav_inactive">Favorite Icon (Inactive)</label></th>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <input type="text"
                                   name="onlinesched_icon_fav_inactive"
                                   id="onlinesched_icon_fav_inactive"
                                   value="<?php echo esc_attr(get_option('onlinesched_icon_fav_inactive', 'far fa-star')); ?>"
                                   placeholder="e.g. far fa-star, fas fa-paw, fa-regular fa-bone"
                                   class="regular-text" />
                            <span style="margin-left:12px; font-size:1.5em; width:30px; text-align:center; color:<?php echo esc_attr(get_option('onlinesched_color_fav_inactive', '#cccccc')); ?>;">
                                <i id="os-icon-inactive-preview" class="<?php echo esc_attr(get_option('onlinesched_icon_fav_inactive', 'far fa-star')); ?>"></i>
                            </span>
                        </div>
                        <p class="description">Font Awesome classes for the unselected favorite icon. Default: <code>far fa-star</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="onlinesched_icon_fav_active">Favorite Icon (Active)</label></th>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <input type="text"
                                   name="onlinesched_icon_fav_active"
                                   id="onlinesched_icon_fav_active"
                                   value="<?php echo esc_attr(get_option('onlinesched_icon_fav_active', 'fas fa-star')); ?>"
                                   placeholder="e.g. fas fa-star, fas fa-paw, fa-solid fa-bone"
                                   class="regular-text" />
                            <span style="margin-left:12px; font-size:1.5em; width:30px; text-align:center; color:<?php echo esc_attr(get_option('onlinesched_color_gold', '#f6c700')); ?>;">
                                <i id="os-icon-active-preview" class="<?php echo esc_attr(get_option('onlinesched_icon_fav_active', 'fas fa-star')); ?>"></i>
                            </span>
                        </div>
                        <p class="description">Font Awesome classes for the selected/active favorite icon. Default: <code>fas fa-star</code></p>
                    </td>
                </tr>
                <script>
                    (function() {
                        var inInput = document.getElementById('onlinesched_icon_fav_inactive');
                        var acInput = document.getElementById('onlinesched_icon_fav_active');
                        var inPrev  = document.getElementById('os-icon-inactive-preview');
                        var acPrev  = document.getElementById('os-icon-active-preview');

                        var inColor = document.getElementById('onlinesched_color_fav_inactive');
                        var acColor = document.getElementById('onlinesched_color_fav_active');

                        function updatePreview(input, preview, colorInput) {
                            if (!input || !preview) return;
                            var classes = input.value.trim() || (input.id.indexOf('active') !== -1 ? 'fas fa-star' : 'far fa-star');
                            preview.className = classes;

                            if (colorInput) {
                                preview.parentElement.style.color = colorInput.value;
                            }
                        }

                        if (inInput) {
                            inInput.addEventListener('input', function() { updatePreview(inInput, inPrev, inColor); });
                        }
                        if (acInput) {
                            acInput.addEventListener('input', function() { updatePreview(acInput, acPrev, acColor); });
                        }
                        if (inColor) {
                            inColor.addEventListener('input', function() { updatePreview(inInput, inPrev, inColor); });
                        }
                        if (acColor) {
                            acColor.addEventListener('input', function() { updatePreview(acInput, acPrev, acColor); });
                        }
                    })();
                </script>
                <tr>
                    <th scope="row">Restore Defaults</th>
                    <td>
                        <button type="button" class="button" id="onlinesched_restore_default_colors">Restore Default Colors</button>
                        <p class="description">Sets the color pickers back to the FM defaults. Click Save Changes to apply.</p>
                    </td>
                </tr>
            </table>
            <script>
                (function () {
                    var restoreButton = document.getElementById('onlinesched_restore_default_colors');
                    if (!restoreButton) {
                        return;
                    }

                    restoreButton.addEventListener('click', function () {
                        var inputs = document.querySelectorAll('input[type="color"][data-default-color]');
                        for (var i = 0; i < inputs.length; i++) {
                            var input = inputs[i];
                            input.value = input.getAttribute('data-default-color');
                        }
                    });
                }());
            </script>

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
        'calendar_subscriptions_enabled' => 'Schedule Calendar Subscriptions',
        'color_primary' => 'Primary Color',
        'color_secondary' => 'Secondary Color',
        'color_accent' => 'Accent Color',
        'color_gold' => 'Favorites Color',
        'color_danger' => 'Danger Color',
        'icon_fav_inactive' => 'Favorite Icon (Inactive)',
        'icon_fav_active' => 'Favorite Icon (Active)',
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
                    <?php
                    $default = 'calendar_subscriptions_enabled' === $key ? '1' : '';
                    onlinesched_config_status_row($label, onlinesched_get_config_status($key, $default));
                    ?>
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
