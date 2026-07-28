/**
 * Schedule shortcode embedding.
 *
 * This spec exercises the `[onlinesched_schedule]` shortcode path end-to-end, separate
 * from the dedicated /schedule/ page-template path covered by 01-page-loads.spec.js.
 *
 * Setup expectation:
 *   tests/fixtures/seed-test-events.sh (or a sibling helper) must create a Page at
 *   slug `/shortcode-embed-test/` whose post_content is:
 *
 *       <h2>Embed Test Heading</h2>
 *       <p>Lead paragraph above the schedule.</p>
 *       [onlinesched_schedule]
 *       <p>Footer paragraph below the schedule.</p>
 *
 *   The heading and paragraphs prove the page chrome was preserved (the shortcode
 *   did not take over the whole page the way the page template does). If the seed
 *   does not set this up, the suite will skip these tests with a clear message.
 *
 * All tests below are stubs (`test.fixme` / `test.skip`) until the seed lands.
 * Drop the .fixme markers as each assertion is implemented.
 */

const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

const EMBED_PATH = '/shortcode-embed-test/';

test.describe('12 — Shortcode Embedding', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(EMBED_PATH);
    // A missing seed page times this out, which is the intended loud failure.
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test.describe('Page chrome preservation', () => {
    test.fixme('embed page heading is visible above the schedule', async ({ page }) => {
      // The shortcode must NOT replace the whole page. The host page's <h2> heading and
      // surrounding paragraphs should still render around the embedded schedule.
      await expect(page.locator('h2', { hasText: 'Embed Test Heading' })).toBeVisible();
      await expect(page.getByText('Lead paragraph above the schedule.')).toBeVisible();
      await expect(page.getByText('Footer paragraph below the schedule.')).toBeVisible();
    });

    test.fixme('embedded schedule renders inside the page body', async ({ page }) => {
      const items = await page.locator(S.scheduleItem).count();
      expect(items).toBeGreaterThan(0);
    });

    test.fixme('embedded schedule does NOT use the kiosk-only header template', async ({ page }) => {
      // header-schedule.php should only fire for the dedicated kiosk page, never the
      // shortcode path. If a Typekit URL or kiosk-specific class leaked, fail loud.
      const html = await page.content();
      expect(html).not.toContain('header-schedule');
    });
  });

  test.describe('Interactive features inside shortcode', () => {
    test.fixme('tabs switch correctly', async ({ page }) => {
      await page.click(`${S.tabLinks}[href="#hours"]`);
      await page.waitForTimeout(300);
      await expect(page.locator(S.tabHours)).toHaveClass(/os-tab-pane--active/);
    });

    test.fixme('search filter works', async ({ page }) => {
      const before = await page.locator(`${S.scheduleItem}:visible`).count();
      await page.fill(S.searchInput, 'Coyote');
      await page.waitForTimeout(400);
      const after = await page.locator(`${S.scheduleItem}:visible`).count();
      expect(after).toBeGreaterThan(0);
      expect(after).toBeLessThan(before);
    });

    test.fixme('event modal opens from a row inside the shortcode', async ({ page }) => {
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(300);
      await expect(page.locator(S.scheduleModal)).toBeVisible();
    });

    test.fixme('login modal still appends to the page body, not inside the shortcode', async ({ page }) => {
      // Login modal is rendered once at body scope - verify it is not nested inside
      // the embed, which would break the focus trap if the page chrome scrolls.
      const modalCount = await page.locator(S.loginModal).count();
      expect(modalCount).toBe(1);
    });

    test.fixme('favorites still toggle on shortcode-embedded events', async ({ page }) => {
      // Favorites are not gated by render path; 04-favorites.spec.js holds the
      // equivalent assertions.
      const firstEvent = page.locator(S.scheduleItem).first();
      await firstEvent.locator(S.favoriteToggle).click();
      await expect(firstEvent).toHaveAttribute('data-favorite', 'true');
    });
  });

  test.describe('Hours-tab recursion guard', () => {
    test.fixme('schedule does not render when its own page is the Hours source', async ({ page }) => {
      // Points the hours page at itself so the render depth guard emits the
      // recursion notice. Needs a WP-CLI helper to flip and restore the option.
      await expect(page.locator('.os-notice--recursion')).toBeVisible();
    });
  });

  test.describe('Multi-shortcode constraint (documented limitation)', () => {
    // Guards the one-shortcode-per-page limitation documented in README.md,
    // rather than asserting that multi-instance works.
    test.fixme('a second shortcode on the same page collides on #schedule id', async ({ page }) => {
      // Two shortcodes produce two #schedule ids, which HTML allows and is
      // invalid. Locked in so a silent fix breaking deep links is caught.
      await page.goto('/shortcode-double-embed-test/');
      await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
      const count = await page.locator(S.schedule).count();
      expect(count).toBe(2); // expected collision until multi-instance support is added
    });
  });
});
