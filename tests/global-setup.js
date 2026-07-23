// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
//
// Pre-flight: abort before any spec runs unless the SPECIFIC seed fixtures
// the browser specs depend on are present — not merely "some events exist".
//
// A weak "count > 0" gate lets a broken or partial seed through (e.g. the
// Essential-tag assignment silently failing, badge_type term meta missing,
// or a reseed interrupted mid-run) and then produces a wall of unrelated
// spec failures/skips with no single, clear cause. This checks the exact
// fixtures the specs are written against, derived from the seed scripts
// themselves (the single source of truth — see tests/README.md "Seed Data"):
//
//   - vanilla-wp, furry-wp, and every other project (the shared Furry
//     Migration reference stack) all seed via
//     tests/fixtures/seed-test-events.sh, whose 12 named events are the
//     authoritative fixture set (that script's own idempotency check already
//     keys off these same titles). Four of them carry the "Essential" tag
//     that 02-tabs/07-hash-routing depend on; one each carry the
//     Cancelled/Restricted/Sensory/VIP tags that 03-filters/11-badges depend
//     on. Presence is checked both by exact title AND by the
//     `schedule-tag-{slug}` class the frontend renders per assigned tag
//     (lib/render.php), so a title-only match can't hide a broken tag
//     assignment.
//   - gaming-wp seeds via tests/docker-gaming/seed-gaming.sh instead: 500
//     randomly-titled events across 20 rooms and a fixed tag pool, with no
//     fixed titles to check. Mirrors the exact thresholds
//     tests/e2e/16-gaming-demo.spec.js itself asserts (20 rooms, >=10 tags,
//     >=400 events), so a broken/partial gaming seed is caught here instead
//     of failing that spec with a less specific message.
const { chromium } = require('@playwright/test');
const selectors = require('./helpers/selectors');

const PROJECT_URLS = {
  'vanilla-wp': 'http://localhost:8081/schedule/',
  'furry-wp': 'http://localhost:8082/schedule/',
  'gaming-wp': 'http://localhost:8083/schedule/',
};

const DEFAULT_URL = 'https://furrymigration.local/schedule/';

const PROJECT_FIX_COMMANDS = {
  'vanilla-wp': 'cd tests/docker-vanilla && ./seed-vanilla.sh',
  'furry-wp': 'cd tests/docker-furry && ./seed-furry.sh',
  'gaming-wp': 'cd tests/docker-gaming && ./seed-gaming.sh',
};
const DEFAULT_FIX_COMMAND = 'npm run test:seed';

// The 12 event titles tests/fixtures/seed-test-events.sh creates (also that
// script's own --force reseed/idempotency list). Authoritative for every
// project except gaming-wp.
const SEED_TEST_EVENTS_TITLES = [
  'Opening Howl Ceremony',
  'Fursuit Parade Staging',
  'Intro to Paw Art',
  'Coyote vs Raccoon Dance-Off',
  "Writing Your Fursona's Story",
  'Dealers Den Guided Tour',
  'Napping in the Raccoon Lounge',
  'Charity Auction for Critter Rescue',
  'Closing Howl and Dead Dog',
  'After Dark Howl',
  'Quiet Paws Chill Zone',
  'VIP Tail Care Lounge',
];

// Minimum visible-event count per tag slug that same seed script's
// badge-testing events establish. schedule-tag-{slug} is the class
// lib/render.php adds per assigned os_tag term — this is what
// 02-tabs/03-filters/07-hash-routing/11-badges actually key off, not just an
// event existing somewhere with that tag's name.
const SEED_TEST_EVENTS_TAG_COUNTS = {
  essential: 4, // Opening Howl Ceremony, Coyote vs Raccoon Dance-Off, Charity Auction for Critter Rescue, Closing Howl and Dead Dog
  cancelled: 1, // Napping in the Raccoon Lounge
  restricted: 1, // After Dark Howl
  sensory: 1, // Quiet Paws Chill Zone
  vip: 1, // VIP Tail Care Lounge
};

const GAMING_MIN_ROOMS = 20;
const GAMING_MIN_TAGS = 10;
const GAMING_MIN_EVENTS = 400;

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

function getPreflightTargets() {
  const projects = getSelectedProjects();
  const names = projects.length ? projects : ['__default__'];
  const seenUrls = new Set();
  const targets = [];

  for (const project of names) {
    const url = PROJECT_URLS[project] || DEFAULT_URL;
    if (seenUrls.has(url)) {
      continue;
    }
    seenUrls.add(url);
    targets.push({
      project,
      url,
      isGaming: project === 'gaming-wp',
      fixCommand: PROJECT_FIX_COMMANDS[project] || DEFAULT_FIX_COMMAND,
    });
  }

  return targets;
}

// WordPress's wptexturize() renders a straight apostrophe in a post_title as
// a curly one (and would do the same for straight quotes / spaced hyphens),
// so "Writing Your Fursona's Story" comes back from the DOM as "...Fursona’s
// Story". Normalize both sides before comparing rather than hand-picking
// Unicode escapes, so any future title with an apostrophe/quote/dash is
// still matched correctly.
function normalizeTitle(text) {
  return text
    .replace(/[‘’]/g, "'")
    .replace(/[“”]/g, '"')
    .replace(/[–—]/g, '-')
    .replace(/\s+/g, ' ')
    .trim();
}

function fail(lines) {
  console.error('\n' + lines.join('\n') + '\n');
  process.exit(1);
}

async function verifySeedTestEventsFixtures(page, target) {
  const rawTitles = await page.locator(selectors.scheduleTitle).allTextContents();
  const normalizedTitles = rawTitles.map(normalizeTitle);

  const missingTitles = SEED_TEST_EVENTS_TITLES.filter(
    (title) => !normalizedTitles.includes(normalizeTitle(title))
  );

  const tagIssues = [];
  for (const [slug, expectedMin] of Object.entries(SEED_TEST_EVENTS_TAG_COUNTS)) {
    const actual = await page.locator(`${selectors.scheduleItem}.schedule-tag-${slug}:visible`).count();
    if (actual < expectedMin) {
      tagIssues.push(`  - tag "${slug}" (schedule-tag-${slug}): expected >= ${expectedMin} visible event(s), found ${actual}`);
    }
  }

  if (missingTitles.length === 0 && tagIssues.length === 0) {
    return;
  }

  const lines = [`Preflight FAILED for ${target.url}: required seed fixtures are missing or incomplete.`];
  if (missingTitles.length) {
    lines.push('Missing known seed event titles (from tests/fixtures/seed-test-events.sh):');
    missingTitles.forEach((title) => lines.push(`  - "${title}"`));
  }
  if (tagIssues.length) {
    lines.push('Missing/incomplete tag-based fixtures (specs key off these classes, not just any event with the tag):');
    lines.push(...tagIssues);
  }
  lines.push(`Fix: reseed this environment — ${target.fixCommand}`);
  lines.push('See tests/README.md "Seed Data" if this project maps to a different environment than expected.');
  fail(lines);
}

async function verifyGamingFixtures(page, target) {
  const roomCount = await page.locator(`${selectors.selectRooms} option:not([value="all"])`).count();
  const tagCount = await page.locator(`${selectors.selectTags} option:not([value="all"])`).count();
  const eventCount = await page.locator(`${selectors.scheduleItem}:visible`).count();

  const issues = [];
  if (roomCount < GAMING_MIN_ROOMS) {
    issues.push(`  - rooms: expected >= ${GAMING_MIN_ROOMS}, found ${roomCount}`);
  }
  if (tagCount < GAMING_MIN_TAGS) {
    issues.push(`  - tags: expected >= ${GAMING_MIN_TAGS}, found ${tagCount}`);
  }
  if (eventCount < GAMING_MIN_EVENTS) {
    issues.push(`  - events: expected >= ${GAMING_MIN_EVENTS}, found ${eventCount}`);
  }

  if (issues.length === 0) {
    return;
  }

  fail([
    `Preflight FAILED for ${target.url}: the high-load gaming seed is missing or incomplete (tests/e2e/16-gaming-demo.spec.js requires all of these).`,
    ...issues,
    `Fix: reseed this environment — ${target.fixCommand}`,
  ]);
}

module.exports = async () => {
  const browser = await chromium.launch();

  try {
    for (const target of getPreflightTargets()) {
      const page = await browser.newPage({ ignoreHTTPSErrors: true });

      try {
        await page.goto(target.url, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector(selectors.schedule, { state: 'visible', timeout: 15000 });
        await page.selectOption(selectors.selectDays, 'all');
        await page.waitForTimeout(500);

        const count = await page.locator(`${selectors.scheduleItem}:visible`).count();
        if (count === 0) {
          fail([
            `No schedule events found at ${target.url}.`,
            `Fix: reseed this environment — ${target.fixCommand}`,
          ]);
        }

        if (target.isGaming) {
          await verifyGamingFixtures(page, target);
        } else {
          await verifySeedTestEventsFixtures(page, target);
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
};
