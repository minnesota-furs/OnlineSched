# OnlineSched Automated Smoke Test Plan

## Overview

A lightweight Playwright-based smoke test suite for the OnlineSched plugin. Runs against the local Docker environment (`https://furrymigration.local/schedule/`) and validates that all interactive features work after each refactor phase. The tests live inside the plugin directory and run via `npm test`.

## Why Playwright

- Already used in the project ecosystem (`fm-price-card` uses `@wordpress/scripts` which bundles Playwright)
- Runs headless Chromium by default — fast CI-friendly
- Native `<dialog>` support, unlike older Puppeteer versions
- Built-in `expect` assertions, auto-waiting, and screenshot-on-failure
- Single dependency: `@playwright/test`

## File Structure

```
OnlineSched/
├── tests/
│   ├── playwright.config.js      # Playwright config (base URL, timeouts, screenshot settings)
│   ├── helpers/
│   │   └── selectors.js          # Central selector map (updates once per phase as classes change)
│   └── e2e/
│       ├── 01-page-loads.spec.js         # Page loads, schedule visible, no console errors
│       ├── 02-tabs.spec.js               # Tab switching (Programming, Essentials, Hours)
│       ├── 03-filters.spec.js            # Search, tag dropdown, day dropdown, room dropdown, reset
│       ├── 04-favorites.spec.js          # Star toggle, favorites filter, cookie persistence
│       ├── 05-modals.spec.js             # All 4 modals: open, content, close, escape, backdrop
│       ├── 06-calendar.spec.js           # Calendar button hrefs, clipboard copy
│       ├── 07-hash-routing.spec.js       # URL hash navigation (#evt-, #tag-, #hour)
│       ├── 08-responsive.spec.js         # Mobile viewport: visibility toggles, layout
│       └── 09-no-jquery-bootstrap.spec.js # Post-refactor: verify zero jQuery/Bootstrap on page
├── package.json                  # Updated with Playwright devDep and test script
└── ...
```

## Setup

### 1. Install Playwright

Add to `package.json` devDependencies:
```json
"@playwright/test": "^1.52.0"
```

Update `scripts`:
```json
"test": "npx playwright test",
"test:headed": "npx playwright test --headed",
"test:ui": "npx playwright test --ui"
```

After install, run `npx playwright install chromium` to download the browser binary.

### 2. Playwright Config (`tests/playwright.config.js`)

```js
// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  retries: 1,
  use: {
    baseURL: 'https://furrymigration.local',
    ignoreHTTPSErrors: true,           // Self-signed cert in Docker
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    viewport: { width: 1280, height: 800 },
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1280, height: 800 } } },
    { name: 'mobile',  use: { viewport: { width: 375, height: 812 } } },
  ],
});
```

Key points:
- `ignoreHTTPSErrors: true` — the Docker setup uses self-signed certs
- Two projects: desktop (1280px) and mobile (375px) — catches responsive regressions
- Screenshots and traces saved only on failure to keep runs fast

### 3. Selector Map (`tests/helpers/selectors.js`)

A single file that maps logical names to CSS selectors. When class names change during the refactor (e.g., `.btn` → `.os-btn`), update this one file — all tests keep working.

```js
// Before refactor (Bootstrap classes)
module.exports = {
  // Page structure
  schedule:         '#schedule',
  scheduleItem:     '.schedule-item',
  scheduleDay:      '.schedule-day',
  scheduleHour:     '.schedule-hour',
  scheduleTitle:    '.schedule-title a',

  // Tabs
  tabList:          '.schedule-tabs',         // → .os-tabs
  tabLinks:         '.schedule-tabs a',       // → .os-tabs a
  tabPaneActive:    '.tab-pane.active',       // → .os-tab-pane--active
  tabProgramming:   '#programming',
  tabHours:         '#hours',

  // Filters
  searchInput:      '#schedule-search-text',
  selectTags:       '#schedule-select-tags',
  selectDays:       '#schedule-select-days',
  selectRooms:      '#schedule-select-rooms',
  resetButton:      '#schedule-reset',
  favoritesToggle:  '#schedule-favorites-toggle',

  // Favorites
  favoriteBtn:      '.schedule-favorite-toggle',
  favoriteStar:     '.schedule-favorite-toggle i',

  // Modals
  loginModalBtn:    '#login-modal-btn',
  loginModal:       '#login-modal',
  loginModalClose:  '#login-modal-close',
  infoModalBtn:     '#info-modal-btn',
  infoModal:        '#info-modal',
  infoModalClose:   '#info-modal-close',
  scheduleModal:    '#modal-schedule',
  scheduleModalTitle: '#modal-schedule-title',
  androidModal:     '#android-google-calendar-modal',

  // Calendar
  modalIcal:        '#modal-schedule-ical',
  modalGoogle:      '#modal-schedule-google',
  modalCopyUrl:     '#modal-copy-url',
  clipboard:        '.schedule-clipboard',

  // Buttons
  loginBtn:         '#login-modal-btn',
  logoutBtn:        '#logout-modal-btn',

  // Responsive
  hiddenXs:         '.hidden-xs',             // → .os-hide-mobile
  visibleXs:        '.visible-xs',            // → .os-show-mobile
};
```

## Test Specs

### 01 — Page Loads (`01-page-loads.spec.js`)

**What it checks:** The schedule page loads without critical errors and the schedule container becomes visible.

```
- Navigate to /schedule/
- Wait for #schedule to be visible (JS unhides it on init)
- Assert page title contains "Schedule"
- Assert at least 1 .schedule-item exists
- Assert at least 1 .schedule-day exists
- Collect console errors — fail if any JS errors (not warnings)
- Assert no "jQuery is not defined" or "$ is not defined" errors
```

### 02 — Tabs (`02-tabs.spec.js`)

**What it checks:** All three tabs switch content panes correctly.

```
- Click "Programming" tab → #programming pane is visible
- Click "Hours" tab → #hours pane is visible, #programming is hidden
- Click Programming tab again → #programming is visible again
- Click "Essentials" tab → programming pane is still the container but filter changes
  (verify setFilterEvents(false) was called by checking visible items have essentials tags)
- Verify URL hash updates on tab click
```

### 03 — Filters (`03-filters.spec.js`)

**What it checks:** All filter controls work and reset properly.

```
- Type "panel" in search → visible items reduce, each visible item contains "panel" in text
- Clear search → items restore
- Select a specific day from dropdown → only that day's section is visible
- Select "All Days" → all days visible again
- Select a tag from dropdown → only items with that tag visible
- Select a room from dropdown → only items in that room visible
- Click Reset → all dropdowns return to defaults, search cleared
- Verify Reset button is disabled when all filters are at default
- Verify Reset button is enabled when any filter is active
```

### 04 — Favorites (`04-favorites.spec.js`)

**What it checks:** Star toggle, favorites filtering, and cookie persistence.

```
- Find first .schedule-favorite-toggle, click it
- Assert star icon changes from far fa-star to fas fa-star (filled)
- Assert parent schedule-item gets data-favorite="true"
- Click favorites filter toggle → only favorited items visible
- Click favorites filter again → all items visible
- Click star again → unfavorites, icon reverts to hollow
- Check cookie: document.cookie contains "schedule_favorites"
- Reload page → verify favorites persisted via cookie
```

### 05 — Modals (`05-modals.spec.js`)

**What it checks:** All four modals open, display content, and close correctly.

```
Login Modal:
- Click #login-modal-btn → #login-modal becomes visible
- Assert modal contains "Login" text
- Click close button → modal hidden
- Re-open, press Escape → modal hidden

Info Modal:
- Click #info-modal-btn → #info-modal visible
- Assert contains "Favorites" text
- Click close → hidden
- Press Escape → hidden

Schedule Modal:
- Click first .schedule-title a → #modal-schedule visible
- Assert #modal-schedule-title is not empty
- Assert modal contains date, time, room fields
- Assert calendar buttons present (Apple, Google, Copy)
- Close modal (click close or press Escape)
- Verify URL hash is cleared after modal close

Android Google Calendar Modal (simulated):
- Verify #android-google-calendar-modal exists in DOM
- (Full test requires Android UA — skip in default suite, note for manual QA)
```

### 06 — Calendar (`06-calendar.spec.js`)

**What it checks:** Calendar button href values and clipboard copy behavior.

```
- Open a schedule modal by clicking an event title
- Assert #modal-schedule-ical href contains "webcal://" and "ical.php"
- Assert #modal-schedule-google href contains "calendar.google.com"
- Click .schedule-clipboard on first event
- Assert .clipboard-effect element appears (the float-up animation)
- Wait for animation to complete → element removed from DOM
```

### 07 — Hash Routing (`07-hash-routing.spec.js`)

**What it checks:** URL hash navigation routes work on page load.

```
- Navigate to /schedule/#hour → Hours tab is active
- Navigate to /schedule/#tag-fursuiting → tag dropdown matches "Fursuiting" (or similar)
- Navigate to /schedule/#evt-XXXX (use first event ID) → schedule modal opens for that event
```

### 08 — Responsive (`08-responsive.spec.js`)

**What it checks:** Mobile-specific visibility and layout work.

```
(Runs in mobile project — 375px viewport)
- .hidden-xs elements are not visible (or .os-hide-mobile after refactor)
- .visible-xs elements are visible (or .os-show-mobile after refactor)
- Filter dropdowns stack vertically (not overflowing)
- Schedule items don't overflow viewport width
- Modal is usable at mobile width (not clipped)
- Tabs are tappable and not truncated beyond readability
```

### 09 — No jQuery/Bootstrap (`09-no-jquery-bootstrap.spec.js`)

**What it checks:** After refactor is complete, the page has no jQuery or Bootstrap loaded. Skip this test during intermediate phases.

```
- Navigate to /schedule/
- Evaluate in page context: typeof window.jQuery === 'undefined'
- Evaluate: typeof window.$ === 'undefined' (or $ !== jQuery)
- Evaluate: document.querySelector('link[href*="bootstrap"]') === null
- Evaluate: document.querySelector('script[src*="jquery"]') === null
- querySelectorAll('[class*="col-xs-"]').length === 0
- querySelectorAll('[class*="col-sm-"]').length === 0
- querySelectorAll('[data-toggle="tab"]').length === 0
- querySelectorAll('[data-dismiss="modal"]').length === 0
```

## Test Data & Database Setup

### The Problem: Dates Matter

The schedule plugin is **date-sensitive** in two critical ways:

1. **Year filter (PHP, server-side):** `page-schedule.php` reads `get_option('event_schedule_year')` and skips any event where `onlinesched_year` doesn't match. If the DB has events tagged as `2024` but the option is set to `2026`, zero events render.

2. **"Now and Future" filter (JS, client-side):** The default day dropdown is `"Current"`, which compares each event's `data-end-time` (Unix timestamp UTC) against `Date.now()`. Events in the past are hidden by default. If all seed data has timestamps from 2024, the page loads with **zero visible events** even though the HTML contains them.

This means tests will fail unless seed data has events with **future timestamps** or tests explicitly select "All Days" before asserting visible items.

### Solution: WP-CLI Seed Script

Create a seed script (`tests/fixtures/seed-test-events.sh`) that uses WP-CLI inside the Docker container to create deterministic test events with **relative dates** (always in the future). This script runs before the test suite.

```bash
#!/bin/bash
# tests/fixtures/seed-test-events.sh
# @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
# Creates test events via WP-CLI inside the Docker container.
# Events are always set in the future relative to "now".

CONTAINER="fm-php"
WP="wp --allow-root --path=/var/www/html"

# Set the event schedule year to current year
YEAR=$(date +%Y)
docker exec $CONTAINER $WP option update event_schedule_year "$YEAR"

# Calculate timestamps: Friday/Saturday/Sunday of next week
NEXT_FRIDAY=$(date -v+fri -v+7d +%s 2>/dev/null || date -d "next friday +7 days" +%s)
NEXT_SATURDAY=$((NEXT_FRIDAY + 86400))
NEXT_SUNDAY=$((NEXT_FRIDAY + 172800))

# Helper: create one event_schedule post with meta and terms
create_event() {
  local TITLE="$1"
  local SORTTIME="$2"
  local DURATION="$3"  # minutes
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

  # Assign room taxonomy
  docker exec $CONTAINER $WP term create event_schedule_room_type "$ROOM" --porcelain 2>/dev/null
  docker exec $CONTAINER $WP post term set $POST_ID event_schedule_room_type "$ROOM"

  # Assign tag taxonomy
  docker exec $CONTAINER $WP term create event_schedule_tags_type "$TAG" --porcelain 2>/dev/null
  docker exec $CONTAINER $WP post term set $POST_ID event_schedule_tags_type "$TAG"

  # Assign panelist taxonomy (if provided)
  if [ -n "$PANELIST" ]; then
    docker exec $CONTAINER $WP term create event_schedule_panelist_type "$PANELIST" --porcelain 2>/dev/null
    docker exec $CONTAINER $WP post term set $POST_ID event_schedule_panelist_type "$PANELIST"
  fi

  echo "Created event: $TITLE (ID: $POST_ID, time: $SORTTIME)"
}

echo "Seeding test events for year $YEAR..."

# Friday events (3 events across 2 hours, 2 rooms, 3 tags)
create_event "Opening Howl Ceremony" \
  $((NEXT_FRIDAY + 36000)) 60 "Mainstage" "Essential" "Kurst Hyperyote" \
  "Kick off the convention with a massive group howl! All species welcome to the stage."

create_event "Fursuit Parade Staging" \
  $((NEXT_FRIDAY + 39600)) 90 "Mainstage" "Fursuiting" "Bandit Raccoon" \
  "Line up for the fursuit parade! Bandit will sort everyone by species — coyotes up front, raccoons causing chaos in the back."

create_event "Intro to Paw Art" \
  $((NEXT_FRIDAY + 39600)) 60 "Panel Room A" "Art" "Brushfox" \
  "Learn to draw paws, snouts, and tails. Bring your sketchbook and your fursona ref sheet."

# Saturday events (4 events — multiple rooms, tags, one cancelled)
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

# Sunday events (2 events)
create_event "Charity Auction for Critter Rescue" \
  $((NEXT_SUNDAY + 36000)) 90 "Mainstage" "Essential" "Kurst Hyperyote" \
  "Bid on art, badges, and that one raccoon plushie everyone wants. All proceeds go to local wildlife rescue."

create_event "Closing Howl and Dead Dog" \
  $((NEXT_SUNDAY + 50400)) 60 "Mainstage" "Essential" "Kurst Hyperyote" \
  "One last howl before we scatter back to our dens. See you next year, furiends."

echo "Seed complete. 9 test events created."
```

### What the Seed Data Provides

| Test Need | Seed Data Coverage |
|---|---|
| At least 1 visible event | 9 events, all with future timestamps |
| Multiple days | Friday, Saturday, Sunday |
| Multiple hours per day | 2-3 time slots per day |
| Multiple rooms | Mainstage, Panel Room A, Panel Room B |
| Multiple tags | Essential, Fursuiting, Art, Writing, Social, Cancelled |
| Panelists | Kurst Hyperyote, Bandit Raccoon, Brushfox, Scribes McFluffington, Sly Coyote |
| Cancelled event | "Napping in the Raccoon Lounge" with Cancelled tag |
| Events for essentials filter | 4 events tagged "Essential" |
| Coyote reference | "Coyote vs Raccoon Dance-Off", Sly Coyote panelist |
| Raccoon reference | Bandit Raccoon panelist, "Napping in the Raccoon Lounge" |
| Enough data for filter tests | 9 events across 3 days, 3 rooms, 6 tags |

### Date Handling Strategy in Tests

Since the "Current" (Now and Future) filter is the **default** day dropdown value, tests must account for this:

**Option A — Tests select "All Days" first (recommended):**
Most test specs start with:
```js
// Ensure all events are visible regardless of current date
await page.selectOption('#schedule-select-days', 'all');
await page.waitForTimeout(300); // Let filter apply
```
This makes tests date-independent for filtering/modal/tab assertions.

**Option B — Tests that specifically test the "Current" filter:**
Test 03 (Filters) should have a dedicated sub-test:
```js
test('Current filter hides past events', async ({ page }) => {
  await page.selectOption('#schedule-select-days', 'Current');
  // All seed events are in the future, so all should be visible
  const visible = await page.locator('.schedule-item:visible').count();
  expect(visible).toBeGreaterThan(0);
});
```
Since seed data always uses future dates, this passes. If someone runs tests after the seed events' dates pass, they re-run the seed script.

**Option C — Clock mocking (advanced, optional):**
Playwright can freeze the browser clock:
```js
await page.clock.setFixedTime(new Date('2026-09-18T10:00:00'));
```
This makes the "Current" filter see a fixed date. Useful but adds complexity — only add if needed.

### Running the Seed Script

**First time / after DB reset:**
```bash
bash tests/fixtures/seed-test-events.sh
```

**Checking if seed data exists (idempotency):**
The seed script can be enhanced with a check:
```bash
COUNT=$(docker exec $CONTAINER $WP post list --post_type=event_schedule --field=ID --format=count)
if [ "$COUNT" -ge 9 ]; then
  echo "Test events already exist ($COUNT events). Skipping seed."
  exit 0
fi
```

**Wipe and reseed:**
```bash
docker exec fm-php wp post delete $(docker exec fm-php wp post list --post_type=event_schedule --field=ID --format=csv) --force --allow-root --path=/var/www/html
bash tests/fixtures/seed-test-events.sh
```

### Integration with `package.json`

Add convenience scripts:
```json
"scripts": {
  "test:seed": "bash tests/fixtures/seed-test-events.sh",
  "test:setup": "npm run test:seed && npx playwright install chromium",
  "test": "npx playwright test",
  "test:headed": "npx playwright test --headed",
  "test:ui": "npx playwright test --ui"
}
```

First-time workflow:
```bash
npm install
npm run test:setup
npm test
```

Subsequent runs (seed data persists in Docker volume):
```bash
npm test
```

After DB wipe (e.g., `docker compose down -v && docker compose up -d`):
```bash
npm run test:seed
npm test
```

### What Happens When Seed Dates Expire

The seed script uses **relative dates** (next Friday from today). Seed events expire ~1 week after creation. Two strategies:

1. **Re-run the seed script** — takes ~10 seconds, creates new future events. Old expired events stay in the DB but are harmless (filtered out by "Current").
2. **Use "All Days"** in test setup — even expired events are testable when the day filter is set to "All Days". Only the "Current" filter sub-test is affected.

**Recommendation:** Add a Playwright `globalSetup` that checks if any visible events exist and warns if not:

```js
// tests/global-setup.js
const { chromium } = require('@playwright/test');

module.exports = async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ ignoreHTTPSErrors: true });
  await page.goto('https://furrymigration.local/schedule/');
  await page.waitForSelector('#schedule', { state: 'visible', timeout: 10000 });
  // Switch to All Days to check total events
  await page.selectOption('#schedule-select-days', 'all');
  await page.waitForTimeout(500);
  const count = await page.locator('.schedule-item:visible').count();
  await browser.close();
  if (count === 0) {
    console.error('\n⚠️  No schedule events found! Run: npm run test:seed\n');
    process.exit(1);
  }
  console.log(`✓ Found ${count} schedule events. Tests can proceed.`);
};
```

Reference in `playwright.config.js`:
```js
module.exports = defineConfig({
  globalSetup: './tests/global-setup.js',
  // ...existing config...
});
```

## Running the Tests

### Commands

| Command | Purpose |
|---|---|
| `npm test` | Run all tests headless (default — Chromium) |
| `npm run test:headed` | Run with visible browser (debugging) |
| `npm run test:ui` | Playwright interactive UI mode |
| `npm run test:seed` | Create/refresh test events in Docker DB |
| `npm run test:setup` | Seed + install Playwright browsers (first-time) |
| `npx playwright test tests/e2e/01-page-loads.spec.js` | Run a single spec file |
| `npx playwright test --project=mobile` | Run only mobile viewport tests |
| `npx playwright test --grep "modal"` | Run tests matching keyword |
| `npx playwright show-report` | Open HTML report after a run |

### Prerequisites

1. Docker environment running: `docker compose up -d`
2. Site accessible at `https://furrymigration.local`
3. Test events seeded: `npm run test:seed` (only needed once per DB lifecycle)
4. `npm install` has been run inside the OnlineSched plugin directory
5. `npx playwright install chromium` has been run at least once

### CI Note
These tests require the full Docker stack running. They are meant for **local development smoke testing** between refactor phases, not CI pipelines. If CI is needed in the future, a Docker-compose service for Playwright can be added.

## Maintenance Between Refactor Phases

The key maintenance point is `tests/helpers/selectors.js`. As each phase renames classes:

| Phase | Selector Changes |
|---|---|
| Phase 0 | None — cleanup only |
| Phase 1 | None — new SCSS added, no HTML changes |
| Phase 2 | `.container-fluid` → `.os-container`, `.row` → `.os-row`, `.col-*` → `.os-col-*` / semantic, `.hidden-xs` → `.os-hide-mobile`, `.btn` → `.os-btn`, `.badge` → `.os-badge`, `.well` → `.os-well`, `.form-control` → `.os-form-control`, `.sr-only` → `.os-sr-only` |
| Phase 3 | `.nav-tabs` → `.os-tabs`, `.tab-pane` → `.os-tab-pane`, `.tab-pane.active` → `.os-tab-pane--active`, remove `data-toggle` checks |
| Phase 4 | Modal selectors: `.modal` → `dialog.os-modal`, `data-dismiss` → `os-close`, visibility check changes from `display:block` to `dialog[open]` |
| Phase 5 | No selector changes — JS internals only |
| Phase 6 | Enable test 09 (no-jquery-bootstrap) |

Update the selector map **at the start of each phase**, then run `npm test` to confirm everything still passes before and after changes.

## Implementation Steps

1. Add `@playwright/test` to `devDependencies` in [package.json](./package.json)
2. Update `scripts` in `package.json` (`test`, `test:headed`, `test:ui`, `test:seed`, `test:setup`)
3. Create `tests/playwright.config.js` (with `globalSetup` reference)
4. Create `tests/global-setup.js` (pre-flight check for seed data)
5. Create `tests/helpers/selectors.js`
6. Create `tests/fixtures/seed-test-events.sh`
7. Create the 9 spec files in `tests/e2e/`
8. Add `tests/test-results/` and `tests/playwright-report/` to `.gitignore`
9. Run `npm install && npm run test:setup`
10. Run `npm test` to verify baseline passes against current (pre-refactor) site
11. Commit the test suite as part of Phase 0

## Additional Considerations

1. Tests should be terse — no over-commenting. If the test name describes it, that's enough.
2. Docker access: `docker exec -it mnfurs-php bash`.
3. Attribution: kurst@mnfurs.org Kurst Hyperyote for Furry Migration.
4. Modern ES6+ style throughout test code.
5. Code must be fully tested and validated.
6. Follow WordPress VIP standards where possible.
7. Ask if there is a contradiction.
8. Update this plan if requirements change.
9. Only update code in the OnlineSched plugin directory.
10. Tests run against Docker at `https://furrymigration.local` — self-signed certs, so `ignoreHTTPSErrors` is required.
11. The test data depends on the database having schedule events seeded via `npm run test:seed`. If the DB is wiped, re-run the seed script.
12. Test 09 (no-jquery-bootstrap) should be skipped (`test.skip`) until Phase 6 is complete.
13. Screenshots on failure go to `tests/test-results/` — add this to `.gitignore`.
14. The seed script creates events ~1 week in the future. If tests start failing because events expired, re-run `npm run test:seed`.
15. Most test specs should start by selecting "All Days" to avoid date-sensitivity. Only the "Current" filter sub-test in 03-filters relies on future dates.
16. The `globalSetup` pre-flight check will abort with a helpful error if no events are found, preventing cryptic test failures.
