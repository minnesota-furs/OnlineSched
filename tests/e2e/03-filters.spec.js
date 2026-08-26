// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('03 — Filters', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test('search filters items', async ({ page }) => {
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.fill(S.searchInput, 'Coyote');
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThan(totalBefore);
  });

  test('search hides hour headings with no matching events', async ({ page }) => {
    const firstTitle = (await page.locator(`${S.scheduleItem}:visible ${S.scheduleTitle}`).first().textContent())?.trim();
    expect(firstTitle).toBeTruthy();

    await page.fill(S.searchInput, firstTitle);
    await page.waitForTimeout(400);

    const emptyVisibleHours = await page.locator(`${S.scheduleHour}:visible`).evaluateAll((hours) => (
      hours.filter((hour) => !Array.from(hour.querySelectorAll('.schedule-item')).some((item) => item.style.display !== 'none')).length
    ));
    expect(emptyVisibleHours).toBe(0);
  });

  test('clearing search restores all items', async ({ page }) => {
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.fill(S.searchInput, 'Coyote');
    await page.waitForTimeout(300);
    await page.fill(S.searchInput, '');
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBe(totalBefore);
  });

  test('day dropdown filters to one day section', async ({ page }) => {
    // Get the first non-default option value
    const firstDay = await page.locator(`${S.selectDays} option:not([value="all"]):not([value="Current"])`).first().getAttribute('value');
    await page.selectOption(S.selectDays, firstDay);
    await page.waitForTimeout(400);
    const visibleDays = await page.locator(`${S.scheduleDay}:visible`).count();
    expect(visibleDays).toBe(1);
  });

  test('selecting All Days shows all day sections', async ({ page }) => {
    const totalDays = await page.locator(`${S.scheduleDay}:visible`).count();
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(400);
    const visibleDays = await page.locator(`${S.scheduleDay}:visible`).count();
    expect(visibleDays).toBe(totalDays);
  });

  test('tag dropdown filters items', async ({ page }) => {
    const tagOption = await page.locator(`${S.selectTags} option:not([value="all"])`).first().getAttribute('value');
    if (!tagOption) return test.skip();
    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(400);
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeLessThanOrEqual(totalBefore);
  });

  test('room dropdown filters items', async ({ page }) => {
    const roomOption = await page.locator(`${S.selectRooms} option:not([value="all"])`).first().getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(400);
    const count = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(count).toBeGreaterThan(0);
  });

  test('Reset button is disabled when no filters active', async ({ page }) => {
    // beforeEach selects 'all' which enables reset; re-navigate to get true default state.
    // Use domcontentloaded so we don't wait for all 3rd-party scripts under Docker load.
    await page.goto('/schedule/', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await expect(page.locator(S.resetButton)).toBeDisabled();
  });

  test('Reset button enables after search and resets on click', async ({ page }) => {
    await page.fill(S.searchInput, 'Raccoon');
    await page.waitForTimeout(300);
    await expect(page.locator(S.resetButton)).toBeEnabled();
    await page.click(S.resetButton);
    await page.waitForTimeout(400);
    await expect(page.locator(S.resetButton)).toBeDisabled();
    // Verify filters returned to default state
    await expect(page.locator(S.searchInput)).toHaveValue('');
    await expect(page.locator(S.selectDays)).toHaveValue('Current');
    await expect(page.locator(S.selectTags)).toHaveValue('all');
    await expect(page.locator(S.selectRooms)).toHaveValue('all');
    // Items should still be visible (seed data is in the future)
    const visible = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(visible).toBeGreaterThan(0);
  });

  test('Current filter shows only future events from seed data', async ({ page }) => {
    await page.selectOption(S.selectDays, 'Current');
    await page.waitForTimeout(400);
    // All seed events are in the future, so at least some should be visible
    const count = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(count).toBeGreaterThan(0);
  });

  test('multi-filter combo: tag + room simultaneously', async ({ page }) => {
    // Select a specific tag
    const tagOption = await page.locator(`${S.selectTags} option:not([value="all"])`).first().getAttribute('value');
    if (!tagOption) return test.skip();
    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(300);
    const afterTag = await page.locator(`${S.scheduleItem}:visible`).count();

    // Now also select a specific room - should narrow results further or keep
    // same. An enabled one: zero-match options stay listed but greyed now.
    const roomOption = await page.locator(`${S.selectRooms} option:not([value="all"]):not([disabled])`).first().getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(400);
    const afterBoth = await page.locator(`${S.scheduleItem}:visible`).count();

    expect(afterBoth).toBeLessThanOrEqual(afterTag);
  });

  test('cancelled event displays Cancelled tag class and hides calendar buttons', async ({ page }) => {
    // Search for the known cancelled seed event
    await page.fill(S.searchInput, 'Napping in the Raccoon Lounge');
    await page.waitForTimeout(400);
    const items = page.locator(`${S.scheduleItem}:visible`);
    const count = await items.count();
    if (count === 0) return test.skip(true, 'Cancelled seed event not found');

    const item = items.first();
    // Cancelled items get the schedule-tag-cancelled class (from $addScheduleTags in the PHP template)
    await expect(item).toHaveClass(/schedule-tag-cancelled/);

    // Cancelled events should NOT have calendar/clipboard buttons
    const calButtons = await item.locator('.schedule-clipboard, .schedule-ical, .schedule-google').count();
    expect(calButtons).toBe(0);
  });

  test('clicking a room name in an event row sets the room filter', async ({ page }) => {
    // Hidden on mobile (max-width: 767px)
    const viewport = page.viewportSize();
    if (viewport && viewport.width <= 767) return test.skip(true, 'Clickable room links may be hidden or hard to tap on mobile');

    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    // Find the first visible clickable room element with text
    const roomLink = page.locator(`${S.scheduleRoom}${S.filterLink}`).first();
    const roomText = await roomLink.textContent();
    if (!roomText?.trim()) return test.skip(true, 'No clickable room found');

    await roomLink.click();
    await page.waitForTimeout(400);

    // Room dropdown should now have a non-default value
    const selectedRoom = await page.locator(S.selectRooms).inputValue();
    expect(selectedRoom).not.toBe('all');

    // Visible items should be filtered
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThanOrEqual(totalBefore);
  });

  test('clicking a tag name in an event row sets the tag filter', async ({ page }) => {
    // Hidden on mobile (max-width: 767px)
    const viewport = page.viewportSize();
    if (viewport && viewport.width <= 767) return test.skip(true, 'Tags are hidden on mobile');

    const totalBefore = await page.locator(`${S.scheduleItem}:visible`).count();
    // Find the first visible clickable tags element with text
    const tagLink = page.locator(`${S.scheduleTags}${S.filterLink}`).first();
    const tagText = await tagLink.textContent();
    if (!tagText?.trim()) return test.skip(true, 'No clickable tag found');

    await tagLink.click();
    await page.waitForTimeout(400);

    // Tag dropdown should now have a non-default value
    const selectedTag = await page.locator(S.selectTags).inputValue();
    expect(selectedTag).not.toBe('all');

    // Visible items should be filtered
    const totalAfter = await page.locator(`${S.scheduleItem}:visible`).count();
    expect(totalAfter).toBeGreaterThan(0);
    expect(totalAfter).toBeLessThanOrEqual(totalBefore);
  });

  test('multi-tag event rows expose each tag as its own filter target', async ({ page }) => {
    const viewport = page.viewportSize();
    if (viewport && viewport.width <= 767) return test.skip(true, 'Tags are hidden on mobile');

    const multiTagItem = page.locator(`${S.scheduleItem}:visible`).filter({
      has: page.locator('.schedule-tags .os-term-item:nth-child(2)'),
    }).first();

    if ((await multiTagItem.count()) === 0) {
      return test.skip(true, 'No visible multi-tag event found');
    }

    const tagItems = multiTagItem.locator('.schedule-tags .os-term-item');
    const tagCount = await tagItems.count();
    expect(tagCount).toBeGreaterThanOrEqual(2);

    const secondTag = tagItems.nth(1);
    const tagText = (
      await secondTag.getAttribute('data-os-term-label') ||
      (await secondTag.textContent())?.replace(/,\s*$/, '')
    )?.trim();
    if (!tagText) return test.skip(true, 'Second tag text missing');

    // The taxonomy slug is the emitted identity, whatever the label reads.
    const slug = await page.evaluate(
      (label) => window.scheduleMasterTags?.[label] ?? null, tagText);
    await secondTag.click();
    await page.waitForTimeout(400);

    const selectedText = (await page.locator(`${S.selectTags} option:checked`).textContent())?.trim();
    expect(selectedText?.toLowerCase()).toBe(tagText.toLowerCase());
    expect(decodeURIComponent(page.url())).toContain(`tags=${slug}`);
  });

  // The state layer holds sets before any checkbox UI exists, so the hash is
  // the only way to drive a multi-selection.
  test('two rooms in the hash show events from both', async ({ page }) => {
    const roomValues = await page.locator(
      `${S.selectRooms} option:not([value="all"]):not([disabled])`).evaluateAll(
        (options) => options.slice(0, 2).map((option) => option.value));
    if (roomValues.length < 2) return test.skip(true, 'Needs two live rooms');

    const counts = [];
    for (const room of roomValues) {
      await page.goto(`/schedule/#days=all&rooms=${room}`);
      await page.waitForSelector(S.schedule, { state: 'visible' });
      await page.waitForTimeout(400);
      counts.push(await page.locator(`${S.scheduleItem}:visible`).count());
    }

    await page.goto(`/schedule/#days=all&rooms=${roomValues.join(',')}`);
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);
    const both = await page.locator(`${S.scheduleItem}:visible`).count();

    // OR within a facet: the pair shows at least as much as either alone, and
    // strictly more than one of them unless the rooms share every event.
    expect(both).toBeGreaterThanOrEqual(Math.max(...counts));
    expect(both).toBeGreaterThan(Math.min(...counts));
  });

  test('a legacy single-value room link still filters', async ({ page }) => {
    const room = await page.locator(
      `${S.selectRooms} option:not([value="all"]):not([disabled])`).first().getAttribute('value');
    if (!room) return test.skip(true, 'Needs a live room');

    await page.goto(`/schedule/#day=all&room=${room}`);
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);

    // An old bookmark maps to a one-element set and the select still shows it,
    // even though the plural form is the only one emitted.
    expect(await page.locator(S.selectRooms).inputValue()).toBe(room);
    const shown = await page.locator(`${S.scheduleItem}:visible`).count();
    const total = await page.locator(S.scheduleItem).count();
    expect(shown).toBeGreaterThan(0);
    expect(shown).toBeLessThan(total);
  });

  test('ticking two rooms in the panel filters to both and names the count', async ({ page }) => {
    const panel = page.locator('[data-filter-panel="schedule-select-rooms"]');
    await panel.locator('.os-filter-panel-button').click();
    const boxes = panel.locator('.os-filter-panel-row input:not([value="all"]):not([disabled])');
    if (await boxes.count() < 2) return test.skip(true, 'Needs two live rooms');

    await boxes.nth(0).check();
    await page.waitForTimeout(300);
    const one = await page.locator(`${S.scheduleItem}:visible`).count();
    await boxes.nth(1).check();
    await page.waitForTimeout(300);
    const two = await page.locator(`${S.scheduleItem}:visible`).count();

    expect(two).toBeGreaterThan(one);
    await expect(panel.locator('.os-filter-panel-button')).toHaveText('Rooms: 2 selected');
    expect(page.url()).toMatch(/rooms=[^&]+(,|%2C)/);
  });

  // All and Now and Future are modes, so they never coexist with a picked day.
  test('a day mode and a picked day replace each other', async ({ page }) => {
    const panel = page.locator('[data-filter-panel="schedule-select-days"]');
    await panel.locator('.os-filter-panel-button').click();
    const allRow = panel.locator('.os-filter-panel-row input[value="all"]');
    const dayRows = panel.locator(
      '.os-filter-panel-row input:not([value="all"]):not([value="Current"]):not([disabled])');
    if (await dayRows.count() < 1) return test.skip(true, 'Needs a live day');

    await allRow.check();
    await page.waitForTimeout(300);
    await dayRows.nth(0).check();
    await page.waitForTimeout(300);
    expect(await allRow.isChecked()).toBe(false);
    expect(await dayRows.nth(0).isChecked()).toBe(true);

    await allRow.check();
    await page.waitForTimeout(300);
    expect(await dayRows.nth(0).isChecked()).toBe(false);
    expect(await panel.locator('.os-filter-panel-row input:checked').count()).toBe(1);
  });

  test('a room chip replaces the set instead of joining it', async ({ page }) => {
    const panel = page.locator('[data-filter-panel="schedule-select-rooms"]');
    await panel.locator('.os-filter-panel-button').click();
    const boxes = panel.locator('.os-filter-panel-row input:not([value="all"]):not([disabled])');
    if (await boxes.count() < 2) return test.skip(true, 'Needs two live rooms');
    await boxes.nth(0).check();
    await boxes.nth(1).check();
    await page.waitForTimeout(300);
    await page.keyboard.press('Escape');

    const chip = page.locator(`${S.scheduleItem}:visible ${S.filterLink} .os-term-item`).first();
    if (await chip.count() === 0) return test.skip(true, 'Needs a filter chip');
    const chipText = (await chip.textContent()).trim();
    await chip.click();
    await page.waitForTimeout(400);

    await expect(panel.locator('.os-filter-panel-button')).toHaveText(chipText);
    expect(await panel.locator('.os-filter-panel-row input:checked').count()).toBe(1);
  });

  // A chosen value stays removable even when another facet empties it.
  test('a checked room stays enabled when a tag excludes it', async ({ page }) => {
    // A room the tag never appears in is what drives the value to zero matches.
    const combo = await page.evaluate(() => {
      const items = Array.from(document.querySelectorAll('.schedule-item'));
      const roomsOf = (item) => Array.from(item.attributes)
        .filter((a) => a.name.startsWith('data-schedule-room-')).map((a) => a.value);
      const tagsOf = (item) => Array.from(item.attributes)
        .filter((a) => a.name.startsWith('data-schedule-tag-')).map((a) => a.value);
      const options = Array.from(document.querySelectorAll('#schedule-select-tags option'))
        .filter((option) => option.value !== 'all');
      for (const option of options) {
        const slug = window.scheduleMasterTags?.[option.textContent.trim()];
        if (!slug) continue;
        const withTag = new Set();
        const everyRoom = new Set();
        items.forEach((item) => {
          const rooms = roomsOf(item);
          rooms.forEach((room) => everyRoom.add(room));
          if (tagsOf(item).includes(slug)) rooms.forEach((room) => withTag.add(room));
        });
        const without = Array.from(everyRoom).filter((room) => !withTag.has(room));
        if (withTag.size && without.length) {
          return { tag: option.value, roomWith: Array.from(withTag)[0], roomWithout: without[0] };
        }
      }
      return null;
    });
    if (!combo) return test.skip(true, 'Fixtures have no tag that excludes a room');

    const panel = page.locator('[data-filter-panel="schedule-select-rooms"]');
    await panel.locator('.os-filter-panel-button').click();
    for (const room of [combo.roomWith, combo.roomWithout]) {
      await panel.locator(`.os-filter-panel-row input[value="${room}"]`).check();
      await page.waitForTimeout(200);
    }
    await panel.locator('.os-filter-panel-close').click();

    const tagPanel = page.locator('[data-filter-panel="schedule-select-tags"]');
    await tagPanel.locator('.os-filter-panel-button').click();
    await tagPanel.locator(`.os-filter-panel-row input[value="${combo.tag}"]`).check();
    await page.waitForTimeout(400);
    await tagPanel.locator('.os-filter-panel-close').click();

    await panel.locator('.os-filter-panel-button').click();
    const excluded = panel.locator(`.os-filter-panel-row input[value="${combo.roomWithout}"]`);
    await expect(excluded).toBeChecked();
    await expect(excluded).toBeEnabled();

    await excluded.uncheck();
    await page.waitForTimeout(300);
    expect(await excluded.isChecked()).toBe(false);
  });

  test('Reset clears the plural hash keys so a reload stays clear', async ({ page }) => {
    const rooms = await page.locator(
      `${S.selectRooms} option:not([value="all"]):not([disabled])`).evaluateAll(
        (options) => options.slice(0, 2).map((option) => option.value));
    if (rooms.length < 2) return test.skip(true, 'Needs two live rooms');

    await page.goto(`/schedule/#days=all&rooms=${rooms.join(',')}`);
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);
    await page.click(S.resetButton);
    await page.waitForTimeout(400);

    expect(page.url()).not.toMatch(/rooms=/);
    expect(page.url()).not.toMatch(/days=/);

    await page.reload();
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);
    await expect(page.locator(
      '[data-filter-panel="schedule-select-rooms"] .os-filter-panel-button')).toHaveText('All Rooms');
  });

  test('the filter panel is announced, closable, and returns focus', async ({ page }) => {
    const panel = page.locator('[data-filter-panel="schedule-select-rooms"]');
    const trigger = panel.locator('.os-filter-panel-button');
    const list = panel.locator('.os-filter-panel-list');

    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    expect(await trigger.getAttribute('aria-haspopup')).toBeNull();

    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(list).toHaveAttribute('role', 'group');
    await expect(list).toHaveAttribute('aria-label', 'Rooms');
    expect(await trigger.getAttribute('aria-controls')).toBe(await list.getAttribute('id'));

    const close = panel.locator('.os-filter-panel-close');
    await expect(close).toBeVisible();
    await expect(close).toHaveAttribute('aria-label', 'Close Rooms filter');
    await close.click();
    await expect(list).toBeHidden();
    expect(await trigger.evaluate((el) => el === document.activeElement)).toBe(true);

    await trigger.click();
    await page.keyboard.press('Escape');
    await expect(list).toBeHidden();
    expect(await trigger.evaluate((el) => el === document.activeElement)).toBe(true);
  });

  test('switching tabs clears the plural keys and survives a reload', async ({ page }) => {
    const rooms = await page.locator(
      `${S.selectRooms} option:not([value="all"]):not([disabled])`).evaluateAll(
        (options) => options.slice(0, 2).map((option) => option.value));
    if (rooms.length < 2) return test.skip(true, 'Needs two live rooms');

    await page.goto(`/schedule/#days=all&rooms=${rooms.join(',')}`);
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);

    await page.locator('[data-os-tab="essentials"]').click();
    await page.waitForTimeout(600);
    expect(page.url()).not.toMatch(/rooms=/);
    expect(page.url()).not.toMatch(/days=/);

    await page.reload();
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);
    await expect(page.locator(
      '[data-filter-panel="schedule-select-rooms"] .os-filter-panel-button')).toHaveText('All Rooms');
  });

  // One tag identity. A label-derived key spelled the same tag three ways.
  test('a tag whose label differs from its slug filters from chip and hash', async ({ page }) => {
    const target = await page.evaluate(() => {
      const options = Array.from(document.querySelectorAll('#schedule-select-tags option'))
        .filter((option) => option.value !== 'all');
      const key = (text) => (text || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      for (const option of options) {
        const label = option.textContent.trim();
        const slug = window.scheduleMasterTags?.[label];
        if (slug && key(slug) !== key(label)) return { label, slug };
      }
      return null;
    });
    if (!target) return test.skip(true, 'Fixtures have no tag whose slug differs from its label');

    const panel = page.locator('[data-filter-panel="schedule-select-tags"] .os-filter-panel-button');
    const chip = page.locator(`${S.scheduleItem}:visible ${S.filterLink} .os-term-item`)
      .filter({ hasText: target.label }).first();
    if (await chip.count() === 0) return test.skip(true, 'Needs a rendered chip for that tag');

    const before = await page.locator(`${S.scheduleItem}:visible`).count();
    await chip.click();
    await page.waitForTimeout(600);

    await expect(panel).toHaveText(target.label);
    expect(await page.locator(`${S.scheduleItem}:visible`).count()).toBeLessThan(before);
    // The hash carries the taxonomy slug, not a key derived from the label.
    expect(decodeURIComponent(page.url())).toContain(`tags=${target.slug}`);

    const narrowed = await page.locator(`${S.scheduleItem}:visible`).count();
    const legacy = target.label.toLowerCase().replace(/[^a-z0-9]/g, '');
    await page.goto(`/schedule/#days=all&tag=${legacy}`);
    await page.waitForSelector(S.schedule, { state: 'visible' });
    await page.waitForTimeout(400);
    await expect(panel).toHaveText(target.label);
    expect(await page.locator(`${S.scheduleItem}:visible`).count()).toBe(narrowed);
  });

  test('clickable room/tag links are not present on kiosk', async ({ page }) => {
    await page.goto('/kiosk-schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);

    const filterLinks = await page.locator(S.filterLink).count();
    expect(filterLinks).toBe(0);
  });

  // Filtered-out options grey out; they never vanish, reorder, or change label.
  test('a narrowing filter disables room options instead of removing them', async ({ page }) => {
    const labelsBefore = await page.locator(`${S.selectRooms} option`).allTextContents();
    await page.fill(S.searchInput, 'Opening Howl');
    await page.waitForTimeout(400);
    const labelsAfter = await page.locator(`${S.selectRooms} option`).allTextContents();
    expect(labelsAfter).toEqual(labelsBefore);
    expect(await page.locator(`${S.selectRooms} option[disabled]`).count()).toBeGreaterThan(0);
  });

  test('clearing the filter re-enables every option', async ({ page }) => {
    await page.fill(S.searchInput, 'Opening Howl');
    await page.waitForTimeout(300);
    await page.fill(S.searchInput, '');
    await page.waitForTimeout(400);
    expect(await page.locator(`${S.selectRooms} option[disabled]`).count()).toBe(0);
    expect(await page.locator(`${S.selectTags} option[disabled]`).count()).toBe(0);
  });

  test('option order is identical before and after a filter cycle', async ({ page }) => {
    const values = (sel) => page.locator(`${sel} option`).evaluateAll(
      (opts) => opts.map((o) => o.value));
    const roomsBefore = await values(S.selectRooms);
    const tagsBefore = await values(S.selectTags);
    await page.fill(S.searchInput, 'Opening Howl');
    await page.waitForTimeout(400);
    expect(await values(S.selectRooms)).toEqual(roomsBefore);
    expect(await values(S.selectTags)).toEqual(tagsBefore);
    await page.fill(S.searchInput, '');
    await page.waitForTimeout(400);
    expect(await values(S.selectRooms)).toEqual(roomsBefore);
    expect(await values(S.selectTags)).toEqual(tagsBefore);
  });

  // Days grey out too: a tag or room pick must not leave selectable days
  // that lead to an empty schedule.
  test('a narrowing filter disables empty days without touching All or Current', async ({ page }) => {
    await page.fill(S.searchInput, 'Opening Howl');
    await page.waitForTimeout(400);
    expect(await page.locator(`${S.selectDays} option[disabled]`).count()).toBeGreaterThan(0);
    // Data-independent contract: an enabled day must actually deliver events.
    const firstEnabled = await page.locator(
      `${S.selectDays} option:not([disabled]):not([value="all"]):not([value="Current"])`).first().getAttribute('value');
    expect(firstEnabled).toBeTruthy();
    await page.selectOption(S.selectDays, firstEnabled);
    await page.waitForTimeout(400);
    expect(await page.locator(`${S.scheduleItem}:visible`).count()).toBeGreaterThan(0);
    await page.selectOption(S.selectDays, 'Current');
    await page.waitForTimeout(300);
    for (const keep of ['all', 'Current']) {
      const opt = page.locator(`${S.selectDays} option[value="${keep}"]`);
      expect(await opt.getAttribute('disabled')).toBeNull();
    }
    await page.fill(S.searchInput, '');
    await page.waitForTimeout(400);
    expect(await page.locator(`${S.selectDays} option[disabled]`).count()).toBe(0);
  });

  // Same-facet counterfactual: picking tag A must not grey tag B, because B's
  // availability is judged as if the tag filter were not set.
  test('selecting a tag leaves its own menu availability unchanged', async ({ page }) => {
    const availability = (sel) => page.locator(`${sel} option`).evaluateAll(
      (opts) => opts.map((o) => [o.value, o.disabled]));
    const tagsBefore = await availability(S.selectTags);
    const tagOption = await page.locator(`${S.selectTags} option:not([value="all"]):not([disabled])`).first().getAttribute('value');
    if (!tagOption) return test.skip();
    await page.selectOption(S.selectTags, tagOption);
    await page.waitForTimeout(400);
    expect(await availability(S.selectTags)).toEqual(tagsBefore);
    await page.selectOption(S.selectTags, 'all');
    await page.waitForTimeout(300);
    const roomsBefore = await availability(S.selectRooms);
    const roomOption = await page.locator(`${S.selectRooms} option:not([value="all"]):not([disabled])`).first().getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(400);
    expect(await availability(S.selectRooms)).toEqual(roomsBefore);
  });

  // Globally unused values are absent; only event-owned values may appear.
  test('every menu option is owned by at least one event', async ({ page }) => {
    const orphans = await page.evaluate(() => {
      const items = Array.from(document.querySelectorAll('.schedule-item'))
        .filter((i) => i.dataset.osFallback !== 'true');
      const owned = { rooms: new Set(), tags: new Set() };
      for (const item of items) {
        for (const attr of item.attributes) {
          if (attr.name.startsWith('data-schedule-room-')) owned.rooms.add(attr.value);
          else if (attr.name.startsWith('data-schedule-tag')) owned.tags.add(String(attr.value));
        }
      }
      const orphanIn = (sel, set) => Array.from(
        document.querySelectorAll(`${sel} option`))
        .filter((o) => o.value !== 'all' && o.value !== 'Current' && !set.has(String(o.value)))
        .map((o) => o.textContent);
      return {
        rooms: orphanIn('#schedule-select-rooms', owned.rooms),
        tags: orphanIn('#schedule-select-tags', owned.tags),
      };
    });
    expect(orphans.rooms).toEqual([]);
    expect(orphans.tags).toEqual([]);
  });

  // The selection must not disable out from under the user.
  test('a selected room stays enabled when a search empties it', async ({ page }) => {
    const roomOption = await page.locator(`${S.selectRooms} option:not([value="all"])`).last().getAttribute('value');
    if (!roomOption) return test.skip();
    await page.selectOption(S.selectRooms, roomOption);
    await page.waitForTimeout(300);
    await page.fill(S.searchInput, 'Opening Howl');
    await page.waitForTimeout(400);
    await expect(page.locator(S.selectRooms)).toHaveValue(roomOption);
    const selectedDisabled = await page.locator(
      `${S.selectRooms} option[value="${roomOption}"]`).getAttribute('disabled');
    expect(selectedDisabled).toBeNull();
  });
});
