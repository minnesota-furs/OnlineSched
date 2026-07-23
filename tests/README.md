# OnlineSched Test Suite

This directory contains the browser, WP-CLI, and fixture tests used to verify OnlineSched.
If you are setting up the suite for the first time, start with the disposable Vanilla
WordPress environment.

## Test Environments

### Vanilla WordPress (recommended)

The Vanilla environment is a throwaway WordPress site with no Furry Migration theme
customizations. It proves the plugin can stand on its own and is also the only environment
allowed to run the destructive WP-CLI integration harness.

From the OnlineSched plugin directory:

```bash
cd tests/docker-vanilla
docker compose up -d
./seed-vanilla.sh
cd ../..
npm test -- --project=vanilla-wp
```

The site runs at `http://localhost:8081` with Twenty Twenty-Five as its baseline theme.

### Furry Migration (local Docker)

The Furry Migration environment is the reference development stack at
`https://furrymigration.local`.

1. Start the Docker stack from the Furry Migration project root.
2. Change to `public_html/wp-content/plugins/OnlineSched`.
3. Run `npm run test:setup` the first time to install browsers and seed data.
4. Run a focused Furry Migration project while developing, for example:

   ```bash
   npm test -- --project=desktop
   ```

### Full configured browser suite

`npm test` runs every configured project. In addition to Furry Migration, it expects the
Vanilla, furry-demo, and gaming-demo WordPress environments to be running and seeded on ports
8081, 8082, and 8083:

```bash
cd tests/docker-vanilla
docker compose up -d
./seed-vanilla.sh

cd ../docker-furry
docker compose up -d
./seed-furry.sh

cd ../docker-gaming
docker compose up -d
./seed-gaming.sh

cd ../..
npm test
```

The full suite also requires the Chromium, Edge, Firefox, and WebKit runtimes installed by
`npm run test:setup`. It can take more than an hour on slower machines, so focused projects
are the friendlier choice while you are actively developing.

## Browser Test Coverage

Each browser spec answers a plain-language question:

| Spec | What it checks |
|---|---|
| 01 - Page loads | Does the schedule page open without errors? |
| 02 - Tabs | Do the Programming, Essentials, and Hours tabs switch correctly? |
| 03 - Filters | Do search, day/tag/room filters, reset, combined filters, and cancelled-event handling work? |
| 04 - Favorites | Can a visitor star an event, and does it survive a page reload? |
| 05 - Modals | Do event popups show the correct details and close cleanly? |
| 06 - Calendar | Do event actions, full-schedule subscriptions, disabled-feed behavior, clipboard, and reduced-motion behavior work? |
| 07 - Hash routing | Do deep links such as `/schedule/#hour` and `/schedule/#evt=123` resolve? |
| 08 - Kiosk mode | Does the kiosk view work at 1080p with the appropriate controls hidden? |
| 09 - Responsive | Does the layout hold up across phone, tablet, and wide displays? |
| 10 - No jQuery / Bootstrap | Confirms the old jQuery and Bootstrap behavior is gone after the refactor. |
| 11 - Badges | Do VIP, Guest of Honor, and cancelled badges render correctly? |
| 12 - Shortcode | Does `[onlinesched_schedule]` render on a normal page? |
| 13 - Hours block | Does the Hours tab and block display correctly? |
| 14 - Standalone | Does the plugin work without host-theme dependencies? |
| 15 - Solo event block | Does the single-event block render on its own? |
| 16 - Gaming demo | Does the gaming room group and filter demo behave correctly? |

New browser tests belong in `tests/e2e/`. Use the central selectors in
`tests/helpers/selectors.js`, and keep assertions valid across the applicable environments
whenever possible. Standalone-specific assertions belong in
`tests/e2e/14-standalone.spec.js`.

## Browsers and Screen Sizes

| Project | Browser | Viewport | Simulates |
|---|---|---|---|
| `desktop` | Chromium | 1280 x 800 | Laptop or desktop |
| `mobile-iphone` | Chromium | 375 x 812 | iPhone |
| `mobile-android` | Chromium | 412 x 915 | Android phone |
| `tablet` | Chromium | 768 x 1024 | Tablet portrait |
| `tablet-landscape` | Chromium | 1366 x 1024 | Large tablet landscape |
| `kiosk` | Edge | 1920 x 1080 | Kiosk television |
| `firefox` | Firefox | 1280 x 800 | Gecko engine |
| `webkit` | WebKit | 1280 x 800 | Safari-compatible engine |
| `vanilla-wp` | Chromium | 1280 x 800 | Standalone WordPress at port 8081 |
| `furry-wp` | Chromium | 1280 x 800 | Furry demo build at port 8082 |
| `gaming-wp` | Chromium | 1280 x 800 | Gaming demo build at port 8083 |

Chromium coverage also represents browsers such as Brave, Vivaldi, and Opera. Firefox covers
the Gecko engine, while WebKit covers Safari-compatible behavior.

## WP-CLI Import and Year Deletion

The CLI integration harness is destructive only inside the disposable Vanilla stack. It
verifies the exact site URL and container name before it runs and refuses the main Furry
Migration environment.

```bash
cd tests/docker-vanilla
docker compose up -d
./seed-vanilla.sh
cd ../..
npm run test:cli
```

The harness covers initial import, repeat import, updates, restoration, dry-run behavior,
cross-year IDs, non-published statuses, invalid input, cleanup after failure, exact-year
deletion, and preservation of every other year.

## Calendar Subscription Setting

The calendar setting harness exercises the real WordPress option on the disposable Vanilla
site. It checks the enabled-by-default upgrade state, saved `0` and `1` values, the checkbox
sanitizer, editable and code-managed admin states, constant and filter overrides, saved-value
preservation, and schedule-page cache cleaning.

```bash
npm run test:calendar-settings
```

The wrapper refuses any container other than `onlinesched-vanilla-cli` and any site URL other
than `http://localhost:8081`. The original option is restored even if an assertion fails.

## App Feed

The app feed harness exercises the sectioned `json.php` contract (`meta`, `schedule`, `hours`,
`info`) end to end on the disposable Vanilla site: the feed-revision invalidation service
(including suspension and nested suspend/resume), a mutation-to-section matrix built on real
posts/terms/options, CSV import and delete-year batch semantics (dry runs never touch,
real runs fire exactly one revision bump regardless of row count), durable `event_uid`
identity across delete-year and reimport, builder output shape against the contract fixtures
in `tests/fixtures/app-feed/`, publication gating, schedule-year scoping, and cancelled/adult
tag derivation. An HTTP layer then checks `ETag`/`304`, the default section, and the `info`
404 path directly against the running site.

```bash
npm run test:app-feed
```

The wrapper refuses any container other than `onlinesched-vanilla-cli` and any site URL other
than `http://localhost:8081`, and fails loudly with the exact startup command if the Vanilla
environment is not running. Every fixture the harness creates (posts, pages, terms, options)
is uniquely named per run and removed again in a `finally` block, so it is safe to run
repeatedly against the same disposable site.

## Deterministic Fixture Tests

Run the standalone fixture checks without starting a browser:

```bash
npm run test:fixture
```

Generate a disposable 150-event CSV for any convention start date:

```bash
php tests/fixtures/generate-furry-test-data.php \
  --start-date=2027-06-30 \
  --days=4 \
  --output=tests/fixtures/generated/furry_test_data.csv
```

The generator defaults to 150 events, external IDs beginning at 4000, three consecutive
days, and deterministic seed `20270630`. `--start-date` is always required. The optional
`--count`, `--start-id`, `--days`, and `--seed` arguments support other disposable schedules
without changing the nine-column CSV format.

Generated CSV files stay ignored under `tests/fixtures/generated/`. The fixture test verifies
byte-for-byte repeatability, the committed golden SHA-256, date-boundary behavior, expected
anchor rows, and invalid-argument handling.

## Seed Data

Each browser environment has its own seed path:

- Furry Migration: `npm run test:seed`, which calls `tests/fixtures/seed-test-events.sh`.
- Vanilla WordPress: `tests/docker-vanilla/seed-vanilla.sh`.
- Furry demo: `tests/docker-furry/seed-furry.sh`.
- Gaming demo: `tests/docker-gaming/seed-gaming.sh`.

The deterministic 150-event fixture is separate from the smaller browser seeds. It exists
for importer and maintenance-command coverage, not as a replacement for the focused UI data.

## Quick Reference

```bash
# First-time browser setup for the Furry Migration environment
npm install
npm run test:setup

# Run one Furry Migration project
npm test -- --project=desktop

# Run the standalone Vanilla project
npm test -- --project=vanilla-wp

# Run every project after all four environments are ready
npm test

# Refresh Furry Migration browser seed data
npm run test:seed

# Run the destructive Vanilla-only WP-CLI integration harness
npm run test:cli

# Check the calendar subscription setting on the disposable Vanilla site
npm run test:calendar-settings

# Run the app feed (json.php) integration harness on the disposable Vanilla site
npm run test:app-feed

# Run the fast, container-free fixture check
npm run test:fixture

# Open the latest Playwright HTML report
npx playwright show-report tests/playwright-report
```
