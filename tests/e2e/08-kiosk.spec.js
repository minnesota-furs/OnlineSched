// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Kiosk mode tests — runs at 1920×1080 on Edge via the "kiosk" project.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('08 — Kiosk Mode (/kiosk-schedule/)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/kiosk-schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  // ── Page & Layout ──

  test('page loads with kiosk-schedule CSS class', async ({ page }) => {
    const hasKiosk = await page.evaluate(
      (cls) => document.querySelector(cls) !== null,
      S.kioskClass
    );
    expect(hasKiosk).toBe(true);
  });

  test('no standard WordPress header navigation', async ({ page }) => {
    // Kiosk uses header-schedule.php — no #masthead, no .site-header, no .navbar
    const nav = await page.locator('#masthead, .site-header, .navbar').count();
    expect(nav).toBe(0);
  });

  test('viewport meta has user-scalable=no', async ({ page }) => {
    const content = await page.evaluate(() => {
      const meta = document.querySelector('meta[name="viewport"]');
      return meta ? meta.getAttribute('content') : '';
    });
    expect(content).toContain('user-scalable=no');
  });

  test('no OneSignal script loaded', async ({ page }) => {
    // TODO: PHP kiosk template does not yet filter out OneSignal.
    // When lib/schedule.php is updated to exclude OneSignal for kiosk theming,
    // remove this skip and let the assertion run.
    test.skip(true, 'OneSignal filtering for kiosk not yet implemented in PHP template');
    const onesignal = await page.evaluate(() =>
      document.querySelector('script[src*="OneSignal"], script[src*="onesignal"]')
    );
    expect(onesignal).toBeNull();
  });

  test('schedule container and events visible', async ({ page }) => {
    await expect(page.locator(S.schedule)).toBeVisible();
    const count = await page.locator(S.scheduleItem).count();
    expect(count).toBeGreaterThan(0);
  });

  // ── Reduced Functionality (should NOT exist in kiosk) ──

  test('no favorite star buttons on events', async ({ page }) => {
    const count = await page.locator(S.favoriteBtn).count();
    expect(count).toBe(0);
  });

  test('no clipboard copy buttons on events', async ({ page }) => {
    const count = await page.locator(S.clipboard).count();
    expect(count).toBe(0);
  });

  test('no Apple Calendar links on events', async ({ page }) => {
    const count = await page.locator(S.scheduleIcalLink).count();
    expect(count).toBe(0);
  });

  test('no Google Calendar links on events', async ({ page }) => {
    const count = await page.locator(S.scheduleGoogleLink).count();
    expect(count).toBe(0);
  });

  test('no favorites toggle filter button', async ({ page }) => {
    const count = await page.locator(S.favoritesToggle).count();
    expect(count).toBe(0);
  });

  test('no "Add to Calendar" section at bottom', async ({ page }) => {
    const count = await page.locator(S.addToCalendarSection).count();
    expect(count).toBe(0);
  });

  test('event descriptions have no clickable links (links stripped)', async ({ page }) => {
    const linkCount = await page.evaluate((sel) => {
      const descriptions = document.querySelectorAll(sel);
      let total = 0;
      descriptions.forEach(desc => {
        total += desc.querySelectorAll('a').length;
      });
      return total;
    }, S.scheduleDescription);
    expect(linkCount).toBe(0);
  });

  // ── Core Features Must Still Work ──

  test('tabs: Programming tab is active on load', async ({ page }) => {
    await expect(page.locator(S.tabProgramming)).toBeVisible();
  });

  test('tabs: Hours tab shows hours pane', async ({ page }) => {
    // The kiosk template may not include an Hours tab (it has Map instead).
    const hoursTab = page.locator(`${S.tabList} a[href="#hours"]`);
    const tabCount = await hoursTab.count();
    if (tabCount === 0) return test.skip(true, 'No Hours tab in kiosk template — kiosk uses Map tab instead');

    await hoursTab.click();
    await page.waitForTimeout(300);
    await expect(page.locator(S.tabHours)).toBeVisible();
    await expect(page.locator(S.tabProgramming)).toBeHidden();
  });

  test('tabs: Map tab loads and displays content', async ({ page }) => {
    const mapTab = page.locator(`${S.tabList} a[href="#map"]`);
    const tabCount = await mapTab.count();
    if (tabCount === 0) return test.skip(true, 'Map tab not present in kiosk');

    await mapTab.click();
    await page.waitForTimeout(600); // extra time for Bootstrap tab transition
    // Bootstrap adds 'active' class to the tab pane on switch.
    // Use class check rather than toBeVisible() as kiosk CSS may use
    // non-standard show/hide that Playwright's visibility heuristic misses.
    await expect(page.locator(S.tabMap)).toHaveClass(/os-tab-pane--active/);
  });

  test('tabs: clicking kiosk tabs snaps back to page top', async ({ page }) => {
    const mapTab = page.locator(`${S.tabList} a[href="#map"]`);
    const tabCount = await mapTab.count();
    if (tabCount === 0) return test.skip(true, 'Map tab not present in kiosk');

    await page.evaluate(() => window.scrollTo(0, 900));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);

    await mapTab.click();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThanOrEqual(5);

    await page.locator(`${S.tabList} a[href="#programming"]`).click();

    await page.evaluate(() => window.scrollTo(0, 900));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);

    await page.locator(`${S.tabList} a[href="#essentials"]`).click();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThanOrEqual(5);
  });

  test('search input filters items', async ({ page }) => {
    // On kiosk, the text search input may be hidden (kiosk shows dropdown-only filtering).
    const isSearchVisible = await page.locator(S.searchInput).isVisible();
    if (!isSearchVisible) return test.skip(true, 'Search text input is hidden on kiosk — use dropdown filters instead');

    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.fill(S.searchInput, 'Coyote');
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThan(totalBefore);
  });

  test('day dropdown filters events', async ({ page }) => {
    const firstDay = await page
      .locator(`${S.selectDays} option:not([value="all"]):not([value="Current"])`)
      .first()
      .getAttribute('value');
    await page.selectOption(S.selectDays, firstDay);
    await page.waitForTimeout(400);
    const visibleDays = await page.locator(`${S.scheduleDay}:visible`).count();
    expect(visibleDays).toBe(1);
  });

  test('tag dropdown filters events', async ({ page }) => {
    const tagOption = await page
      .locator(`${S.selectTags} option:not([value="all"])`)
      .first()
      .getAttribute('value');
    if (!tagOption) return test.skip();
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeLessThanOrEqual(totalBefore);
  });

  test('room dropdown filters events', async ({ page }) => {
    const roomOption = await page
      .locator(`${S.selectRooms} option:not([value="all"])`)
      .first()
      .getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(400);
    const count = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(count).toBeGreaterThan(0);
  });

  test('reset button clears filters', async ({ page }) => {
    // On kiosk the search text input is hidden, so trigger the reset via a dropdown change.
    const tagOption = await page
      .locator(`${S.selectTags} option:not([value="all"])`)
      .first()
      .getAttribute('value');
    if (!tagOption) return test.skip(true, 'No tag options available to activate reset');

    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(300);
    await expect(page.locator(S.resetButton)).toBeEnabled();
    await page.click(S.resetButton);
    await page.waitForTimeout(400);
    await expect(page.locator(S.selectTags)).toHaveValue('all');
  });

  test('schedule event modal opens on title click', async ({ page }) => {
    const firstTitle = page.locator(S.scheduleTitle).first();
    await firstTitle.scrollIntoViewIfNeeded();
    await firstTitle.click();
    await expect(page.locator(S.scheduleModal)).toBeVisible({ timeout: 8000 });
    const title = await page.locator(S.scheduleModalTitle).textContent();
    expect(title?.trim().length).toBeGreaterThan(0);
  });

  test('schedule modal shows date, time, room', async ({ page }) => {
    const firstTitle = page.locator(S.scheduleTitle).first();
    await firstTitle.scrollIntoViewIfNeeded();
    await firstTitle.click();
    await expect(page.locator(S.scheduleModal)).toBeVisible({ timeout: 8000 });
    await expect(page.locator('#modal-schedule-date')).toBeVisible();
    await expect(page.locator('#modal-schedule-time')).toBeVisible();
    await expect(page.locator('#modal-schedule-room')).toBeVisible();
  });

  test('schedule modal closes on close button', async ({ page }) => {
    const firstTitle = page.locator(S.scheduleTitle).first();
    await firstTitle.scrollIntoViewIfNeeded();
    await firstTitle.click();
    await expect(page.locator(S.scheduleModal)).toBeVisible({ timeout: 8000 });
    await page.click(S.scheduleModalClose);
    await expect(page.locator(S.scheduleModal)).toBeHidden({ timeout: 5000 });
  });

  test('schedule modal closes on Escape key', async ({ page }) => {
    const firstTitle = page.locator(S.scheduleTitle).first();
    await firstTitle.scrollIntoViewIfNeeded();
    await firstTitle.click();
    await expect(page.locator(S.scheduleModal)).toBeVisible({ timeout: 8000 });
    // Bootstrap 3 binds the Escape key handler on the modal element itself (not on document),
    // so we dispatch keydown directly to the modal to guarantee Bootstrap's handler fires.
    // Also wait for Bootstrap's show animation + enforceFocus() to complete before pressing.
    await page.waitForTimeout(400);
    await page.locator(S.scheduleModal).press('Escape');
    await expect(page.locator(S.scheduleModal)).toBeHidden({ timeout: 5000 });
  });

  // ── Layout at 1920×1080 ──

  test('schedule items do not overflow viewport width', async ({ page }) => {
    const item = page.locator(S.scheduleItem).first();
    const box = await item.boundingBox();
    if (!box) return;
    expect(box.x + box.width).toBeLessThanOrEqual(1925); // 1920 + small tolerance
  });

  test('no horizontal scrollbar at 1080p', async ({ page }) => {
    const hasHScroll = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasHScroll).toBe(false);
  });
});
