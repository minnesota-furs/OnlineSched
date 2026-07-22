# Contributing to OnlineSched

OnlineSched is a WordPress event scheduling plugin built by convention people for everyone.
Keep the code boring where it should be boring, and keep the test data a little furry.

## The Pack Welcomes You

We would genuinely love your fixes, improvements, and new features. People are awesome,
good code is awesome, and this project is better every time someone shares something back.
If you found a bug, cleaned up something rough, or built a feature you think others could
use, please open a pull request. We want to see it.

We don't care whether your contribution comes from hands or paws — as long as it's good,
you're welcome in the pack.

A little honesty so nobody's feelings get hurt: OnlineSched is run by a small crew of
volunteers, and our main focus is our own convention's site. That means we can't merge
everything, and sometimes we'll pass on a change or ask for edits — not because your work
isn't good, but because we have to keep the project maintainable for the way we use it.
Please don't take a "no thanks" or a "not right now" personally. We appreciate every
contribution, even the ones we don't end up merging, and we'll always try to explain our
reasoning. Fork it, build on it, make it yours — that's exactly the kind of thing we hoped
would happen when we shared this.

## Local Development

There are two ways to work on OnlineSched, depending on where you're running it. Pick the
one that matches your setup.

### Option A: Inside the Furry Migration stack

If you're one of us — or anyone working inside the full Furry Migration Docker
environment — do your PHP, Composer, and npm work from the `fm-php` container so you're
using the same PHP and Node versions we ship against.

Open a shell in the container:

```bash
docker exec -it fm-php bash
```

Or run one-off commands without opening a shell:

```bash
docker exec fm-php bash -c "cd /var/www/html/wp-content/plugins/OnlineSched && php -l OnlineSched.php"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm run build"
```

Don't run `npm install` or `composer update` on the host unless you are intentionally
working outside the Docker environment.

### Option B: On your own site (no Furry Migration stack)

You don't need the Furry Migration environment to work on OnlineSched. The plugin ships
with its own throwaway WordPress stack under `tests/docker-vanilla/`, so you can develop
and test against a clean WordPress install with no theme dependencies and nothing of ours
required. You will need Docker for the test site. To build assets and run the host-side
checks, you will also need PHP 8.2+, Composer, and Node 22+ with npm.

1. **Clone and build the plugin.** From the plugin directory, install dependencies and
   build the front-end assets:

   ```bash
   composer install --no-dev
   npm install
   npm run build
   ```

   (If you'd rather not install the build tools on your host, you can run these inside a
   container with PHP 8.2+, Composer, and Node 22+ — the commands are the same.)

2. **Start the disposable WordPress stack.** This is a self-contained WordPress + database
   just for testing:

   ```bash
   cd tests/docker-vanilla
   docker compose up -d
   ./seed-vanilla.sh
   ```

   When it finishes, the test site is at **http://localhost:8081**. The admin username is
   `admin`, and the generated password is written to `tests/docker-vanilla/.wp_admin_pass`.
   The seed script installs WordPress, activates OnlineSched, and loads sample events so you
   have something to look at right away.

3. **Run WP-CLI against the test site** through its bundled CLI container — this is how you
   exercise the `wp onlinesched` import/delete commands locally:

   The CSV path must exist inside the container. For example, after generating the test CSV
   described in the [test guide](tests/README.md#deterministic-fixture-tests):

   ```bash
   docker exec onlinesched-vanilla-cli wp --allow-root --path=/var/www/html onlinesched import /var/www/html/wp-content/plugins/OnlineSched/tests/fixtures/generated/furry_test_data.csv --dry-run
   ```

4. **Tear it down** whenever you want a clean slate — nothing here touches a real site:

   ```bash
   docker compose down -v
   ```

This is the same Vanilla stack our own test suite runs against, so it provides a clean
compatibility baseline without relying on Furry Migration theme behavior. See the
[OnlineSched test guide](tests/README.md) for the full walkthrough.

## Branches and Pull Requests

- Use short descriptive branch names.
- Keep one phase or feature per pull request.
- Explain manual testing in the PR.
- Call out any user-facing behavior change clearly.

## Coding Standards

- Escape output and sanitize input.
- Use WordPress nonces for writes.
- Use the Settings API for admin options when practical.
- Keep public CSS classes namespaced with `os-`.
- New JavaScript should be vanilla ES6+.
- Do not add jQuery or Bootstrap dependencies.
- Do not commit OAuth credentials, local environment secrets, or production URLs.

## Testing

**You do not need the Furry Migration stack to contribute or run the standalone checks.**
Here's what each check needs, so you can choose the right lane on your own machine:

| Check | Command | What it needs |
|---|---|---|
| Fixture generator | `npm run test:fixture` | PHP 8.2+ — no containers required |
| WP-CLI import/delete | `npm run test:cli` | Node 22+ with npm, PHP 8.2+, and the seeded Vanilla stack |
| Calendar subscription setting | `npm run test:calendar-settings` | The seeded Vanilla stack; restores the original option value when it finishes |
| Browser suite (Vanilla) | `npm test -- --project=vanilla-wp` | Node 22+ with npm, Chromium, and the seeded Vanilla stack |
| Browser suite (full) | `npm test` | All browser runtimes, Furry Migration, and the seeded Vanilla, furry-demo, and gaming-demo stacks |

`test:fixture` is the quickest importer check. For browser work, run the smallest relevant
Playwright project while developing. `test:cli` is a destructive integration harness that is
safe only because it targets the disposable Vanilla stack; run it for importer, WP-CLI,
year-deletion, and release changes rather than for every edit.

`test:calendar-settings` checks the real WordPress option, default-enabled behavior,
code-managed overrides, administrator presentation, and cache transition on the disposable
Vanilla site. It refuses any other container or site URL and restores the prior option in a
`finally` block.

If you're developing on your own site, spin up the Vanilla stack first (see
[Option B](#option-b-on-your-own-site-no-furry-migration-stack) above), then run the Vanilla
Playwright project:

```bash
npm test -- --project=vanilla-wp
```

The browser seed script defaults to `fm-php`, but it can be pointed at the Vanilla stack with
an environment variable. For example, to seed events without the Furry Migration container:

```bash
OS_TEST_CONTAINER=onlinesched-vanilla-cli npm run test:seed
```

Inside the Furry Migration stack, seed the local schedule and run a focused browser project
with no overrides:

```bash
npm run test:seed
npm test -- --project=desktop
```

The full Playwright suite also expects the three disposable demo environments on ports 8081,
8082, and 8083. It can take more than an hour on slower local machines, so use focused smoke
tests while developing and save the full suite for phase/release sign-off. Full step-by-step
instructions live in the [OnlineSched test guide](tests/README.md).

## Attribution

Original authors stay credited. Recent cleanup and refactor work should be credited to
the project contributors where appropriate.
