#!/bin/bash
# @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
# Seed vanilla WordPress environment for OnlineSched testing.

set -e

# Configuration
CONTAINER="onlinesched-vanilla-wp"
WP="wp --allow-root --path=/var/www/html"
SITE_URL="http://localhost:8081"
ADMIN_USER="admin"
ADMIN_PASS="password"
ADMIN_EMAIL="admin@example.local"

# Helper to run WP command
wp_run() {
  docker exec "$CONTAINER" $WP "$@"
}

echo "Waiting for database and WordPress to be ready..."
# Simple wait loop
MAX_RETRIES=30
RETRY_COUNT=0
until wp_run core is-installed 2>/dev/null; do
  if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
    echo "Timed out waiting for WP-CLI"
    exit 1
  fi
  
  if wp_run core install --url="$SITE_URL" --title="OnlineSched Vanilla" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email 2>/dev/null; then
    break
  fi
  
  echo "  Still waiting for WordPress installation ($RETRY_COUNT/$MAX_RETRIES)..."
  sleep 5
  RETRY_COUNT=$((RETRY_COUNT + 1))
done
echo "✓ WordPress installed."

echo "Activating OnlineSched plugin..."
wp_run plugin activate OnlineSched

echo "Configuring OnlineSched pages..."
# Create main schedule page
SCHEDULE_PAGE_ID=$(wp_run post create --post_type=page --post_title="Schedule" --post_name="schedule" --post_status=publish --porcelain)
wp_run post meta update $SCHEDULE_PAGE_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_schedule_page_id $SCHEDULE_PAGE_ID

# Create kiosk page
KIOSK_PAGE_ID=$(wp_run post create --post_type=page --post_title="Kiosk" --post_name="kiosk-schedule" --post_status=publish --porcelain)
wp_run post meta update $KIOSK_PAGE_ID _wp_page_template "page-schedule.php"
wp_run option update onlinesched_kiosk_page_id $KIOSK_PAGE_ID

# Create hours page with block content
# We need to construct the block markup
HOURS_BLOCK='<!-- wp:onlinesched/hours-of-operations -->
<!-- wp:onlinesched/hours-department {"department":"Dealers Den","location":"Greenway A-B"} -->
<!-- wp:onlinesched/hours-day {"day":"Friday"} -->
<!-- wp:onlinesched/hours-time {"hours":"10:00 AM - 6:00 PM"} /-->
<!-- /wp:onlinesched/hours-day -->
<!-- wp:onlinesched/hours-day {"day":"Saturday"} -->
<!-- wp:onlinesched/hours-time {"hours":"10:00 AM - 6:00 PM"} /-->
<!-- /wp:onlinesched/hours-day -->
<!-- /wp:onlinesched/hours-department -->
<!-- /wp:onlinesched/hours-of-operations -->'

HOURS_PAGE_ID=$(wp_run post create --post_type=page --post_title="Hours" --post_content="$HOURS_BLOCK" --post_status=publish --porcelain)
wp_run option update onlinesched_hours_page_id $HOURS_PAGE_ID

# Create map page with actual FM content
MAP_CONTENT='Furry Migration takes place mainly on two floors. Registration is located on the 2nd floor, near the escalator.

<div class="fm-maps">
<h2 id="first-floor" style="text-align: center;">First Floor</h2>
<div style="text-align:center; padding: 20px; background: #eee; border: 2px dashed #999; border-radius: 10px; margin: 20px 0;">
  <i class="fas fa-map-marked-alt" style="font-size: 4em; color: #666; margin-bottom: 10px; display: block;"></i>
  <p><strong>[MAP PLACEHOLDER]</strong></p>
  <p>In a production environment, this would be a floor plan image.</p>
</div>
<div class="os-row location-list">
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">CHARITY:</span> 1st Floor, Alcove D</div>
</div>
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">DEALERS DEN:</span> 1st Floor, Hyatt Exhibit Hall</div>
</div>
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">MAIN STAGE:</span> 1st Floor, Grand Ballroom</div>
</div>
</div>
<h2 id="second-floor" style="text-align: center;">Second Floor</h2>
<div style="text-align:center; padding: 20px; background: #eee; border: 2px dashed #999; border-radius: 10px; margin: 20px 0;">
  <i class="fas fa-paw" style="font-size: 4em; color: #666; margin-bottom: 10px; display: block;"></i>
  <p><strong>[MAP PLACEHOLDER]</strong></p>
</div>
<div class="os-row location-list">
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">CONSUITE:</span> 2nd Floor, Northwoods</div>
</div>
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">FURSUIT LOUNGE:</span> 2nd Floor, Mirage</div>
</div>
<div class="os-col-sm-6">
<div class="location-item"><span class="location-name">REGISTRATION:</span> 2nd Floor, Greenway Promenade</div>
</div>
</div>
</div>
<style> .fm-maps .location-name {font-weight:bold; color: #0d375a;} .location-item { margin-bottom: 8px; padding: 10px; background: #f9f9f9; border-left: 4px solid #017940; } </style>'

MAP_PAGE_ID=$(wp_run post create --post_type=page --post_title="Maps" --post_content="$MAP_CONTENT" --post_status=publish --porcelain)
wp_run option update onlinesched_map_page_id $MAP_PAGE_ID

echo "✓ Pages created and options set."

# Run the standard event seed
echo "Seeding test events..."
# Ensure the seed script is executable
chmod +x ../fixtures/seed-test-events.sh
OS_TEST_CONTAINER="$CONTAINER" OS_TEST_WP="$WP" ../fixtures/seed-test-events.sh --force

echo "✓ Vanilla seed complete!"
