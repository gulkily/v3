import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const script = await readFile('public/assets/feature_flags.js', 'utf8');
let navigationFails = false, saveFails = false, writes = 0;
await page.route('http://navigation.test/**', async route => {
  const path = new URL(route.request().url()).pathname;
  if (path === '/') return route.fulfill({ contentType: 'text/html', body: `<nav class="nav"><a href="/">Board</a><a href="/messages" data-private-message-unread>Messages</a><a href="/invites/">Invite</a></nav><main><div style="height:900px"></div><div data-feature-flag-row data-flag-default="true"><form data-feature-flag-form data-feature-flag-toggle><input name="key" value="FORUM_PRIVATE_MESSAGES_ENABLED"><input name="value" value="false"><button class="switch" role="switch" aria-checked="true">Toggle</button><span data-role="feature-flag-status"></span></form></div><div style="height:900px"></div></main><script>window.stops=0;window.ForumPrivateMessageAvailability={disable(){window.stops++}};window.ForumBrowserSigning={ensureActionIdentity:async()=>{},signCanonicalRecord:async()=> 'signature'};</script><script>${script}</script>` });
  if (path === '/api/prepare_feature_flag_change') {
    writes++;
    return route.fulfill({ status: saveFails ? 403 : 200, json: saveFails ? { status: 'error', error: 'Rejected' } : { status: 'ok', wrote_record: 'no', effective_value: 'false' } });
  }
  if (path === '/api/feature_flags/navigation') return route.fulfill({ status: navigationFails ? 503 : 200, json: { status: 'ok', html: '<nav class="nav"><a href="/">Board</a><a href="/invites/">Invite</a></nav>', messaging_enabled: false, viewer: null, scripts: [] } });
  return route.abort();
});
try {
  for (const scenario of ['success', 'refresh-failed', 'save-failed']) {
    navigationFails = scenario === 'refresh-failed'; saveFails = scenario === 'save-failed';
    await page.goto('http://navigation.test/');
    const button = page.getByRole('switch');
    await button.scrollIntoViewIfNeeded(); await button.focus();
    const scroll = await page.evaluate(() => scrollY);
    const before = writes;
    await button.click();
    await page.waitForFunction(() => !document.querySelector('.switch').disabled);
    assert.equal(writes, before + 1);
    assert.equal(await page.locator('[data-private-message-unread]').count(), saveFails ? 1 : 0);
    assert.equal(await page.evaluate(() => document.activeElement === document.querySelector('.switch')), true);
    assert.equal(await page.evaluate(() => scrollY), scroll);
    assert.equal(await page.locator('nav a[href="/invites/"]').count(), 1);
    if (navigationFails) {
      assert.match(await page.locator('[data-role="feature-flag-status"]').innerText(), /Saved. Navigation could not refresh/);
      await page.getByRole('button', { name: 'Reload page' }).click();
      await page.waitForLoadState();
      assert.equal(writes, before + 1, 'Recovery must not repeat the write');
    }
    if (saveFails) assert.equal(await page.evaluate(() => window.stops), 0);
  }
  console.log('Live navigation disable, save rejection, refresh failure, focus/scroll and reload recovery passed.');
} finally { await browser.close(); }
