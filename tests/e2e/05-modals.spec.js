// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('05 — Modals', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test.describe('Login Modal', () => {
    test('opens on login button click', async ({ page }) => {
      await page.click(S.loginModalBtn);
      await page.waitForTimeout(300);
      await expect(page.locator(S.loginModal)).toBeVisible();
      await expect(page.locator(S.loginModal)).toContainText('Login');
    });

    test('closes on close button click', async ({ page }) => {
      await page.click(S.loginModalBtn);
      await page.waitForTimeout(200);
      await page.click(S.loginModalClose);
      await page.waitForTimeout(300);
      await expect(page.locator(S.loginModal)).toBeHidden();
    });

    test('closes on Escape key', async ({ page }) => {
      await page.click(S.loginModalBtn);
      await page.waitForTimeout(200);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
      await expect(page.locator(S.loginModal)).toBeHidden();
    });
  });

  test.describe('Info Modal', () => {
    test('opens on info button click', async ({ page }) => {
      await page.click(S.infoModalBtn);
      await page.waitForTimeout(300);
      await expect(page.locator(S.infoModal)).toBeVisible();
      await expect(page.locator(S.infoModal)).toContainText('Favorites');
    });

    test('closes on close button click', async ({ page }) => {
      await page.click(S.infoModalBtn);
      await page.waitForTimeout(200);
      await page.click(S.infoModalClose);
      await page.waitForTimeout(300);
      await expect(page.locator(S.infoModal)).toBeHidden();
    });

    test('closes on Escape key', async ({ page }) => {
      await page.click(S.infoModalBtn);
      await page.waitForTimeout(200);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
      await expect(page.locator(S.infoModal)).toBeHidden();
    });
  });

  test.describe('Schedule Event Modal', () => {
    test('opens when clicking an event title', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator(S.scheduleModal)).toBeVisible();
    });

    test('modal title is populated', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      const title = await page.locator(S.scheduleModalTitle).textContent();
      expect(title?.trim().length).toBeGreaterThan(0);
    });

    test('modal contains date, time, and room fields', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator('#modal-schedule-date')).toBeVisible();
      await expect(page.locator('#modal-schedule-time')).toBeVisible();
      await expect(page.locator('#modal-schedule-room')).toBeVisible();
    });

    test('calendar buttons present in modal footer', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator(S.modalIcal)).toBeVisible();
      await expect(page.locator(S.modalGoogle)).toBeVisible();
      await expect(page.locator(S.modalCopyUrl)).toBeVisible();
    });

    test('closes on close button click', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await page.click(S.scheduleModalClose);
      await page.waitForTimeout(300);
      await expect(page.locator(S.scheduleModal)).toBeHidden();
    });

    test('closes on Escape key', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
      await expect(page.locator(S.scheduleModal)).toBeHidden();
    });
  });

  test.describe('Android Google Calendar Modal', () => {
    test('element exists in DOM', async ({ page }) => {
      await expect(page.locator(S.androidModal)).toHaveCount(1);
    });
    // Full open/close test requires Android UA override — covered by manual QA.
  });
});

