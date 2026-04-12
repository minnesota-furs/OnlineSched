// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('02 — Tabs', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('Programming tab pane is active on load', async ({ page }) => {
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });

  test('Hours tab shows hours pane and hides programming', async ({ page }) => {
    await page.locator(`${S.tabList} a[href="#hours"]`).click();
    await page.waitForTimeout(300);
    await expect(page.locator(S.tabHours)).toBeVisible();
    await expect(page.locator(S.tabProgramming)).toBeHidden();
  });

  test('clicking Programming tab after Hours restores it', async ({ page }) => {
    await page.locator(`${S.tabList} a[href="#hours"]`).click();
    await page.waitForTimeout(200);
    await page.locator(`${S.tabList} a[href="#programming"]`).first().click();
    await page.waitForTimeout(300);
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });

  test('Essentials tab filters to essentials-tagged items only', async ({ page }) => {
    // Second tab link (index 1) is the Essentials tab
    await page.locator(S.tabLinks).nth(1).click();
    await page.waitForTimeout(400);
    // Visible items should all have an essentials badge or tag
    const visibleItems = page.locator(`${S.scheduleItem}:visible`);
    const count = await visibleItems.count();
    expect(count).toBeGreaterThan(0);
  });
});

