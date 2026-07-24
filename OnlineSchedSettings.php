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
        .onlinesched-tab-panel {
            margin-top: 1em;
        }
        .onlinesched-app-info-page-list {
            margin: 0.5em 0;
            max-width: 40em;
        }
        .onlinesched-app-info-page-list li {
            display: flex;
            align-items: center;
            gap: 0.5em;
            padding: 4px 8px;
            border: 1px solid #ccd0d4;
            border-bottom: none;
            background: #fff;
        }
        .onlinesched-app-info-page-list li:last-child {
            border-bottom: 1px solid #ccd0d4;
        }
        .onlinesched-app-info-page-list .onlinesched-app-info-page-title {
            flex: 1 1 auto;
        }
        .onlinesched-app-info-page-list button.button-link {
            padding: 0 4px;
            text-decoration: none;
        }
        .onlinesched-app-info-page-list button.button-link:disabled {
            opacity: 0.35;
            cursor: not-allowed;
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
    onlinesched_register_main_setting('onlinesched_app_schedule_published', 'onlinesched_sanitize_checkbox');
    onlinesched_register_main_setting('onlinesched_con_start', 'onlinesched_sanitize_con_start');
    onlinesched_register_main_setting('onlinesched_con_end', 'onlinesched_sanitize_con_end');
    onlinesched_register_main_setting('onlinesched_public_date_start', 'onlinesched_sanitize_public_date_start');
    onlinesched_register_main_setting('onlinesched_public_date_end', 'onlinesched_sanitize_public_date_end');
    onlinesched_register_main_setting('onlinesched_app_info_page_ids', 'onlinesched_sanitize_page_id_csv');
    onlinesched_register_main_setting('onlinesched_room_sort_priority', 'sanitize_text_field');
    onlinesched_register_main_setting('onlinesched_time_min_step', 'onlinesched_sanitize_minute_step');
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

/**
 * Sanitizer for the event-form minute-dropdown increment.
 */
function onlinesched_sanitize_minute_step($value)
{
    $step = (int) $value;
    return in_array($step, array(1, 5, 15, 30), true) ? (string) $step : '15';
}

function onlinesched_minute_step_row()
{
    $current = (int) get_option('onlinesched_time_min_step', 15);
    $choices = array(
        30 => 'Half hour (:00, :30)',
        15 => 'Quarter hour (:00, :15, :30, :45)',
        5  => 'Five minutes',
        1  => 'Every minute',
    );
    ?>
    <tr>
        <th scope="row"><label for="onlinesched_time_min_step">Event Start-Time Minutes</label></th>
        <td>
            <select name="onlinesched_time_min_step" id="onlinesched_time_min_step">
                <?php foreach ($choices as $step => $label) : ?>
                    <option value="<?php echo esc_attr($step); ?>" <?php selected($current, $step); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Minute choices offered in the event edit form. An event whose saved minute is not on this grid still shows every minute while editing, so its time is never silently changed.</p>
        </td>
    </tr>
    <?php
}

/**
 * Sanitizer for an ordered comma-separated list of page IDs.
 */
function onlinesched_sanitize_page_id_csv($value)
{
    $ids = array();
    foreach (explode(',', (string)$value) as $piece) {
        $id = absint(trim($piece));
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return implode(',', $ids);
}

/**
 * Human label for a date-pair option, used only in the settings-error message below.
 */
function onlinesched_date_pair_label($option_name)
{
    $labels = array(
        'onlinesched_con_start' => 'Operational Start Date',
        'onlinesched_con_end' => 'Operational End Date',
        'onlinesched_public_date_start' => 'Public Start Date',
        'onlinesched_public_date_end' => 'Public End Date',
    );
    return isset($labels[$option_name]) ? $labels[$option_name] : $option_name;
}

/**
 * Sanitize one half of a start/end date pair, refusing to persist an inverted pair.
 *
 * register_setting() sanitize callbacks only ever see their own field's value, but
 * options.php processes every registered option from a single options.php submission
 * in one request, so $_POST already holds the paired field's submitted value at the
 * moment either callback runs — reading it here is safe and requires no extra hook.
 *
 * If both dates in the pair are valid and would end up inverted (start after end),
 * this field's submitted value is discarded in favor of its previously saved value,
 * and a settings error is surfaced once per pair (from the start-field callback only,
 * so the pair doesn't produce a duplicate message).
 */
function onlinesched_sanitize_date_pair($value, $option_name, $paired_option_name, $is_start_field)
{
    $sanitized = onlinesched_app_sanitize_date($value);

    $paired_raw = array_key_exists($paired_option_name, $_POST)
        ? wp_unslash($_POST[$paired_option_name])
        : get_option($paired_option_name, '');
    $paired_sanitized = onlinesched_app_sanitize_date($paired_raw);

    if ('' !== $sanitized && '' !== $paired_sanitized) {
        $start = $is_start_field ? $sanitized : $paired_sanitized;
        $end = $is_start_field ? $paired_sanitized : $sanitized;

        if ($start > $end) {
            if ($is_start_field) {
                add_settings_error(
                    $option_name,
                    'onlinesched_date_pair_inverted',
                    sprintf(
                        '%s must be on or before %s. The previous value was kept.',
                        onlinesched_date_pair_label($option_name),
                        onlinesched_date_pair_label($paired_option_name)
                    )
                );
            }
            return get_option($option_name, '');
        }
    }

    return $sanitized;
}

function onlinesched_sanitize_con_start($value)
{
    return onlinesched_sanitize_date_pair($value, 'onlinesched_con_start', 'onlinesched_con_end', true);
}

function onlinesched_sanitize_con_end($value)
{
    return onlinesched_sanitize_date_pair($value, 'onlinesched_con_end', 'onlinesched_con_start', false);
}

function onlinesched_sanitize_public_date_start($value)
{
    return onlinesched_sanitize_date_pair($value, 'onlinesched_public_date_start', 'onlinesched_public_date_end', true);
}

function onlinesched_sanitize_public_date_end($value)
{
    return onlinesched_sanitize_date_pair($value, 'onlinesched_public_date_end', 'onlinesched_public_date_start', false);
}

function onlinesched_app_feed_settings_rows()
{
    $published = get_option('onlinesched_app_schedule_published', '1');
    ?>
    <tr>
        <th scope="row">App Schedule Publication</th>
        <td>
            <input type="hidden" name="onlinesched_app_schedule_published" value="0" />
            <label for="onlinesched_app_schedule_published">
                <input type="checkbox" id="onlinesched_app_schedule_published" name="onlinesched_app_schedule_published" value="1"
                    <?php checked('1' === (string)$published); ?> />
                Publish the schedule to the app feed
            </label>
            <p class="description">Controls the JSON app feed's schedule section only. When disabled, the schedule section returns a successful empty response with <code>schedule_published: false</code> so app clients show an intentional pre-publication state; the meta, hours, and info sections stay available. Independent of the Schedule Calendar Subscriptions (ICS) setting above, and does not affect the public schedule page.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="onlinesched_con_start">Operational Start Date</label></th>
        <td>
            <input type="date" id="onlinesched_con_start" name="onlinesched_con_start"
                   value="<?php echo esc_attr(get_option('onlinesched_con_start', '')); ?>" />
            <p class="description">First day of convention operations, including pre-con setup days. App clients use this window for day tabs and con-week behavior.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="onlinesched_con_end">Operational End Date</label></th>
        <td>
            <input type="date" id="onlinesched_con_end" name="onlinesched_con_end"
                   value="<?php echo esc_attr(get_option('onlinesched_con_end', '')); ?>" />
            <p class="description">Last day of convention operations, including post-con activities such as dead dog.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="onlinesched_public_date_start">Public Start Date</label></th>
        <td>
            <input type="date" id="onlinesched_public_date_start" name="onlinesched_public_date_start"
                   value="<?php echo esc_attr(get_option('onlinesched_public_date_start', '')); ?>" />
            <p class="description">Official first convention day, for display.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="onlinesched_public_date_end">Public End Date</label></th>
        <td>
            <input type="date" id="onlinesched_public_date_end" name="onlinesched_public_date_end"
                   value="<?php echo esc_attr(get_option('onlinesched_public_date_end', '')); ?>" />
            <p class="description">Official last convention day, for display.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="onlinesched_app_info_page_select">App Info Pages</label></th>
        <td>
            <?php
            $app_info_saved_csv = get_option('onlinesched_app_info_page_ids', '');
            $app_info_selected_ids = array_filter(array_map('absint', explode(',', (string) $app_info_saved_csv)));
            $app_info_pages = onlinesched_get_page_selector_pages();
            $app_info_pages_by_id = array();
            foreach ($app_info_pages as $app_info_page) {
                $app_info_pages_by_id[(int) $app_info_page->ID] = $app_info_page;
            }
            ?>
            <div id="onlinesched-app-info-pages">
                <select id="onlinesched_app_info_page_select" class="regular-text onlinesched-page-select">
                    <option value="0"><?php echo esc_html('-- Select a Page --'); ?></option>
                    <?php foreach ($app_info_pages as $app_info_page) : ?>
                        <option value="<?php echo esc_attr($app_info_page->ID); ?>">
                            <?php echo esc_html(sprintf('%s%s', $app_info_page->post_title ? $app_info_page->post_title : '(no title)', 'publish' !== $app_info_page->post_status ? ' [' . $app_info_page->post_status . ']' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" id="onlinesched_app_info_page_add">Add</button>
                <?php if (empty($app_info_pages)) : ?>
                    <p class="description">No pages found. Create a WordPress page first, then return here.</p>
                <?php endif; ?>
                <ul id="onlinesched_app_info_page_list" class="onlinesched-app-info-page-list">
                    <?php
                    $app_info_selected_count = count($app_info_selected_ids);
                    foreach (array_values($app_info_selected_ids) as $app_info_selected_index => $app_info_selected_id) :
                        $app_info_selected_page = isset($app_info_pages_by_id[$app_info_selected_id]) ? $app_info_pages_by_id[$app_info_selected_id] : null;
                        $app_info_selected_title = $app_info_selected_page
                            ? ($app_info_selected_page->post_title ? $app_info_selected_page->post_title : '(no title)')
                            : sprintf('(page #%d not found)', $app_info_selected_id);
                        $app_info_selected_suffix = ($app_info_selected_page && 'publish' !== $app_info_selected_page->post_status) ? ' [' . $app_info_selected_page->post_status . ']' : '';
                        $app_info_selected_display_title = $app_info_selected_title . $app_info_selected_suffix;
                        $app_info_is_first = (0 === $app_info_selected_index);
                        $app_info_is_last = ($app_info_selected_index === $app_info_selected_count - 1);
                        ?>
                        <li data-page-id="<?php echo esc_attr($app_info_selected_id); ?>">
                            <span class="onlinesched-app-info-page-title"><?php echo esc_html($app_info_selected_display_title); ?></span>
                            <button type="button" class="button-link onlinesched-app-info-page-up" aria-label="<?php echo esc_attr(sprintf('Move %s up', $app_info_selected_display_title)); ?>" <?php disabled($app_info_is_first); ?>>&uarr;</button>
                            <button type="button" class="button-link onlinesched-app-info-page-down" aria-label="<?php echo esc_attr(sprintf('Move %s down', $app_info_selected_display_title)); ?>" <?php disabled($app_info_is_last); ?>>&darr;</button>
                            <button type="button" class="button-link onlinesched-app-info-page-remove" aria-label="<?php echo esc_attr(sprintf('Remove %s', $app_info_selected_display_title)); ?>">&times;</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <input type="hidden" id="onlinesched_app_info_page_ids" name="onlinesched_app_info_page_ids"
                       value="<?php echo esc_attr($app_info_saved_csv); ?>" />
            </div>
            <p class="description">Ordered pages served by the app feed's info section (parking, hotel, code of conduct, and similar). Only published pages appear in the feed. Choose a page and click Add, then use the arrows to reorder.</p>
        </td>
    </tr>
    <script>
        (function () {
            var wrap = document.getElementById('onlinesched-app-info-pages');
            if (!wrap) {
                return;
            }
            var select = document.getElementById('onlinesched_app_info_page_select');
            var addButton = document.getElementById('onlinesched_app_info_page_add');
            var list = document.getElementById('onlinesched_app_info_page_list');
            var hiddenInput = document.getElementById('onlinesched_app_info_page_ids');

            function rows() {
                return list.querySelectorAll('li[data-page-id]');
            }

            function currentIds() {
                var ids = [];
                rows().forEach(function (li) {
                    ids.push(li.getAttribute('data-page-id'));
                });
                return ids;
            }

            function syncHiddenInput() {
                hiddenInput.value = currentIds().join(',');
            }

            // Keep Up disabled on the first row and Down disabled on the last row after
            // every add / remove / reorder, matching what PHP renders on initial load.
            function refreshRowControls() {
                var items = rows();
                items.forEach(function (li, index) {
                    var upBtn = li.querySelector('.onlinesched-app-info-page-up');
                    var downBtn = li.querySelector('.onlinesched-app-info-page-down');
                    if (upBtn) {
                        upBtn.disabled = (index === 0);
                    }
                    if (downBtn) {
                        downBtn.disabled = (index === items.length - 1);
                    }
                });
            }

            // A disabled button can't hold focus. If the control the user just used became
            // disabled by moving to the edge, move focus to another enabled control in the
            // same row instead of letting focus silently fall back to <body>.
            function focusRowControl(li, preferredButton) {
                if (preferredButton && !preferredButton.disabled) {
                    preferredButton.focus();
                    return;
                }
                var fallback = li.querySelector('button:not(:disabled)');
                if (fallback) {
                    fallback.focus();
                }
            }

            function makeButton(className, label, html) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = className;
                button.setAttribute('aria-label', label);
                button.innerHTML = html;
                return button;
            }

            function addPage(id, label) {
                if (!id || id === '0' || currentIds().indexOf(id) !== -1) {
                    return;
                }
                var li = document.createElement('li');
                li.setAttribute('data-page-id', id);

                var titleSpan = document.createElement('span');
                titleSpan.className = 'onlinesched-app-info-page-title';
                titleSpan.textContent = label;
                li.appendChild(titleSpan);

                // Labels carry the page title, matching what PHP renders for pre-populated
                // rows. Reorder never rebuilds a button (insertBefore only moves the existing
                // <li>), so a row's labels stay correctly attached to its title as it moves.
                li.appendChild(makeButton('button-link onlinesched-app-info-page-up', 'Move ' + label + ' up', '&uarr;'));
                li.appendChild(makeButton('button-link onlinesched-app-info-page-down', 'Move ' + label + ' down', '&darr;'));
                li.appendChild(makeButton('button-link onlinesched-app-info-page-remove', 'Remove ' + label, '&times;'));

                list.appendChild(li);
                syncHiddenInput();
                refreshRowControls();
            }

            addButton.addEventListener('click', function () {
                var id = select.value;
                if (!id || id === '0') {
                    return;
                }
                var label = select.options[select.selectedIndex].text;
                addPage(id, label);
                select.value = '0';
            });

            list.addEventListener('click', function (e) {
                var target = e.target;
                if (target.disabled) {
                    return;
                }
                var li = target.closest('li[data-page-id]');
                if (!li) {
                    return;
                }

                if (target.classList.contains('onlinesched-app-info-page-remove')) {
                    // Capture neighbors before removal — li.nextElementSibling/
                    // previousElementSibling are only meaningful while still attached.
                    var nextLi = li.nextElementSibling;
                    var prevLi = li.previousElementSibling;

                    li.parentNode.removeChild(li);
                    syncHiddenInput();
                    refreshRowControls();

                    // The removed button is gone, so focus never lands on it — always send it
                    // somewhere deliberate instead of letting it fall back to <body>.
                    var nextFocus = null;
                    if (nextLi) {
                        nextFocus = nextLi.querySelector('.onlinesched-app-info-page-remove');
                    } else if (prevLi) {
                        nextFocus = prevLi.querySelector('.onlinesched-app-info-page-remove');
                    }
                    (nextFocus || addButton).focus();
                } else if (target.classList.contains('onlinesched-app-info-page-up')) {
                    var prev = li.previousElementSibling;
                    if (prev) {
                        list.insertBefore(li, prev);
                        syncHiddenInput();
                        refreshRowControls();
                        focusRowControl(li, target);
                    }
                } else if (target.classList.contains('onlinesched-app-info-page-down')) {
                    var next = li.nextElementSibling;
                    if (next) {
                        list.insertBefore(next, li);
                        syncHiddenInput();
                        refreshRowControls();
                        focusRowControl(li, target);
                    }
                }
            });

            refreshRowControls();
        })();
    </script>
    <?php
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
    $saved_value = absint(get_option($option_name, $default));
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <?php /* Disabled inputs never submit — this hidden field carries the saved value
                     forward whenever the visible control above is disabled, so a managed-in-code
                     row can never blank the stored fallback on save. */ ?>
            <input type="hidden" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($saved_value); ?>" />
            <input type="number" min="0" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>"
                   value="<?php echo esc_attr($saved_value); ?>" class="small-text"
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
    $saved_value = sanitize_hex_color(get_option($option_name, $default)) ?: $default;
    $constant_name = onlinesched_get_constant_name($key);
    $filter_name = 'os_config_' . sanitize_key($key);
    $managed_by_constant = defined($constant_name);
    $managed_by_filter = has_filter($filter_name);
    $managed_in_code = $managed_by_constant || $managed_by_filter;
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <?php /* Disabled inputs never submit — this hidden field carries the raw saved
                     DB value forward whenever the visible control below is disabled, so a
                     managed-in-code row can never blank (or silently overwrite with today's
                     constant/filter value) the dormant stored fallback on save. */ ?>
            <input type="hidden" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($saved_value); ?>" />
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

/**
 * Slug => label for the Event Settings tabs, in display order.
 *
 * Single source of truth for the nav-tab-wrapper links and the matching
 * onlinesched-tab-panel wrappers, so both always agree on what tabs exist.
 */
function onlinesched_settings_tabs()
{
    return array(
        'basic-setup' => 'Basic Setup',
        'calendar-subscriptions' => 'Schedule Calendar Subscriptions',
        'app-feed' => 'App Feed',
        'advanced-display' => 'Advanced Display',
        'appearance' => 'Appearance',
    );
}

/**
 * The tab to show on render: from ?tab= when it names a real tab, else the first tab.
 *
 * Resolved server-side (not just client-side) so a full page load — including the
 * options.php save redirect and JS-disabled navigation — always lands on the right
 * panel without waiting on JS to run.
 */
function onlinesched_active_settings_tab()
{
    $tabs = onlinesched_settings_tabs();
    $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    return array_key_exists($requested, $tabs) ? $requested : array_key_first($tabs);
}

function OnlineSched_options_page()
{
    $tabs = onlinesched_settings_tabs();
    $active_tab = onlinesched_active_settings_tab();
    $tab_base_url = admin_url('edit.php?post_type=os_event&page=onlinesched-settings');
    ?>
    <div class="wrap">
        <h1>Event Schedule Settings</h1>
        <?php
        // Custom settings pages don't get WordPress's automatic "Settings
        // saved." notice; surface it from the options.php redirect flag.
        if (isset($_GET['settings-updated']) && !get_settings_errors()) {
            add_settings_error('onlinesched_messages', 'onlinesched_settings_saved', 'Settings saved.', 'updated');
        }
        settings_errors();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('onlinesched_option_group'); ?>

            <h2 class="nav-tab-wrapper" id="onlinesched-settings-tabs" role="tablist">
                <?php foreach ($tabs as $tab_slug => $tab_label) :
                    $is_active_tab = ($tab_slug === $active_tab);
                    ?>
                    <a
                        href="<?php echo esc_url(add_query_arg('tab', $tab_slug, $tab_base_url)); ?>"
                        class="nav-tab<?php echo $is_active_tab ? ' nav-tab-active' : ''; ?>"
                        data-tab="<?php echo esc_attr($tab_slug); ?>"
                        id="onlinesched-tab-<?php echo esc_attr($tab_slug); ?>"
                        role="tab"
                        aria-selected="<?php echo $is_active_tab ? 'true' : 'false'; ?>"
                        aria-controls="onlinesched-tab-panel-<?php echo esc_attr($tab_slug); ?>"
                    ><?php echo esc_html($tab_label); ?></a>
                <?php endforeach; ?>
            </h2>

            <div class="onlinesched-tab-panel" data-tab="basic-setup" id="onlinesched-tab-panel-basic-setup" role="tabpanel" aria-labelledby="onlinesched-tab-basic-setup"<?php echo ('basic-setup' === $active_tab) ? '' : ' style="display:none;"'; ?>>
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
            </div>

            <div class="onlinesched-tab-panel" data-tab="calendar-subscriptions" id="onlinesched-tab-panel-calendar-subscriptions" role="tabpanel" aria-labelledby="onlinesched-tab-calendar-subscriptions"<?php echo ('calendar-subscriptions' === $active_tab) ? '' : ' style="display:none;"'; ?>>
            <h2>Schedule Calendar Subscriptions</h2>
            <table class="form-table" role="presentation">
                <?php onlinesched_calendar_subscriptions_setting_row(); ?>
            </table>
            </div>

            <div class="onlinesched-tab-panel" data-tab="app-feed" id="onlinesched-tab-panel-app-feed" role="tabpanel" aria-labelledby="onlinesched-tab-app-feed"<?php echo ('app-feed' === $active_tab) ? '' : ' style="display:none;"'; ?>>
            <h2>App Feed</h2>
            <p>Settings for the JSON app feed consumed by mobile companion apps and other structured clients.</p>
            <table class="form-table" role="presentation">
                <?php onlinesched_app_feed_settings_rows(); ?>
            </table>
            </div>

            <div class="onlinesched-tab-panel" data-tab="advanced-display" id="onlinesched-tab-panel-advanced-display" role="tabpanel" aria-labelledby="onlinesched-tab-advanced-display"<?php echo ('advanced-display' === $active_tab) ? '' : ' style="display:none;"'; ?>>
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
                onlinesched_minute_step_row();
                ?>
            </table>
            </div>

            <div class="onlinesched-tab-panel" data-tab="appearance" id="onlinesched-tab-panel-appearance" role="tabpanel" aria-labelledby="onlinesched-tab-appearance"<?php echo ('appearance' === $active_tab) ? '' : ' style="display:none;"'; ?>>
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
            </div>

            <script>
                (function () {
                    var tabWrapper = document.getElementById('onlinesched-settings-tabs');
                    if (!tabWrapper) {
                        return;
                    }
                    var tabs = Array.prototype.slice.call(tabWrapper.querySelectorAll('.nav-tab'));
                    var panels = document.querySelectorAll('.onlinesched-tab-panel');
                    // settings_fields() always renders this hidden field; options.php redirects
                    // Save back to whatever URL it holds. Real navigation (JS off) keeps it in
                    // sync automatically because the browser reloads with ?tab= in the address
                    // bar; the JS-driven instant switch below has to update it by hand or Save
                    // always returns to whichever tab was active on page load.
                    var refererInput = document.querySelector('input[name="_wp_http_referer"]');

                    // Full WAI-ARIA APG tabs pattern (automatic activation), applied only once
                    // JS is confirmed running: PHP renders plain, naturally-tabbable links (so
                    // JS-off keyboard/mouse users can still reach and click every tab); JS then
                    // layers on roving tabindex (only the active tab is Tab-reachable) and
                    // ArrowLeft/ArrowRight/Home/End to move focus+activation between tabs.
                    //
                    // The links are real, fully-qualified hrefs (?tab=... included) rendered by
                    // PHP, so activation only ever needs to toggle classes/visibility/tabindex —
                    // no need to recompute or validate the target tab id here.
                    function activateTab(tab, opts) {
                        var tabId = tab.getAttribute('data-tab');
                        var tabHref = tab.getAttribute('href');
                        var moveFocus = !opts || false !== opts.moveFocus;

                        tabs.forEach(function (candidate) {
                            var isActive = candidate === tab;
                            candidate.classList.toggle('nav-tab-active', isActive);
                            candidate.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            candidate.setAttribute('tabindex', isActive ? '0' : '-1');
                        });
                        panels.forEach(function (panel) {
                            panel.style.display = (panel.getAttribute('data-tab') === tabId) ? '' : 'none';
                        });

                        history.replaceState(null, '', tabHref);
                        if (refererInput) {
                            refererInput.value = tabHref;
                        }
                        if (moveFocus) {
                            tab.focus();
                        }
                    }

                    // Roving tabindex starts here: PHP doesn't render tabindex at all (so
                    // JS-off users get normal link tab order), JS sets it up on init.
                    tabs.forEach(function (tab) {
                        tab.setAttribute('tabindex', tab.classList.contains('nav-tab-active') ? '0' : '-1');

                        tab.addEventListener('click', function (e) {
                            e.preventDefault();
                            activateTab(tab, { moveFocus: false });
                        });
                    });

                    tabWrapper.addEventListener('keydown', function (e) {
                        var currentIndex = tabs.indexOf(document.activeElement);
                        if (currentIndex === -1) {
                            return;
                        }

                        var targetIndex = null;
                        if ('ArrowRight' === e.key) {
                            targetIndex = (currentIndex + 1) % tabs.length;
                        } else if ('ArrowLeft' === e.key) {
                            targetIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                        } else if ('Home' === e.key) {
                            targetIndex = 0;
                        } else if ('End' === e.key) {
                            targetIndex = tabs.length - 1;
                        }

                        if (null !== targetIndex) {
                            e.preventDefault();
                            activateTab(tabs[targetIndex]);
                        }
                    });
                })();
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
