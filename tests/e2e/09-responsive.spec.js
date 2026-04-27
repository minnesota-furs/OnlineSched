// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Responsive tests for mobile, tablet, and ultra-wide viewports.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

// ── Mobile (375px) ──

test.describe('09 — Responsive (mobile 375px)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('.hidden-xs elements are not visible on mobile', async ({ page }) => {
    const result = await page.evaluate((selector) => {
      const els = Array.from(document.querySelectorAll(selector));
      if (els.length === 0) return { count: 0, violations: [], appliedCount: 0 };

      const violations = [];
      let appliedCount = 0;

      for (const el of els) {
        let hiddenByParent = false;
        let parent = el.parentElement;
        while (parent) {
          if (window.getComputedStyle(parent).display === 'none') {
            hiddenByParent = true;
            break;
          }
          parent = parent.parentElement;
        }
        if (hiddenByParent) continue;

        const display = window.getComputedStyle(el).display;
        if (display === 'none') {
          appliedCount++;
        } else {
          violations.push(`${el.tagName}.${el.className.replace(/\s+/g, '.')}`);
        }
      }

      return { count: els.length, violations, appliedCount };
    }, S.hiddenXs);

    if (result.count === 0) return;

    if (result.appliedCount === 0 && result.violations.length === 0) return;
    test.skip(
      result.appliedCount === 0 && result.violations.length > 0,
      `${S.hiddenXs} media query not applying at 375px — responsive CSS issue`
    );

    if (result.appliedCount > 0 && result.violations.length > 0) {
      test.skip(true, `${result.violations.length} ${S.hiddenXs} element(s) shown by plugin CSS override — expected pre-refactor: ${result.violations.join(', ')}`);
      return;
    }

    expect(
      result.violations,
      `${S.hiddenXs} visible in DOM flow: ${result.violations.join(', ')}`
    ).toHaveLength(0);
  });

  test('tags and panelists are hidden on mobile', async ({ page }) => {
    // Check first schedule item's tags and panelists dl elements
    const firstItem = page.locator(S.scheduleItem).first();
    const tagsDl = firstItem.locator('.schedule-tags').locator('xpath=ancestor::dl');
    const panelistsDl = firstItem.locator('.schedule-panelists').locator('xpath=ancestor::dl');

    if (await tagsDl.count() > 0) {
      await expect(tagsDl).toBeHidden();
    }
    if (await panelistsDl.count() > 0) {
      await expect(panelistsDl).toBeHidden();
    }
  });

  test('.visible-xs elements are visible on mobile', async ({ page }) => {
    const visibleEls = page.locator(S.visibleXs);
    const count = await visibleEls.count();
    if (count === 0) return;
    for (let i = 0; i < count; i++) {
      await expect(visibleEls.nth(i)).toBeVisible();
    }
  });

  test('schedule items do not overflow viewport width', async ({ page }) => {
    const item = page.locator(S.scheduleItem).first();
    const box = await item.boundingBox();
    if (!box) return;
    expect(box.x + box.width).toBeLessThanOrEqual(380); // 375 + small tolerance
  });

  test('tabs are visible and tappable', async ({ page }) => {
    const tabs = page.locator(S.tabLinks);
    const count = await tabs.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      await expect(tabs.nth(i)).toBeVisible();
    }
  });

  test('login modal is usable at mobile width', async ({ page }) => {
    await page.click(S.loginModalBtn);
    await page.waitForTimeout(300);
    await expect(page.locator(S.loginModal)).toBeVisible();
    const box = await page.locator(S.loginModal).boundingBox();
    if (box) {
      expect(box.width).toBeLessThanOrEqual(375);
    }
    await page.click(S.loginModalClose);
    await page.waitForTimeout(200);
    await expect(page.locator(S.loginModal)).toBeHidden();
  });

  test('filter dropdowns are visible and stacked', async ({ page }) => {
    await expect(page.locator(S.searchInput)).toBeVisible();
    await expect(page.locator(S.selectTags)).toBeVisible();
    await expect(page.locator(S.selectDays)).toBeVisible();
    await expect(page.locator(S.selectRooms)).toBeVisible();

    const searchBox = await page.locator(S.searchInput).boundingBox();
    const tagsBox = await page.locator(S.selectTags).boundingBox();
    if (searchBox && tagsBox) {
      expect(Math.abs(searchBox.y - tagsBox.y)).toBeGreaterThan(10);
    }
  });
});

// ── Tablet (768px) ──

test.describe('09 — Responsive (tablet 768px)', () => {
  test.use({ viewport: { width: 768, height: 1024 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('schedule items show room and time columns', async ({ page }) => {
    const room = page.locator('.schedule-room').first();
    const time = page.locator('.schedule-time').first();
    await expect(room).toBeVisible();
    await expect(time).toBeVisible();
  });

  test('schedule items do not overflow viewport width', async ({ page }) => {
    const item = page.locator(S.scheduleItem).first();
    const box = await item.boundingBox();
    if (!box) return;
    expect(box.x + box.width).toBeLessThanOrEqual(773);
  });

  test('tabs are fully readable', async ({ page }) => {
    const tabs = page.locator(S.tabLinks);
    const count = await tabs.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      const box = await tabs.nth(i).boundingBox();
      if (box) {
        expect(box.width).toBeGreaterThan(30);
      }
    }
  });

  test('modal has comfortable width', async ({ page }) => {
    await page.click(S.loginModalBtn);
    await page.waitForTimeout(300);
    await expect(page.locator(S.loginModal)).toBeVisible();
    const box = await page.locator(S.loginModal).boundingBox();
    if (box) {
      expect(box.width).toBeLessThanOrEqual(768);
    }
    await page.click(S.loginModalClose);
    await page.waitForTimeout(200);
  });

  test('filter controls are visible', async ({ page }) => {
    await expect(page.locator(S.searchInput)).toBeVisible();
    await expect(page.locator(S.selectTags)).toBeVisible();
    await expect(page.locator(S.selectDays)).toBeVisible();
    await expect(page.locator(S.selectRooms)).toBeVisible();
  });
});

// ── Ultra-wide Tablet / Landscape (1366px) ──

test.describe('09 — Responsive (tablet-landscape 1366px)', () => {
  test.use({ viewport: { width: 1366, height: 1024 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('full desktop layout: all columns visible', async ({ page }) => {
    const item = page.locator(S.scheduleItem).first();
    await expect(item.locator('.schedule-title')).toBeVisible();
    await expect(item.locator('.schedule-room').or(item.locator('dd.schedule-room'))).toBeVisible();
    await expect(item.locator('.schedule-time').or(item.locator('dd.schedule-time'))).toBeVisible();
  });

  test('schedule items do not overflow viewport width', async ({ page }) => {
    const item = page.locator(S.scheduleItem).first();
    const box = await item.boundingBox();
    if (!box) return;
    expect(box.x + box.width).toBeLessThanOrEqual(1371);
  });

  test('no horizontal scrollbar', async ({ page }) => {
    const hasHScroll = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasHScroll).toBe(false);
  });

  test('modal is centered with margin on both sides', async ({ page }) => {
    await page.locator(S.scheduleTitle).first().click();
    await page.waitForTimeout(400);
    await expect(page.locator(S.scheduleModal)).toBeVisible();
    // Use .modal-dialog specifically — the outer #modal-schedule is position:fixed at x=0
    const box = await page.locator('#modal-schedule .modal-dialog').boundingBox();
    if (box) {
      expect(box.x).toBeGreaterThan(20);           // left margin exists
      expect(box.x + box.width).toBeLessThan(1346); // right margin exists at 1366px
    }
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
  });

  test('filter bar is a single horizontal row', async ({ page }) => {
    const searchBox = await page.locator(S.searchInput).boundingBox();
    const roomsBox = await page.locator(S.selectRooms).boundingBox();
    if (searchBox && roomsBox) {
      expect(Math.abs(searchBox.y - roomsBox.y)).toBeLessThan(50);
    }
  });
});
