const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('Hash Visibility Bugfix', () => {
  test('#programming hash shows schedule and activates tab', async ({ page }) => {
    await page.goto('/schedule/#programming');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await expect(page.locator(S.schedule)).toBeVisible();
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });

  test('#essentials hash shows schedule and activates tab', async ({ page }) => {
    await page.goto('/schedule/#essentials');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await expect(page.locator(S.schedule)).toBeVisible();
    // Essentials tab should be active
    const essentialsTabParent = page.locator('[data-os-tab="essentials"] >> xpath=..');
    await expect(essentialsTabParent).toHaveClass(/os-tabs__item--active/);
    // Should be filtered (Programming pane visible, but items filtered)
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });

  test('#hours hash shows schedule and activates tab', async ({ page }) => {
    await page.goto('/schedule/#hours');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await expect(page.locator(S.schedule)).toBeVisible();
    await expect(page.locator(S.tabHours)).toBeVisible();
  });

  test('#programming hash on kiosk shows schedule', async ({ page }) => {
    await page.goto('/kiosk-schedule/#programming');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await expect(page.locator(S.schedule)).toBeVisible();
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });
});
