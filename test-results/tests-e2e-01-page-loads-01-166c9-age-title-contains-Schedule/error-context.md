# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/01-page-loads.spec.js >> 01 — Page Loads >> page title contains Schedule
- Location: tests/e2e/01-page-loads.spec.js:19:3

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/schedule/", waiting until "load"

```

# Test source

```ts
  1  | // @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
  2  | const { test, expect } = require('@playwright/test');
  3  | const S = require('../helpers/selectors');
  4  | 
  5  | test.describe('01 — Page Loads', () => {
  6  |   let consoleErrors = [];
  7  | 
  8  |   test.beforeEach(async ({ page }) => {
  9  |     consoleErrors = [];
  10 |     page.on('console', msg => {
  11 |       if (msg.type() === 'error') consoleErrors.push(msg.text());
  12 |     });
> 13 |     await page.goto('/schedule/');
     |                ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  14 |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  15 |     await page.selectOption(S.selectDays, 'all');
  16 |     await page.waitForTimeout(300);
  17 |   });
  18 | 
  19 |   test('page title contains Schedule', async ({ page }) => {
  20 |     await expect(page).toHaveTitle(/Schedule/i);
  21 |   });
  22 | 
  23 |   test('at least one schedule-item renders', async ({ page }) => {
  24 |     const count = await page.locator(S.scheduleItem).count();
  25 |     expect(count).toBeGreaterThan(0);
  26 |   });
  27 | 
  28 |   test('at least one schedule-day renders', async ({ page }) => {
  29 |     const count = await page.locator(S.scheduleDay).count();
  30 |     expect(count).toBeGreaterThan(0);
  31 |   });
  32 | 
  33 |   test('no critical JS console errors', async ({ page }) => {
  34 |     const critical = consoleErrors.filter(e =>
  35 |       e !== 'Error' &&                                        // bare browser-internal error (Firefox/WebKit)
  36 |       !e.startsWith('NS_ERROR_') &&                           // Firefox network-layer errors
  37 |       !e.includes('favicon') &&
  38 |       !e.includes('404') &&
  39 |       !e.toLowerCase().includes('onesignal') &&
  40 |       !e.includes('Can only be used on') &&                   // OneSignal domain restriction on localhost (WebKit)
  41 |       !e.toLowerCase().includes('ssl certificate') &&
  42 |       !e.toLowerCase().includes('content security policy') && // CSP notices
  43 |       !e.toLowerCase().includes('net::err_')                  // Chromium network errors
  44 |     );
  45 |     expect(critical).toHaveLength(0);
  46 |   });
  47 | 
  48 |   test('jQuery is not undefined (pre-refactor baseline)', async ({ page }) => {
  49 |     // Phase 0-4: jQuery should exist. Expect true.
  50 |     // Phase 5+:  jQuery is removed. Change expect to: expect(defined).toBe(false);
  51 |     //            Also rename this test to "jQuery is removed (post-refactor)"
  52 |     const defined = await page.evaluate(() => typeof window.jQuery !== 'undefined');
  53 |     expect(defined).toBe(true);
  54 |   });
  55 | });
  56 | 
  57 | 
```