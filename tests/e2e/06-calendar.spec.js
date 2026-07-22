// @author kurst@mnfurs.org Kurst Hyperyote for Furry Migration
const { test, expect } = require('@playwright/test');
const S = require('../helpers/selectors');

const PHP_ERROR_PATTERN = /(?:PHP\s+(?:Deprecated|Fatal error|Notice|Parse error|Warning)|Deprecated:|Fatal error:|Notice:|Parse error:|Warning:)/i;
const SUBSCRIPTIONS_DISABLED_PARAM = 'onlinesched_test_calendar_subscriptions=disabled';

function withSubscriptionsDisabled(path) {
  const separator = path.includes('?') ? '&' : '?';
  return `${path}${separator}${SUBSCRIPTIONS_DISABLED_PARAM}`;
}

async function installClipboardShim(page) {
  await page.evaluate(() => {
    let clipboardText = '';
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: {
        writeText: async (text) => {
          clipboardText = String(text);
        },
        readText: async () => clipboardText,
      },
    });
  });
}

async function expectIcsResponse(response, options = {}) {
  const { minEvents = 1, exactEvents = null } = options;

  expect(response.ok()).toBeTruthy();
  expect(response.headers()['content-type']).toContain('text/calendar');

  const body = await response.text();
  expect(body.match(/^BEGIN:VCALENDAR\r?$/gm) || []).toHaveLength(1);
  expect(body.match(/^END:VCALENDAR\r?$/gm) || []).toHaveLength(1);
  expect(body).not.toMatch(PHP_ERROR_PATTERN);

  const events = body.match(/^BEGIN:VEVENT\r?$/gm) || [];
  if (exactEvents !== null) {
    expect(events).toHaveLength(exactEvents);
  } else {
    expect(events.length).toBeGreaterThanOrEqual(minEvents);
  }

  if (events.length > 0) {
    expect(body).toContain('SUMMARY:');
    expect(body).toContain('DESCRIPTION:');
  }

  return body;
}

async function expectDisabledAggregateResponse(response, forbiddenValues = []) {
  expect(response.status()).toBe(200);
  expect(response.headers()['cache-control']).toContain('no-store');
  expect(response.headers()['x-robots-tag']).toContain('noindex');
  expect(response.headers()['content-disposition']).toMatch(/\.ics/i);

  const body = await expectIcsResponse(response, { exactEvents: 0 });
  expect(body).not.toContain('UID:');
  expect(body).not.toContain('DTSTART');
  expect(body).not.toContain('DTEND');

  for (const value of forbiddenValues.filter(Boolean)) {
    expect(body).not.toContain(String(value));
  }

  return body;
}

function getIcsUids(body) {
  return (body.match(/^UID:.+\r?$/gm) || []).sort();
}

function getIcsEventSignatures(body) {
  const unfolded = body.replace(/\r?\n[ \t]/g, '');
  return (unfolded.match(/BEGIN:VEVENT\r?\n[\s\S]*?\r?\nEND:VEVENT/g) || [])
    .map((event) => event
      .split(/\r?\n/)
      .filter((line) => !line.startsWith('DTSTAMP:'))
      .join('\n'))
    .sort();
}

test.describe('06 — Calendar', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/schedule/');
    await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
    await page.selectOption(S.selectDays, 'all');
    await page.waitForTimeout(300);
  });

  test.describe('Modal Calendar Links', () => {
    test.beforeEach(async ({ page }) => {
      // Open first event modal
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator(S.scheduleModal)).toBeVisible();
    });

    test('Apple Calendar link contains webcal:// and ical.php', async ({ page }) => {
      const href = await page.locator(S.modalIcal).getAttribute('href');
      expect(href).toContain('webcal://');
      expect(href).toContain('ical.php');
    });

    test('Apple Calendar link contains event post ID', async ({ page }) => {
      const itemId = await page.locator(S.scheduleItem).first().getAttribute('id');
      const postId = itemId?.replace('onlineevt-', '');
      const href = await page.locator(S.modalIcal).getAttribute('href');
      expect(href).toContain(`cal-id=${postId}`);
    });

    test('Google Calendar link contains calendar.google.com', async ({ page }) => {
      const href = await page.locator(S.modalGoogle).getAttribute('href');
      expect(href).toContain('calendar.google.com');
    });
  });

  test.describe('Clipboard Copy', () => {
    test('clipboard copy spawns float-up effect element', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      const effect = page.locator(S.clipboardEffect);
      await expect(effect).toHaveCount(1);
    });

    test('clipboard effect element auto-removes after animation', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      // Wait for animation duration (1.5s + buffer)
      await page.waitForTimeout(2000);
      const count = await page.locator(S.clipboardEffect).count();
      expect(count).toBe(0);
    });

    test('clipboard effect contains "Copied" text', async ({ page }) => {
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      const effect = page.locator(S.clipboardEffect);
      await expect(effect).toContainText('Copied');
    });

    test('inline clipboard copies correct event URL to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      const firstItem = page.locator(S.scheduleItem).first();
      const itemId = await firstItem.getAttribute('id');
      const evtId = itemId?.replace('onlineevt-', '');

      await firstItem.locator(S.clipboard).click();
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('/schedule/');
      expect(clipText).toContain('evt=' + evtId);
    });

    test('modal copy button copies page URL to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      await page.locator(S.scheduleTitle).first().click();
      await page.waitForTimeout(400);
      await expect(page.locator(S.scheduleModal)).toBeVisible();

      await page.click(S.modalCopyUrl);
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('/schedule/');
      // Modal sets the hash to the event ID before showing
      expect(clipText).toContain('evt=');
    });

    test('prefers-reduced-motion skips clipboard animation', async ({ page }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.locator(S.clipboard).first().click();
      await page.waitForTimeout(300);
      // With reduced motion, the animation is skipped - no .clipboard-effect element created
      const count = await page.locator(S.clipboardEffect).count();
      expect(count).toBe(0);
    });

    test('prefers-reduced-motion still copies to clipboard', async ({ page }) => {
      await installClipboardShim(page);
      await page.emulateMedia({ reducedMotion: 'reduce' });

      const firstItem = page.locator(S.scheduleItem).first();
      const itemId = await firstItem.getAttribute('id');
      const evtId = itemId?.replace('onlineevt-', '');

      await firstItem.locator(S.clipboard).click();
      await page.waitForTimeout(300);

      const clipText = await page.evaluate(() => navigator.clipboard.readText());
      expect(clipText).toContain('evt=' + evtId);
    });
  });

  test.describe('Add to Calendar Section', () => {
    test('"Add to Calendar" section exists on /schedule/ page', async ({ page }) => {
      const section = page.locator(S.addToCalendarSection);
      const count = await section.count();
      expect(count).toBe(1);
    });
  });

  test.describe('ICS endpoints', () => {
    test('ical.php returns one valid event feed without PHP errors', async ({ page }) => {
      const firstItem = page.locator(S.scheduleItem).first();
      const itemId = await firstItem.getAttribute('id');
      expect(itemId).toMatch(/^onlineevt-\d+$/);

      const postId = itemId.replace('onlineevt-', '');
      const response = await page.request.get(`/wp-content/plugins/OnlineSched/ical.php?cal-id=${postId}`);

      const body = await expectIcsResponse(response, { exactEvents: 1 });
      const timezone = body.match(/^X-WR-TIMEZONE:(.+)\r?$/m)?.[1];
      const start = body.match(/^DTSTART:(\d{8}T\d{6}Z)\r?$/m)?.[1];

      expect(timezone).toBeTruthy();
      expect(start).toBeTruthy();

      const utcDate = new Date(
        `${start.slice(0, 4)}-${start.slice(4, 6)}-${start.slice(6, 8)}` +
        `T${start.slice(9, 11)}:${start.slice(11, 13)}:${start.slice(13, 15)}Z`
      );
      const calendarTime = new Intl.DateTimeFormat('en-US', {
        timeZone: timezone,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
      }).format(utcDate);
      const displayedTime = await firstItem.locator('xpath=ancestor::div[contains(@class, "schedule-hour")]/h3').textContent();

      expect(calendarTime).toBe(displayedTime.trim());
    });

    test('icalby.php returns a valid multi-event feed without PHP errors', async ({ page }) => {
      const response = await page.request.get('/wp-content/plugins/OnlineSched/icalby.php?room=all&tag=all&textlen=0');

      await expectIcsResponse(response, { minEvents: 2 });
    });
  });

  test.describe('Schedule subscription publishing', () => {
    test.beforeEach(async ({}, testInfo) => {
      test.skip(testInfo.project.name !== 'vanilla-wp', 'Request-scoped publication tests run on the disposable Vanilla site');
    });

    test('enabled, disabled, and re-enabled aggregate feeds preserve event identity and content', async ({ page }) => {
      const path = '/wp-content/plugins/OnlineSched/icalby.php?room=all&tag=all&textlen=0';
      const firstBody = await expectIcsResponse(await page.request.get(path), { minEvents: 2 });
      await expectDisabledAggregateResponse(await page.request.get(withSubscriptionsDisabled(path)));
      const secondBody = await expectIcsResponse(await page.request.get(path), { minEvents: 2 });
      const firstUids = getIcsUids(firstBody);

      expect(firstUids.length).toBeGreaterThanOrEqual(2);
      expect(getIcsUids(secondBody)).toEqual(firstUids);
      expect(getIcsEventSignatures(secondBody)).toEqual(getIcsEventSignatures(firstBody));
    });

    test('disabled aggregate endpoint exits before an os_event WP_Query', async ({ page }) => {
      const response = await page.request.get(
        withSubscriptionsDisabled('/wp-content/plugins/OnlineSched/icalby.php?room=all&tag=all&textlen=0') +
        '&onlinesched_test_calendar_query_guard=armed'
      );

      expect(response.headers()['x-onlinesched-test-query-guard']).toBe('armed');
      await expectDisabledAggregateResponse(response);
    });

    test('disabled full, filtered, and compatibility feeds are valid empty calendars', async ({ page }) => {
      const firstItem = page.locator(S.scheduleItem).first();
      const knownTitle = (await firstItem.locator(S.scheduleTitle).textContent())?.trim();
      const knownPostId = (await firstItem.getAttribute('id'))?.replace('onlineevt-', '');
      const room = await page.locator(`${S.selectRooms} option:not([value="all"])`).first().getAttribute('value');
      const tag = await page.locator(`${S.selectTags} option:not([value="all"])`).first().getAttribute('value');

      expect(knownTitle).toBeTruthy();
      expect(knownPostId).toMatch(/^\d+$/);
      expect(room).toBeTruthy();
      expect(tag).toBeTruthy();

      const paths = [
        '/wp-content/plugins/OnlineSched/icalby.php?room=all&tag=all&textlen=0',
        `/wp-content/plugins/OnlineSched/icalby.php?room=${encodeURIComponent(room)}&tag=${encodeURIComponent(tag)}&limit=1&textlen=20`,
        `/wp-content/plugins/OnlineSched/icalbyroom.php?room=${encodeURIComponent(room)}`,
      ];

      for (const path of paths) {
        const response = await page.request.get(withSubscriptionsDisabled(path));
        await expectDisabledAggregateResponse(response, [knownTitle, knownPostId]);
      }
    });

    test('disabled subscriptions hide only aggregate controls', async ({ page }) => {
      await page.goto(withSubscriptionsDisabled('/schedule/'));
      await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
      await page.selectOption(S.selectDays, 'all');

      await expect(page.getByText('Schedule calendar subscriptions are not available yet.', { exact: true })).toBeVisible();
      await expect(page.locator(`${S.addToCalendarSection} button`)).toHaveCount(0);

      await page.locator(S.infoModalBtn).click();
      await expect(page.locator(S.infoModal)).toBeVisible();
      await expect(page.locator(S.infoModal)).toContainText('Full-schedule subscriptions are paused for now');
      await expect(page.locator(S.infoModal)).toContainText('you can still add any visible individual event');
      await page.locator(S.infoModalClose).click();

      const event = page.locator(S.scheduleItem).filter({ has: page.locator(S.scheduleIcalLink) }).first();
      await expect(event).toBeVisible();
      await expect(event.locator(S.scheduleIcalLink)).toBeVisible();
      await expect(event.locator(S.scheduleGoogleLink)).toBeVisible();
      await expect(event.locator(S.clipboard)).toBeVisible();
      await expect(event.locator(S.favoriteBtn)).toBeVisible();

      const eventTitle = (await event.locator(S.scheduleTitle).textContent())?.trim();
      const postId = (await event.getAttribute('id'))?.replace('onlineevt-', '');
      const icalHref = await event.locator(S.scheduleIcalLink).getAttribute('href');
      const googleHref = await event.locator(S.scheduleGoogleLink).getAttribute('href');

      expect(eventTitle).toBeTruthy();
      expect(postId).toMatch(/^\d+$/);
      expect(icalHref).toContain(`ical.php?cal-id=${postId}`);
      expect(googleHref).toContain('calendar.google.com');
      expect(decodeURIComponent(googleHref)).toContain(`ical.php?cal-id=${postId}`);

      const singleEventResponse = await page.request.get(
        withSubscriptionsDisabled(`/wp-content/plugins/OnlineSched/ical.php?cal-id=${postId}`)
      );
      const singleEventBody = await expectIcsResponse(singleEventResponse, { exactEvents: 1 });
      expect(singleEventBody).toContain(`SUMMARY:${eventTitle}`);

      await event.locator(S.scheduleTitle).click();
      await expect(page.locator(S.scheduleModal)).toBeVisible();
      await expect(page.locator(S.modalIcal)).toBeVisible();
      await expect(page.locator(S.modalGoogle)).toBeVisible();
      expect(await page.locator(S.modalIcal).getAttribute('href')).toContain(`ical.php?cal-id=${postId}`);
      expect(await page.locator(S.modalGoogle).getAttribute('href')).toContain('calendar.google.com');
    });

    test('disabled subscriptions retain the Android one-time Google event action', async ({ page }) => {
      await page.addInitScript(() => {
        Object.defineProperty(Navigator.prototype, 'userAgent', {
          configurable: true,
          get: () => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125 Mobile Safari/537.36',
        });
      });
      await page.goto(withSubscriptionsDisabled('/schedule/'));
      await page.waitForSelector(S.schedule, { state: 'visible', timeout: 15000 });
      await page.selectOption(S.selectDays, 'all');

      const event = page.locator(S.scheduleItem).filter({ has: page.locator(S.scheduleGoogleLink) }).first();
      await event.locator(S.scheduleTitle).click();
      await expect(page.locator(S.scheduleModal)).toBeVisible();
      await page.locator(S.modalGoogle).click();

      await expect(page.locator(S.androidModal)).toBeVisible();
      const oneTimeButton = page.locator(`${S.androidModal} .android-gcal-onetime-btn`);
      await expect(oneTimeButton).toBeVisible();
      await expect(oneTimeButton).toHaveAttribute('data-gcal-url', /calendar\.google\.com\/calendar\/render\?action=TEMPLATE/);
    });

    test('disabled subscriptions retain Solo Event calendar actions', async ({ page }) => {
      await page.goto(withSubscriptionsDisabled('/solo-event-demo/'), { waitUntil: 'domcontentloaded' });

      const cards = page.locator('.os-solo-event-card');
      await expect(cards).toHaveCount(2);
      await expect(cards.locator(S.scheduleIcalLink)).toHaveCount(2);
      await expect(cards.locator(S.scheduleGoogleLink)).toHaveCount(2);

      const icalHref = await cards.first().locator(S.scheduleIcalLink).getAttribute('href');
      const googleHref = await cards.first().locator(S.scheduleGoogleLink).getAttribute('href');
      expect(icalHref).toContain('ical.php?cal-id=');
      expect(googleHref).toContain('calendar.google.com');
      expect(decodeURIComponent(googleHref)).toContain('ical.php?cal-id=');
    });

    test('disabled feed reference removes aggregate examples but keeps the individual-event rule', async ({ page }) => {
      await page.goto(withSubscriptionsDisabled('/calendar-feed-reference/'), { waitUntil: 'domcontentloaded' });

      const reference = page.locator('.ical-cheat-sheet');
      await expect(reference).toBeVisible();
      await expect(reference).toContainText('Full-schedule calendar subscriptions are currently disabled.');
      await expect(reference).toContainText('Full and filtered schedule feeds return an empty calendar.');
      await expect(reference).toContainText('Individual event calendar links remain available');
      await expect(reference).not.toContainText('icalby.php');
      await expect(reference).not.toContainText('icalbyroom.php');
      await expect(reference).not.toContainText('Example URLs');
    });
  });
});
