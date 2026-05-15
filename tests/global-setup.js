// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
// Pre-flight: abort if no schedule events exist in the DB.
const { chromium } = require('@playwright/test');

const PROJECT_URLS = {
  'vanilla-wp': 'http://localhost:8081/schedule/',
  'furry-wp': 'http://localhost:8082/schedule/',
  'gaming-wp': 'http://localhost:8083/schedule/',
};

function getSelectedProjects() {
  const projects = [];

  for (let i = 0; i < process.argv.length; i++) {
    const arg = process.argv[i];

    if (arg === '--project' && process.argv[i + 1]) {
      projects.push(process.argv[i + 1]);
      i++;
      continue;
    }

    if (arg.startsWith('--project=')) {
      projects.push(arg.slice('--project='.length));
    }
  }

  return projects;
}

function getPreflightUrls() {
  const projects = getSelectedProjects();
  const urls = projects
    .map(project => PROJECT_URLS[project] || 'https://furrymigration.local/schedule/')
    .filter((url, index, allUrls) => allUrls.indexOf(url) === index);

  return urls.length ? urls : ['https://furrymigration.local/schedule/'];
}

module.exports = async () => {
  const browser = await chromium.launch();

  try {
    for (const url of getPreflightUrls()) {
      const page = await browser.newPage({ ignoreHTTPSErrors: true });

      try {
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#schedule', { state: 'visible', timeout: 15000 });
        await page.selectOption('#schedule-select-days', 'all');
        await page.waitForTimeout(500);

        const count = await page.locator('.schedule-item:visible').count();

        if (count === 0) {
          console.error(`\nNo schedule events found at ${url}. Run the matching seed script.\n`);
          process.exit(1);
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
};
