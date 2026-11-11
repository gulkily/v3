// Encrypted per-browser cache of read private messages (DM instant load, Stage 2).
// Runs the cache module on a routed https origin so IndexedDB and WebCrypto behave as on the site.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const source = readFileSync(new URL('../../public/assets/private_message_cache.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
try {
  const page = await browser.newPage();
  await page.route('https://cache.test/**', route => route.fulfill({
    contentType: 'text/html',
    body: '<!doctype html><section data-private-message-cache="1"></section>',
  }));
  await page.goto('https://cache.test/');
  await page.evaluate(() => localStorage.setItem('forum_pki_fingerprint', 'AAAA1111'));
  await page.addScriptTag({ content: source });

  // Round trip.
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.put('m1', 'hello ünïcode')), true);
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), 'hello ünïcode');
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('missing')), null);

  // Stored only as ciphertext, under a key that cannot be exported.
  const stored = await page.evaluate(async () => {
    const database = await new Promise(resolve => { const r = indexedDB.open(window.ForumPrivateMessageCache.databaseName); r.onsuccess = () => resolve(r.result); });
    const read = (store, key) => new Promise(resolve => { const r = database.transaction(store).objectStore(store).get(key); r.onsuccess = () => resolve(r.result); });
    const entry = await read('messages', 'aaaa1111:m1');
    const key = await read('keys', 'key');
    database.close();
    return { text: new TextDecoder().decode(entry.data), extractable: key.extractable };
  });
  assert.ok(!stored.text.includes('hello'));
  assert.equal(stored.extractable, false);

  // Another identity never sees these entries.
  await page.evaluate(() => localStorage.setItem('forum_pki_fingerprint', 'bbbb2222'));
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), null);
  await page.evaluate(() => localStorage.setItem('forum_pki_fingerprint', 'aaaa1111'));
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), 'hello ünïcode');

  // Flag off: no reads or writes.
  await page.evaluate(() => document.querySelector('section').dataset.privateMessageCache = '0');
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), null);
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.put('m2', 'x')), false);
  await page.evaluate(() => document.querySelector('section').dataset.privateMessageCache = '1');

  // Clearing removes the database, key included, even while this page has it open.
  await page.evaluate(() => window.ForumPrivateMessageCache.clearAll());
  assert.equal(await page.evaluate(async () => (await indexedDB.databases()).some(d => d.name === window.ForumPrivateMessageCache.databaseName)), false);
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), null);

  // Storage that refuses to open is a miss, never an error.
  await page.evaluate(() => window.ForumPrivateMessageCache.clearAll());
  await page.evaluate(() => { window.indexedDB.open = () => { throw new DOMException('denied', 'SecurityError'); }; });
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.get('m1')), null);
  assert.equal(await page.evaluate(() => window.ForumPrivateMessageCache.put('m1', 'x')), false);

  // Key generation failure is a miss too.
  const fresh = await browser.newPage();
  await fresh.route('https://cache.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><section data-private-message-cache="1"></section>' }));
  await fresh.goto('https://cache.test/');
  await fresh.evaluate(() => { localStorage.setItem('forum_pki_fingerprint', 'cccc3333'); crypto.subtle.generateKey = () => Promise.reject(new Error('no keys')); });
  await fresh.addScriptTag({ content: source });
  assert.equal(await fresh.evaluate(() => window.ForumPrivateMessageCache.put('m1', 'x')), false);
  assert.equal(await fresh.evaluate(() => window.ForumPrivateMessageCache.get('m1')), null);

  console.log('private_message_cache_browser: ok');
} finally {
  await browser.close();
}
