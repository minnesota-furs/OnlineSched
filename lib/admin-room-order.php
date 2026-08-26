<?php
/**
 * Drag-to-order UI for Room Sort Priority. Presentation only: the ordering is
 * shared with the app feed, but this widget is not.
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
            <div id="onlinesched-room-order" hidden style="max-width:820px;">
                <div style="display:flex; gap:18px;">
                    <div style="flex:1 1 0;">
                        <p><strong>Shown first, in this order</strong></p>
                        <ul class="onlinesched-room-list onlinesched-room-ordered" style="min-height:60px; margin:0; padding:6px; border:1px solid #c3c4c7; background:#fff;">
                            <?php foreach ($ordered as $slug => $name) : ?>
                                <?php onlinesched_room_order_item($slug, $name, true); ?>
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
                                <?php onlinesched_room_order_item($slug, $name, false); ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <p class="description">
                    Drag a room, or use the arrows and Pin. Saved as room slugs.
                </p>
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

function onlinesched_enqueue_room_order_assets()
{
    if (!isset($_GET['page']) || 'onlinesched-settings' !== $_GET['page']) {
        return;
    }
    $path = plugin_dir_path(__FILE__) . 'admin-room-order.js';
    wp_enqueue_script(
        'onlinesched-room-order',
        plugin_dir_url(__FILE__) . 'admin-room-order.js',
        array(),
        file_exists($path) ? filemtime($path) : '1.0',
        true
    );
}

add_action('admin_enqueue_scripts', 'onlinesched_enqueue_room_order_assets');

function onlinesched_room_order_item($slug, $name, $pinned)
{
    ?>
    <li draggable="true" data-slug="<?php echo esc_attr($slug); ?>"
        style="display:flex; align-items:center; gap:6px; padding:6px 8px; margin:0 0 4px; border:1px solid #dcdcde; background:#f6f7f7; cursor:grab;">
        <span style="flex:1 1 auto;"><?php echo esc_html($name); ?></span>
        <button type="button" class="button button-small onlinesched-room-nudge" data-move="up"
                aria-label="Move <?php echo esc_attr($name); ?> up"<?php echo $pinned ? '' : ' hidden'; ?>>&uarr;</button>
        <button type="button" class="button button-small onlinesched-room-nudge" data-move="down"
                aria-label="Move <?php echo esc_attr($name); ?> down"<?php echo $pinned ? '' : ' hidden'; ?>>&darr;</button>
        <button type="button" class="button button-small onlinesched-room-toggle"
                aria-label="<?php echo esc_attr(($pinned ? 'Remove ' : 'Pin ') . $name); ?>"
                data-room="<?php echo esc_attr($name); ?>">
            <?php echo $pinned ? 'Remove' : 'Pin'; ?>
        </button>
    </li>
    <?php
}
