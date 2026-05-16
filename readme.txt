=== OnlineSched ===
Contributors: bl, bm, al
Tags: events, schedule, calendar, convention, timetable
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
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

Use room and tag slugs, not display names. Calendar clients may cache feeds, so the website schedule is always the most current source for last-minute changes.

= Can I override the schedule templates? =

Yes. Copy any template from wp-content/plugins/OnlineSched/templates/ into a matching path in your theme under an onlinesched/ folder. For example, to override the tab bar, create: your-theme/onlinesched/partials/schedule-tabs.php. The full list of overridable partials is in the README on GitHub.

== Changelog ==

= 1.0.0 =

Initial open-source release preparation.
