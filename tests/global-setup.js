// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Pre-flight: abort if no schedule events exist in the DB.
const { chromium } = require('@playwright/test');

module.exports = async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ ignoreHTTPSErrors: true });

  try {
    await page.goto('https://furrymigration.local/schedule/', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#schedule', { state: 'visible', timeout: 15000 });
    await page.selectOption('#schedule-select-days', 'all');
    await page.waitForTimeout(500);

    const count = await page.locator('.schedule-item:visible').count();

    if (count === 0) {
      console.error('\n⚠️  No schedule events found! Run: npm run test:seed\n');
      process.exit(1);
    }
    console.log(`✓ Found ${count} schedule events. Tests can proceed.`);
  } finally {
    await browser.close();
  }
};

