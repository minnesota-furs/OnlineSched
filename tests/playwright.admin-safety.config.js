const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  testMatch: '17-admin-event-safety.spec.js',
  timeout: 15_000,
  workers: 1,
  projects: [
    {
      name: 'desktop',
      use: {
        viewport: { width: 1280, height: 800 },
      },
    },
  ],
  outputDir: './test-results/admin-event-safety',
  reporter: [['list']],
});
