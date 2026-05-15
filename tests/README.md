# OnlineSched Test Suite

This directory contains the automated Playwright test suite for the OnlineSched plugin.

## Test Environments

The suite is designed to run against two different environments:

1.  **Furry Migration Docker** (Reference)
    *   **URL:** `https://furrymigration.local`
    *   **Port:** 443 (HTTPS) / 80 (HTTP)
    *   **Theme:** Furry Migration (Custom)
    *   **Status:** Development Baseline

2.  **Vanilla WordPress** (Standalone)
    *   **URL:** `http://localhost:8081`
    *   **Port:** 8081
    *   **Theme:** Twenty Twenty-Five (Default)
    *   **Status:** Verification environment for theme-independence.

## Running Tests

### 1. Furry Migration Environment

Ensure the main Docker stack is running from the project root:
```bash
docker-compose up -d
```

Run tests from the `public_html/wp-content/plugins/OnlineSched` directory:
```bash
npm test
```

### 2. Vanilla WordPress Environment

Spin up the vanilla stack:
```bash
cd tests/docker-vanilla
docker-compose up -d
./seed-vanilla.sh
```

Run tests against the vanilla project:
```bash
npx playwright test --project=vanilla-wp
```

## Adding Tests

*   New tests should be added to `tests/e2e/`.
*   Use central selectors from `tests/helpers/selectors.js`.
*   Ensure tests pass in both environments.
*   Standalone-specific assertions should be added to `tests/e2e/14-standalone.spec.js`.

## Seed Data

Test data is seeded via WP-CLI. 
*   FM environment: `npm run test:seed` (calls `tests/fixtures/seed-test-events.sh`).
*   Vanilla environment: `tests/docker-vanilla/seed-vanilla.sh`.
