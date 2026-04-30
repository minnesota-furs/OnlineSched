# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/05-modals.spec.js >> 05 — Modals >> Schedule Event Modal >> opens when clicking an event title
- Location: tests/e2e/05-modals.spec.js:74:5

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/schedule/", waiting until "load"

```

# Test source

```ts
  1   | // @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
  2   | const { test, expect } = require('@playwright/test');
  3   | const S = require('../helpers/selectors');
  4   | 
  5   | test.describe('05 — Modals', () => {
  6   |   test.beforeEach(async ({ page }) => {
> 7   |     await page.goto('/schedule/');
      |                ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  8   |     await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
  9   |     await page.selectOption(S.selectDays, 'all');
  10  |     await page.waitForTimeout(300);
  11  |   });
  12  | 
  13  |   test.describe('Login Modal', () => {
  14  |     test('opens on login button click', async ({ page }) => {
  15  |       await page.click(S.loginModalBtn);
  16  |       await page.waitForTimeout(300);
  17  |       await expect(page.locator(S.loginModal)).toBeVisible();
  18  |       await expect(page.locator(S.loginModal)).toContainText('Login');
  19  |     });
  20  | 
  21  |     test('closes on close button click', async ({ page }) => {
  22  |       await page.click(S.loginModalBtn);
  23  |       await page.waitForTimeout(200);
  24  |       await page.click(S.loginModalClose);
  25  |       await page.waitForTimeout(300);
  26  |       await expect(page.locator(S.loginModal)).toBeHidden();
  27  |     });
  28  | 
  29  |     test('closes on Escape key', async ({ page }) => {
  30  |       await page.click(S.loginModalBtn);
  31  |       await page.waitForTimeout(200);
  32  |       await page.keyboard.press('Escape');
  33  |       await page.waitForTimeout(300);
  34  |       await expect(page.locator(S.loginModal)).toBeHidden();
  35  |     });
  36  |   });
  37  | 
  38  |   test.describe('Info Modal', () => {
  39  |     test('opens on info button click', async ({ page }) => {
  40  |       await page.click(S.infoModalBtn);
  41  |       await page.waitForTimeout(300);
  42  |       await expect(page.locator(S.infoModal)).toBeVisible();
  43  |       await expect(page.locator(S.infoModal)).toContainText('Favorites');
  44  |     });
  45  | 
  46  |     test('contains all four help sections', async ({ page }) => {
  47  |       await page.click(S.infoModalBtn);
  48  |       await page.waitForTimeout(300);
  49  |       const modalText = await page.locator(S.infoModal).textContent();
  50  |       expect(modalText).toContain('Favorites');
  51  |       expect(modalText).toContain('Login');
  52  |       expect(modalText).toContain('Calendar');
  53  |       expect(modalText).toContain('Share');
  54  |     });
  55  | 
  56  |     test('closes on close button click', async ({ page }) => {
  57  |       await page.click(S.infoModalBtn);
  58  |       await page.waitForTimeout(200);
  59  |       await page.click(S.infoModalClose);
  60  |       await page.waitForTimeout(300);
  61  |       await expect(page.locator(S.infoModal)).toBeHidden();
  62  |     });
  63  | 
  64  |     test('closes on Escape key', async ({ page }) => {
  65  |       await page.click(S.infoModalBtn);
  66  |       await page.waitForTimeout(200);
  67  |       await page.keyboard.press('Escape');
  68  |       await page.waitForTimeout(300);
  69  |       await expect(page.locator(S.infoModal)).toBeHidden();
  70  |     });
  71  |   });
  72  | 
  73  |   test.describe('Schedule Event Modal', () => {
  74  |     test('opens when clicking an event title', async ({ page }) => {
  75  |       await page.locator(S.scheduleTitle).first().click();
  76  |       await page.waitForTimeout(400);
  77  |       await expect(page.locator(S.scheduleModal)).toBeVisible();
  78  |     });
  79  | 
  80  |     test('modal title is populated', async ({ page }) => {
  81  |       await page.locator(S.scheduleTitle).first().click();
  82  |       await page.waitForTimeout(400);
  83  |       const title = await page.locator(S.scheduleModalTitle).textContent();
  84  |       expect(title?.trim().length).toBeGreaterThan(0);
  85  |     });
  86  | 
  87  |     test('modal contains date, time, and room fields', async ({ page }) => {
  88  |       await page.locator(S.scheduleTitle).first().click();
  89  |       await page.waitForTimeout(400);
  90  |       await expect(page.locator('#modal-schedule-date')).toBeVisible();
  91  |       await expect(page.locator('#modal-schedule-time')).toBeVisible();
  92  |       await expect(page.locator('#modal-schedule-room')).toBeVisible();
  93  |     });
  94  | 
  95  |     test('modal description is populated', async ({ page }) => {
  96  |       await page.locator(S.scheduleTitle).first().click();
  97  |       await page.waitForTimeout(400);
  98  |       const desc = await page.locator('#modal-schedule-description').textContent();
  99  |       expect(desc?.trim().length).toBeGreaterThan(0);
  100 |     });
  101 | 
  102 |     test('modal shows panelist name when present', async ({ page }) => {
  103 |       // Open modal for first event (Opening Howl Ceremony - has panelist "Kurst Hyperyote")
  104 |       await page.locator(S.scheduleTitle).first().click();
  105 |       await page.waitForTimeout(400);
  106 |       const panelistField = page.locator('#modal-schedule-panelist, #modal-schedule-panelists, .schedule-panelists');
  107 |       const count = await panelistField.count();
```