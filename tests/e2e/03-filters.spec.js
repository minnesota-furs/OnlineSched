// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('03 — Filters', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('search filters items', async ({ page }) => {
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.fill(S.searchInput, 'Coyote');
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThan(totalBefore);
  });

  test('clearing search restores all items', async ({ page }) => {
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.fill(S.searchInput, 'Coyote');
    await page.waitForTimeout(300);
    await page.fill(S.searchInput, '');
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBe(totalBefore);
  });

  test('day dropdown filters to one day section', async ({ page }) => {
    // Get the first non-default option value
    const firstDay = await page.locator(`${S.selectDays} option:not([value="all"]):not([value="Current"])`).first().getAttribute('value');
    await page.selectOption(S.selectDays, firstDay);
    await page.waitForTimeout(400);
    const visibleDays = await page.locator(`${S.scheduleDay}:visible`).count();
    expect(visibleDays).toBe(1);
  });

  test('selecting All Days shows all day sections', async ({ page }) => {
    const totalDays = await page.locator(`${S.scheduleDay}:visible`).count();
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(400);
    const visibleDays = await page.locator(`${S.scheduleDay}:visible`).count();
    expect(visibleDays).toBe(totalDays);
  });

  test('tag dropdown filters items', async ({ page }) => {
    const tagOption = await page.locator(`${S.selectTags} option:not([value="all"])`).first().getAttribute('value');
    if (!tagOption) return test.skip();
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeLessThanOrEqual(totalBefore);
  });

  test('room dropdown filters items', async ({ page }) => {
    const roomOption = await page.locator(`${S.selectRooms} option:not([value="all"])`).first().getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(400);
    const count = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(count).toBeGreaterThan(0);
  });

  test('Reset button is disabled when no filters active', async ({ page }) => {
    // beforeEach selects 'all' which enables reset; re-navigate to get true default state
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(300);
    await expect(page.locator(S.resetButton)).toBeDisabled();
  });

  test('Reset button enables after search and resets on click', async ({ page }) => {
    await page.fill(S.searchInput, 'Raccoon');
    await page.waitForTimeout(300);
    await expect(page.locator(S.resetButton)).toBeEnabled();
    await page.click(S.resetButton);
    await page.waitForTimeout(400);
    await expect(page.locator(S.resetButton)).toBeDisabled();
    // Verify filters returned to default state
    await expect(page.locator(S.searchInput)).toHaveValue('');
    await expect(page.locator(S.selectDays)).toHaveValue('Current');
    await expect(page.locator(S.selectTags)).toHaveValue('all');
    await expect(page.locator(S.selectRooms)).toHaveValue('all');
    // Items should still be visible (seed data is in the future)
    const visible = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(visible).toBeGreaterThan(0);
  });

  test('Current filter shows only future events from seed data', async ({ page }) => {
    await page.selectOption(S.selectDays, 'Current');
    await page.waitForTimeout(400);
    // All seed events are in the future, so at least some should be visible
    const count = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(count).toBeGreaterThan(0);
  });
});

