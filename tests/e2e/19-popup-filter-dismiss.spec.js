// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

// The popup repeats the row's room and tag filter links. Clicking one filtered
// the schedule behind it but left the popup covering the result.
test.describe('19 - Popup dismisses when it filters', () => {
  const isOpen = (page) =>
    page.evaluate(() => document.getElementById('modal-schedule')?.hasAttribute('open') === true);

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(400);
    await page.locator('.schedule-title a[data-target="#modal-schedule"]').first().click();
    await page.waitForTimeout(400);
    expect(await isOpen(page)).toBe(true);
  });

  test('clicking the room closes the popup and filters', async ({ page }) => {
    const term = page.locator('#modal-schedule-room .os-term-item[data-os-term-slug]').first();
    test.skip(await term.count() === 0, 'no room term in this event popup');
    const slug = await term.getAttribute('data-os-term-slug');

    await term.click();
    await page.waitForTimeout(400);
    expect(await isOpen(page)).toBe(false);
    await expect(page.locator(S.selectRooms)).toHaveValue(slug);

    const hash = await page.evaluate(() => location.hash);
    expect(hash).toContain(`room=${slug}`);
    expect(hash).not.toContain('evt=');
  });

  test('clicking a tag closes the popup and filters', async ({ page }) => {
    const term = page.locator('#modal-schedule-tags .os-term-item').first();
    test.skip(await term.count() === 0, 'no tag term in this event popup');
    const label = await term.getAttribute('data-os-term-label');

    await term.click();
    await page.waitForTimeout(400);
    expect(await isOpen(page)).toBe(false);

    const selected = await page.$eval(S.selectTags, el => el.options[el.selectedIndex].textContent.trim());
    expect(selected).toBe(label);
    expect(await page.evaluate(() => location.hash)).not.toContain('evt=');
  });

  test('the popup stays open when nothing else was clicked', async ({ page }) => {
    await page.locator('#modal-schedule-title').click();
    await page.waitForTimeout(300);
    expect(await isOpen(page)).toBe(true);
  });
});
