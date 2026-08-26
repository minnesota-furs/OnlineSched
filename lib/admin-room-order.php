<?php
/**
 * Drag-to-order UI for Room Sort Priority.
 *
 * Presentation only. The setting itself stays in the plugin because both the
 * schedule SQL and the app feed order rooms by it; this file just makes it
 * clickable. Delete the file, its require, and admin-room-order.js to go back
 * to the plain text field.
 */

function onlinesched_room_sort_priority_row()
{
    $option_name = 'onlinesched_room_sort_priority';
    $stored = (string) get_option($option_name, '');
    $chosen = array_values(array_filter(array_map('trim', explode(',', $stored))));

    $rooms = get_terms(array('taxonomy' => 'os_room', 'hide_empty' => false));
    if (is_wp_error($rooms)) {
        $rooms = array();
    }
    $names = array();
    foreach ($rooms as $room) {
        $names[$room->slug] = $room->name;
    }

    // A stored slug whose room is gone still shows, so an order is never
    // silently shortened by a room being renamed away.
    $ordered = array();
    foreach ($chosen as $slug) {
        $ordered[$slug] = isset($names[$slug]) ? $names[$slug] : $slug . ' (no such room)';
    }
    $pool = array_diff_key($names, $ordered);
    asort($pool);
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($option_name); ?>">Room Sort Priority</label></th>
        <td>
            <div id="onlinesched-room-order" style="display:none; max-width:760px;">
                <div style="display:flex; gap:18px;">
                    <div style="flex:1 1 0;">
                        <p><strong>Shown first, in this order</strong></p>
                        <ul class="onlinesched-room-list onlinesched-room-ordered" style="min-height:60px; margin:0; padding:6px; border:1px solid #c3c4c7; background:#fff;">
                            <?php foreach ($ordered as $slug => $name) : ?>
                                <li data-slug="<?php echo esc_attr($slug); ?>" style="padding:6px 8px; margin:0 0 4px; border:1px solid #dcdcde; background:#f6f7f7; cursor:grab;">
                                    <?php echo esc_html($name); ?>
                                    <a href="#" class="onlinesched-room-move" style="float:right;">remove</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="description onlinesched-room-empty" style="display:none;">
                            Nothing pinned, so every room sorts alphabetically.
                        </p>
                    </div>
                    <div style="flex:1 1 0;">
                        <p><strong>Alphabetical after those</strong></p>
                        <ul class="onlinesched-room-list onlinesched-room-pool" style="min-height:60px; margin:0; padding:6px; border:1px solid #c3c4c7; background:#fff;">
                            <?php foreach ($pool as $slug => $name) : ?>
                                <li data-slug="<?php echo esc_attr($slug); ?>" style="padding:6px 8px; margin:0 0 4px; border:1px solid #dcdcde; background:#f6f7f7; cursor:grab;">
                                    <?php echo esc_html($name); ?>
                                    <a href="#" class="onlinesched-room-move" style="float:right;">pin</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <p class="description">Drag to reorder, or use pin and remove. Saved as room slugs.</p>
            </div>

            <div class="onlinesched-room-typed">
                <input type="text" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>"
                       value="<?php echo esc_attr($stored); ?>" class="regular-text" />
                <p class="description">
                    Comma-separated room slugs, in the order they should appear. Rooms
                    left out follow alphabetically. Slugs are used so a room can be
                    renamed without losing its place.
                </p>
                <?php if (!empty($names)) : ?>
                    <p class="description"><strong>Available rooms</strong></p>
                    <ul class="description" style="margin:0 0 0 1em;">
                        <?php foreach ($names as $slug => $name) : ?>
                            <li><code><?php echo esc_html($slug); ?></code> &mdash; <?php echo esc_html($name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!isset($_GET['page']) || 'onlinesched-settings' !== $_GET['page']) {
        return;
    }
    $path = plugin_dir_path(__FILE__) . 'admin-room-order.js';
    wp_enqueue_script(
        'onlinesched-room-order',
        plugin_dir_url(__FILE__) . 'admin-room-order.js',
        array('jquery', 'jquery-ui-sortable'),
        file_exists($path) ? filemtime($path) : '1.0',
        true
    );
});
