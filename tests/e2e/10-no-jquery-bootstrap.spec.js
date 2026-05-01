// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Validates OnlineSched no longer emits Bootstrap/jQuery markup or assets.
// The host theme may still expose jQuery for its own Bootstrap menu.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('10 — No jQuery / Bootstrap (Phase 6+)', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  });

  test('no Bootstrap requires jQuery console error', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });

    expect(errors.filter(error => error.includes("Bootstrap's JavaScript requires jQuery"))).toHaveLength(0);
  });

  test('no OnlineSched-owned jQuery script tag', async ({ page }) => {
    const count = await page.evaluate(() =>
      Array.from(document.scripts).filter(script => {
        const src = script.getAttribute('src') || '';
        return src.includes('OnlineSched') && src.toLowerCase().includes('jquery');
      }).length
    );
    expect(count).toBe(0);
  });

  test('no OnlineSched-owned Bootstrap asset tag', async ({ page }) => {
    const count = await page.evaluate(() =>
      Array.from(document.querySelectorAll('script[src], link[href]')).filter(el => {
        const url = el.getAttribute('src') || el.getAttribute('href') || '';
        return url.includes('OnlineSched') && url.toLowerCase().includes('bootstrap');
      }).length
    );
    expect(count).toBe(0);
  });

  // Col-* checks are scoped to OnlineSched-owned markup (#schedule + dialogs).
  // Use regex /^col-XX-/ to match Bootstrap classes exactly — avoids false-positive
  // hits on os-col-xs-* etc. (class* substring match would catch those too).
  // Excluded from scope (not plugin-owned):
  //   .schedule-description / #modal-schedule-description — user-generated WP post content
  //   .hours-of-operations — theme-injected Hours tab content (theme uses Bootstrap grid)
  //   #footer — theme footer rendered inside #schedule by get_footer()
  test('no Bootstrap col-xs- classes remain in OnlineSched DOM', async ({ page }) => {
    const count = await page.evaluate(() => {
      const re = /^col-xs-/;
      return [
        ...document.querySelectorAll('#schedule *'),
        ...document.querySelectorAll('dialog.os-modal *'),
      ].filter(el =>
        !el.closest('.schedule-description, #modal-schedule-description, .hours-of-operations, #footer') &&
        Array.from(el.classList).some(c => re.test(c))
      ).length;
    });
    expect(count).toBe(0);
  });

  test('no Bootstrap col-sm- classes remain in OnlineSched DOM', async ({ page }) => {
    const count = await page.evaluate(() => {
      const re = /^col-sm-/;
      return [
        ...document.querySelectorAll('#schedule *'),
        ...document.querySelectorAll('dialog.os-modal *'),
      ].filter(el =>
        !el.closest('.schedule-description, #modal-schedule-description, .hours-of-operations, #footer') &&
        Array.from(el.classList).some(c => re.test(c))
      ).length;
    });
    expect(count).toBe(0);
  });

  test('no Bootstrap col-md- classes remain in OnlineSched DOM', async ({ page }) => {
    const count = await page.evaluate(() => {
      const re = /^col-md-/;
      return [
        ...document.querySelectorAll('#schedule *'),
        ...document.querySelectorAll('dialog.os-modal *'),
      ].filter(el =>
        !el.closest('.schedule-description, #modal-schedule-description, .hours-of-operations, #footer') &&
        Array.from(el.classList).some(c => re.test(c))
      ).length;
    });
    expect(count).toBe(0);
  });

  test('no Bootstrap col-lg- classes remain in OnlineSched DOM', async ({ page }) => {
    const count = await page.evaluate(() => {
      const re = /^col-lg-/;
      return [
        ...document.querySelectorAll('#schedule *'),
        ...document.querySelectorAll('dialog.os-modal *'),
      ].filter(el =>
        !el.closest('.schedule-description, #modal-schedule-description, .hours-of-operations, #footer') &&
        Array.from(el.classList).some(c => re.test(c))
      ).length;
    });
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
    // Scope .os-btn to #schedule to avoid matching buttons inside closed <dialog> elements
    await expect(page.locator('#schedule .os-btn').first()).toBeVisible();
    await expect(page.locator('.os-tabs')).toBeVisible();
    await expect(page.locator('.os-form-control').first()).toBeVisible();
    await expect(page.locator('dialog.os-modal').first()).toBeAttached();
  });
});
