// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('06 — Calendar', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
    // Open first event modal
    await page.locator(S.scheduleTitle).first().click();
    await page.waitForTimeout(400);
    await expect(page.locator(S.scheduleModal)).toBeVisible();
  });

  test('Apple Calendar link contains webcal:// and ical.php', async ({ page }) => {
    const href = await page.locator(S.modalIcal).getAttribute('href');
    expect(href).toContain('webcal://');
    expect(href).toContain('ical.php');
  });

  test('Google Calendar link contains calendar.google.com', async ({ page }) => {
    const href = await page.locator(S.modalGoogle).getAttribute('href');
    expect(href).toContain('calendar.google.com');
  });

  test('clipboard copy spawns float-up effect element', async ({ page }) => {
    // Close modal first, then test clipboard on the schedule item directly
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    await page.locator(S.clipboard).first().click();
    await page.waitForTimeout(300);

    const effect = page.locator(S.clipboardEffect);
    await expect(effect).toHaveCount(1);
  });

  test('clipboard effect element auto-removes after animation', async ({ page }) => {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    await page.locator(S.clipboard).first().click();
    // Wait for animation duration (1.5s + buffer)
    await page.waitForTimeout(2000);

    const count = await page.locator(S.clipboardEffect).count();
    expect(count).toBe(0);
  });
});

