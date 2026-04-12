#!/bin/bash
# @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
# Seed test events via WP-CLI inside the Docker container.
# Events always use relative future dates so they pass the "Current" filter.

set -e

CONTAINER="fm-php"
WP="wp --allow-root --path=/var/www/html"

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
EXISTING=$(docker exec $CONTAINER $WP post list \
  --post_type=event_schedule --post_status=publish \
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
    "Closing Howl and Dead Dog"; do
    IDS=$(docker exec $CONTAINER $WP post list \
      --post_type=event_schedule --post_status=publish \
      --fields=ID,post_title --format=csv 2>/dev/null \
      | grep "\"$TITLE\"" | cut -d',' -f1)
    if [ -n "$IDS" ]; then
      docker exec $CONTAINER $WP post delete $IDS --force 2>/dev/null || true
      echo "  Deleted: $TITLE"
    fi
  done
fi

docker exec $CONTAINER $WP option update event_schedule_year "$YEAR"
echo "Set event_schedule_year to $YEAR"

# Ensure the Essentials tab filter knows which tag slugs are "essentials".
# The JS checks window.essentialsTags (array of slugs) via this WP option.
# Without this, the Essentials tab shows 0 items and test 02-tabs fails.
docker exec $CONTAINER $WP option update onlinesched_essentials_tags '["essential"]' --format=json 2>/dev/null || true
echo "Set onlinesched_essentials_tags to [\"essential\"]"

create_event() {
  local TITLE="$1"
  local SORTTIME="$2"
  local DURATION="$3"
  local ROOM="$4"
  local TAG="$5"
  local PANELIST="$6"
  local CONTENT="$7"

  POST_ID=$(docker exec $CONTAINER $WP post create \
    --post_type=event_schedule \
    --post_title="$TITLE" \
    --post_content="$CONTENT" \
    --post_status=publish \
    --porcelain)

  docker exec $CONTAINER $WP post meta update $POST_ID onlinesched_sorttime "$SORTTIME"
  docker exec $CONTAINER $WP post meta update $POST_ID onlinesched_timelen "$DURATION"
  docker exec $CONTAINER $WP post meta update $POST_ID onlinesched_year "$YEAR"

  docker exec $CONTAINER $WP term create event_schedule_room_type "$ROOM" --porcelain 2>/dev/null || true
  docker exec $CONTAINER $WP post term set $POST_ID event_schedule_room_type "$ROOM"

  docker exec $CONTAINER $WP term create event_schedule_tags_type "$TAG" --porcelain 2>/dev/null || true
  docker exec $CONTAINER $WP post term set $POST_ID event_schedule_tags_type "$TAG"

  if [ -n "$PANELIST" ]; then
    docker exec $CONTAINER $WP term create event_schedule_panelist_type "$PANELIST" --porcelain 2>/dev/null || true
    docker exec $CONTAINER $WP post term set $POST_ID event_schedule_panelist_type "$PANELIST"
  fi

  echo "  Created: $TITLE (ID: $POST_ID)"
}

echo "Seeding 9 test events for $YEAR..."

# Friday
create_event "Opening Howl Ceremony" \
  $((NEXT_FRIDAY + 36000)) 60 "Mainstage" "Essential" "Kurst Hyperyote" \
  "Kick off the convention with a massive group howl! All species welcome to the stage."

create_event "Fursuit Parade Staging" \
  $((NEXT_FRIDAY + 39600)) 90 "Mainstage" "Fursuiting" "Bandit Raccoon" \
  "Line up for the fursuit parade! Bandit will sort everyone by species — coyotes up front, raccoons causing chaos in the back."

create_event "Intro to Paw Art" \
  $((NEXT_FRIDAY + 39600)) 60 "Panel Room A" "Art" "Brushfox" \
  "Learn to draw paws, snouts, and tails. Bring your sketchbook and your fursona ref sheet."

# Saturday
create_event "Coyote vs Raccoon Dance-Off" \
  $((NEXT_SATURDAY + 36000)) 120 "Mainstage" "Essential" "" \
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

echo "✓ Seed complete. 9 test events created for $YEAR."

