# OnlineSched
A flexible event scheduling plugin for WordPress conventions and organizations.

## Dedication

This project is dedicated in memory of Cyn and Snap.

Both supported me during the years this tool was being built, and both helped keep me moving when the work was heavier than it looked. They are part of why this project made it across the line.

**Note to the Public:** We built OnlineSched primarily for our own use at Furry Migration, but we want the world to have it as a gift! The developers of this project are open to fully supporting and helping people, with the goal of enabling other groups to have this tool. We hope everyone—both for-profit and non-profit organizations—can use this as a free solution. We are a non-profit organization and do this with volunteers. If your organization needs dedicated support above and beyond what we can provide for free, and you are not a 501(c)(3) non-profit, please reach out to discuss options.

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

### Development Notes

Release zips include compiled assets and Composer dependencies so normal WordPress users can install them directly. Source checkouts are for development and need Composer plus npm before they are usable.

In the Furry Migration Docker environment, run PHP, Composer, and npm from inside `fm-php`:

```bash
docker exec fm-php bash -c "cd /var/www/html/wp-content/plugins/OnlineSched && composer install --no-dev"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm install"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm run build"
```

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
* `#evt={post_id}` - one per event row, used by deep links such as `/schedule/#evt=123`.
* `#modal-schedule`, `#login-modal`, `#info-modal`, `#android-google-calendar-modal` —
  appended to the page once per render.

Embedding the shortcode twice on the same page produces duplicate IDs, which is invalid
HTML. In practice, the symptoms are:

* Clicking an event in the second schedule opens the event modal pointing at the first
  schedule.
* Tab clicks in the second schedule scroll the first schedule.
* `/your-page/#evt=123` always resolves to the first schedule, regardless of which
  schedule contains the event.
* Deep links to `#hours`, `#tag=tag-slug`, or combined filters such as
  `#tag=tag-slug&room=room-slug` activate the tab/filter state in the first schedule
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

## Migrating Legacy Furry Migration Hours

This section is only for Furry Migration's old ACF-powered Hours of Operations page.
Fresh OnlineSched installs should use the native **Hours of Operations** block directly.

Before removing the old theme renderer from a production site, migrate the configured
Hours page to native OnlineSched blocks:

```bash
docker exec fm-php bash -c "cd /var/www/html && wp --allow-root onlinesched migrate-hours --dry-run"
docker exec fm-php bash -c "cd /var/www/html && wp --allow-root onlinesched migrate-hours --backup"
```

To target a specific page first, pass the page ID:

```bash
docker exec fm-php bash -c "cd /var/www/html && wp --allow-root onlinesched migrate-hours 2207 --dry-run"
docker exec fm-php bash -c "cd /var/www/html && wp --allow-root onlinesched migrate-hours 2207 --backup"
```

The `--backup` flag stores the previous page content in
`_onlinesched_hours_premigration`. The migration preserves existing intro copy, removes the
old `[hours_of_operations]` shortcode, and appends the native Hours block. Do not delete old
ACF post meta until the schedule Hours tab has been checked on desktop and mobile.

## First-Time Setup

After activating the plugin, complete these steps before your schedule will appear:

1. **Create your schedule page.** Add a new Page in WordPress, give it a title like "Schedule", and set its template to **Online Schedule** (in the Page Attributes box on the right). Publish it.
2. **Tell the plugin where your pages are.** Go to **Event Scheduling → Event Settings** in the WordPress admin. Under the Pages section, select the page you just created as the Schedule page. If you also have Kiosk, Live, Hours, and Map pages, assign those too.
3. **Set the year.** On the same settings screen, enter the active convention year.
4. **Optional: customize colors.** The Colors section lets you change the primary green, secondary blue, accent orange, gold, and danger red to match your organization's branding. The defaults match Furry Migration's palette.
5. **Optional: configure social login.** Go to **Event Scheduling → Social Login** to enter your Google, Discord, Telegram, or Facebook OAuth credentials. Social login is completely disabled until you add credentials — no providers show up in the login modal on a fresh install.

That's it. Add events under **Event Scheduling → Events**, assign them rooms, days, tags, and panelists, and they will appear in the schedule automatically.

---

## Customizing the Look

### Colors

Go to **Event Scheduling → Event Settings → Colors**. Every color the plugin uses is configurable there — no CSS required. Changes take effect immediately across the schedule, badges, tabs, and modals.

### Icons & Attribution

The schedule uses [Font Awesome Free](https://fontawesome.com/) for most UI elements. Custom icons (like the Coyote in the Floof Den demo) may be sourced from external libraries.

*   **Coyote Icon:** Sourced from [SVGRepo](https://www.svgrepo.com/svg/97569/coyote) (CC0 License).

### Fonts

The plugin ships with [Metropolis](https://fontsource.org/fonts/metropolis) as its default schedule font. It loads this from your server as a self-hosted web font — no Google Fonts, no external requests.

**If your theme already provides Metropolis** (for example, via Adobe Fonts / Typekit), you can tell the plugin not to load its own copy to avoid downloading the font twice. Add this to your theme's `functions.php`:

```php
// Our theme already loads Metropolis — skip the plugin's copy.
add_filter( 'onlinesched_load_fonts', '__return_false' );
```

**If you want a completely different font**, you don't need to disable anything. Just override the CSS variable after the plugin's stylesheet loads:

```php
// In functions.php or a custom plugin — point the schedule at your own font.
add_action( 'wp_head', function () {
    echo '<style>:root { --os-font-family: "Your Font Name", sans-serif; }</style>';
} );
```

The `--os-font-family` variable controls the day-header and hour-header typeface. Everything else inherits from your theme.

### Icons (Font Awesome)

The schedule uses [Font Awesome Free](https://fontawesome.com/) for icons like the calendar, star, copy, and clock symbols. The plugin loads its own copy so it works even if your theme doesn't include Font Awesome.

**If your theme already loads Font Awesome**, add this to your `functions.php` to prevent loading it twice:

```php
// Our theme already enqueues Font Awesome — skip the plugin's copy.
add_filter( 'onlinesched_load_fontawesome', '__return_false' );
```

If you're unsure whether your theme loads it, leave the filter off. Loading it twice doesn't break anything visually — it just wastes a network request.

### Custom CSS

The plugin exposes CSS custom properties (variables) so you can adjust the look without hunting through the stylesheet. The most useful ones:

```css
/* Drop this in Appearance → Customize → Additional CSS */
:root {
  --os-font-size-base: 16px;                 /* base font size for all em values inside the schedule */
  --os-font-family: "Your Font", sans-serif; /* schedule header font */
  --os-radius: 0px;                          /* make corners square */
  --os-tabs-height: 48px;                    /* height of the tab bar */
  --os-transition: 0.15s ease;               /* speed up animations */
}
```

`--os-font-size-base` is the most important one to know about. Every `em`-relative size inside the schedule (titles, metadata, badges, icons) computes from this value. The default is `16px`, which matches most themes. If your theme uses a larger body font — Twenty Twenty-Five ships with `18px`, for example — the schedule will look proportionally smaller than surrounding content. Set this to match your body text and everything re-scales correctly:

```css
/* Match a theme that uses 18px body text */
:root { --os-font-size-base: 18px; }
```

Colors have their own admin UI, but you can also override them with CSS variables if you need values the settings screen doesn't expose:

```css
:root {
  --os-green: #00aa55;   /* primary color (tabs active, badges, etc.) */
  --os-blue:  #1a3a5c;   /* secondary color (day headers, modal text) */
  --os-gold:  #ffcc00;   /* favorites star color */
}
```

### Header Flare (the paw print in day headers)

Go to **Event Scheduling -> Event Settings -> Header Flare**. You can:
- Turn it off completely with the checkbox.
- Choose a built-in icon from the select menu: paw, dog, cat, crow, horse, dragon, otter, hippo, frog, or fish.
- Use anything you want — your org's logo, an SVG, an ice cream cone, literally anything — by pasting an image URL into the Image field. When an image URL is set it takes over from the icon select entirely.
- Leave the icon field blank to hide the icon while keeping the flare enabled (useful if you only want a custom image or specific CSS effects).

---

## For Theme Developers

### Template Overrides

Every template the plugin renders can be overridden by your theme — the same pattern WordPress uses for WooCommerce templates.

**To override the full schedule page**, create this file in your theme:

```
your-theme/onlinesched/page-schedule.php
```

**To override a single partial** (a smaller piece of the page), create the matching file under `onlinesched/partials/` in your theme. For example, to replace the tab bar:

```
your-theme/onlinesched/partials/schedule-tabs.php
```

Available partials: `schedule-tabs`, `schedule-filters`, `schedule-event-row`, `schedule-calendar-actions`, `schedule-event-modal`, `login-modal`, `info-modal`, `android-google-calendar-modal`.

Copy the original from `wp-content/plugins/OnlineSched/templates/partials/` as your starting point.

### PHP Hooks Reference

The plugin fires these actions and filters so themes and other plugins can extend behaviour without editing plugin files.

| Hook | Type | When it fires |
|---|---|---|
| `os_before_schedule` | action | Before the schedule wrapper `<div>` is output |
| `os_after_schedule` | action | After the schedule wrapper closes |
| `os_before_schedule_item` | action | Before each event row, receives `$post_id` |
| `os_after_schedule_item` | action | After each event row, receives `$post_id` |
| `os_event_description` | filter | Filters the event description HTML, receives `($html, $post_id)` |
| `os_event_badge_html` | filter | Filters the badge HTML for a row, receives `($html, $post_id)` |
| `os_render_schedule_args` | filter | Filters the full args array before rendering |
| `os_sticky_offsets` | filter | Array of sticky pixel offsets for the tab bar; use this if your theme has a sticky header |
| `os_kiosk_head_styles` | filter | Array of stylesheet URLs injected into the kiosk page `<head>` |
| `onlinesched_load_fontawesome` | filter | Return `false` to skip loading the plugin's Font Awesome bundle |
| `onlinesched_load_fonts` | filter | Return `false` to skip loading the plugin's Metropolis font bundle |

**Example — bumping the sticky tab offset for a 60px sticky header:**

```php
add_filter( 'os_sticky_offsets', function ( $offsets ) {
    $offsets['desktop'] = 60;
    $offsets['mobile']  = 60;
    return $offsets;
} );
```

**Example — adding content above the schedule:**

```php
add_action( 'os_before_schedule', function () {
    echo '<p class="schedule-notice">Schedule is subject to change.</p>';
} );
```

---

## Tested Against

*   **WordPress:** 6.4, 6.5, 6.6, 6.7
*   **PHP:** 8.2, 8.3
*   **Themes:** Furry Migration (Classic), Twenty Twenty-Four, Twenty Twenty-Five (Block)

========================================================================
HOW TO RUN THE AUTOMATED TESTS
========================================================================

OnlineSched uses a Playwright end-to-end test suite that verifies the plugin
works correctly across different browsers and screen sizes. The suite runs
against two environments: the reference **Furry Migration Docker** stack and
a standalone **Vanilla WordPress** stack.

------------------------------------------------------------------------
ENVIRONMENT 1: Furry Migration (Local Docker)
------------------------------------------------------------------------

> **Note:** This environment is used for reference development. It requires
> the full Furry Migration Docker stack.

1. Ensure the Docker stack is running from the project root.
2. Navigate to `public_html/wp-content/plugins/OnlineSched`.
3. Run `npm run test:setup` (first time only) to install browsers and seed data.
4. Run `npm test` to execute the full suite.

------------------------------------------------------------------------
ENVIRONMENT 2: Vanilla WordPress (Standalone)
------------------------------------------------------------------------

> **Note:** This environment ensures the plugin works without theme dependencies.

1. Navigate to `tests/docker-vanilla`.
2. Run `docker-compose up -d`.
3. Run `./seed-vanilla.sh` to install WordPress and seed data.
4. Navigate back to the plugin root and run:
   `npx playwright test --project=vanilla-wp`

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
07 - Hash routing     Does /schedule/#hour or /schedule/#evt=123 work?
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
