// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const path = require('path');
const S = require('../helpers/selectors');

const hoursOpenNowScript = path.resolve(__dirname, '../../src/js/hoursOpenNow.js');

async function renderOpenNow(page, now, start, end) {
  await page.goto('about:blank');
  await page.setContent(`
    <div class="os-hours" data-timezone="America/Chicago"
      data-operational-start="${start}" data-operational-end="${end}">
      <section class="os-hours__dept">
        <dl class="os-hours__days">
          <dt>Thursday</dt>
          <dd><span class="os-hours__time" data-start="09:00" data-end="17:00">9am - 5pm</span></dd>
        </dl>
      </section>
    </div>
  `);
  await page.evaluate((timestamp) => {
    const NativeDate = Date;
    window.Date = class extends NativeDate {
      constructor(...args) {
        super(...(args.length ? args : [timestamp]));
      }

      static now() {
        return timestamp;
      }
    };
  }, new Date(now).getTime());
  await page.addScriptTag({ path: hoursOpenNowScript });
}

async function openHoursTab(page) {
  await page.goto('/schedule/#tab=hours');
  await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  await page.locator(`${S.tabList} a[href="#hours"]`).click();
  await expect(page.locator(S.tabHours)).toBeVisible();
}

async function skipIfHoursNotMigrated(page) {
  const hoursCount = await page.locator(S.hoursBlock).count();
  test.skip(hoursCount === 0, 'Configured Hours page has not been migrated to OnlineSched native blocks yet.');
}

test.describe('13 — Hours block', () => {
  test('renders native Hours markup without Bootstrap grid', async ({ page }) => {
    await openHoursTab(page);
    await skipIfHoursNotMigrated(page);

    await expect(page.locator(S.hoursBlock)).toBeVisible();
    await expect(page.locator(S.hoursDepartment).first()).toBeVisible();
    await expect(page.locator(S.hoursDay).first()).toBeVisible();
    await expect(page.locator(S.hoursTimes).first()).toBeVisible();

    const bootstrapGridCount = await page.locator(`${S.hoursBlock} [class*="col-"], ${S.hoursBlock} .row`).count();
    expect(bootstrapGridCount).toBe(0);
  });

  test('uses one column on narrow screens', async ({ page }, testInfo) => {
    test.skip(testInfo.project.use.viewport.width > 767, 'Mobile-only Hours layout assertion.');

    await openHoursTab(page);
    await skipIfHoursNotMigrated(page);

    const columnCount = await page.locator(S.hoursRow).evaluate((row) => {
      return getComputedStyle(row).gridTemplateColumns.split(' ').filter(Boolean).length;
    });

    expect(columnCount).toBe(1);
  });
});

test.describe('Hours open-now operational window', () => {
  test('does not highlight a matching weekday before the convention', async ({ page }) => {
    await renderOpenNow(page, '2026-08-13T17:00:00Z', '2026-09-10', '2026-09-14');

    await expect(page.locator('.os-hours__open')).toHaveCount(0);
  });

  test('highlights a matching weekday during the convention', async ({ page }) => {
    await renderOpenNow(page, '2026-09-10T17:00:00Z', '2026-09-10', '2026-09-14');

    await expect(page.locator('.os-hours__open')).toBeVisible();
  });

  test('does not highlight when the operational dates are missing', async ({ page }) => {
    await renderOpenNow(page, '2026-09-10T17:00:00Z', '', '');

    await expect(page.locator('.os-hours__open')).toHaveCount(0);
  });
});
