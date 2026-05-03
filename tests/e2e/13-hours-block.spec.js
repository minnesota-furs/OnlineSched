// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

async function openHoursTab(page) {
  await page.goto('/schedule/#hours');
  await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  await page.locator(`${S.tabList} a[href="#hours"]`).click();
  await expect(page.locator(S.tabHours)).toBeVisible();
}

async function skipIfHoursNotMigrated(page) {
  const hoursCount = await page.locator(S.hoursBlock).count();
  test.skip(hoursCount === 0, 'Configured Hours page has not been migrated to OnlineSched native blocks yet.');
}

test.describe('13 — Hours block', () => {
  test('renders native Hours markup without Bootstrap grid', async ({ page }) => {
    await openHoursTab(page);
    await skipIfHoursNotMigrated(page);

    await expect(page.locator(S.hoursBlock)).toBeVisible();
    await expect(page.locator(S.hoursDepartment).first()).toBeVisible();
    await expect(page.locator(S.hoursDay).first()).toBeVisible();
    await expect(page.locator(S.hoursTimes).first()).toBeVisible();

    const bootstrapGridCount = await page.locator(`${S.hoursBlock} [class*="col-"], ${S.hoursBlock} .row`).count();
    expect(bootstrapGridCount).toBe(0);
  });

  test('uses one column on narrow screens', async ({ page }, testInfo) => {
    test.skip(testInfo.project.use.viewport.width > 767, 'Mobile-only Hours layout assertion.');

    await openHoursTab(page);
    await skipIfHoursNotMigrated(page);

    const columnCount = await page.locator(S.hoursRow).evaluate((row) => {
      return getComputedStyle(row).gridTemplateColumns.split(' ').filter(Boolean).length;
    });

    expect(columnCount).toBe(1);
  });
});
