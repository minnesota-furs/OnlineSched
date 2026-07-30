const path = require('path');
const { test, expect } = require('@playwright/test');

const scriptPath = path.resolve(__dirname, '../../assets/js/admin-event-safety.js');

async function installSafety(page, config, html) {
  await page.setContent(html);
  await page.evaluate((value) => {
    window.OnlineSchedEventSafety = value;
    window.onlineschedConfirmCalls = [];
    window.confirm = (message) => {
      window.onlineschedConfirmCalls.push(message);
      return false;
    };
  }, config);
  await page.addScriptTag({ path: scriptPath });
}

async function confirmCount(page) {
  return page.evaluate(() => window.onlineschedConfirmCalls.length);
}

test.describe('OnlineSched event removal warnings', () => {
  test.beforeEach(async ({ }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop');
  });

  test('published editor trash and status removal warn', async ({ page }) => {
    await installSafety(
      page,
      {
        screen: 'post',
        editorPublished: true,
        confirmMessage: 'Published event warning',
      },
      `
        <form id="post">
          <select id="post_status">
            <option value="publish">Published</option>
            <option value="draft">Draft</option>
          </select>
        </form>
        <a class="submitdelete" href="#trashed">Move to Trash</a>
      `
    );

    await page.click('a.submitdelete');
    expect(await confirmCount(page)).toBe(1);
    expect(await page.evaluate(() => window.location.hash)).toBe('');

    const publishedSubmit = await page.evaluate(() => document.getElementById('post').dispatchEvent(
      new Event('submit', { bubbles: true, cancelable: true })
    ));
    expect(publishedSubmit).toBe(true);
    expect(await confirmCount(page)).toBe(1);

    await page.selectOption('#post_status', 'draft');
    const draftSubmit = await page.evaluate(() => document.getElementById('post').dispatchEvent(
      new Event('submit', { bubbles: true, cancelable: true })
    ));
    expect(draftSubmit).toBe(false);
    expect(await confirmCount(page)).toBe(2);
  });

  test('unpublished editor actions do not warn', async ({ page }) => {
    await installSafety(
      page,
      {
        screen: 'post',
        editorPublished: false,
        confirmMessage: 'Published event warning',
      },
      `
        <form id="post">
          <select id="post_status"><option value="draft">Draft</option></select>
        </form>
        <a class="submitdelete" href="#trashed">Move to Trash</a>
      `
    );

    await page.click('a.submitdelete');
    expect(await confirmCount(page)).toBe(0);
    expect(await page.evaluate(() => window.location.hash)).toBe('#trashed');

    const draftSubmit = await page.evaluate(() => document.getElementById('post').dispatchEvent(
      new Event('submit', { bubbles: true, cancelable: true })
    ));
    expect(draftSubmit).toBe(true);
    expect(await confirmCount(page)).toBe(0);
  });

  test('event list warns for published row, bulk trash, and bulk status', async ({ page }) => {
    await installSafety(
      page,
      {
        screen: 'list',
        editorPublished: false,
        confirmMessage: 'Published event warning',
      },
      `
        <select id="bulk-action-selector-top">
          <option value="-1">Bulk actions</option>
          <option value="trash">Move to Trash</option>
        </select>
        <button id="doaction" type="button">Apply</button>
        <select id="bulk-action-selector-bottom">
          <option value="-1">Bulk actions</option>
          <option value="trash">Move to Trash</option>
        </select>
        <button id="doaction2" type="button">Apply</button>
        <div id="bulk-edit">
          <select name="_status">
            <option value="-1">No Change</option>
            <option value="publish">Published</option>
            <option value="draft">Draft</option>
          </select>
          <button id="bulk_edit" type="button">Update</button>
        </div>
        <table>
          <tbody>
            <tr class="status-publish">
              <th class="check-column"><input id="published-box" type="checkbox"></th>
              <td><a id="published-trash" class="submitdelete" href="#published-trash">Trash</a></td>
            </tr>
            <tr class="status-draft">
              <th class="check-column"><input id="draft-box" type="checkbox"></th>
              <td><a id="draft-trash" class="submitdelete" href="#draft-trash">Trash</a></td>
            </tr>
          </tbody>
        </table>
      `
    );

    await page.click('#published-trash');
    expect(await confirmCount(page)).toBe(1);
    expect(await page.evaluate(() => window.location.hash)).toBe('');

    await page.click('#draft-trash');
    expect(await confirmCount(page)).toBe(1);
    expect(await page.evaluate(() => window.location.hash)).toBe('#draft-trash');

    await page.check('#draft-box');
    await page.selectOption('#bulk-action-selector-top', 'trash');
    await page.click('#doaction');
    expect(await confirmCount(page)).toBe(1);

    await page.check('#published-box');
    await page.click('#doaction');
    expect(await confirmCount(page)).toBe(2);

    await page.selectOption('#bulk-action-selector-bottom', 'trash');
    await page.click('#doaction2');
    expect(await confirmCount(page)).toBe(3);

    await page.selectOption('#bulk-edit select[name="_status"]', 'draft');
    await page.click('#bulk_edit');
    expect(await confirmCount(page)).toBe(4);
  });

  test('native cancellation tags synchronize the cancellation checkbox', async ({ page }) => {
    await installSafety(
      page,
      {
        screen: 'post',
        editorPublished: true,
        confirmMessage: 'Published event warning',
      },
      `
        <input id="onlinesched-event-cancelled" type="checkbox" checked>
        <input id="onlinesched-event-cancellation-changed" type="hidden" value="0">
        <div id="tagsdiv-os_tag">
          <div class="tagchecklist">
            <span id="cancelled-tag">
              Cancelled
              <button type="button" class="ntdelbutton" aria-label="Remove term: Cancelled">X</button>
            </span>
          </div>
        </div>
      `
    );

    await page.click('#cancelled-tag button');
    await expect(page.locator('#onlinesched-event-cancelled')).not.toBeChecked();
    await expect(page.locator('#onlinesched-event-cancellation-changed')).toHaveValue('0');

    await page.evaluate(() => {
      const tag = document.createElement('span');
      tag.textContent = 'Canceled';
      document.querySelector('#tagsdiv-os_tag .tagchecklist').appendChild(tag);
    });
    await expect(page.locator('#onlinesched-event-cancelled')).toBeChecked();
    await expect(page.locator('#onlinesched-event-cancellation-changed')).toHaveValue('0');

    await page.uncheck('#onlinesched-event-cancelled');
    await expect(page.locator('#onlinesched-event-cancellation-changed')).toHaveValue('1');
  });
});
