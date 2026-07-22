=== OnlineSched ===
Contributors: bl, bm, al
Tags: events, schedule, calendar, convention, timetable
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 2.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A flexible event scheduling plugin for WordPress conventions and organizations.

== Description ==

OnlineSched manages event schedules with rooms, tags, days, panelists, favorites, social login,
calendar feeds, kiosk views, and configurable display settings.

Social login providers are disabled by default until credentials are configured by a site admin.

== Installation ==

1. Upload the `OnlineSched` folder to `wp-content/plugins/`.
2. Activate OnlineSched in WordPress.
3. Open Event Scheduling > Event Settings and select the schedule pages.
4. Optional: configure Social Login providers under Event Scheduling > Social Login.

== Frequently Asked Questions ==

= Does OnlineSched require a specific theme? =

No. The plugin works with any WordPress theme. It ships with its own stylesheet, Font Awesome icons, and Metropolis font so it looks consistent on a fresh install.

= My theme already loads Font Awesome. Can I stop the plugin from loading it again? =

Yes. Add this to your theme's functions.php:

    add_filter( 'onlinesched_load_fontawesome', '__return_false' );

= My theme already loads Metropolis (or I want a different font). What do I do? =

To skip the plugin's Metropolis bundle entirely, add this to your theme's functions.php:

    add_filter( 'onlinesched_load_fonts', '__return_false' );

To keep the bundle but switch to a different font, override the CSS variable instead:

    add_action( 'wp_head', function () {
        echo '<style>:root { --os-font-family: "Your Font", sans-serif; }</style>';
    } );

= Are social login credentials included? =

No. Credentials must be entered in the Social Login admin page or provided by private constants. No login providers appear on a fresh install until credentials are configured.

= How do favorites work without logging in? =

Visitors can star events without logging in. Logged-out favorites are stored only in that visitor's browser as local schedule state, are not private account data, and are not synced to the server. If Social Login is configured and the visitor logs in, OnlineSched ties synced favorites to the active OAuth session and merges the local browser favorites into that server-side favorite list. Logging out ends the synced session while the local browser favorites feature continues to work.

= Can I change the colors without editing CSS? =

Yes. Go to Event Scheduling → Event Settings → Colors in the WordPress admin. Every color the plugin uses has a picker there.

= Does OnlineSched provide calendar feed URLs? =

Yes. OnlineSched includes public read-only calendar endpoints for external calendar clients and displays.

Single event ICS:

    /wp-content/plugins/OnlineSched/ical.php?cal-id=123

Filtered schedule ICS:

    /wp-content/plugins/OnlineSched/icalby.php?room=main-stage
    /wp-content/plugins/OnlineSched/icalby.php?tag=essentials
    /wp-content/plugins/OnlineSched/icalby.php?room=main-stage,panel-room-a&tag=essentials&limit=10&textlen=300

JSON room feed:

    /wp-content/plugins/OnlineSched/json.php?room=main-stage
    /wp-content/plugins/OnlineSched/json.php?rooms=main-stage,panel-room-a
    /wp-content/plugins/OnlineSched/json.php?tag=essential
    /wp-content/plugins/OnlineSched/json.php?room=all
    /wp-content/plugins/OnlineSched/json.php?group=programming

The JSON feed is meant for signs, lobby screens, static pages, and other lightweight displays. Use room and tag slugs, not display names. A positive limit value returns up to that many upcoming events.

Omit the room parameter, or use room=all, to include every room. Sites can define named JSON groups with the onlinesched_json_room_groups option or the os_json_room_groups filter. If a requested group is not configured, OnlineSched returns an empty JSON array instead of guessing. Older programming=1 and gaming=1 display URLs are treated as deprecated group aliases; new integrations should use group=programming or group=gaming.

Calendar clients may cache feeds, so the website schedule is always the most current source for last-minute changes.

ICS feeds use UTC event timestamps, CRLF line endings, folded content lines, METHOD:PUBLISH, and text/calendar response headers for compatibility with Google Calendar/Gmail, Outlook, Microsoft 365, Apple Calendar, and Android calendar apps. Calendar metadata includes the configured calendar name and the site's WordPress timezone.

= Can I pause schedule subscriptions while preparing a new schedule year? =

Yes. Go to Event Scheduling > Event Settings > Schedule Calendar Subscriptions and clear Publish full-schedule calendar subscriptions.

Disabling schedule subscriptions empties full and filtered schedule feeds. It does not disable individual event calendar actions.

While publishing is disabled, full and filtered schedule feeds return a valid empty calendar and the full-schedule subscription buttons are hidden. Existing subscribers stay connected and receive the schedule again from the same URL after publishing is re-enabled.

Individual event calendar actions remain available because those events are already visible on the schedule page. The public schedule, individual event feeds, and JSON feed are unchanged. The setting is enabled by default on upgrade.

Calendar applications control their own refresh timing, so a paused or resumed subscription may take time to update.

= Can I override the schedule templates? =

Yes. Copy any template from wp-content/plugins/OnlineSched/templates/ into a matching path in your theme under an onlinesched/ folder. For example, to override the tab bar, create: your-theme/onlinesched/partials/schedule-tabs.php. The full list of overridable partials is in the README on GitHub.

== Acknowledgements ==

OnlineSched began as a prototype built by the original Furry Migration team, with Ringer and Mouring as key builders. It was subsequently expanded, updated, and cleaned up, and this open-source release reflects the work of everyone who contributed along the way.

== Changelog ==

= 2.2.0 =

Adds an administrator setting for pausing full and filtered schedule subscriptions with a valid empty calendar while keeping individual event calendar actions available.

= 2.1.0 =

Adds WP-CLI CSV import and exact schedule-year deletion commands, plus a deterministic PHP fixture generator for disposable schedule testing.

= 2.0.0 =

Stores event times as true Unix timestamps, renders them in the WordPress site timezone, and emits standards-compliant UTC calendar feeds.

= 1.3.1 =

Bug-fix release for the CSV importer/exporter and schedule modal.

= 1.3.0 =

Post-launch cleanup release with role repair, generic JSON feed behavior, and clean Hours block transition cleanup.

= 1.1.0 =

Post-launch cleanup release.

= 1.0.0 =

Initial open-source release preparation.
