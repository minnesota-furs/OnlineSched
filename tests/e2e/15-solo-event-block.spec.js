// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Lightweight smoke for the solo-event block demo page.
const { test, expect } = require('@playwright/test');

test('solo-event block demo loads on furry project', async ({ page }) => {
  test.skip(test.info().project.name !== 'furry-wp', 'Only runs on furry-wp project');

  await page.goto('/solo-event-block-demo/', { waitUntil: 'domcontentloaded' });

  const cards = page.locator('.os-solo-event-card');
  await expect(cards).toHaveCount(2);

  const wrapperIds = await cards.evaluateAll((nodes) => nodes.map((node) => node.id));
  expect(new Set(wrapperIds).size).toBe(wrapperIds.length);

  const eventIds = await cards.evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-os-event-id')));
  expect(eventIds.every(Boolean)).toBe(true);
  expect(new Set(eventIds).size).toBe(eventIds.length);

  await expect(cards.locator('.os-solo-event-card__title a')).toHaveCount(0);
  await expect(cards.first().locator('.os-solo-event-card__description')).toBeVisible();
  await expect(cards.first().locator('.schedule-google')).toHaveCSS('text-decoration-line', 'none');
  await expect(cards.first().locator('.schedule-ical')).toHaveCSS('text-decoration-line', 'none');
});
