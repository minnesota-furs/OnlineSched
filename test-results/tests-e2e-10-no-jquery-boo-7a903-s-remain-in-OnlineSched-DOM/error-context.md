# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/10-no-jquery-bootstrap.spec.js >> 10 — No jQuery / Bootstrap (Phase 6+) >> no Bootstrap col-md- classes remain in OnlineSched DOM
- Location: tests/e2e/10-no-jquery-bootstrap.spec.js:83:3

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/schedule/", waiting until "load"

```

# Test source

```ts
  1   | // @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
  2   | // Validates OnlineSched no longer emits Bootstrap/jQuery markup or assets.
  3   | // The host theme may still expose jQuery for its own Bootstrap menu.
  4   | const { test, expect } = require('@playwright/test');
  5   | const S = require('../helpers/selectors');
  6   | 
  7   | test.describe('10 — No jQuery / Bootstrap (Phase 6+)', () => {
  8   | 
  9   |   test.beforeEach(async ({ page }) => {
> 10  |     await page.goto('/schedule/');
      |                ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  11  |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  12  |   });
  13  | 
  14  |   test('no Bootstrap requires jQuery console error', async ({ page }) => {
  15  |     const errors = [];
  16  |     page.on('pageerror', error => errors.push(error.message));
  17  |     page.on('console', msg => {
  18  |       if (msg.type() === 'error') errors.push(msg.text());
  19  |     });
  20  | 
  21  |     await page.goto('/schedule/');
  22  |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  23  | 
  24  |     expect(errors.filter(error => error.includes("Bootstrap's JavaScript requires jQuery"))).toHaveLength(0);
  25  |   });
  26  | 
  27  |   test('no OnlineSched-owned jQuery script tag', async ({ page }) => {
  28  |     const count = await page.evaluate(() =>
  29  |       Array.from(document.scripts).filter(script => {
  30  |         const src = script.getAttribute('src') || '';
  31  |         return src.includes('OnlineSched') && src.toLowerCase().includes('jquery');
  32  |       }).length
  33  |     );
  34  |     expect(count).toBe(0);
  35  |   });
  36  | 
  37  |   test('no OnlineSched-owned Bootstrap asset tag', async ({ page }) => {
  38  |     const count = await page.evaluate(() =>
  39  |       Array.from(document.querySelectorAll('script[src], link[href]')).filter(el => {
  40  |         const url = el.getAttribute('src') || el.getAttribute('href') || '';
  41  |         return url.includes('OnlineSched') && url.toLowerCase().includes('bootstrap');
  42  |       }).length
  43  |     );
  44  |     expect(count).toBe(0);
  45  |   });
  46  | 
  47  |   // Col-* checks are scoped to OnlineSched-owned markup (#schedule + dialogs).
  48  |   // Use regex /^col-XX-/ to match Bootstrap classes exactly — avoids false-positive
  49  |   // hits on os-col-xs-* etc. (class* substring match would catch those too).
  50  |   // Excluded from scope (not plugin-owned):
  51  |   //   .schedule-description / #modal-schedule-description — user-generated WP post content
  52  |   //   #footer — theme footer rendered inside #schedule by get_footer()
  53  |   test('no Bootstrap col-xs- classes remain in OnlineSched DOM', async ({ page }) => {
  54  |     const count = await page.evaluate((S) => {
  55  |       const re = /^col-xs-/;
  56  |       const exclusions = `${S.scheduleDescription}, ${S.theme.modalDescription}, ${S.theme.footer}`;
  57  |       return [
  58  |         ...document.querySelectorAll('#schedule *'),
  59  |         ...document.querySelectorAll('dialog.os-modal *'),
  60  |       ].filter(el =>
  61  |         !el.closest(exclusions) &&
  62  |         Array.from(el.classList).some(c => re.test(c))
  63  |       ).length;
  64  |     }, S);
  65  |     expect(count).toBe(0);
  66  |   });
  67  | 
  68  |   test('no Bootstrap col-sm- classes remain in OnlineSched DOM', async ({ page }) => {
  69  |     const count = await page.evaluate((S) => {
  70  |       const re = /^col-sm-/;
  71  |       const exclusions = `${S.scheduleDescription}, ${S.theme.modalDescription}, ${S.theme.footer}`;
  72  |       return [
  73  |         ...document.querySelectorAll('#schedule *'),
  74  |         ...document.querySelectorAll('dialog.os-modal *'),
  75  |       ].filter(el =>
  76  |         !el.closest(exclusions) &&
  77  |         Array.from(el.classList).some(c => re.test(c))
  78  |       ).length;
  79  |     }, S);
  80  |     expect(count).toBe(0);
  81  |   });
  82  | 
  83  |   test('no Bootstrap col-md- classes remain in OnlineSched DOM', async ({ page }) => {
  84  |     const count = await page.evaluate((S) => {
  85  |       const re = /^col-md-/;
  86  |       const exclusions = `${S.scheduleDescription}, ${S.theme.modalDescription}, ${S.theme.footer}`;
  87  |       return [
  88  |         ...document.querySelectorAll('#schedule *'),
  89  |         ...document.querySelectorAll('dialog.os-modal *'),
  90  |       ].filter(el =>
  91  |         !el.closest(exclusions) &&
  92  |         Array.from(el.classList).some(c => re.test(c))
  93  |       ).length;
  94  |     }, S);
  95  |     expect(count).toBe(0);
  96  |   });
  97  | 
  98  |   test('no Bootstrap col-lg- classes remain in OnlineSched DOM', async ({ page }) => {
  99  |     const count = await page.evaluate((S) => {
  100 |       const re = /^col-lg-/;
  101 |       const exclusions = `${S.scheduleDescription}, ${S.theme.modalDescription}, ${S.theme.footer}`;
  102 |       return [
  103 |         ...document.querySelectorAll('#schedule *'),
  104 |         ...document.querySelectorAll('dialog.os-modal *'),
  105 |       ].filter(el =>
  106 |         !el.closest(exclusions) &&
  107 |         Array.from(el.classList).some(c => re.test(c))
  108 |       ).length;
  109 |     }, S);
  110 |     expect(count).toBe(0);
```