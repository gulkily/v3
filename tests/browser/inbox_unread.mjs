// The Unread count arrives after paint as data-unread-count on the Unread link and
// its mirrors. CSS draws an outline, so nothing in the header moves or resizes.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const html = execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, 'inbox', '1', '1', '1'], { encoding: 'utf8' });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
for (const [width, height] of [[1100, 260], [390, 760]]) {
  for (const approved of [true, false]) {
    const page = await browser.newPage({ viewport: { width, height } });
    let count = 3, calls = 0;
    if (approved) {
      await page.addInitScript(() => {
        const fingerprint = 'a'.repeat(40);
        for (const [key, value] of [['forum_pki_fingerprint', fingerprint], ['forum_pki_public_key', 'k'], ['forum_pki_private_key', 'k'], ['forum_pki_approved_fingerprint', fingerprint]]) localStorage.setItem(key, value);
      });
    }
    await page.route('http://app.test/**', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/api/inbox/unread') { calls++; return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: count === null ? 'error' : 'ok', unread_count: count }), status: count === null ? 503 : 200 }); }
      if (path.startsWith('/assets/')) {
        const file = path.replace(/\.[0-9a-f]{12}\./, '.');
        try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(project + '/public' + file) }); }
        catch { return route.fulfill({ status: 404, body: '' }); }
      }
      if (path.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
      return route.fulfill({ contentType: 'text/html', body: html });
    });
    const boxes = () => page.evaluate(() => [...document.querySelectorAll('.site-header a, .site-header summary')].map(e => { const r = e.getBoundingClientRect(); return [r.x, r.y, r.width, r.height].join(','); }).join(';'));
    const state = () => page.evaluate(() => [document.querySelector('[data-inbox-unread]').dataset.unreadCount ?? null, document.querySelector('.account-menu__trigger').dataset.unreadCount ?? null, document.getElementById('inbox-unread-status').textContent]);
    await page.goto('http://app.test/unread');
    if (!approved) {
      await page.waitForTimeout(300);
      assert.equal(calls, 0, `${width}: a browser not remembered as approved asks for nothing`);
      assert.deepEqual((await state()).slice(0, 2), [null, null]);
      await page.close();
      continue;
    }
    await page.waitForFunction(() => document.querySelector('[data-inbox-unread]').dataset.unreadCount === '3');
    // Messages stays on the bar with Inbox on: its link is what starts the
    // Messages unread count and the private-message history sync.
    assert.equal(await page.locator('[data-private-message-unread]').count(), 1, `${width}: one Messages link starts messaging`);
    await page.waitForFunction(() => window.ForumPrivateMessageHistorySync && window.ForumPrivateMessageUnread, null, { timeout: 5000 });
    assert.deepEqual(await state(), ['3', '3', '3 unread'], `${width}: link, More tab and status agree`);
    const before = await boxes();
    count = 0;
    await page.evaluate(() => window.ForumInboxUnread.refresh());
    await page.waitForFunction(() => document.querySelector('[data-inbox-unread]').dataset.unreadCount === '0');
    assert.equal(await boxes(), before, `${width}: a count change moves nothing`);
    count = null;
    await page.evaluate(() => window.ForumInboxUnread.refresh());
    await page.waitForFunction(() => document.querySelector('[data-inbox-unread]').dataset.unreadCount === undefined);
    assert.deepEqual(await state(), [null, null, 'Unread status unavailable'], `${width}: a failure shows no count, never a stale one`);
    assert.equal(await boxes(), before, `${width}: a failure moves nothing`);
    await page.close();
  }
}
console.log('Unread count checked at desktop and phone widths without layout shift.');
await browser.close();
