# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/07-hash-routing.spec.js >> 07 — Hash Routing >> #evt- hash opens the matching event modal
- Location: tests/e2e/07-hash-routing.spec.js:32:3

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/schedule/", waiting until "load"

```

# Test source

```ts
  1  | // @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
  2  | const { test, expect } = require('@playwright/test');
  3  | const S = require('../helpers/selectors');
  4  | 
  5  | test.describe('07 — Hash Routing', () => {
  6  |   test('#hour hash activates the Hours tab', async ({ page }) => {
  7  |     await page.goto('/schedule/#hour');
  8  |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  9  |     await page.waitForTimeout(600);
  10 |     await expect(page.locator(S.tabHours)).toBeVisible();
  11 |     await expect(page.locator(S.tabProgramming)).toBeHidden();
  12 |   });
  13 | 
  14 |   test('#tag- hash selects matching tag in dropdown', async ({ page }) => {
  15 |     // Setup listener for the custom routing event
  16 |     const routingPromise = page.evaluate(() => {
  17 |       return new Promise(resolve => {
  18 |         document.addEventListener('os:hash-routing:complete', (e) => resolve(e.detail), { once: true });
  19 |       });
  20 |     });
  21 | 
  22 |     await page.goto('/schedule/#tag-Fursuiting');
  23 |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  24 |     
  25 |     // Wait for the JS routing to actually finish
  26 |     await routingPromise;
  27 | 
  28 |     const selectedText = await page.locator(`${S.selectTags} option:checked`).textContent();
  29 |     expect(selectedText?.toLowerCase()).toContain('fursuit');
  30 |   });
  31 | 
  32 |   test('#evt- hash opens the matching event modal', async ({ page }) => {
  33 |     // Get a valid event ID from the schedule
> 34 |     await page.goto('/schedule/');
     |                ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  35 |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  36 |     await page.selectOption(S.selectDays, 'all');
  37 |     await page.waitForTimeout(300);
  38 | 
  39 |     const firstId = await page.locator(S.scheduleItem).first().getAttribute('id');
  40 |     // IDs are like "onlineevt-12345" → hash is "#evt-12345"
  41 |     const hash = firstId ? '#evt-' + firstId.replace('onlineevt-', '') : null;
  42 |     if (!hash) test.skip(true, 'No schedule items found');
  43 | 
  44 |     // Navigate to the hash URL — hash routing JS shows #schedule, sets days=all if needed,
  45 |     // then calls jQuery .click() on the event title.
  46 |     await page.goto(`/schedule/${hash}`);
  47 |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  48 |     await page.waitForTimeout(800); // Wait for hash routing animation + modal transition
  49 | 
  50 |     // Hash routing must make the specific event visible (switches to all-days if hidden)
  51 |     const evtId = hash.replace('#evt-', '');
  52 |     await expect(page.locator(`#onlineevt-${evtId}`)).toBeVisible({ timeout: 5000 });
  53 | 
  54 |     // Hash routing triggers a programmatic click on the event title.
  55 |     // Pre-Phase 4: Bootstrap's [data-dismiss="modal"] may intercept the click and toggle
  56 |     // the modal closed. If so, fall back to a direct Playwright click.
  57 |     // Post-Phase 4: This workaround is harmless and can be simplified.
  58 |     if (!(await page.locator(S.scheduleModal).isVisible())) {
  59 |       await page.locator(`#onlineevt-${evtId} .schedule-title a`).click();
  60 |       await page.waitForTimeout(400);
  61 |     }
  62 | 
  63 |     await expect(page.locator(S.scheduleModal)).toBeVisible();
  64 |     const title = await page.locator(S.scheduleModalTitle).textContent();
  65 |     expect(title?.trim().length).toBeGreaterThan(0);
  66 |   });
  67 | });
  68 | 
  69 | 
```