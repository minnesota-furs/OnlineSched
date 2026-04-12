// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// SKIP until Phase 6 is complete.
// Validates zero Bootstrap/jQuery presence on the page post-refactor.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('09 — No jQuery / Bootstrap (Phase 6+)', () => {
  test.skip(true, 'Enable after Phase 6: Final Cleanup & Removal is complete.');

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  });

  test('window.jQuery is undefined', async ({ page }) => {
    const defined = await page.evaluate(() => typeof window.jQuery);
    expect(defined).toBe('undefined');
  });

  test('window.$ is undefined', async ({ page }) => {
    const defined = await page.evaluate(() => typeof window.$);
    expect(defined).toBe('undefined');
  });

  test('no Bootstrap CSS link tag', async ({ page }) => {
    const el = await page.evaluate(() =>
      document.querySelector('link[href*="bootstrap"]')
    );
    expect(el).toBeNull();
  });

  test('no jQuery script tag', async ({ page }) => {
    const el = await page.evaluate(() =>
      document.querySelector('script[src*="jquery"]')
    );
    expect(el).toBeNull();
  });

  test('no Bootstrap col-xs- classes remain in DOM', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[class*="col-xs-"]').length
    );
    expect(count).toBe(0);
  });

  test('no Bootstrap col-sm- classes remain in DOM', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[class*="col-sm-"]').length
    );
    expect(count).toBe(0);
  });

  test('no Bootstrap col-md- classes remain in DOM', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[class*="col-md-"]').length
    );
    expect(count).toBe(0);
  });

  test('no Bootstrap col-lg- classes remain in DOM', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[class*="col-lg-"]').length
    );
    expect(count).toBe(0);
  });

  test('no data-toggle="tab" attributes remain', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[data-toggle="tab"]').length
    );
    expect(count).toBe(0);
  });

  test('no data-dismiss="modal" attributes remain', async ({ page }) => {
    const count = await page.evaluate(() =>
      document.querySelectorAll('[data-dismiss="modal"]').length
    );
    expect(count).toBe(0);
  });

  test('os- namespaced classes present on key elements', async ({ page }) => {
    await expect(page.locator('.os-btn').first()).toBeVisible();
    await expect(page.locator('.os-tabs')).toBeVisible();
    await expect(page.locator('.os-form-control').first()).toBeVisible();
    await expect(page.locator('dialog.os-modal').first()).toHaveCount(1);
  });
});

