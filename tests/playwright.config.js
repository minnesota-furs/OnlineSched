// @ts-check
// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { defineConfig, devices } = require('@playwright/test');

// Kiosk targets Edge at 1920×1080 (convention floor display).
// If Edge is not installed on this machine, set PLAYWRIGHT_NO_EDGE=1 to fall back to Chromium.
//   npm run test:kiosk              → uses Edge
//   npm run test:kiosk:chromium     → uses Chromium (fallback)
const kioskUse = { viewport: { width: 1920, height: 1080 } };
if (!process.env.PLAYWRIGHT_NO_EDGE) {
  kioskUse.channel = 'msedge';
}

// All non-kiosk spec files (glob pattern excludes 08-kiosk.spec.js)
const KIOSK_SPEC = '08-kiosk.spec.js';

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 45_000,  // 45s — gives Docker room under 8-project parallel load; was 30s
  retries: 2,       // retry up to 2× to handle transient Docker page-load timeouts under parallel load
  workers: 4,       // cap at 4 simultaneous browser workers to reduce Docker memory pressure
  globalSetup: './global-setup.js',
  use: {
    baseURL: 'https://furrymigration.local',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    // ── Core viewports (Chromium) ──
    { name: 'desktop',          use: { viewport: { width: 1280, height: 800 } },  testIgnore: KIOSK_SPEC },
    { name: 'mobile-iphone',    use: { viewport: { width: 375, height: 812 } },   testIgnore: KIOSK_SPEC },
    { name: 'mobile-android',   use: { viewport: { width: 412, height: 915 } },   testIgnore: KIOSK_SPEC },
    { name: 'tablet',           use: { viewport: { width: 768, height: 1024 } },  testIgnore: KIOSK_SPEC },
    { name: 'tablet-landscape', use: { viewport: { width: 1366, height: 1024 } }, testIgnore: KIOSK_SPEC },

    // ── Kiosk mode (Edge at 1080p, falls back to Chromium if PLAYWRIGHT_NO_EDGE=1) ──
    {
      name: 'kiosk',
      use: kioskUse,
      testMatch: KIOSK_SPEC,
    },

    // ── Alternative browser engines ──
    { name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 800 } }, testIgnore: KIOSK_SPEC },
    { name: 'webkit',  use: { ...devices['Desktop Safari'],  viewport: { width: 1280, height: 800 } }, testIgnore: KIOSK_SPEC },

    // ── Standalone vanilla WP ──
    {
      name: 'vanilla-wp',
      use: {
        baseURL: 'http://localhost:8081',
        viewport: { width: 1280, height: 800 },
      },
      testIgnore: KIOSK_SPEC,
    },

    // ── Furry demo build ──
    {
      name: 'furry-wp',
      use: {
        baseURL: 'http://localhost:8082',
        viewport: { width: 1280, height: 800 },
      },
      testMatch: /15-solo-event-block\.spec\.js/,
    },

    // -- High-load gaming demo build --
    {
      name: 'gaming-wp',
      use: {
        baseURL: 'http://localhost:8083',
        viewport: { width: 1280, height: 800 },
      },
      testMatch: /16-gaming-demo\.spec\.js/,
    },
  ],
  outputDir: './test-results',
  reporter: [['html', { outputFolder: './playwright-report', open: 'never' }], ['list']],
});
