import assert from 'node:assert/strict';

// DM instant load: a conversation read once in this browser reopens from the encrypted local copy.
// Needs FORUM_PRIVATE_MESSAGE_CACHE_ENABLED=1 for the test server; with the flag off it checks that nothing is saved.
export async function checkCachedConversation(page, base) {
  const counterpart = await page.locator('.private-conversation').getAttribute('data-counterpart-username-token');
  const url = base + '/messages/conversation/' + counterpart;
  const cacheOn = await page.locator('.private-conversation').getAttribute('data-private-message-cache') === '1';
  // The flag must actually reach the page; otherwise a "flag on" run would silently test the flag-off path.
  assert.equal(cacheOn, process.env.FORUM_PRIVATE_MESSAGE_CACHE_ENABLED === '1', 'cache flag reaches the conversation page');
  const allVerified = () => document.querySelectorAll('[data-private-message-id]').length > 0
    && document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length === document.querySelectorAll('[data-private-message-id]').length;
  const saved = () => new Promise(resolve => {
    const open = indexedDB.open('forum-private-message-cache-v1');
    open.onupgradeneeded = () => { open.transaction.abort(); resolve(0); };
    open.onerror = () => resolve(0);
    open.onsuccess = () => {
      const database = open.result;
      if (!database.objectStoreNames.contains('messages')) { database.close(); resolve(0); return; }
      const count = database.transaction('messages').objectStore('messages').count();
      count.onsuccess = () => { database.close(); resolve(count.result); };
    };
  });

  await page.goto(url);
  await page.waitForFunction(allVerified);
  const cards = await page.locator('[data-private-message-id]').count();
  await page.waitForTimeout(300); // saves finish just after the cards show
  const stored = await page.evaluate(saved);
  if (!cacheOn) {
    assert.equal(stored, 0, 'flag off saves nothing');
    return;
  }
  // Earlier conversations and list previews in this browser are saved too; each message shown here must be.
  const savedIds = await page.evaluate(() => new Promise(resolve => {
    const open = indexedDB.open('forum-private-message-cache-v1');
    open.onsuccess = () => {
      const keys = open.result.transaction('messages').objectStore('messages').getAllKeys();
      keys.onsuccess = () => { open.result.close(); resolve(keys.result.map(key => String(key).split(':').pop())); };
    };
  }));
  const shownIds = await page.locator('[data-private-message-id]').evaluateAll(nodes => nodes.map(node => node.dataset.privateMessageId));
  assert.ok(stored >= cards && shownIds.every(id => savedIds.includes(id)), 'every verified message is saved');
  const texts = await page.locator('[data-role="private-message-plaintext"]').allTextContents();

  // Record which scripts ask for OpenPGP. History sync may load it for its own background work; the reader must not.
  await page.addInitScript(() => {
    let loader;
    window.__openPgpCallers = [];
    Object.defineProperty(window, '__forumOpenPgpLoader', { configurable: true, get() { return loader; }, set(value) {
      const load = value.load;
      value.load = function () { window.__openPgpCallers.push(String(new Error().stack)); return load.apply(this, arguments); };
      loader = value;
    } });
  });
  // Second visit: same text, no conversation fetch for the reader.
  let fetched = 0;
  const count = request => {
    if (request.url().includes('/api/private_messages/conversation')) fetched++;
  };
  page.on('request', count);
  await page.goto(url);
  // The transcript shows only once saved copies are in place: every message is already read and the newest is in view.
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageCacheChecked === '1');
  assert.equal(await page.evaluate(allVerified), true);
  assert.ok(await page.evaluate(() => {
    const last = [...document.querySelectorAll('[data-private-message-id]')].pop().getBoundingClientRect();
    return last.top < innerHeight && last.bottom > 0;
  }), 'newest message is in view');
  await page.waitForFunction(allVerified);
  page.off('request', count);
  assert.deepEqual(await page.locator('[data-role="private-message-plaintext"]').allTextContents(), texts);
  assert.equal(fetched, 0, 'cached messages need no mailbox fetch');
  assert.equal(await page.evaluate(() => window.__openPgpCallers.filter(stack => stack.includes('private_message_reader')).length), 0,
    'cached messages need no OpenPGP');
  assert.equal(await page.locator('[data-role="private-message-verification"]:not([hidden])').count(), cards);

  // Another identity in this browser never sees these copies.
  const original = await page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
  await page.evaluate(() => localStorage.setItem('forum_pki_fingerprint', 'someone-else'));
  assert.equal(await page.evaluate(id => window.ForumPrivateMessageCache.get(id),
    await page.locator('[data-private-message-id]').first().getAttribute('data-private-message-id')), null);
  await page.evaluate(value => localStorage.setItem('forum_pki_fingerprint', value), original);

  // The Messages list shows the same saved copy as this conversation's preview, before its list request returns.
  const latest = texts[texts.length - 1].replace(/\s+/gu, ' ').trim();
  let release;
  const held = new Promise(resolve => { release = resolve; });
  await page.route('**/api/private_messages/conversations**', async route => { await held; await route.continue(); });
  await page.goto(base + '/messages');
  await page.waitForFunction(() => document.querySelector('[data-conversation-list]').dataset.privateMessageCacheChecked === '1');
  const row = page.locator('[data-conversation-row][data-counterpart="' + counterpart + '"]');
  assert.equal(await row.getAttribute('data-preview-state'), 'verified', 'preview filled while the list request is still pending');
  assert.ok((await row.locator('[data-role="preview"]').textContent()).endsWith(latest.slice(0, 200)));
  release();
  await page.unroute('**/api/private_messages/conversations**');
  await page.waitForFunction(() => document.querySelector('[data-role="rows"]').getAttribute('aria-busy') === 'false');
  assert.equal(await row.getAttribute('data-preview-state'), 'verified', 'the list request keeps the saved preview');
  assert.ok((await row.locator('[data-role="preview"]').textContent()).endsWith(latest.slice(0, 200)));
}
