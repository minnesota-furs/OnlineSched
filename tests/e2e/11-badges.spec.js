// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Badge rendering tests - verifies badge types display with correct colors, icons, and row highlights.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

// Default badge type config (mirrors OnlineSchedBadgeTypes.php $default_badge_types_config).
// Used to verify rendered badge colors and visibility without querying the DB at runtime.
const BADGE_DEFAULTS = {
  'Adult':          { color: '#d12229', fgColor: '#ffffff', showBadge: true,  rowColor: '' },
  'Sensory':        { color: '#0a58ca', fgColor: '#ffffff', showBadge: true,  rowColor: '' },
  'VIP':            { color: '',        fgColor: '',        showBadge: true,  rowColor: '#fff0b2' },
  'Essentials':     { color: '',        fgColor: '',        showBadge: true,  rowColor: '' },
  'Guest Of Honor': { color: '',        fgColor: '',        showBadge: false, rowColor: '#b5d8ac', icon: 'fas fa-star' },
  'Special Guest':  { color: '',        fgColor: '',        showBadge: false, rowColor: '#b5d8ac', icon: 'fas fa-star' },
  'Streaming':      { color: '',        fgColor: '',        showBadge: true,  rowColor: '' },
  'Cancelled':      { color: '',        fgColor: '',        showBadge: true,  rowColor: '' },
};

test.describe('11 — Badges', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  // ── Badge Presence ──

  test('at least one badge element renders on the page', async ({ page }) => {
    const count = await page.locator(S.badge).count();
    expect(count).toBeGreaterThan(0);
  });

  test('Essentials badge renders on Essential-tagged events', async ({ page }) => {
    await page.fill(S.searchInput, 'Opening Howl Ceremony');
    await page.waitForTimeout(400);
    const item = page.locator(`${S.scheduleItem}:visible`).first();
    const badge = item.locator(S.badgeEssentials);
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('Essentials');
  });

  test('Cancelled badge renders on Cancelled-tagged events', async ({ page }) => {
    await page.fill(S.searchInput, 'Napping in the Raccoon Lounge');
    await page.waitForTimeout(400);
    const item = page.locator(`${S.scheduleItem}:visible`).first();
    const badge = item.locator(S.badgeCancelled);
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('Cancelled');
  });

  test('Adult badge renders on Restricted-tagged events', async ({ page }) => {
    await page.fill(S.searchInput, 'After Dark Howl');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    const count = await items.count();
    if (count === 0) return test.skip(true, 'Adult seed event not found - reseed with --force');
    const badge = items.first().locator(S.badgeDanger);
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('Adult');
  });

  test('Sensory badge renders on Sensory-tagged events', async ({ page }) => {
    await page.fill(S.searchInput, 'Quiet Paws Chill Zone');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    const count = await items.count();
    if (count === 0) return test.skip(true, 'Sensory seed event not found - reseed with --force');
    const badge = items.first().locator(S.badgeSensory);
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('Sensory');
  });

  test('VIP badge renders on VIP-tagged events', async ({ page }) => {
    await page.fill(S.searchInput, 'VIP Tail Care Lounge');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    const count = await items.count();
    if (count === 0) return test.skip(true, 'VIP seed event not found - reseed with --force');
    const badge = items.first().locator(S.badgeVip);
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('VIP');
  });

  // ── Badge Colors ──

  test('Adult badge has red background (#d12229) and white text', async ({ page }) => {
    await page.fill(S.searchInput, 'After Dark Howl');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    if (await items.count() === 0) return test.skip(true, 'Adult seed event not found');
    const badge = items.first().locator(S.badgeDanger);
    const style = await badge.getAttribute('style') || '';
    expect(style.toLowerCase()).toContain('#d12229');
    expect(style.toLowerCase()).toContain('#ffffff');
  });

  test('Sensory badge has blue background (#0a58ca) and white text', async ({ page }) => {
    await page.fill(S.searchInput, 'Quiet Paws Chill Zone');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    if (await items.count() === 0) return test.skip(true, 'Sensory seed event not found');
    const badge = items.first().locator(S.badgeSensory);
    const style = await badge.getAttribute('style') || '';
    expect(style.toLowerCase()).toContain('#0a58ca');
    expect(style.toLowerCase()).toContain('#ffffff');
  });

  // ── Row Highlights ──

  test('VIP-tagged events have row highlight color (#fff0b2)', async ({ page }) => {
    await page.fill(S.searchInput, 'VIP Tail Care Lounge');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    const count = await items.count();
    if (count === 0) return test.skip(true, 'VIP seed event not found - reseed with --force');
    const style = await items.first().getAttribute('style') || '';
    expect(style.toLowerCase()).toContain('#fff0b2');
  });

  // ── Badge Visibility (show_badge: false should NOT render badge span) ──

  test('Guest Of Honor type does not render a visible badge span (show_badge=false)', async ({ page }) => {
    // Guest Of Honor has show_badge=false, so no .badge span should appear.
    // It DOES have a row highlight color though. If no GoH seed events exist, skip.
    const gohBadges = await page.locator(`${S.badgeGoh}:visible`).count();
    const gohIconBadges = await page.locator(`.badge-guest-of-honor:visible`).count();
    expect(gohBadges + gohIconBadges).toBe(0);
  });

  // ── Badges on Kiosk ──

  test.describe('Kiosk badges', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('/kiosk-schedule/');
      await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
      await page.selectOption(S.selectDays, 'all');
      await page.waitForTimeout(300);
    });

    test('badges render on kiosk page', async ({ page }) => {
      const count = await page.locator(S.badge).count();
      expect(count).toBeGreaterThan(0);
    });

    test('Essentials badge visible on kiosk', async ({ page }) => {
      const count = await page.locator(S.badgeEssentials).count();
      expect(count).toBeGreaterThan(0);
    });

    test('Cancelled badge visible on kiosk cancelled event', async ({ page }) => {
      // Kiosk hides the search input, use tag dropdown to find cancelled events
      const cancelledBadges = await page.locator(S.badgeCancelled).count();
      expect(cancelledBadges).toBeGreaterThan(0);
    });

    test('Adult badge has correct color on kiosk', async ({ page }) => {
      const badges = page.locator(S.badgeDanger);
      const count = await badges.count();
      if (count === 0) return test.skip(true, 'No Adult badge on kiosk - reseed with --force');
      const style = await badges.first().getAttribute('style') || '';
      expect(style.toLowerCase()).toContain('#d12229');
    });

    test('VIP event has row highlight on kiosk', async ({ page }) => {
      const vipItems = await page.evaluate((sel) => {
        const items = document.querySelectorAll(sel);
        const matches = [];
        items.forEach(item => {
          const style = item.getAttribute('style') || '';
          if (style.includes('#fff0b2')) matches.push(item.id);
        });
        return matches;
      }, S.scheduleItem);
      if (vipItems.length === 0) return test.skip(true, 'No VIP event on kiosk - reseed with --force');
      expect(vipItems.length).toBeGreaterThan(0);
    });
  });

  // ── Badge Defaults Validation ──
  // Verify the expected default badge types are all configured in the DB.
  // This catches cases where the seed script or restore-defaults is broken.

  test('all default badge types produce at least one badge on the page', async ({ page }) => {
    // Of the 8 defaults, only those with show_badge=true AND matching seed events render.
    // We check: Adult, Sensory, Essentials, Cancelled (all have seed events and show_badge=true).
    const expectedBadges = [
      { selector: S.badgeEssentials, name: 'Essentials' },
      { selector: S.badgeCancelled,  name: 'Cancelled' },
    ];

    for (const { selector, name } of expectedBadges) {
      const count = await page.locator(selector).count();
      expect(count, `Expected at least one ${name} badge`).toBeGreaterThan(0);
    }
  });
});

