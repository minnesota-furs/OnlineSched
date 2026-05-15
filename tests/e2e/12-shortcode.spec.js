// Phase 7 — Schedule Shortcode embedding
//
// This spec exercises the `[onlinesched_schedule]` shortcode path end-to-end, separate
// from the dedicated /schedule/ page-template path covered by 01-page-loads.spec.js.
//
// Setup expectation:
//   tests/fixtures/seed-test-events.sh (or a sibling helper) must create a Page at
//   slug `/shortcode-embed-test/` whose post_content is:
//
//       <h2>Embed Test Heading</h2>
//       <p>Lead paragraph above the schedule.</p>
//       [onlinesched_schedule]
//       <p>Footer paragraph below the schedule.</p>
//
//   The heading and paragraphs prove the page chrome was preserved (the shortcode
//   did not take over the whole page the way the page template does). If the seed
//   does not set this up, the suite will skip these tests with a clear message.
//
// All tests below are stubs (`test.fixme` / `test.skip`) until the seed lands.
// Drop the .fixme markers as each assertion is implemented.

const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

const EMBED_PATH = '/shortcode-embed-test/';

test.describe('12 — Shortcode Embedding', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(EMBED_PATH);
    // The shortcode-embedded schedule uses the same #schedule selector as the page-template
    // path. If the seed page is missing, this wait will time out — that's the intended
    // failure mode (loud, not silent).
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
      // Login modal is rendered once at body scope — verify it is not nested inside
      // the embed, which would break the focus trap if the page chrome scrolls.
      const modalCount = await page.locator(S.loginModal).count();
      expect(modalCount).toBe(1);
    });

    test.fixme('favorites still toggle on shortcode-embedded events', async ({ page }) => {
      // Favorites are not gated by render path — they should work the same way
      // the dedicated /schedule/ page does. See 04-favorites.spec.js for the
      // equivalent assertions.
      const firstEvent = page.locator(S.scheduleItem).first();
      await firstEvent.locator(S.favoriteToggle).click();
      await expect(firstEvent).toHaveAttribute('data-favorite', 'true');
    });
  });

  test.describe('Hours-tab recursion guard', () => {
    test.fixme('schedule does not render when its own page is the Hours source', async ({ page }) => {
      // Setup: configure onlinesched_hours_page_id to point at the shortcode-embed
      // page itself, then reload. The render function's static $depth guard should
      // emit the os-notice--recursion div for the inner render and not blow the
      // stack.
      //
      // This test depends on a WP-CLI helper to flip the option, run the assertion,
      // then restore the original value. Land that helper before un-fixme-ing.
      await expect(page.locator('.os-notice--recursion')).toBeVisible();
    });
  });

  test.describe('Multi-shortcode constraint (documented limitation)', () => {
    // Per README.md "Limitations": only one [onlinesched_schedule] is supported per
    // page. These tests guard the documented behavior — they do NOT assert that
    // multi-instance works. If a future phase adds multi-instance support, flip the
    // assertions accordingly.
    test.fixme('a second shortcode on the same page collides on #schedule id', async ({ page }) => {
      // Seed a page at /shortcode-double-embed-test/ with two [onlinesched_schedule]
      // blocks, then assert that document.querySelectorAll('#schedule').length === 2
      // (HTML allows it, but it is invalid). This test exists to lock in the
      // limitation so a silent fix that breaks deep links is caught.
      await page.goto('/shortcode-double-embed-test/');
      await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
      const count = await page.locator(S.schedule).count();
      expect(count).toBe(2); // expected collision until multi-instance support is added
    });
  });
});
