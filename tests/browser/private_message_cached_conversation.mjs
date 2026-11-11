import assert from 'node:assert/strict';

// DM instant load: a conversation read once in this browser reopens from the encrypted local copy.
// Needs FORUM_PRIVATE_MESSAGE_CACHE_ENABLED=1 for the test server; with the flag off it checks that nothing is saved.
export async function checkCachedConversation(page, base) {
  const counterpart = await page.locator('.private-conversation').getAttribute('data-counterpart-username-token');
  const url = base + '/messages/conversation/' + counterpart;
  const cacheOn = await page.locator('.private-conversation').getAttribute('data-private-message-cache') === '1';
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
  assert.equal(stored, cards, 'every verified message is saved once');
  const texts = await page.locator('[data-role="private-message-plaintext"]').allTextContents();

  // Second visit: same text, no conversation fetch for the reader.
  let fetched = 0;
  const count = request => { if (request.url().includes('/api/private_messages/conversation')) fetched++; };
  page.on('request', count);
  await page.goto(url);
  await page.waitForFunction(allVerified);
  page.off('request', count);
  assert.deepEqual(await page.locator('[data-role="private-message-plaintext"]').allTextContents(), texts);
  assert.equal(fetched, 0, 'cached messages need no mailbox fetch');
  assert.equal(await page.locator('[data-role="private-message-verification"]:not([hidden])').count(), cards);

  // Another identity in this browser never sees these copies.
  const original = await page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
  await page.evaluate(() => localStorage.setItem('forum_pki_fingerprint', 'someone-else'));
  assert.equal(await page.evaluate(id => window.ForumPrivateMessageCache.get(id),
    await page.locator('[data-private-message-id]').first().getAttribute('data-private-message-id')), null);
  await page.evaluate(value => localStorage.setItem('forum_pki_fingerprint', value), original);
}
