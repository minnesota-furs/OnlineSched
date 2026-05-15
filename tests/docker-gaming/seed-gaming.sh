#!/bin/bash
# seed-gaming.sh: High-Load Gaming & Furry Demo Seeder 🐺🎲⛓️
# Full den structure for the "Dragon's Hoard."
# Robustness patterns pulled from the Floof Den (seed-furry.sh).

set -e

CONTAINER="onlinesched-gaming-cli"
SITE_URL="http://localhost:8083"
ADMIN_USER="admin"
ADMIN_PASS_FILE=".wp_admin_pass"
if [ ! -f "$ADMIN_PASS_FILE" ]; then
  openssl rand -base64 16 > "$ADMIN_PASS_FILE"
fi
ADMIN_PASS=$(cat "$ADMIN_PASS_FILE")
ADMIN_EMAIL="gaming@example.com"

echo "🎲 Initiating the Dragon's Hoard full structural pounce..."

# Helper to run WP command
wp_run() {
  docker exec -u 0:0 "$CONTAINER" wp "$@" --allow-root
}

# Wait for database and WordPress to be ready
echo "  Waiting for database..."
RETRY_COUNT=0
MAX_RETRIES=10
until wp_run core is-installed >/dev/null 2>&1 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
  if wp_run core install --url="$SITE_URL" --title="Dragon's Hoard" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email 2>/dev/null; then
    break
  fi
  sleep 5
  RETRY_COUNT=$((RETRY_COUNT + 1))
done
echo "✓ WordPress base response detected."

echo "  Scouring the dungeon (Wiping database)..."
docker exec onlinesched-gaming-db mysql -u wordpress -pwordpress -e "DROP DATABASE IF EXISTS wordpress; CREATE DATABASE wordpress;"

echo "  Re-initializing clean WordPress..."
wp_run core install --url="$SITE_URL" --title="Dragon's Hoard Gaming & Furry Demo" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email

echo "Installing and activating Astra theme..."
wp_run theme install astra --activate

echo "Activating OnlineSched plugin..."
wp_run plugin activate OnlineSched

echo "Injecting Horde Overdrive (Dragon Styling)..."
cat <<'EOF' > /tmp/horde-overdrive.php
<?php
/* Plugin Name: Horde Overdrive */
add_action('wp_head', function() {
    echo '<style>
        #schedule {
            background-color: #1a1a1a;
            color: #eee;
        }
        .os-row.schedule-item {
            background: rgba(40, 40, 40, 0.9);
            border-bottom: 1px solid #444;
        }
        .schedule-title a { color: #ff4500 !important; }
        .os-badge { border-radius: 0; text-transform: uppercase; font-weight: 900; }
    </style>';
});
EOF
docker cp /tmp/horde-overdrive.php "onlinesched-gaming-wp":/var/www/html/wp-content/plugins/
wp_run plugin activate horde-overdrive

echo "Configuring OnlineSched pages..."
# Separate Schedule Page
SCHEDULE_ID=$(wp_run post create --post_type=page --post_title="The Hoard (Schedule)" --post_name="schedule" --post_status=publish --porcelain)
wp_run post meta update $SCHEDULE_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_schedule_page_id $SCHEDULE_ID

# Separate Kiosk Page
KIOSK_ID=$(wp_run post create --post_type=page --post_title="Hoard Display (Kiosk)" --post_name="kiosk-schedule" --post_status=publish --porcelain)
wp_run post meta update $KIOSK_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_kiosk_page_id $KIOSK_ID

echo "Establishing Navigation Menu..."
wp_run menu create "Main Menu"
wp_run menu item add-post main-menu $SCHEDULE_ID
wp_run menu item add-post main-menu $KIOSK_ID
wp_run menu location assign main-menu primary

echo "Setting Front Page..."
wp_run option update show_on_front page
wp_run option update page_on_front $SCHEDULE_ID

echo "Configuring Dragon's Hoard branding..."
wp_run option update onlinesched_color_primary "#8b0000" # Dragon Red
wp_run option update onlinesched_color_secondary "#2f4f4f" # Dark Slate
wp_run option update onlinesched_tab_programming_label "The Hunt"
wp_run option update onlinesched_year $(date +%Y)

# Enable Header Flare with the centralized coyote
wp_run option update onlinesched_enable_header_flare 1
wp_run option update onlinesched_header_flare_image "/wp-content/plugins/OnlineSched/tests/demo-assets/coyote.svg"

# Create Rooms (20+ rooms)
echo "🏰 Establishing 20 rooms in the dungeon..."
for i in {1..20}; do
  wp_run term create os_room "Dungeon Room $i" --description="Gaming space $i" > /dev/null
done

# Create Taxonomy Terms (Mixed Gaming and Furry)
echo "⚔️ Marking the combined horde tracks..."
# Gaming Tracks
wp_run term create os_tag "D&D 5E" > /dev/null
wp_run term create os_tag "Board Games" > /dev/null
wp_run term create os_tag "CCG" > /dev/null
wp_run term create os_tag "Warhammer" > /dev/null
wp_run term create os_tag "Video Games" > /dev/null
wp_run term create os_tag "LARP" > /dev/null
wp_run term create os_tag "Retro Gaming" > /dev/null
# Furry Tracks
wp_run term create os_tag "Fursuiting" > /dev/null
wp_run term create os_tag "Art" > /dev/null
wp_run term create os_tag "Social" > /dev/null
wp_run term create os_tag "Writing" > /dev/null
wp_run term create os_tag "Music" > /dev/null

# Generate Events via WP Eval (Robust Internal Loop)
echo "🐲 Unleashing the mixed horde (500 events for high-load test)..."
wp_run eval '
$room_ids = get_terms(["taxonomy" => "os_room", "fields" => "ids", "hide_empty" => false]);
$tag_ids = get_terms(["taxonomy" => "os_tag", "fields" => "ids", "hide_empty" => false]);

if (empty($room_ids) || empty($tag_ids)) {
    echo "Error: No rooms or tags found. Cannot leash the horde.\n";
    exit(1);
}

$gaming_titles = ["Epic Quest", "Boss Fight", "Dungeon Crawl", "Dice Roll", "Dragon Hunt", "Hoard Raid", "Level Up Session", "Guild Meet", "Tabletop Strategy", "RPG Campaign", "Trading Card Tournament", "Miniature Painting", "Speedrun Challenge", "VR Experience", "Retro Arcade Free Play", "Fighting Game Bracket", "Raid Boss Encounter"];
$furry_titles = ["Fursuit Walk", "Art Jam", "Puppy Pile", "Howl Session", "Paw Meet", "Tail Wag", "Anthro Social", "Critter Craft", "Fursuit Games", "Dance Competition", "Charity Auction", "Furry Writers Meetup", "Photography Walk", "Squeaker Testing", "Muzzle Nuzzles", "Species Meet", "Fursuit Parade Prep"];

$current_year = get_option("onlinesched_year") ?: date("Y");
$base_time = time() - (12 * 3600);

for ($i = 1; $i <= 500; $i++) {
    $is_furry = (rand(0, 1) === 0);
    $base_title = $is_furry ? $furry_titles[array_rand($furry_titles)] : $gaming_titles[array_rand($gaming_titles)];

    $event_id = wp_insert_post([
        "post_type" => "os_event",
        "post_title" => "$base_title #$i",
        "post_status" => "publish"
    ]);

    if ($event_id) {
        wp_set_object_terms($event_id, (int)$room_ids[array_rand($room_ids)], "os_room");
        wp_set_object_terms($event_id, (int)$tag_ids[array_rand($tag_ids)], "os_tag");

        $random_offset = rand(0, 48 * 3600);
        $sort_time = $base_time + $random_offset;

        update_post_meta($event_id, "onlinesched_sorttime", $sort_time);
        update_post_meta($event_id, "onlinesched_timelen", 60);
        update_post_meta($event_id, "onlinesched_year", $current_year);
    }

    if ($i % 50 === 0) {
        echo "  ... $i events unleashed.\n";
    }
}
'

echo "🏁 The Dragon's Hoard is established. Awooo! 🎲🔥"
echo "--------------------------------------------------"
echo "Admin URL     : $SITE_URL/wp-admin"
echo "Admin User    : $ADMIN_USER"
echo "Admin Password: $ADMIN_PASS (Saved to $ADMIN_PASS_FILE)"
echo "--------------------------------------------------"
