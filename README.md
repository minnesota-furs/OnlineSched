# OnlineSched
A flexible event scheduling plugin for WordPress conventions and organizations.

## Dedication

This project is dedicated in memory of Cyn and Snap.

Both supported me during the years this tool was being built, and both helped keep me moving when the work was heavier than it looked. They are part of why this project made it across the line.

## Community and Support

We built OnlineSched for Furry Migration, but we are sharing it because we want other conventions and community organizations to have a capable scheduling tool without starting from scratch. It is our gift to the wider community, and both nonprofit and for-profit organizations are welcome to use it.

Furry Migration is a nonprofit organization, and this project is maintained by volunteers. We are happy to help when we can. If a for-profit organization needs dedicated support beyond what our volunteers can provide, please reach out so we can talk about options.

## Acknowledgements

OnlineSched began as a prototype built by the original Furry Migration team, with Ringer and Mouring as key builders. It was later expanded, updated, and cleaned up for this open-source release. The project reflects the work of everyone who contributed along the way.

## Contents

* [Installation](#installation)
* [First-Time Setup](#first-time-setup)
* [Embedding the Schedule](#embedding-the-schedule-shortcode)
* [Calendar Feeds and External Endpoints](#calendar-feeds-and-external-endpoints)
* [Favorites and Privacy](#favorites-and-privacy)
* [Customizing the Look](#customizing-the-look)
* [For Theme Developers](#for-theme-developers)
* [WP-CLI Schedule Maintenance](#wp-cli-schedule-maintenance)
* [Development and Testing](#development-and-testing)

## Installation

You can install this plugin either by downloading a pre-built release or by building it from source.

### Option 1: Download from GitHub Releases (Recommended)
1. Go to the [Releases](https://github.com/minnesota-furs/OnlineSched/releases) page on GitHub.
2. Download the `OnlineSched-x.x.x.zip` file from the latest release.
3. In your WordPress admin dashboard, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Choose the downloaded zip file and click **Install Now**.
5. Activate the plugin.

### Option 2: Build from Source
If you are developing the plugin or want to build it yourself:

1. Clone the repository into your WordPress `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/minnesota-furs/OnlineSched.git
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

If you want to package a release zip locally, run `npm run release`. This generates a clean installation zip in the `dist/` directory.

## First-Time Setup

After activating the plugin, complete these steps before your schedule will appear:

1. **Create your schedule page.** Add a new Page in WordPress, give it a title such as "Schedule," set its template to **Online Schedule**, and publish it.
2. **Connect the page to OnlineSched.** Go to **Event Scheduling > Event Settings** and select the page you just created as the Schedule page. If you also use Kiosk, Live, Hours, or Map pages, assign those here too.
3. **Set the active year.** Enter the current convention or schedule year on the same settings screen.
4. **Add events.** Go to **Event Scheduling > Events** and assign each event its room, day, tags, and panelists.
5. **Optional: customize the display.** Use the Colors and Header Flare settings to match your organization's visual identity.
6. **Optional: configure social login.** Go to **Event Scheduling > Social Login** to add credentials for the providers you want to use. No login providers appear until credentials are configured.

Once those pieces are in place, the schedule is ready to publish.

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

**Use only one `[onlinesched_schedule]` shortcode per page.**

Each schedule uses a fixed set of element IDs for tabs, event links, and modals. Adding a second schedule creates duplicate IDs, so its tabs and event links may control the first schedule instead.

If you need two schedule views, use one shortcode with the `tabs`, `tag`, or `room` attribute, or place the views on separate pages and link between them. The dedicated **Online Schedule** page template has the same one-schedule-per-page limitation.

## Calendar Feeds and External Endpoints

OnlineSched exposes public read-only endpoints for calendar clients, kiosks, signage,
and other external displays. These endpoints return events from the configured schedule
year only.

### Single Event ICS

Use this when you need one event as an `.ics` file:

```text
/wp-content/plugins/OnlineSched/ical.php?cal-id=123
```

Parameters:

* `cal-id` - WordPress post ID for an `os_event`.

### Filtered Schedule ICS

Use this for full-schedule or filtered calendar subscriptions:

```text
/wp-content/plugins/OnlineSched/icalby.php
/wp-content/plugins/OnlineSched/icalby.php?room=main-stage
/wp-content/plugins/OnlineSched/icalby.php?tag=essentials
/wp-content/plugins/OnlineSched/icalby.php?room=main-stage,panel-room-a&tag=essentials&limit=10&textlen=300
/wp-content/plugins/OnlineSched/icalby.php?room=all
/wp-content/plugins/OnlineSched/icalby.php?tag=all&textlen=0
/wp-content/plugins/OnlineSched/icalby.php?room=all&cancelled_title_prefix=true
```

Parameters:

* `room` or `rooms` - one or more `os_room` slugs, comma separated. Use `all` for all rooms.
* `tag` or `tags` - one or more `os_tag` slugs, comma separated. Use `all` for all tags.
* `limit` - maximum number of upcoming events to include.
* `textlen` - maximum description length. Default is `250`; use `0` or a negative value for full descriptions.
* `cancelled_title_prefix` - set to `1`, `true`, `yes`, or `on` to prefix cancelled event summaries with `Cancelled - `. This opt-in compatibility aid is limited to full and filtered schedule ICS feeds.

Cancelled events are included with `STATUS:CANCELLED`. The optional title prefix supplements
that standards-compliant status for display systems that do not show cancellation state; it
does not change stored event titles, individual event feeds, or any other output.

ICS output uses UTC `DTSTART`/`DTEND` values with trailing `Z`, CRLF line endings,
folded content lines, `METHOD:PUBLISH`, and `text/calendar; method=PUBLISH` response
headers for broad compatibility with Google Calendar/Gmail, Outlook, Microsoft 365,
Apple Calendar, and Android calendar apps. Calendar metadata includes the configured
calendar name and the site's WordPress timezone. Event UIDs are generated as globally
scoped values using the site host.

### Schedule Subscription Publishing

**Disabling schedule subscriptions empties full and filtered schedule feeds. It does
not disable individual event calendar actions.**

When you are preparing a new schedule year, you can pause full-schedule calendar
subscriptions without taking away the calendar buttons on events that are already
visible. Go to **Event Scheduling > Event Settings > Schedule Calendar Subscriptions**
and clear **Publish full-schedule calendar subscriptions**.

While publishing is disabled:

* Full and filtered schedule feeds return a valid empty calendar, including feeds
  filtered by room or tag.
* Full-schedule subscription buttons are hidden.
* Existing subscribers stay connected and receive the schedule again after publishing
  is re-enabled at the same feed URL.
* Individual event calendar actions remain available because those events are already
  visible on the schedule page.
* The public schedule, individual event feeds, and JSON feed are unchanged.

The setting is enabled by default on upgrade. Calendar applications decide when to
refresh subscriptions, so changes may not appear immediately after you pause or resume
publishing.

### JSON Feed

OnlineSched includes a public JSON app feed for the companion app, kiosks, and other
structured clients that need schedule, hours, and info data without loading the full
interactive schedule.

**Breaking change in 3.0.0**: `json.php` replaced its former signage-oriented output
(a flat array of `room`/`title`/`startTime`/`description`) with the sectioned,
schema-versioned contract documented below. This was a deliberate, owner-accepted
break made while the project had no known external feed consumers; see
[CHANGELOG](CHANGELOG.md) for the full list of what changed. Signage displays built
against the old output should migrate to `?section=schedule`.

The feed has four sections, selected with `?section=`:

```text
/wp-content/plugins/OnlineSched/json.php?section=meta
/wp-content/plugins/OnlineSched/json.php?section=schedule
/wp-content/plugins/OnlineSched/json.php?section=hours
/wp-content/plugins/OnlineSched/json.php?section=info
/wp-content/plugins/OnlineSched/json.php?section=info&page=parking
/wp-content/plugins/OnlineSched/json.php
```

Omitting `section` is the same as `?section=schedule`.

#### `meta` — handshake

The first call an app makes. Reports the schema version, a revision number per
section, a composed change stamp, the convention window, publication state, and the
configured info-page index.

```json
{
  "schema_version": 1,
  "con_name": "Furry Migration",
  "year": "2026",
  "timezone": "America/Chicago",
  "revisions": { "schedule": 41, "hours": 7, "info": 12 },
  "change_stamp": "41.7.12",
  "con_start": "2026-09-09",
  "con_end": "2026-09-14",
  "public_dates": { "start": "2026-09-11", "end": "2026-09-13" },
  "schedule_published": true,
  "sections": ["schedule", "hours", "info"],
  "info_pages": [
    { "slug": "parking", "title": "Parking", "updated": "2026-07-20T15:04:00Z" }
  ]
}
```

An app can poll `meta` cheaply and only refetch a section whose revision number (or
the composed `change_stamp`) has moved since its last successful fetch.

#### `schedule` — full active-year schedule

```json
{
  "schema_version": 1,
  "generated": "2026-07-23T14:02:11Z",
  "timezone": "America/Chicago",
  "year": "2026",
  "schedule_published": true,
  "rooms": [{ "slug": "main-stage", "name": "Main Stage" }],
  "tags": [{ "slug": "essentials", "name": "Essentials" }],
  "events": [
    {
      "event_uid": "2026:OPENCER01",
      "wp_post_id": 417,
      "title": "Opening Ceremonies",
      "start": "2026-09-11T18:00:00-05:00",
      "end": "2026-09-11T19:00:00-05:00",
      "rooms": ["main-stage"],
      "tags": ["essentials"],
      "panelists": ["Board"],
      "description_html": "<p>Kick off Furry Migration 2026 with the board and special guests.</p>",
      "cancelled": false,
      "adult": false,
      "modified": "2026-07-20T12:00:00Z"
    }
  ]
}
```

Parameters (same room/tag matching as the ICS schedule feed):

* `room` or `rooms` - one or more `os_room` slugs, comma separated. Omit this value or use `all` to include every room.
* `tag` or `tags` - one or more `os_tag` slugs, comma separated. Use `all` to include every tag.
* `group` - a named room/tag group (see [JSON Groups](#json-groups) below). A requested-but-unconfigured group returns an empty schedule rather than guessing.

`cancelled` and `adult` are derived from tag names, matched case-insensitively: an
event tagged `Cancelled` (or `Canceled`) reports `cancelled: true`; an event tagged
`Restricted` reports `adult: true`. `event_uid` is a durable identity safe to key
app-side favorites and reminders on — see [Event UID](#event-uid) below.

When schedule publication is turned off (see [App Feed Settings](#app-feed-settings)
below), this section still responds `200 OK` with `schedule_published: false` and
empty `events`/`rooms`/`tags` arrays — never an error or a 404.

#### `hours` — configured Hours page, losslessly

A direct export of the Hours blocks on the page configured under **Event Scheduling
> Event Settings > Hours Page**. Free-form hours/note text is exported as sanitized
plain text (`sanitize_text_field`) preserving the authored structure; there is no
open/close time parsing.

```json
{
  "schema_version": 1,
  "generated": "2026-07-23T13:40:00Z",
  "departments": [
    {
      "name": "Dealers Den",
      "location": "",
      "days": [
        { "day": "Friday", "entries": [{ "hours_text": "Noon – 6 PM", "note": "" }] }
      ]
    }
  ]
}
```

#### `info` — admin-curated pages

```text
/wp-content/plugins/OnlineSched/json.php?section=info
```

returns the configured page index:

```json
{
  "schema_version": 1,
  "generated": "2026-07-23T12:00:00Z",
  "pages": [
    { "slug": "parking", "title": "Parking", "updated": "2026-07-20T15:04:00Z" }
  ]
}
```

Add `&page={slug}` for one page's sanitized content and image list:

```text
/wp-content/plugins/OnlineSched/json.php?section=info&page=parking
```

```json
{
  "schema_version": 1,
  "generated": "2026-07-23T12:00:00Z",
  "page": {
    "slug": "parking",
    "title": "Parking",
    "updated": "2026-07-20T15:04:00Z",
    "content_html": "<h2>Ramps</h2><p>The closest ramp is attached to the hotel.</p>",
    "images": ["https://example.org/wp-content/uploads/2026/07/parking-map.png"]
  }
}
```

An unknown slug returns `404` with `{"schema_version": 1, "error": "unknown_info_page"}`.

#### ETag / Last-Modified / 304

Every section response sends `ETag` and, once a section has moved at least once,
`Last-Modified`. Send the returned `ETag` back as `If-None-Match` on the next request
to get a `304 Not Modified` with no body when nothing has changed. ETags combine the
schema version, the section's revision number, the request's resolved filters, and a
hash of the exact response bytes — so an ETag changes if and only if the
representation changes, and identical representations keep identical ETags. Body,
`ETag`, and `Last-Modified` are always derived from one coherent revision snapshot,
even when schedule edits land mid-request.

#### Event UID

`event_uid` is stable across CSV reimports, so it is safe to key app-side favorites
and reminders on. Imported events derive it from the schedule year and the CSV's
external event ID, so deleting and reimporting a schedule year reproduces the same
UID for the same external ID. Manually created events (no external ID) get a UUID
generated once and persisted in post meta.

#### App Feed Settings

Configured under **Event Scheduling > Event Settings > App Feed**:

* **App Schedule Publication** - controls the `schedule` section only, independent of
  Schedule Calendar Subscriptions (ICS) above. When disabled, `schedule` responds
  successfully with `schedule_published: false` and empty `events`/`rooms`/`tags` so
  app clients can show an intentional pre-publication state. The `meta`, `hours`, and
  `info` sections, and the public schedule page, are unaffected.
* **Operational Start/End Date** - the convention's operational window, including
  pre-con setup and post-con activities such as dead dog. Reported in `meta` as
  `con_start`/`con_end`. The start date must be on or before the end date; saving
  an inverted pair keeps the previously saved dates and shows an error instead.
* **Public Start/End Date** - the official, publicly announced convention dates.
  Reported in `meta` as `public_dates.start`/`public_dates.end`. Same start-before-end
  rule as above.
* **App Info Pages** - the pages served by the `info` section (parking, hotel, code
  of conduct, and similar), picked from a page dropdown and added to an ordered list
  with an Add button. Use the list's Up/Down/Remove controls to reorder or drop a
  page; the order shown is the order the app feed serves them in. Only published
  pages appear in the feed.

Section revisions bump automatically on every relevant change: event saves,
publish/trash/restore, taxonomy term edits, CSV imports and year deletes (each as one
batch touch, not once per row), and the settings above.

#### JSON Groups

Groups let a site keep short, readable feed URLs for the `schedule` section (signs,
kiosks, and other external displays). OnlineSched does not ship organization-specific
groups by default. If a group is requested but not configured, the schedule section
returns an empty result instead of guessing.

Add groups from a theme or small site plugin with the `os_json_room_groups` filter:

```php
add_filter(
	'os_json_room_groups',
	function ( $groups ) {
		$groups['programming'] = array(
			'rooms' => array(
				'main-stage',
				'panel-room-a',
				'panel-room-b',
				'workshop-room',
			),
		);

		$groups['gaming'] = array(
			'exclude_rooms' => array(
				'main-stage',
				'panel-room-a',
				'panel-room-b',
				'registration',
			),
			'exclude_tags' => array(
				'open-gaming',
			),
		);

		return $groups;
	}
);
```

The available group keys are up to your site. For example, a site could use
`group=dealers`, `group=main-events`, or `group=kids-track` as long as those keys are
registered through the filter.

Sites that prefer configuration over code can store an array or JSON object in the
`onlinesched_json_room_groups` option. The filter still runs after the option is read,
so a theme can add, change, or remove groups for the current site.

### Finding Slugs

Room and tag filter values use WordPress term slugs, not display names. For example, a
room named `Main Stage` may have the slug `main-stage`.

Admins can place this shortcode on a private utility page to show current endpoint
examples and copyable room/tag slugs:

```text
[ical_schedule_cheat_display]
```

### Feed Caching

Calendar clients control how often they refresh subscriptions. Apple, Google, Outlook,
and other clients may cache feeds for hours or longer. The schedule page is always the
most current source when last-minute changes matter.

## Favorites and Privacy

Visitors can star events without logging in. Those logged-out favorites are stored only
in that visitor's browser as local schedule state, are not private account data, and are
not synced to the server. They can be changed by anyone using the same browser profile.

If Social Login is configured and the visitor logs in, OnlineSched ties synced favorites
to the active OAuth session and merges the browser-local favorites into that server-side
favorite list. Logging out ends the synced session; the local browser favorites feature
continues to work without requiring login.

## Customizing the Look

### Colors

Go to **Event Scheduling → Event Settings → Colors**. Every color the plugin uses is configurable there — no CSS required. Changes take effect immediately across the schedule, badges, tabs, and modals.

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

The Coyote icon used in the Floof Den demo comes from [SVGRepo](https://www.svgrepo.com/svg/97569/coyote) and is available under the CC0 license.

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

Go to **Event Scheduling > Event Settings > Header Flare**. You can:
- Turn it off completely with the checkbox.
- Choose a built-in icon from the select menu: paw, dog, cat, crow, horse, dragon, otter, hippo, frog, or fish.
- Use your organization's logo, an SVG, an ice cream cone, or anything else that fits by pasting an image URL into the Image field. When an image URL is set, it takes over from the icon selection.
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
| `os_ical_uid_prefix` | filter | Prefix for generated iCal event UIDs; defaults to `os-` |
| `os_ical_timezone` | filter | Calendar metadata timezone; defaults to the site's named WordPress timezone or `UTC` |
| `os_ical_calendar_description` | filter | Description used in iCal calendar metadata |
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

## WP-CLI Schedule Maintenance

OnlineSched can import the same nine-column CSV used by the WordPress admin uploader. The format and meaning of its columns do not change:

```text
ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags
```

Import a file into the active schedule year, or select a year without changing the active `onlinesched_year` option:

```bash
wp onlinesched import /absolute/path/events.csv
wp onlinesched import /absolute/path/events.csv --year=2026
wp onlinesched import /absolute/path/events.csv --year=2026 --dry-run
```

`--dry-run` validates the complete file and reports planned inserts, updates, and errors without writing anything. Re-importing a CSV updates matching events in place using the schedule year and CSV `ID`; events absent from the file are left alone.

You can also permanently delete every OnlineSched event assigned to one explicitly named year:

```bash
wp onlinesched delete-year 2026 --dry-run
wp onlinesched delete-year 2026
wp onlinesched delete-year 2026 --yes
```

Run the dry-run first so you can review the exact count. The delete command requires confirmation unless `--yes` is supplied. It does not change the active schedule year or delete rooms, speakers, tags, or day terms.

---

## Tested Against

* **WordPress:** 6.4 through 6.8
* **PHP:** 8.2, 8.3
* **Themes:** Furry Migration (Classic), Twenty Twenty-Four, Twenty Twenty-Five (Block)

## Development and Testing

Release zips include compiled assets and Composer dependencies, so normal WordPress users can install them directly. Source checkouts need Composer and npm before they are ready to use.

In the Furry Migration Docker environment, run PHP, Composer, and npm inside `fm-php`:

```bash
docker exec fm-php bash -c "cd /var/www/html/wp-content/plugins/OnlineSched && composer install --no-dev"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm install"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm run build"
```

Contributors can find the complete browser, WP-CLI, fixture, environment, and quick-reference instructions in the [OnlineSched test guide](tests/README.md).
