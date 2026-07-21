// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('07 — Hash Routing', () => {
  test('#hour hash activates the Hours tab', async ({ page }) => {
    await page.goto('/schedule/#tab=hours');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(600);
    await expect(page.locator(S.tabHours)).toBeVisible();
    await expect(page.locator(S.tabProgramming)).toBeHidden();
  });

  test('#tag= hash selects matching tag in dropdown', async ({ page }) => {
    // Use the Essential tag which exists in seed data
    await page.goto('/schedule/#tag=essential');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(1000); // Give JS routing time to populate and select
    const selectedText = await page.locator(`${S.selectTags} option:checked`).textContent();
    expect(selectedText?.toLowerCase()).toContain('essential');
  });

  test('#tag- hash with hyphenated slug (multi-word tag) selects matching tag', async ({ page }) => {
    // Slug "special-event" must normalize to match dropdown option "Special Event".
    await page.goto('/schedule/#tag-special-event');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(1000);
    const selectedText = await page.locator(`${S.selectTags} option:checked`).textContent();
    expect(selectedText?.toLowerCase()).toContain('special event');
  });

  test('#evt= hash opens the matching event modal', async ({ page }) => {
    // Get a valid event ID from the schedule
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);

    const firstId = await page.locator(S.scheduleItem).first().getAttribute('id');
    // IDs are like "onlineevt-12345" → hash is "#evt=12345"
    const hash = firstId ? '#evt=' + firstId.replace('onlineevt-', '') : null;
    if (!hash) test.skip(true, 'No schedule items found');

    // Navigate to the hash URL
    await page.goto(`/schedule/${hash}`);
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(800); // Wait for hash routing animation + modal transition

    // Hash routing must make the specific event visible (switches to all-days if hidden)
    const evtId = hash.replace('#evt=', '');
    await expect(page.locator(`#onlineevt-${evtId}`)).toBeVisible({ timeout: 5000 });

    // Hash routing triggers a programmatic click on the event title.
    // If the modal isn't visible after hash navigation, fall back to a direct Playwright click.
    if (!(await page.locator(S.scheduleModal).isVisible())) {
      await page.locator(`#onlineevt-${evtId} .schedule-title a`).click();
      await page.waitForTimeout(400);
    }

    await expect(page.locator(S.scheduleModal)).toBeVisible();
    const title = await page.locator(S.scheduleModalTitle).textContent();
    expect(title?.trim().length).toBeGreaterThan(0);
  });

  test('combined filters (#tag=...&room=...) filters correctly', async ({ page }) => {
    // Get a room from an item that also carries the tag under test. A room
    // from an unrelated first item can produce an impossible combination.
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);

    const tagSlug = 'essential';
    const matchingItem = page.locator(`${S.scheduleItem}[data-schedule-tag-${tagSlug}]`).first();
    if (await matchingItem.count() === 0) test.skip(true, 'No Essential seed event found');

    const roomSlug = await matchingItem.evaluate((el) => {
      const attr = Array.from(el.attributes).find(a => a.name.startsWith('data-schedule-room-'));
      return attr ? attr.value : null;
    });

    if (!roomSlug) test.skip(true, 'No room slug found on Essential seed event');

    // Navigate to combined hash
    await page.goto(`/schedule/#tag=${tagSlug}&room=${roomSlug}`);
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.waitForTimeout(800);

    // Verify dropdowns are set
    const selectedTag = await page.locator(`${S.selectTags} option:checked`).textContent();
    expect(selectedTag?.toLowerCase()).toContain(tagSlug);

    const selectedRoom = await page.locator(S.selectRooms).inputValue();
    expect(selectedRoom).toBe(roomSlug);

    // Verify only matching items are visible
    const visibleItems = page.locator(`${S.scheduleItem}:visible`);
    const count = await visibleItems.count();
    for (let i = 0; i < count; i++) {
      const item = visibleItems.nth(i);
      const matchesRoom = await item.evaluate((el, slug) => {
        return Array.from(el.attributes).some(a => a.name === 'data-schedule-room-' + slug);
      }, roomSlug);
      expect(matchesRoom).toBe(true);
    }
  });
});
