# App Feed Contract Fixtures (plugin-local copy)

These files are a plugin-repo-local copy of the canonical app-feed contract
fixtures maintained in the `furry-migration-app` repository at
`contract/*.json`. That repository is the source of truth for the shape of
`json.php`'s sectioned responses; this copy exists so `tests/cli/test-app-feed.php`
can assert against them without a cross-repo dependency.

If the contract changes, update the canonical files in `furry-migration-app`
first, then copy the change here in the same commit that changes
`lib/app-feed.php`.

Files (schema_version 1):

- `meta.json` — handshake: revisions, change stamp, con window, publication
- `schedule.json` — full active-year schedule (events keyed by `event_uid`)
- `hours.json` — lossless free-form hours export
- `info-list.json` — info section index
- `info-page.json` — one info page with content

`tests/cli/test-app-feed.php` compares the *shape* of the builder output
(`onlinesched_app_feed_meta()`, `..._schedule()`, `..._hours()`, `..._info()`)
against these fixtures: same keys, same value types. It does not assert the
example values themselves, since real site data will differ.
