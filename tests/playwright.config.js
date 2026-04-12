// @ts-check
// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  retries: 1,
  globalSetup: './global-setup.js',
  use: {
    baseURL: 'https://furrymigration.local',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1280, height: 800 } } },
    { name: 'mobile',  use: { viewport: { width: 375, height: 812 } } },
  ],
  outputDir: './test-results',
  reporter: [['html', { outputFolder: './playwright-report', open: 'never' }], ['list']],
});

