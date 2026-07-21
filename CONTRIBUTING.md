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

Use the project Docker environment for PHP, Composer, and npm work:

```bash
docker exec -it fm-php bash
```

For one-off commands:

```bash
docker exec fm-php bash -c "cd /var/www/html/wp-content/plugins/OnlineSched && php -l OnlineSched.php"
docker exec fm-php bash -c "source /usr/local/nvm/nvm.sh && cd /var/www/html/wp-content/plugins/OnlineSched && npm run build"
```

Do not run `npm install` or `composer update` on the host unless you are intentionally working
outside the Docker environment.

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

Before merge, run focused checks for the files you changed. For release-level work, run:

```bash
npm run test:seed
npm test
```

The full Playwright suite can take more than an hour on slower local machines, so use focused
smoke tests while developing and save the full suite for phase/release sign-off.

## Attribution

Original authors stay credited. Recent cleanup and refactor work should be credited to
the project contributors where appropriate.
