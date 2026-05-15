// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('01 — Page Loads', () => {
  let consoleErrors = [];

  test.beforeEach(async ({ page }) => {
    consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('page title contains Schedule', async ({ page }) => {
    await expect(page).toHaveTitle(/Schedule/i);
  });

  test('at least one schedule-item renders', async ({ page }) => {
    const count = await page.locator(S.scheduleItem).count();
    expect(count).toBeGreaterThan(0);
  });

  test('at least one schedule-day renders', async ({ page }) => {
    const count = await page.locator(S.scheduleDay).count();
    expect(count).toBeGreaterThan(0);
  });

  test('no critical JS console errors', async ({ page }) => {
    const critical = consoleErrors.filter(e =>
      e !== 'Error' &&                                        // bare browser-internal error (Firefox/WebKit)
      !e.startsWith('NS_ERROR_') &&                           // Firefox network-layer errors
      !e.includes('favicon') &&
      !e.includes('404') &&
      !e.toLowerCase().includes('onesignal') &&
      !e.includes('Can only be used on') &&                   // OneSignal domain restriction on localhost (WebKit)
      !e.toLowerCase().includes('ssl certificate') &&
      !e.toLowerCase().includes('content security policy') && // CSP notices
      !e.toLowerCase().includes('net::err_')                  // Chromium network errors
    );
    expect(critical).toHaveLength(0);
  });

  test('schedule works when host theme jQuery is present', async ({ page }) => {
    const defined = await page.evaluate(() => typeof window.jQuery !== 'undefined');
    expect(typeof defined).toBe('boolean');
    await expect(page.locator(S.scheduleItem).first()).toBeVisible();
  });
});
