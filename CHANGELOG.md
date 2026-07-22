# Changelog

## 2.2.0

- Added **Publish full-schedule calendar subscriptions** under **Event Scheduling > Event Settings > Schedule Calendar Subscriptions**.
- When publishing is disabled, full and filtered schedule feeds return a valid empty calendar while existing subscribers remain connected to the same URL.
- Kept individual event calendar actions available for events already visible on the schedule page; the public schedule, individual event feeds, and JSON feed are unchanged.
- Kept schedule subscription publishing enabled by default for existing installations. Calendar applications may take time to reflect a pause or resume because they control their own refresh timing.

## 2.1.0

- Added `wp onlinesched import` for importing the existing OnlineSched CSV format from WP-CLI, including read-only dry runs and year-scoped update-in-place behavior.
- Added `wp onlinesched delete-year` for confirmed, permanent deletion of events from one exact schedule year without deleting taxonomy terms or changing the active year option.
- Added a deterministic PHP fixture generator for disposable 150-event import and re-import testing.

## 1.3.1

- Wired up the CSV Export button, which was previously defined but never hooked to `admin_init`.
- CSV importer now recovers from `term_exists` errors on rooms, days, panelists, and tags by reusing the existing term instead of aborting the run.
- Fixed a fatal risk in day-term migration when parsing a malformed date.
- Fixed day-term cache key mismatch that stored the display name instead of the slug.
- Added a missing `is_wp_error()` guard before iterating deleted taxonomy terms.
- Fixed a bug where a bad-header CSV upload left the DB transaction open.
- Corrected the "Panalists" typo to "Panelists" in the unused-terms cleanup message.
- Import/delete admin notices now report counts and render above the form instead of below it.
- Schedule modal description now strips stray `&nbsp;` entities and hides the description block and divider when the result is empty.

## 1.3.0

- Repaired OnlineSched custom role creation so capabilities are stored as named grants.
- Added an admin-role repair path for existing installs with malformed capability entries.
- Made the public JSON feed generic by default and moved site-specific groups to configuration/hooks.
- Cleaned the final Hours ACF transition path after block output validation.
- Refreshed release metadata for the v1.3.0 distribution.

## 1.1.0

- Removed post-launch legacy migration paths after production data was converted.
- Kept compatibility endpoints that still have callers while tightening release packaging.
- Cleaned native Hours block handling after the ACF migration bridge was retired.
- Refreshed distribution packaging checks for private plans, tests, maps, and local artifacts.

## 1.0.0

- Prepared OnlineSched for open-source release.
- Added Advanced Header Flare settings with custom Image and SVG support.
- Polished Modal UI typography and spacing to match reference designs.
- Added configurable schedule pages, colors, sticky offsets, calendar names, and room sort order.
- Added template override support and schedule template partials.
- Renamed event CPT/taxonomies to `os_event`, `os_room`, `os_tag`, `os_day`, and `os_panelist`.
- Removed default OAuth credentials from provider configuration.
- Hardened favorites saving/loading with nonce checks and session-derived identity.
- Added privacy-policy, exporter, and eraser hooks for synced favorites data.
- Updated package identity, plugin headers, license, and contributor documentation.
