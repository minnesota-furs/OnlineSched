// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Validates the high-load gaming demo Docker target.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('16 - Gaming Demo', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(test.info().project.name !== 'gaming-wp', 'Only runs on gaming-wp project');
    await page.goto('/schedule/', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  });

  test('high-load schedule renders with expected filters', async ({ page }) => {
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(500);

    await expect(page.locator(S.scheduleItem).first()).toBeVisible();
    await expect(page.locator(`${S.selectRooms} option:not([value="all"])`)).toHaveCount(20);

    const tagCount = await page.locator(`${S.selectTags} option:not([value="all"])`).count();
    expect(tagCount).toBeGreaterThanOrEqual(10);

    const eventCount = await page.locator(S.scheduleItem).count();
    expect(eventCount).toBeGreaterThanOrEqual(400);
  });

  test('high-load search and modal paths stay responsive', async ({ page }) => {
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(500);

    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalBefore).toBeGreaterThan(100);

    await page.fill(S.searchInput, 'Dragon');
    await page.waitForTimeout(400);

    const filtered = page.locator(`${S.scheduleItem}:visible`);
    const totalAfter = await filtered.count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThan(totalBefore);

    await filtered.locator(S.scheduleTitle).first().click();
    await expect(page.locator(S.scheduleModal)).toBeVisible();
  });
});
