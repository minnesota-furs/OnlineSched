// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

async function installClipboardShim(page) {
  await page.evaluate(() => {
    let clipboardText = '';
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: {
        writeText: async (text) => {
          clipboardText = String(text);
        },
        readText: async () => clipboardText,
      },
    });
  });
}

test.describe('06 — Calendar', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test.describe('Modal Calendar Links', () => {
    test.beforeEach(async ({ page }) => {
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

    test('Apple Calendar link contains event post ID', async ({ page }) => {
      const itemId = await page.locator(S.scheduleItem).first().getAttribute('id');
      const postId = itemId?.replace('onlineevt-', '');
      const href = await page.locator(S.modalIcal).getAttribute('href');
      expect(href).toContain(`cal-id=${postId}`);
    });

    test('Google Calendar link contains calendar.google.com', async ({ page }) => {
      const href = await page.locator(S.modalGoogle).getAttribute('href');
      expect(href).toContain('calendar.google.com');
    });
  });

  test.describe('Clipboard Copy', () => {
    test('clipboard copy spawns float-up effect element', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      const effect = page.locator(S.clipboardEffect);
      await expect(effect).toHaveCount(1);
    });

    test('clipboard effect element auto-removes after animation', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      // Wait for animation duration (1.5s + buffer)
      await page.waitForTimeout(2000);
      const count = await page.locator(S.clipboardEffect).count();
      expect(count).toBe(0);
    });

    test('clipboard effect contains "Copied" text', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      const effect = page.locator(S.clipboardEffect);
      await expect(effect).toContainText('Copied');
    });

    test('inline clipboard copies correct event URL to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      const firstItem = page.locator(S.scheduleItem).first();
      const itemId = await firstItem.getAttribute('id');
      const evtId = itemId?.replace('onlineevt-', '');

      await firstItem.locator(S.clipboard).click();
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('/schedule/');
      expect(clipText).toContain('#evt-' + evtId);
    });

    test('modal copy button copies page URL to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator(S.scheduleModal)).toBeVisible();

      await page.click(S.modalCopyUrl);
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('/schedule/');
      // Modal sets the hash to the event ID before showing
      expect(clipText).toContain('#evt-');
    });

    test('prefers-reduced-motion skips clipboard animation', async ({ page }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      // With reduced motion, the animation is skipped - no .clipboard-effect element created
      const count = await page.locator(S.clipboardEffect).count();
      expect(count).toBe(0);
    });

    test('prefers-reduced-motion still copies to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      await page.emulateMedia({ reducedMotion: 'reduce' });

      const firstItem = page.locator(S.scheduleItem).first();
      const itemId = await firstItem.getAttribute('id');
      const evtId = itemId?.replace('onlineevt-', '');

      await firstItem.locator(S.clipboard).click();
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('#evt-' + evtId);
    });
  });

  test.describe('Add to Calendar Section', () => {
    test('"Add to Calendar" section exists on /schedule/ page', async ({ page }) => {
      const section = page.locator(S.addToCalendarSection);
      const count = await section.count();
      expect(count).toBe(1);
    });
  });
});
