#!/bin/bash
# @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
# Seed test events via WP-CLI inside the Docker container.
# Events always use relative future dates so they pass the "Current" filter.

set -e

CONTAINER="${OS_TEST_CONTAINER:-fm-php}"
WP_CMD="${OS_TEST_WP:-wp --allow-root --path=/var/www/html}"

# Helper to run WP command inside container
wp_run() {
  docker exec "$CONTAINER" $WP_CMD "$@"
}

YEAR=$(date +%Y)

# Calculate next-week Friday (always 7+ days in the future) — Python3 for macOS/Linux portability
read -r NEXT_FRIDAY NEXT_SATURDAY NEXT_SUNDAY FRI_LABEL SAT_LABEL SUN_LABEL <<< "$(python3 -c "
import datetime, time
today = datetime.date.today()
days_ahead = (4 - today.weekday()) % 7  # 4 = Friday
if days_ahead < 7:
    days_ahead += 7
fri = today + datetime.timedelta(days=days_ahead)
sat = fri + datetime.timedelta(days=1)
sun = fri + datetime.timedelta(days=2)
def ts(d): return int(time.mktime(d.timetuple()))
print(ts(fri), ts(sat), ts(sun), fri.strftime('%Y-%m-%d'), sat.strftime('%Y-%m-%d'), sun.strftime('%Y-%m-%d'))
")"

echo "Event dates (must all be in the future):"
echo "  Friday  : $FRI_LABEL"
echo "  Saturday: $SAT_LABEL"
echo "  Sunday  : $SUN_LABEL"

# Verify dates are actually in the future
NOW=$(python3 -c "import time; print(int(time.time()))")
if [ "$NEXT_FRIDAY" -le "$NOW" ]; then
  echo "ERROR: Calculated Friday ($FRI_LABEL) is not in the future. Check system clock."
  exit 1
fi
echo "✓ Dates verified as future."

# Idempotency: check by title (NOT total count — real DB may already have 100+ events)
EXISTING=$(wp_run post list \
  --post_type=os_event --post_status=publish \
  --fields=post_title --format=csv 2>/dev/null \
  | grep -c "Opening Howl Ceremony" || true)

if [ "$EXISTING" -ge 1 ]; then
  echo "✓ Test seed events already exist. Skipping. (Run with --force to reseed)"
  if [ "$1" != "--force" ]; then exit 0; fi
  echo "Forcing reseed — deleting existing seed events by title..."
  for TITLE in \
    "Opening Howl Ceremony" \
    "Fursuit Parade Staging" \
    "Intro to Paw Art" \
    "Coyote vs Raccoon Dance-Off" \
    "Writing Your Fursona's Story" \
    "Dealers Den Guided Tour" \
    "Napping in the Raccoon Lounge" \
    "Charity Auction for Critter Rescue" \
    "Closing Howl and Dead Dog" \
    "After Dark Howl" \
    "Quiet Paws Chill Zone" \
    "VIP Tail Care Lounge"; do
    IDS=$(wp_run post list \
      --post_type=os_event --post_status=publish \
      --fields=ID,post_title --format=csv 2>/dev/null \
      | grep "\"$TITLE\"" | cut -d',' -f1)
    if [ -n "$IDS" ]; then
      wp_run post delete $IDS --force 2>/dev/null || true
      echo "  Deleted: $TITLE"
    fi
  done
fi

wp_run option update onlinesched_year "$YEAR"
echo "Set onlinesched_year to $YEAR"

# Ensure the Essentials tab filter knows which tag slugs are "essentials".
# The JS checks window.essentialsTags (array of slugs) via this WP option.
# Without this, the Essentials tab shows 0 items and test 02-tabs fails.
wp_run option update onlinesched_essentials_tags '["essential"]' --format=json 2>/dev/null || true
echo "Set onlinesched_essentials_tags to [\"essential\"]"

# ── Badge Type Defaults ──
# The badge system uses several WP options. Restore them so badges render with
# correct colors, icons, and row highlights. These match the admin "Restore Defaults" values.
echo "Setting up default badge types..."

wp_run option update onlinesched_badge_types \
  '["Adult","Cancelled","Essentials","Guest Of Honor","Sensory","Special Guest","Streaming","VIP"]' \
  --format=json 2>/dev/null || true

wp_run option update onlinesched_badge_types_display \
  '{"Adult":true,"Sensory":true,"VIP":true,"Essentials":true,"Guest Of Honor":false,"Special Guest":false,"Streaming":true,"Cancelled":true}' \
  --format=json 2>/dev/null || true

wp_run option update onlinesched_badge_types_colors \
  '{"Adult":"#d12229","Sensory":"#0a58ca","VIP":"","Essentials":"","Guest Of Honor":"","Special Guest":"","Streaming":"","Cancelled":""}' \
  --format=json 2>/dev/null || true

wp_run option update onlinesched_badge_types_fg_colors \
  '{"Adult":"#ffffff","Sensory":"#ffffff","VIP":"","Essentials":"","Guest Of Honor":"","Special Guest":"","Streaming":"","Cancelled":""}' \
  --format=json 2>/dev/null || true

wp_run option update onlinesched_badge_types_row_colors \
  '{"Adult":"","Sensory":"","VIP":"#fff0b2","Essentials":"","Guest Of Honor":"#b5d8ac","Special Guest":"#b5d8ac","Streaming":"","Cancelled":""}' \
  --format=json 2>/dev/null || true

wp_run option update onlinesched_badge_types_icons \
  '{"Adult":"","Sensory":"","VIP":"","Essentials":"","Guest Of Honor":"fas fa-star","Special Guest":"fas fa-star","Streaming":"","Cancelled":""}' \
  --format=json 2>/dev/null || true

echo "Badge type options set."

# Assign badge types to tags via term meta.
# The auto-assign hook maps by slug, but "Essential" -> slug "essential" does NOT match "essentials".
# We must explicitly set badge_type term meta for each tag the seed creates.
assign_badge_type() {
  local TAG_SLUG="$1"
  local BADGE_TYPE="$2"
  TERM_ID=$(wp_run term list os_tag \
    --slug="$TAG_SLUG" --field=term_id --format=csv 2>/dev/null | tail -1)
  if [ -n "$TERM_ID" ] && [ "$TERM_ID" != "term_id" ]; then
    wp_run term meta update "$TERM_ID" badge_type "$BADGE_TYPE" 2>/dev/null || true
    echo "  Assigned badge_type '$BADGE_TYPE' to tag '$TAG_SLUG' (term $TERM_ID)"
  fi
}

create_event() {
  local TITLE="$1"
  local SORTTIME="$2"
  local DURATION="$3"
  local ROOM="$4"
  local TAGS="$5" # Can be comma-separated
  local PANELIST="$6" # Can be comma-separated
  local CONTENT="$7"

  POST_ID=$(wp_run post create \
    --post_type=os_event \
    --post_title="$TITLE" \
    --post_content="$CONTENT" \
    --post_status=publish \
    --porcelain)

  wp_run post meta update $POST_ID onlinesched_sorttime "$SORTTIME"
  wp_run post meta update $POST_ID onlinesched_timelen "$DURATION"
  wp_run post meta update $POST_ID onlinesched_year "$YEAR"

  wp_run term create os_room "$ROOM" --porcelain 2>/dev/null || true
  wp_run post term set $POST_ID os_room "$ROOM"

  IFS=',' read -ra TAG_NAMES <<< "$TAGS"
  CLEAN_TAGS=()
  for TAG in "${TAG_NAMES[@]}"; do
    TRIMMED_TAG=$(echo "$TAG" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
    wp_run term create os_tag "$TRIMMED_TAG" --porcelain 2>/dev/null || true
    CLEAN_TAGS+=("$TRIMMED_TAG")
  done
  wp_run post term set $POST_ID os_tag "${CLEAN_TAGS[@]}"

  if [ -n "$PANELIST" ]; then
    # Split by comma and trim
    IFS=',' read -ra NAMES <<< "$PANELIST"
    CLEAN_NAMES=()
    for NAME in "${NAMES[@]}"; do
      TRIMMED=$(echo "$NAME" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
      wp_run term create os_panelist "$TRIMMED" --porcelain 2>/dev/null || true
      CLEAN_NAMES+=("$TRIMMED")
    done
    wp_run post term set $POST_ID os_panelist "${CLEAN_NAMES[@]}"
  fi

  echo "  Created: $TITLE (ID: $POST_ID)"
}

echo "Seeding 12 test events for $YEAR..."

# Friday
create_event "Opening Howl Ceremony" \
  $((NEXT_FRIDAY + 36000)) 60 "Mainstage" "Essential, Special Event" "Kurst Hyperyote" \
  "Kick off the convention with a massive group howl! All species welcome to the stage."

create_event "Fursuit Parade Staging" \
  $((NEXT_FRIDAY + 39600)) 90 "Mainstage" "Fursuiting" "Bandit Raccoon" \
  "Line up for the fursuit parade! Bandit will sort everyone by species — coyotes up front, raccoons causing chaos in the back."

create_event "Intro to Paw Art" \
  $((NEXT_FRIDAY + 39600)) 60 "Panel Room A" "Art" "Brushfox" \
  "Learn to draw paws, snouts, and tails. Bring your sketchbook and your fursona ref sheet."

# Saturday
create_event "Coyote vs Raccoon Dance-Off" \
  $((NEXT_SATURDAY + 36000)) 120 "Mainstage" "Essential" "Sly Coyote, Bandit Raccoon" \
  "The age-old rivalry continues on the dance floor. Team Coyote and Team Raccoon battle for convention supremacy."

create_event "Writing Your Fursona's Story" \
  $((NEXT_SATURDAY + 39600)) 60 "Panel Room A" "Writing" "Scribes McFluffington" \
  "Every fursona has a backstory. Learn how to write yours without it turning into a novel (unless you want it to)."

create_event "Dealers Den Guided Tour" \
  $((NEXT_SATURDAY + 43200)) 60 "Panel Room B" "Social" "Sly Coyote" \
  "Sly Coyote walks you through the best booths, hidden gems, and where to find the best tail accessories."

create_event "Napping in the Raccoon Lounge" \
  $((NEXT_SATURDAY + 46800)) 60 "Panel Room A" "Cancelled" "" \
  "Unfortunately the raccoons ate all the snacks and fell asleep before the panel. Cancelled."

# Sunday
create_event "Charity Auction for Critter Rescue" \
  $((NEXT_SUNDAY + 36000)) 90 "Mainstage" "Essential" "Kurst Hyperyote" \
  "Bid on art, badges, and that one raccoon plushie everyone wants. All proceeds go to local wildlife rescue."

create_event "Closing Howl and Dead Dog" \
  $((NEXT_SUNDAY + 50400)) 60 "Mainstage" "Essential" "Kurst Hyperyote" \
  "One last howl before we scatter back to our dens. See you next year, furiends."

# Badge-testing events (Adult and Sensory have distinct badge colors)
create_event "After Dark Howl" \
  $((NEXT_SATURDAY + 75600)) 90 "Panel Room B" "Restricted" "Silver Husky, Night Wolf, Midnight Canine" \
  "The adults-only late night session. Badge required for entry. 18+ only."

create_event "Quiet Paws Chill Zone" \
  $((NEXT_SUNDAY + 43200)) 60 "Panel Room A" "Sensory" "" \
  "A low-stimulation space for furs who need a sensory break. Dim lights, soft music, bean bags."

create_event "VIP Tail Care Lounge" \
  $((NEXT_SATURDAY + 50400)) 90 "Panel Room B" "VIP" "Kurst Hyperyote" \
  "Exclusive tail care session for VIP badge holders. Includes premium floof brushes, detangling sprays, and a complimentary tail bow."

# ── Assign badge types to tags via term meta ──
echo "Assigning badge types to tags..."
assign_badge_type "essential" "Essentials"
assign_badge_type "cancelled" "Cancelled"
assign_badge_type "restricted" "Adult"
assign_badge_type "sensory" "Sensory"
assign_badge_type "vip" "VIP"

echo "Done. 12 test events created for $YEAR."
