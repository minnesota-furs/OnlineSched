// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('04 — Favorites', () => {
  test.beforeEach(async ({ page }) => {
    // Clear favorites cookie before each test
    await page.goto('/schedule/');
    await page.evaluate(() => {
      document.cookie = 'schedule_favorites=;path=/;max-age=0';
    });
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('starring an event fills the icon and sets data-favorite', async ({ page }) => {
    const btn = page.locator(S.favoriteBtn).first();
    const icon = btn.locator('i');
    await expect(icon).toHaveClass(/far/);

    await btn.click();
    await page.waitForTimeout(200);

    await expect(icon).toHaveClass(/fas/);
    const item = btn.locator('xpath=ancestor::*[contains(@class,"schedule-item")]');
    await expect(item).toHaveAttribute('data-favorite', 'true');
  });

  test('un-starring reverts icon to hollow', async ({ page }) => {
    const btn = page.locator(S.favoriteBtn).first();
    await btn.click();
    await page.waitForTimeout(200);
    await btn.click();
    await page.waitForTimeout(200);
    await expect(btn.locator('i')).toHaveClass(/far/);
  });

  test('favorites filter toggle shows only starred items', async ({ page }) => {
    await page.locator(S.favoriteBtn).first().click();
    await page.waitForTimeout(200);

    await page.click(S.favoritesToggle);
    await page.waitForTimeout(400);

    const visibleItems = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(visibleItems).toBe(1);
  });

  test('toggling favorites filter off restores all items', async ({ page }) => {
    const total = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.locator(S.favoriteBtn).first().click();
    await page.waitForTimeout(200);
    await page.click(S.favoritesToggle);
    await page.waitForTimeout(300);
    await page.click(S.favoritesToggle);
    await page.waitForTimeout(400);

    const visibleAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(visibleAfter).toBe(total);
  });

  test('favorites persist across page reload via cookie', async ({ page }) => {
    await page.locator(S.favoriteBtn).first().click();
    await page.waitForTimeout(300);

    const cookie = await page.evaluate(() => document.cookie);
    expect(cookie).toContain('schedule_favorites');

    await page.reload({ waitUntil: 'domcontentloaded' }); // avoid waiting for all 3rd-party scripts under load
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(400);

    const filledStars = await page.locator(`${S.favoriteBtn} i.fas`).count();
    expect(filledStars).toBeGreaterThan(0);
  });
});

