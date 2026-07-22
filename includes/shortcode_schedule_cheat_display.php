<?php
// Shortcode: [ical_schedule_cheat_display]
// Outputs a reference for iCal endpoints and parameters
add_shortcode('ical_schedule_cheat_display', function() {
    // Enqueue the stylesheet only when shortcode is used
	wp_enqueue_style( 'online-schedule-css', plugin_dir_url(__DIR__)."build/main.css", array(),   filemtime(plugin_dir_path(__DIR__)."build/main.css"));

	if (!onlinesched_calendar_subscriptions_enabled()) {
		return '<div class="ical-cheat-sheet"><h2>Calendar Feed Reference</h2><p><strong>Full-schedule calendar subscriptions are currently disabled.</strong></p><p>Full and filtered schedule feeds return an empty calendar. Individual event calendar links remain available for events shown on the public schedule.</p></div>';
	}

    // Get plugin root URL (not includes/)
    $plugin_root_url = plugin_dir_url(__DIR__);
    $icalby_url = $plugin_root_url . 'icalby.php';
    $icalbyroom_url = $plugin_root_url . 'icalbyroom.php';
    $ical_url = $plugin_root_url . 'ical.php';

    // Get all room slugs
    $rooms = get_terms(array(
        'taxonomy' => 'os_room',
        'hide_empty' => false,
        'fields' => 'slugs',
    ));
    // Get all tag slugs
    $tags = get_terms(array(
        'taxonomy' => 'os_tag',
        'hide_empty' => false,
        'fields' => 'slugs',
    ));

    // Get all room terms (objects)
    $room_terms = get_terms(array(
        'taxonomy' => 'os_room',
        'hide_empty' => false,
    ));
    // Get all tag terms (objects)
    $tag_terms = get_terms(array(
        'taxonomy' => 'os_tag',
        'hide_empty' => false,
    ));

    // Use first 2 rooms and tags for examples
    $room_example = array_slice($rooms, 0, 2);
    $tag_example = array_slice($tags, 0, 2);
    $room_example_str = implode(',', $room_example);
    $tag_example_str = implode(',', $tag_example);
    $single_room = isset($rooms[0]) ? $rooms[0] : 'room1';
    $single_tag = isset($tags[0]) ? $tags[0] : 'tag1';

    ob_start();
    ?>
    <div class="ical-cheat-sheet">
        <h2>Calendar Feed Reference</h2>
        <h3>Endpoints</h3>
        <ul>
            <li><strong>All Events:</strong> <code><?php echo esc_html($icalby_url); ?></code> <br><em>Returns all events. Supports filtering by room, tag, and limit.</em></li>
            <li><strong>By Room:</strong> <code><?php echo esc_html($icalbyroom_url); ?>?room=&lt;room-slug&gt;</code> <br><em>Returns events for a specific room or rooms.</em></li>
            <li><strong>Single Event:</strong> <code><?php echo esc_html($ical_url); ?>?cal-id=&lt;event-id&gt;</code> <br><em>Returns a single event by its ID.</em></li>
        </ul>
        <h3>Parameters</h3>
        <table class="table table-bordered">
            <thead><tr><th>Parameter</th><th>Description</th><th>Example</th></tr></thead>
            <tbody>
                <tr><td>room / rooms</td><td>Room slug(s), comma separated. Use <code>all</code> for all rooms.</td><td>room=mainstage,workshop-room</td></tr>
                <tr><td>tag / tags</td><td>Tag slug(s), comma separated. Use <code>all</code> for all tags.</td><td>tag=essentials,restricted</td></tr>
                <tr><td>limit</td><td>Limit number of events returned</td><td>limit=5</td></tr>
                <tr><td>textlen</td><td>Max description length (default 250). <br>Set to 0 or negative for full description.</td><td>textlen=300</td></tr>
                <tr><td>cal-id</td><td>Event ID (for single event)</td><td>cal-id=1234</td></tr>
            </tbody>
        </table>
        <h3>Special Values</h3>
        <ul>
            <li><code>all</code> &mdash; Use for <strong>room</strong> or <strong>tag</strong> to include all rooms/tags.</li>
        </ul>
        <h3>Available Room Types</h3>
        <ul class="cheat-list cheat-list-rooms">
            <?php foreach ($room_terms as $room) {
                echo '<li><span class="cheat-title">' . esc_html($room->name) . '</span> <span class="cheat-slug">(' . esc_html($room->slug) . ')</span> <button class="cheat-copy-btn" onclick="navigator.clipboard.writeText(\'' . esc_js($room->slug) . '\')" aria-label="Copy slug" title="Copy slug">📋</button></li>';
            } ?>
        </ul>
        <h3>Available Tag Types</h3>
        <ul class="cheat-list cheat-list-tags">
            <?php foreach ($tag_terms as $tag) {
                echo '<li><span class="cheat-title">' . esc_html($tag->name) . '</span> <span class="cheat-slug">(' . esc_html($tag->slug) . ')</span> <button class="cheat-copy-btn" onclick="navigator.clipboard.writeText(\'' . esc_js($tag->slug) . '\')" aria-label="Copy slug" title="Copy slug">📋</button></li>';
            } ?>
        </ul>
        <h3>How to Use</h3>
        <ol>
            <li>Choose an endpoint above.</li>
            <li>Use parameters to filter by room, tag, or limit.</li>
            <li>Room/tag values can be comma separated for multiple values.</li>
            <li>Copy the example URL and replace with your desired values.</li>
        </ol>
        <h3>Example URLs (using current room/tag slugs)</h3>
        <p>These examples use real, current room and tag slugs from your schedule. Replace with others as needed.</p>
        <ul>
            <li><code><?php echo esc_html($icalby_url); ?>?room=<?php echo esc_html($room_example_str); ?>&amp;tag=<?php echo esc_html($tag_example_str); ?>&amp;limit=10&amp;textlen=300</code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?room=all&amp;tag=<?php echo esc_html($single_tag); ?></code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?room=<?php echo esc_html($single_room); ?>&amp;limit=5</code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?tag=all&amp;textlen=0</code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?room=<?php echo esc_html($room_example_str); ?>&amp;tag=<?php echo esc_html($tag_example_str); ?>&amp;limit=10</code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?tag=<?php echo esc_html($single_tag); ?></code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?room=all</code></li>
            <li><code><?php echo esc_html($icalby_url); ?>?tag=all</code></li>
            <li><code><?php echo esc_html($icalbyroom_url); ?>?room=<?php echo esc_html($single_room); ?></code></li>
            <li><code><?php echo esc_html($ical_url); ?>?cal-id=1234</code></li>
        </ul>
    </div>
    <script>
    // Add feedback for copy buttons
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.cheat-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          btn.textContent = '\u2714';
          setTimeout(function() { btn.textContent = '\uD83D\uDCCB'; }, 1200);
        });
      });
    });
    </script>
    <?php
    return ob_get_clean();
});
