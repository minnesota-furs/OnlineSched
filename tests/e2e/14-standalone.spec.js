// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Validates OnlineSched is independent of the Furry Migration theme.
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

test.describe('14 — Standalone Verification', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  });

  test('no theme-specific stylesheets enqueued (vanilla only)', async ({ page }) => {
    test.skip(test.info().project.name !== 'vanilla-wp', 'Only runs on vanilla-wp project');

    const urls = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(link => link.getAttribute('href'))
        .filter(Boolean)
    );

    const fmThemeUrls = urls.filter(url => url.includes('furry-migration') || url.includes('mnfurs'));
    expect(fmThemeUrls, `Found theme leaks: ${fmThemeUrls.join(', ')}`).toHaveLength(0);
  });

  test('no theme-specific scripts enqueued (vanilla only)', async ({ page }) => {
    test.skip(test.info().project.name !== 'vanilla-wp', 'Only runs on vanilla-wp project');

    const srcs = await page.evaluate(() =>
      Array.from(document.scripts)
        .map(script => script.getAttribute('src'))
        .filter(Boolean)
    );

    const fmThemeSrcs = srcs.filter(src => src.includes('furry-migration') || src.includes('mnfurs'));
    expect(fmThemeSrcs, `Found script leaks: ${fmThemeSrcs.join(', ')}`).toHaveLength(0);
  });

  test('schedule page does not include theme-specific wrapper markup', async ({ page }) => {
    // Exclude the vanilla wp-block-library and theme-specific block styles if they use these classes.
    // We are looking for the Phase 6/8 leaks: title-left, title-right, hours-of-operations (the old class)
    const leaks = await page.evaluate(() => {
      return document.querySelectorAll('.title-left, .title-right, .hours-of-operations').length;
    });

    if (test.info().project.name === 'vanilla-wp') {
      expect(leaks).toBe(0);
    }
  });

  test('event modal opens and closes without theme JS', async ({ page }) => {
    await page.locator(S.selectDays).selectOption({ label: 'All Days' });
    await page.locator(`${S.scheduleItem}:visible ${S.scheduleTitle}`).first().click();
    await expect(page.locator(S.scheduleModal)).toBeVisible();
    await expect(page).toHaveURL(/evt=/);

    await page.locator(S.scheduleModalClose).click();
    await expect(page.locator(S.scheduleModal)).toBeHidden();
    await expect(page).not.toHaveURL(/evt=/);
  });

  test('login modal opens and closes without theme JS', async ({ page }) => {
    await page.locator(S.loginModalBtn).click();
    await expect(page.locator(S.loginModal)).toBeVisible();
    await page.locator(S.loginModalClose).click();
    await expect(page.locator(S.loginModal)).toBeHidden();
  });

  test('info modal opens and closes without theme JS', async ({ page }) => {
    await page.locator(S.infoModalBtn).click();
    await expect(page.locator(S.infoModal)).toBeVisible();
    await page.locator(S.infoModalClose).click();
    await expect(page.locator(S.infoModal)).toBeHidden();
  });

  test('body class includes standard-schedule', async ({ page }) => {
    const body = page.locator('body');
    await expect(body).toHaveClass(/standard-schedule/);
  });

  test('partial override regression test (vanilla only)', async ({ page }) => {
    test.skip(test.info().project.name !== 'vanilla-wp', 'Only runs on vanilla-wp project');

    const container = 'onlinesched-vanilla-wp';
    const themePath = '/var/www/html/wp-content/themes/twentytwentyfive';
    const overrideDir = `${themePath}/onlinesched/partials`;
    const overrideFile = `${overrideDir}/schedule-tabs.php`;
    const sentinel = '<!-- partial-override-active -->';

    const { execSync } = require('child_process');

    try {
      // Create override
      execSync(`docker exec ${container} mkdir -p ${overrideDir}`);
      execSync(`docker exec ${container} bash -c "echo '${sentinel}' > ${overrideFile}"`);

      await page.reload();
      await page.waitForSelector(S.schedule);

      const content = await page.content();
      expect(content).toContain(sentinel);

      // Remove override
      execSync(`docker exec ${container} rm ${overrideFile}`);

      await page.reload();
      await page.waitForSelector(S.schedule);

      const contentAfter = await page.content();
      expect(contentAfter).not.toContain(sentinel);
      await expect(page.locator(S.tabList)).toBeVisible();

    } finally {
      // Cleanup just in case
      try { execSync(`docker exec ${container} rm -f ${overrideFile}`); } catch (e) {}
    }
  });
});
