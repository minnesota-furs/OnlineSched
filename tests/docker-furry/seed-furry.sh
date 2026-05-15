#!/bin/bash
# @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
# OnlineSched: Furry Fun Demo Seeding (The Floof Den)

set -e

CONTAINER="onlinesched-furry-cli"
SITE_URL="http://localhost:8082"
ADMIN_USER="admin"
ADMIN_PASS_FILE=".wp_admin_pass"
if [ ! -f "$ADMIN_PASS_FILE" ]; then
  openssl rand -base64 16 > "$ADMIN_PASS_FILE"
fi
ADMIN_PASS=$(cat "$ADMIN_PASS_FILE")
ADMIN_EMAIL="furry@example.com"

echo "🐺 Initializing The Floof Den..."

# Helper to run wp-cli inside the dedicated container
wp_run() {
  docker exec -u 0:0 "$CONTAINER" wp "$@" --allow-root
}

# Wait for database and WordPress to be ready
echo "  Waiting for database..."
RETRY_COUNT=0
MAX_RETRIES=10
until wp_run core is-installed >/dev/null 2>&1 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
  if wp_run core install --url="$SITE_URL" --title="The Floof Den" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email 2>/dev/null; then
    break
  fi
  sleep 5
  RETRY_COUNT=$((RETRY_COUNT + 1))
done
echo "✓ WordPress installed."

echo "  Wiping database..."
# Wipe directly via the DB container to avoid WP-CLI SSL issues
docker exec onlinesched-furry-db mysql -u wordpress -pwordpress -e "DROP DATABASE IF EXISTS wordpress; CREATE DATABASE wordpress;"

# Re-install after reset to ensure clean state with our user
echo "  Re-initializing clean WordPress..."
wp_run core install --url="$SITE_URL" --title="The Floof Den" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email

echo "Installing and activating Astra theme..."
wp_run theme install astra --activate

echo "Activating OnlineSched plugin..."
wp_run plugin activate OnlineSched

echo "Configuring pretty permalinks..."
wp_run rewrite structure '/%postname%/' --hard
wp_run rewrite flush --hard

echo "Injecting Floof Overdrive (CSS & Content Fluff)..."
cat <<'EOF' > /tmp/floof-overdrive.php
<?php
/* Plugin Name: Floof Overdrive */
add_action('wp_head', function() {
    echo '<style>
        /* Repeating Coyote Background for the Schedule */
        #schedule {
            background-image: url("/wp-content/plugins/OnlineSched/tests/demo-assets/coyote.svg");
            background-size: 150px;
            background-repeat: repeat;
            background-attachment: fixed;
            background-color: rgba(253, 252, 240, 0.95);
            background-blend-mode: overlay;
        }

        /* Make the schedule card slightly transparent to see the paws */
        .os-row.schedule-item { background: rgba(255,255,255,0.9); }
    </style>';
});

// Update boring sample page content
add_action('init', function() {
    $sample_page = get_page_by_path('sample-page');
    if ($sample_page) {
        wp_update_post([
            'ID' => $sample_page->ID,
            'post_title' => 'Welcome to the Den!',
            'post_content' => '<!-- wp:paragraph --><p>Bark pupsem sniffer snoot paw bork. Waggy tail floof clouds pupperino shoob maximum borkdrive. 🐺</p><!-- /wp:paragraph -->
            <!-- wp:paragraph --><p>Borkdrive heckin good boye clouds smol bork. Porgo long doggo fluffer. 🦊 The fox is napping here, but you are welcome to join the hunt! Sniff out the best events and mark them with your paw (the favorite icon) to track your prey.</p><!-- /wp:paragraph -->
            <!-- wp:paragraph --><p>Heckin good boye smol bork drive shoob doggo. Clouds fluffer bark snoot snoot. Awooo!</p><!-- /wp:paragraph -->'
        ]);
    }

    // Replace the default "Hello world!" post
    $hello_world = get_page_by_path('hello-world', OBJECT, 'post');
    if ($hello_world) {
        wp_update_post([
            'ID' => $hello_world->ID,
            'post_title' => 'Welcome to the Hunt! 🐺🦊',
            'post_content' => '<!-- wp:paragraph --><p>Awoooo! Welcome to your first hunt in the Floof Den. This post is just marking the territory. Bark pupsem sniffer snoot paw bork. 🐾</p><!-- /wp:paragraph -->
            <!-- wp:paragraph --><p>Waggy tail floof clouds pupperino shoob maximum borkdrive. Feel the fur, be the fur. OwO!</p><!-- /wp:paragraph -->'
        ]);

        // Update the default comment to be a sniff
        $comments = get_comments(['post_id' => $hello_world->ID]);
        foreach($comments as $comment) {
            wp_update_comment([
                'comment_ID' => $comment->comment_ID,
                'comment_content' => 'Sniff sniff... smells like a fresh pack member! Awooo! 🐾'
            ]);
        }
    }
});
EOF
docker cp /tmp/floof-overdrive.php "onlinesched-furry-wp":/var/www/html/wp-content/plugins/
wp_run plugin activate floof-overdrive

echo "Applying Convention-Style Branding..."
# ADA Compliant "Alpha" Palette
wp_run option update onlinesched_color_primary "#d12229" # Alpha Red (Contrast 5.48)
wp_run option update onlinesched_color_secondary "#2c3e50" # Midnight Blue
wp_run option update onlinesched_color_accent "#e67e22" # Fox Orange
wp_run option update onlinesched_tab_programming_label "The Hunt"
wp_run option update onlinesched_tab_hours_label "Den Times"

# Dynamic Favorite Icons (Phase 10.5) - ADA Heart
wp_run option update onlinesched_icon_fav_inactive "far fa-heart"
wp_run option update onlinesched_icon_fav_active "fas fa-heart"
wp_run option update onlinesched_color_fav_inactive "#767676" # ADA Grey
wp_run option update onlinesched_color_fav_active "#d12229"   # Alpha Red

# Enable Header Flare with a custom SVG
wp_run option update onlinesched_enable_header_flare 1
wp_run option update onlinesched_header_flare_image "/wp-content/plugins/OnlineSched/tests/demo-assets/coyote.svg"

echo "Configuring Custom Badge Colors..."
# JSON format: { "label_slug": { "bg": "#hex", "text": "#hex" } }
BADGE_COLORS='{
  "adult": { "bg": "#d12229", "text": "#ffffff" },
  "sensory": { "bg": "#2980b9", "text": "#ffffff" },
  "workshop": { "bg": "#27ae60", "text": "#ffffff" },
  "vip": { "bg": "#f1c40f", "text": "#000000" }
}'
wp_run option update onlinesched_badge_types_colors "$BADGE_COLORS" --format=json

echo "Creating The Floof Den Maps (Multi-Floor)..."
MAP_CONTENT='<div class="os-maps">
<div style="text-align:right; font-size: 2em; opacity: 0.1; margin-bottom: -40px;"><i class="fas fa-paw"></i> <i class="fas fa-wolf-pack-battalion"></i></div>
<h2>Floor 1: The Gathering Ground <i class="fas fa-paw" style="color:#d12229; font-size: 0.6em;"></i></h2>
<p>Bark pupsem sniffer snoot paw bork. Waggy tail floof clouds pupperino shoob maximum borkdrive. 🐺 A wild fox appears!</p>
<div class="os-row location-list">
  <div class="os-col-sm-6"><div class="location-item" style="border-left-color: #d12229;"><span class="location-name"><i class="fas fa-wolf-pack-battalion"></i> ALPHA STAGE:</span> Grand Ballroom</div></div>
  <div class="os-col-sm-6"><div class="location-item"><span class="location-name"><i class="fas fa-utensils"></i> THE KITCHEN:</span> Consuite <i class="fas fa-paw" style="font-size: 0.8em; opacity:0.3;"></i></div></div>
</div>

<hr />

<h2>Floor 2: The High Den <i class="fas fa-paw" style="color:#d12229; font-size: 0.6em;"></i></h2>
<p>Borkdrive heckin good boye clouds smol bork. Porgo long doggo fluffer. 🦊 The fox is napping here.</p>
<div class="os-row location-list">
  <div class="os-col-sm-6"><div class="location-item"><span class="location-name"><i class="fas fa-door-open"></i> DEN A:</span> Panel Room 1 <i class="fas fa-paw" style="font-size: 0.8em; opacity:0.3;"></i></div></div>
  <div class="os-col-sm-6"><div class="location-item"><span class="location-name"><i class="fas fa-door-open"></i> DEN B:</span> Panel Room 2 <i class="fas fa-paw" style="font-size: 0.8em; opacity:0.3;"></i></div></div>
</div>
<div style="text-align:left; font-size: 2em; opacity: 0.1; margin-top: 20px;"><i class="fas fa-paw"></i> <i class="fas fa-paw"></i> <i class="fas fa-paw"></i></div>
</div>'

MAP_PAGE_ID=$(wp_run post create --post_type=page --post_title="Maps & Dens" --post_content="$MAP_CONTENT" --post_status=publish --porcelain)
wp_run option update onlinesched_map_page_id $MAP_PAGE_ID

echo "Creating The Den Times (Complex Hours)..."
HOURS_CONTENT='<div class="os-hours">
<div style="float:right; font-size: 3em; opacity: 0.05;"><i class="fas fa-wolf-pack-battalion"></i></div>
<h2>Den Times <i class="fas fa-paw" style="color:#d12229; font-size: 0.6em;"></i></h2>
<ul>
  <li><strong>Friday:</strong> 10:00 AM - 2:00 AM (Next Day) 🐾</li>
  <li><strong>Saturday:</strong> 9:00 AM - 4:00 AM (Next Day) 🐾</li>
  <li><strong>Sunday:</strong> 9:00 AM - 5:00 PM 🐾</li>
</ul>

<hr />

<h2><i class="fas fa-paw"></i> Department Hours</h2>
<table style="width:100%; border-collapse: collapse;">
  <thead>
    <tr style="background: #f8f9fa;">
      <th style="padding: 10px; border: 1px solid #ddd;">Dept 🐺</th>
      <th style="padding: 10px; border: 1px solid #ddd;">Opens</th>
      <th style="padding: 10px; border: 1px solid #ddd;">Closes</th>
    </tr>
  </thead>
  <tbody>
    <tr><td style="padding: 8px; border: 1px solid #ddd;">Registration 🦊</td><td style="padding: 8px; border: 1px solid #ddd;">9:00 AM</td><td style="padding: 8px; border: 1px solid #ddd;">8:00 PM</td></tr>
    <tr><td style="padding: 8px; border: 1px solid #ddd;">Dealers Den 🐾</td><td style="padding: 8px; border: 1px solid #ddd;">10:00 AM</td><td style="padding: 8px; border: 1px solid #ddd;">6:00 PM</td></tr>
    <tr><td style="padding: 8px; border: 1px solid #ddd;">Consuite 🐺</td><td style="padding: 8px; border: 1px solid #ddd;">24 Hours</td><td style="padding: 8px; border: 1px solid #ddd;">-</td></tr>
  </tbody>
</table>
</div>'

HOURS_PAGE_ID=$(wp_run post create --post_type=page --post_title="The Den Times" --post_content="$HOURS_CONTENT" --post_status=publish --porcelain)
wp_run option update onlinesched_hours_page_id $HOURS_PAGE_ID

echo "Creating Shortcode Demo Page..."
SHORTCODE_CONTENT='<div class="os-shortcode-demo" style="border: 4px solid #d12229; padding: 20px; border-radius: 15px; background: #fff;">
<h1 style="text-align:center;"><i class="fas fa-code"></i> The Shortcode Pounce <i class="fas fa-paw"></i></h1>
<p style="text-align:center; font-style: italic;">"This page demonstrates the power of the <strong>[onlinesched_schedule]</strong> shortcode. It can be dropped anywhere in the den!"</p>

<div style="background: #fdfcf0; padding: 15px; border-left: 5px solid #e67e22; margin: 20px 0;">
  <i class="fas fa-wolf-pack-battalion"></i> <strong>Alpha Note:</strong> The shortcode inherits the styling and "Foxboy Jump" mechanics of the full page template, but lives inside your existing page layout.
</div>

[onlinesched_schedule]

<div style="text-align:center; margin-top: 30px; opacity: 0.5;">
  <i class="fas fa-paw"></i> <i class="fas fa-paw"></i> <i class="fas fa-paw"></i>
  <p>End of Shortcode Pounce</p>
</div>
</div>'

wp_run post create --post_type=page --post_title="Shortcode Pounce Demo" --post_name="shortcode-demo" --post_content="$SHORTCODE_CONTENT" --post_status=publish

echo "Setting Body Background and Logo..."
# We use custom CSS to add a subtle "furry" texture or pattern if needed
wp_run theme mod set background_color "fdfcf0"

echo "Configuring OnlineSched Main Page..."
SCHEDULE_PAGE_ID=$(wp_run post create --post_type=page --post_title="The Hunt (Schedule)" --post_name="schedule" --post_status=publish --porcelain)
wp_run post meta update $SCHEDULE_PAGE_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_schedule_page_id $SCHEDULE_PAGE_ID

echo "Configuring Kiosk Mode..."
KIOSK_PAGE_ID=$(wp_run post create --post_type=page --post_title="Den Display" --post_name="kiosk-schedule" --post_status=publish --porcelain)
wp_run post meta update $KIOSK_PAGE_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_kiosk_page_id $KIOSK_PAGE_ID

# Disable Steam (as requested by Alpha)
wp_run option update onlinesched_social_steam_enabled 0

echo "Seeding Test Events with OwO Flavor..."
# We reuse the existing seed script but it will use the new CLI container
export OS_TEST_CONTAINER="onlinesched-furry-cli"
export OS_TEST_WP="wp --allow-root"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
"$SCRIPT_DIR/../fixtures/seed-test-events.sh" --force

# Create a dedicated solo-event block demo page in the furry site too.
SOLO_EVENT_IDS=$(wp_run post list --post_type=os_event --orderby=ID --order=ASC --post_status=publish --format=ids --posts_per_page=2)
SOLO_EVENT_ID_ONE=$(echo "$SOLO_EVENT_IDS" | awk '{ print $1 }')
SOLO_EVENT_ID_TWO=$(echo "$SOLO_EVENT_IDS" | awk '{ print $2 }')
if [ -z "$SOLO_EVENT_ID_ONE" ]; then
  echo "⚠️  No seeded os_event posts found; skipping Solo Event Block Den Demo page."
else
  if [ -z "$SOLO_EVENT_ID_TWO" ]; then
    SOLO_EVENT_ID_TWO="$SOLO_EVENT_ID_ONE"
  fi
  SOLO_BLOCK_ONE='<!-- wp:onlinesched/solo-event {"eventId":'${SOLO_EVENT_ID_ONE}'} /-->'
  SOLO_BLOCK_TWO='<!-- wp:onlinesched/solo-event {"eventId":'${SOLO_EVENT_ID_TWO}'} /-->'
  SOLO_DEMO_CONTENT="<!-- wp:paragraph --><p>This page demonstrates the new Single Event block inside a furry flavored site shell.</p><!-- /wp:paragraph -->
${SOLO_BLOCK_ONE}
${SOLO_BLOCK_TWO}"

  wp_run post create --post_type=page --post_title="Solo Event Block Den Demo" --post_name="solo-event-block-demo" \
    --post_content="$SOLO_DEMO_CONTENT" --post_status=publish
fi

echo "✓ The Floof Den is fully fortified! Awoooo! 🐺🏁"
echo "--------------------------------------------------"
echo "Admin URL     : $SITE_URL/wp-admin"
echo "Admin User    : $ADMIN_USER"
echo "Admin Password: $ADMIN_PASS (Saved to $ADMIN_PASS_FILE)"
echo "--------------------------------------------------"
