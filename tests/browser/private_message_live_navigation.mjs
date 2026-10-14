import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const script = await readFile('public/assets/feature_flags.js', 'utf8');
let navigationFails = false, saveFails = false, writes = 0;
await page.route('http://navigation.test/**', async route => {
  const path = new URL(route.request().url()).pathname;
  if (path === '/') return route.fulfill({ contentType: 'text/html', body: `<nav class="nav"><a href="/">Board</a><a href="/messages" data-private-message-unread>Messages</a><a href="/invites/">Invite</a></nav><main><div style="height:900px"></div><div data-feature-flag-row data-flag-default="true"><form data-feature-flag-form data-feature-flag-toggle><input name="key" value="FORUM_PRIVATE_MESSAGES_ENABLED"><input name="value" value="false"><button class="switch" role="switch" aria-checked="true">Toggle</button><span data-role="feature-flag-status"></span></form></div><div style="height:900px"></div></main><script>window.stops=0;window.ForumPrivateMessageAvailability={setEnabled(){window.stops++}};window.ForumBrowserSigning={ensureActionIdentity:async()=>{},signCanonicalRecord:async()=> 'signature'};</script><script>${script}</script>` });
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
  await page.unrouteAll();
  const navLink = '<a href="/messages" data-private-message-unread data-viewer="alice" aria-label="Messages" aria-describedby="private-message-unread-status">Messages <span data-role="unread-count">…</span><span id="private-message-unread-status" hidden>Unread status loading</span></a>';
  const assets = ['/assets/private_message_availability.js', '/assets/private_message_unread.js'];
  let releaseNavigation, holdNavigation = false, navigationHeld = false, writesInCycle = 0;
  let enabled = false, unreadFails = false, assetFails = false, unreadRequests = 0;
  const assetRequests = new Map();
  await page.route('http://navigation.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path.startsWith('/assets/')) {
      assetRequests.set(path, (assetRequests.get(path) || 0) + 1);
      if (assetFails) return route.abort();
      return route.fulfill({ contentType: 'text/javascript', body: await readFile('public' + path, 'utf8') });
    }
    if (path === '/') return route.fulfill({ contentType: 'text/html', body: `<nav class="nav"><a href="/">Board</a>${enabled ? navLink : ''}<a href="/invites/">Invite</a></nav><main><div data-feature-flag-row data-flag-default="true"><form data-feature-flag-form data-feature-flag-toggle><input name="key" value="FORUM_PRIVATE_MESSAGES_ENABLED"><input name="value" value="${!enabled}"><button class="switch" role="switch" aria-checked="${enabled}">Toggle</button><span data-role="feature-flag-status"></span></form></div></main><script>window.ForumBrowserSigning={ensureActionIdentity:async()=>{},signCanonicalRecord:async()=> 'signature'};</script>${enabled ? assets.map(src => `<script src="${src}"></script>`).join('') : ''}<script>${script}</script>` });
    if (path === '/api/prepare_feature_flag_change') {
      writesInCycle++;
      enabled = new URLSearchParams(route.request().postData()).get('value') === 'true';
      return route.fulfill({ json: { status: 'ok', wrote_record: 'no', effective_value: String(enabled) } });
    }
    if (path === '/api/feature_flags/navigation') {
      if (holdNavigation) { navigationHeld = true; await new Promise(resolve => { releaseNavigation = resolve; }); }
      return route.fulfill({ json: { status: 'ok', html: `<nav class="nav"><a href="/">Board</a>${enabled ? navLink : ''}<a href="/invites/">Invite</a></nav>`, messaging_enabled: enabled, viewer: enabled ? 'alice' : null, scripts: enabled ? assets : [] } });
    }
    if (path === '/api/private_messages/unread') {
      unreadRequests++;
      return route.fulfill({ status: unreadFails ? 503 : 200, json: { status: 'ok', viewer: 'alice', unread_count: 2, revision: 'one', conversations: {} } });
    }
    return route.abort();
  });
  await page.goto('http://navigation.test/');
  for (const state of [true, false, true, false, true]) {
    await page.getByRole('switch').click();
    await page.waitForFunction(() => !document.querySelector('.switch').disabled);
    assert.equal(await page.locator('[data-private-message-unread]').count(), state ? 1 : 0);
    if (state) {
      await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadCount === '2');
      assert.equal(await page.locator('[data-private-message-unread]').isVisible(), true);
      assert.equal(await page.locator('[data-messaging-disabled-notice]').count(), 0);
      assert.equal(await page.locator('nav a').evaluateAll(nodes => nodes.map(node => node.getAttribute('href')).join(',')), '/,/messages,/invites/');
    }
  }
  assert.equal(unreadRequests, 3, 'One unread refresh per confirmed enable');
  for (const count of assetRequests.values()) assert.equal(count, 1, 'Load each missing asset once');
  await page.getByRole('switch').click();
  await page.waitForFunction(() => !document.querySelector('.switch').disabled);
  unreadFails = true;
  await page.getByRole('switch').click();
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'unavailable');
  assert.equal(await page.locator('[data-role="unread-count"]').innerText(), '?');
  // A fresh disabled page with a broken asset gets explicit reload recovery.
  enabled = false; assetFails = true;
  await page.goto('http://navigation.test/');
  await page.getByRole('switch').click();
  await page.getByRole('button', { name: 'Reload page' }).waitFor();
  assert.equal(await page.locator('[data-role="unread-count"]').innerText(), '?');
  // An identity change while navigation is delayed must not install the old viewer's link.
  enabled = false; assetFails = false; holdNavigation = true;
  await page.goto('http://navigation.test/');
  const beforeWrites = writesInCycle;
  await page.getByRole('switch').click();
  while (!navigationHeld) await new Promise(resolve => setTimeout(resolve, 10));
  await page.evaluate(() => {
    document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }));
    localStorage.setItem('forum_pki_username', 'changed');
  });
  releaseNavigation();
  await page.getByRole('button', { name: 'Reload page' }).waitFor();
  assert.equal(await page.locator('[data-private-message-unread]').count(), 0);
  assert.equal(writesInCycle, beforeWrites + 1, 'Concurrent submissions of the same flag are serialized');
  console.log('Live navigation disable, save rejection, refresh failure, focus/scroll, enable cycles, unread failure, asset failure and reload recovery passed.');
} finally { await browser.close(); }
