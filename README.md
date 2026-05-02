# OnlineSched
A flexible event scheduling plugin for WordPress conventions and organizations.

## Installation

You can install this plugin either by downloading a pre-built release or by building it from source.

### Option 1: Download from GitHub Releases (Recommended)
1. Go to the [Releases](https://github.com/onlinesched/OnlineSched/releases) page on GitHub.
2. Download the `OnlineSched-x.x.x.zip` file from the latest release.
3. In your WordPress admin dashboard, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Choose the downloaded zip file and click **Install Now**.
5. Activate the plugin.

### Option 2: Build from Source
If you are developing the plugin or want to build it yourself:

1. Clone the repository into your WordPress `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/onlinesched/OnlineSched.git
   cd OnlineSched
   ```
2. Install PHP dependencies via Composer:
   ```bash
   composer install --no-dev
   ```
3. Install Node dependencies and build the assets:
   ```bash
   npm install
   npm run build
   ```
4. Activate the plugin in your WordPress admin dashboard.

If you want to package a release zip locally, run `npm run release`. This will generate a clean installation zip in the `dist/` directory.

## Embedding the Schedule (Shortcode)

You can easily embed the schedule on any post or page using the shortcode:

```text
[onlinesched_schedule]
```

**Optional Attributes:**
* `mode` - Set to `standard` (default), `kiosk` (hides favorites/login), or `live`. Example: `[onlinesched_schedule mode="kiosk"]`
* `tabs` - A comma-separated list of tabs to display. Default is `programming,essentials,hours`. Example: `[onlinesched_schedule tabs="programming,hours"]`
* `tag` - Pre-filter the schedule to only show events with a specific tag slug. Example: `[onlinesched_schedule tag="fursuiting"]`
* `room` - Pre-filter the schedule to only show events in a specific room slug. Example: `[onlinesched_schedule room="mainstage"]`

### Limitations

**Only one `[onlinesched_schedule]` per page.**

The schedule renders with a fixed set of element IDs that the JavaScript, the URL hash
router, and the modal layer all depend on:

* `#schedule` — the main wrapper.
* `#programming`, `#essentials`, `#hours`, `#map` — tab panes.
* `#evt-{post_id}` — one per event row, used by deep links such as `/schedule/#evt-123`.
* `#modal-schedule`, `#login-modal`, `#info-modal`, `#android-google-calendar-modal` —
  appended to the page once per render.

Embedding the shortcode twice on the same page produces duplicate IDs, which is invalid
HTML. In practice, the symptoms are:

* Clicking an event in the second schedule opens the event modal pointing at the first
  schedule.
* Tab clicks in the second schedule scroll the first schedule.
* `/your-page/#evt-123` always resolves to the first schedule, regardless of which
  schedule contains the event.
* Deep links to `#hours` or `#tag-something` activate the tab in the first schedule
  only.

If a host page genuinely needs two schedule views (for example, a "today" filter and an
"all week" filter side by side), use a single shortcode and a tag/room/`tabs`
attribute, or split the views across two separate pages and link between them. Multi-
instance shortcode rendering is not on the 1.0 roadmap; it would require namespacing
every emitted ID and rewriting the JS to scope its DOM queries within a parent
container.

The dedicated page-template path (assigning the "Online Schedule" template to a Page)
has the same constraint for the same reason — only one schedule lives in the DOM at a
time.

========================================================================
HOW TO RUN THE AUTOMATED TESTS
========================================================================

> **Note for Open Source Users:** The Playwright end-to-end test suite is currently tightly coupled to the Furry Migration local Docker environment (`fm-php`). You do not need to run these tests to use the plugin. If you are developing locally outside of that Docker stack, the `npm run test:setup` command will fail because it expects to seed data via WP-CLI inside the `fm-php` container.

These tests check that the schedule page on the website still works
correctly. Think of them like a robot that clicks around the site and
makes sure nothing is broken. The robot tests on different screen
sizes (phone, tablet, big monitor, kiosk TV) and even tries different
web browsers (Chrome, Firefox, Safari, Edge).

------------------------------------------------------------------------
BEFORE YOU START (Furry Migration Environment Only) -- you need these things:
------------------------------------------------------------------------

  1. Docker Desktop is running (the little whale icon in your taskbar)

  2. Your local WordPress site is running and reachable in a browser.
     If your local URL differs from the test configuration, update the test
     base URL before running the suite.

  3. You have a terminal open and you're inside the OnlineSched folder:

         cd public_html/wp-content/plugins/OnlineSched

     If you're not sure where you are, type "pwd" and it should end
     with "plugins/OnlineSched".

------------------------------------------------------------------------
FIRST TIME SETUP (only do this once)
------------------------------------------------------------------------

Step 1 -- Install all the stuff the tests need:

    npm install

   This downloads a bunch of packages. It takes a minute or two.
   You'll see a progress bar. Wait until it finishes.

Step 2 -- Download the test browsers and add fake events to the database:

    npm run test:setup

   This does two things:
     a) Creates 9 fake convention events in the database (like
        "Opening Howl Ceremony" and "Fursuit Parade Staging").
        These fake events are set one week in the future so the
        schedule page shows them.
     b) Downloads the browsers the robot uses to test (Chrome, Edge,
        Firefox, Safari).

   This step takes a few minutes the first time because it has to
   download several browsers. Be patient!

That's it for setup! You only do these two steps once. The fake events
stay in the database and the browsers stay downloaded until someone
wipes everything.

------------------------------------------------------------------------
RUNNING THE TESTS (do this whenever you want to check things)
------------------------------------------------------------------------

Just type:

    npm test

The robot will:
  - Open invisible browser windows in the background
  - Visit the schedule page on Chrome, Firefox, Safari, and Edge
  - Try phone size, tablet size, desktop size, and kiosk TV size
  - Click on tabs, type in the search box, open popups, etc.
  - Tell you if anything is broken

It takes about 5-10 minutes to finish because it tests so many
combinations of browsers and screen sizes.

When it's done you'll see something like:

    150 passed   <-- good! everything works
     20 skipped  <-- these are tests for future features, ignore them
      0 failed   <-- you want this to say 0

If you see "failed" tests, scroll up to read which ones broke and
what the error message says.

------------------------------------------------------------------------
OTHER WAYS TO RUN THE TESTS
------------------------------------------------------------------------

Watch the robot click around (good for seeing what's happening):

    npm run test:headed

Open a fancy interactive dashboard where you can pick which tests
to run and watch them live:

    npm run test:ui

Run ONLY the kiosk TV tests (Edge browser at 1080p):

    npm run test:kiosk

Run ONLY the kiosk tests WITHOUT Edge (use this if Edge is not installed on your machine):

    npm run test:kiosk:chromium

Run ONLY the phone-size tests:

    npm run test:mobile

Run ONLY Firefox tests (good for checking alternative browsers):

    npm run test:firefox

Run ONLY Safari tests:

    npm run test:webkit

Run just one specific test file:

    npx playwright test --config=tests/playwright.config.js tests/e2e/05-modals.spec.js

Run tests matching a keyword:

    npx playwright test --config=tests/playwright.config.js --grep "modal"

See a pretty HTML report after a test run:

    npx playwright show-report tests/playwright-report

------------------------------------------------------------------------
IF TESTS SUDDENLY START FAILING (seed data expired)
------------------------------------------------------------------------

The fake test events are set one week in the future. After that week
passes, the schedule page looks empty and some tests will fail saying
"found 0 events."

Fix it by re-running the seed script:

    npm run test:seed

This adds a fresh batch of fake events for next week. You can run it
as many times as you want. Old expired events just get ignored.

------------------------------------------------------------------------
IF YOU WIPED THE DATABASE
------------------------------------------------------------------------

If someone ran "docker compose down -v" (which erases everything),
the test events are gone too. You'll need to run the full setup again:

    npm run test:setup

Then run the tests normally:

    npm test

------------------------------------------------------------------------
IF A BROWSER IS MISSING
------------------------------------------------------------------------

If you see an error like "browser msedge is not installed" or
"browser webkit is not installed", just run:

    npx playwright install

This re-downloads all the browsers the tests need.

------------------------------------------------------------------------
WHAT ALL THE TEST FILES CHECK
------------------------------------------------------------------------

01 - Page loads       Does the schedule page even open without errors?
02 - Tabs             Do the Programming / Essentials / Hours tabs work?
03 - Filters          Does EVERY filter work?
                        - Text search box
                        - Day dropdown (All Days / Now and Future / Friday etc)
                        - Tag dropdown (Fursuiting, Art, etc)
                        - Room dropdown (Mainstage, Panel Room A, etc)
                        - Reset button (disabled when nothing active)
                        - Two filters active at once (combo test)
                        - Cancelled event shows badge, no calendar buttons
04 - Favorites        Can you star an event? Does it save when you
                      reload the page?
05 - Modals           Do the popup windows open, show the right info
                      (title, date, time, room, description, panelist),
                      and close properly?
06 - Calendar         Do the "Add to Calendar" buttons have correct
                      links (including the event ID in the URL)? Does
                      the copy-to-clipboard animation work? Does
                      reduced-motion accessibility skip animations?
07 - Hash routing     Does /schedule/#hour or /schedule/#evt-123 work?
08 - Kiosk mode       Does the kiosk TV page at /kiosk-schedule/ work
                      at 1080p on Edge? Are favorites and calendar
                      buttons correctly hidden? Do search, filters,
                      tabs, and modals still work?
09 - Responsive       Does the page look right on a phone? A tablet?
                      A big ultra-wide tablet? Does it scroll properly?
10 - No jQuery        (Skipped for now -- runs after the big refactor
                      to make sure old code is fully removed)

------------------------------------------------------------------------
WHAT BROWSERS AND SCREEN SIZES ARE TESTED
------------------------------------------------------------------------

  Browser        Screen Size         What it simulates
  -------------- ------------------- ---------------------------
  Chrome         1280 x 800          Normal laptop/desktop
  Chrome         375 x 812           iPhone
  Chrome         412 x 915           Android phone
  Chrome         768 x 1024          iPad / tablet (portrait)
  Chrome         1366 x 1024         Big tablet / iPad landscape
  Edge           1920 x 1080         Kiosk TV display (1080p)
  Firefox        1280 x 800          Firefox on desktop
  Safari         1280 x 800          Safari on desktop (WebKit)

This covers almost every browser engine that exists:
  - Chrome/Edge = Chromium engine (also covers Brave, Vivaldi, Opera)
  - Firefox = Gecko engine (also covers Waterfox, Pale Moon, etc.)
  - Safari = WebKit engine (also covers Orion, GNOME Web, etc.)

------------------------------------------------------------------------
QUICK REFERENCE (copy-paste cheat sheet)
------------------------------------------------------------------------

First time setup:
    cd public_html/wp-content/plugins/OnlineSched
    npm install
    npm run test:setup

Run all tests:
    npm test

Refresh expired test data:
    npm run test:seed

See test report:
    npx playwright show-report tests/playwright-report

------------------------------------------------------------------------
