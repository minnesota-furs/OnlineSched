// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

// The stored os_room term slug is the only room identity. A slug derived in the
// browser disagreed for punctuated names, so a room reached the filter twice.
test.describe('18 - Room slug authority', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(400);
  });

  test('every room reaches the filter exactly once', async ({ page }) => {
    const options = await page.$$eval(`${S.selectRooms} option:not([value="all"])`,
      els => els.map(e => ({ label: e.textContent.trim(), value: e.value })));
    expect(options.length).toBeGreaterThan(0);

    const labels = options.map(o => o.label);
    const values = options.map(o => o.value);
    expect(labels).toEqual([...new Set(labels)]);
    expect(values).toEqual([...new Set(values)]);
  });

  test('no option falls back to displaying its slug', async ({ page }) => {
    // A duplicate had no entry in the label map and rendered its raw slug.
    const options = await page.$$eval(`${S.selectRooms} option:not([value="all"])`,
      els => els.map(e => ({ label: e.textContent.trim(), value: e.value })));
    for (const option of options) {
      expect(option.label).not.toBe(option.value);
      expect(option.label).not.toMatch(/^[a-z0-9]+(-[a-z0-9]+)*$/);
    }
  });

  test('room attributes come only from the server', async ({ page }) => {
    // Any attribute whose value is not a rendered term slug was added client
    // side, which is the defect itself rather than a symptom.
    const result = await page.evaluate(() => {
      const served = new Set([...document.querySelectorAll('[data-os-term-slug]')]
        .map(el => el.dataset.osTermSlug));
      const used = new Set();
      for (const item of document.querySelectorAll('.schedule-item')) {
        for (const attr of item.attributes) {
          if (attr.name.startsWith('data-schedule-room-')) used.add(attr.value);
        }
      }
      return { synthetic: [...used].filter(v => !served.has(v)), served: served.size };
    });
    expect(result.served).toBeGreaterThan(0);
    expect(result.synthetic).toEqual([]);
  });

  test('a punctuated room filters and routes on its stored slug', async ({ page }) => {
    const room = await page.evaluate(() => {
      const el = [...document.querySelectorAll('.schedule-room .os-term-item[data-os-term-slug]')]
        .find(e => /[()&]/.test(e.dataset.osTermLabel || ''));
      return el ? { slug: el.dataset.osTermSlug, label: el.dataset.osTermLabel } : null;
    });
    test.skip(!room, 'no punctuated room name in this fixture');

    const option = page.locator(`${S.selectRooms} option[value="${room.slug}"]`);
    await expect(option).toHaveCount(1);
    await expect(option).toHaveText(room.label);

    await page.selectOption(S.selectRooms, room.slug);
    await page.waitForTimeout(400);

    // Read the room attribute, not the rendered name: cancelled rows carry the
    // room but print no room text.
    const stray = await page.$$eval(`${S.scheduleItem}:not(.os-fallback-item)`,
      (els, slug) => els.filter(e => e.offsetParent !== null)
        .filter(e => ![...e.attributes].some(a => a.name.startsWith('data-schedule-room-') && a.value === slug))
        .map(e => e.id), room.slug);
    expect(stray).toEqual([]);
    expect(await page.evaluate(() => location.hash)).toContain(room.slug);
  });

  test('clicking a room name selects that room', async ({ page }) => {
    const values = await page.$$eval(`${S.selectRooms} option`, els => els.map(e => e.value));
    const picked = await page.evaluate((known) => {
      // The clicked term wins, so a multi-room row selects the one clicked.
      const el = [...document.querySelectorAll('.schedule-room.schedule-filter-link .os-term-item[data-os-term-slug]')]
        .find(e => known.includes(e.dataset.osTermSlug) && e.offsetParent !== null);
      if (!el) return null;
      el.click();
      return el.dataset.osTermSlug;
    }, values);
    test.skip(!picked, 'no clickable room term in the option set');

    await page.waitForTimeout(400);
    await expect(page.locator(S.selectRooms)).toHaveValue(picked);
  });
});
