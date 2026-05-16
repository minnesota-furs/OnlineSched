# Contributing to OnlineSched

OnlineSched is a WordPress event scheduling plugin built by convention people for everyone.
Keep the code boring where it should be boring, and keep the test data a little furry.

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
