// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/first_post_identity.mjs
// A new visitor's key stays in the browser until their first signed action; no dialog ever opens.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const seededKeys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'first-post-identity-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'),
  FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'true' };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(seededKeys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const head = () => spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout.trim();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const dialogs = []; page.on('dialog', dialog => { dialogs.push(dialog.type() + ': ' + dialog.message()); void dialog.dismiss(); });
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  const identityRequests = []; const timings = {};
  page.on('request', request => {
    if (/\/api\/(prepare_identity|create_identity|link_identity)$/.test(request.url())) { identityRequests.push(new URL(request.url()).pathname); timings[request.url()] = Date.now(); }
  });
  page.on('response', response => { if (timings[response.url()]) timings[response.url()] = Date.now() - timings[response.url()]; });
  const startHead = head();

  // Reading: the automatic guest key is created locally, and nothing is sent.
  await page.goto(base + '/');
  await page.waitForFunction(() => /^[0-9A-F]{40}$/.test(localStorage.getItem('forum_pki_fingerprint') || ''));
  await page.goto(base + '/threads/root-001');
  await page.waitForFunction(() => window.__forumBrowserIdentity && window.__forumBrowserIdentity.storedVoteIdentityReadinessState() !== 'idle');
  await page.waitForTimeout(1500);
  assert.deepEqual(identityRequests, [], 'reading must not register the key');
  assert.equal(head(), startHead, 'reading must not commit');
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_pki_published_fingerprint') || ''), '');
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_pki_username')), 'guest');

  // A local key named mouse is a guest under that name: the Forte toolbar shows the name without a profile link.
  await page.evaluate(() => localStorage.setItem('forum_pki_username', 'mouse'));
  const toolbar = async () => { await page.goto(base + '/forte'); const node = page.locator('[data-paned-identity-status]');
    await page.waitForFunction(() => document.querySelector('[data-paned-identity-label]').textContent === (localStorage.getItem('forum_pki_username') || 'Guest'));
    return { label: await node.locator('[data-paned-identity-label]').textContent(), href: await node.getAttribute('href'), loggedIn: await node.getAttribute('data-identity-logged-in') }; };
  assert.deepEqual(await toolbar(), { label: 'mouse', href: '/account/key/', loggedIn: '0' });
  // Right before first paint: with the deferred toolbar script blocked, the inline script alone shows the name.
  await page.route(/toolbar_identity_status/, route => route.abort());
  await page.goto(base + '/forte');
  assert.equal(await page.locator('[data-paned-identity-label]').textContent(), 'mouse');
  assert.equal(await page.locator('[data-paned-identity-status]').getAttribute('href'), '/account/key/');
  await page.unroute(/toolbar_identity_status/);
  const parsed = await page.evaluate(async () => { const response = await fetch(location.href); const doc = new DOMParser().parseFromString(await response.text(), 'text/html'); return doc.querySelector('[data-paned-identity-label]').textContent; });
  assert.equal(parsed, 'Guest', 'server HTML stays the same for every viewer');
  assert.deepEqual(identityRequests, [], 'showing the name sends nothing');
  // The key itself was made as guest; renaming a local key is the composer's job (Stage 4).
  await page.evaluate(() => localStorage.setItem('forum_pki_username', 'guest'));
  await page.goto(base + '/threads/root-001');

  // First Like registers once, then votes; a second Like only signs and sends.
  async function like(selector) {
    const button = page.locator(selector).first();
    await button.locator('xpath=ancestor::article[1]').hover();
    const applied = page.waitForResponse(r => /\/api\/apply_(thread|post)_tag$/.test(r.url()));
    await button.click();
    assert.match(await (await applied).text(), /status=ok/);
  }
  await like('[data-action="apply-thread-tag"][data-tag="like"]');
  assert.deepEqual(identityRequests, ['/api/prepare_identity', '/api/create_identity'], 'first Like registers exactly once');
  const fingerprint = await page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_pki_published_fingerprint')), fingerprint);
  assert.notEqual(head(), startHead);
  await page.reload();
  await page.waitForFunction(() => window.__forumBrowserIdentity && window.__forumBrowserIdentity.storedVoteIdentityReadinessState() === 'ready');
  const postLike = page.locator('[data-action="apply-post-tag"][data-tag="like"]');
  assert.ok(await postLike.count(), "fixture thread has a post to like");
  await like('[data-action="apply-post-tag"][data-tag="like"]');
  assert.deepEqual(identityRequests, ['/api/prepare_identity', '/api/create_identity'], 'later actions do not register again');
  const registered = await toolbar();
  assert.deepEqual(registered, { label: 'guest', href: '/forte/user/guest', loggedIn: '1' });

  assert.deepEqual(dialogs, [], 'no browser dialog may open');
  assert.deepEqual(errors, []);

  // Renaming is off on this site. Change still works for a key kept in this browser, and goes away once a post or Like registers it.
  {
    const { context: ctx, page: p, seen } = await (async () => {
      const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
      const page = await context.newPage(); const seen = { dialogs: [], errors: [] };
      page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); }); page.on('pageerror', e => seen.errors.push(e.message));
      return { context, page, seen };
    })();
    await p.goto(base + '/threads/root-001');
    await p.waitForFunction(() => /^[0-9A-F]{40}$/.test(localStorage.getItem('forum_pki_fingerprint') || ''));
    // Outbox history (a sent item and an unsigned draft) is not queued signed work.
    await p.evaluate(() => new Promise((resolve, reject) => {
      const open = indexedDB.open('forum-offline-outbox-v1', 1);
      open.onupgradeneeded = () => open.result.createObjectStore('items', { keyPath: 'id' });
      open.onsuccess = () => { const tx = open.result.transaction('items', 'readwrite'); const store = tx.objectStore('items');
        store.put({ id: 'sent-1', state: 'accepted', intent: { canonicalRecord: 'r', detachedSignature: 's' } }); store.put({ id: 'draft-1', state: 'draft' });
        tx.oncomplete = () => { open.result.close(); resolve(); }; tx.onerror = reject; };
      open.onerror = reject;
    }));
    const row = p.locator('[data-compose-kind="reply"] [data-compose-identity]');
    await p.locator('[data-compose-kind="reply"] textarea[name="body"]').fill('A draft');
    const before = await p.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    await row.getByRole('button', { name: 'Change' }).click();
    await row.locator('[data-role="compose-identity-input"]').fill('pip');
    await row.getByRole('button', { name: 'Done' }).click();
    await p.waitForFunction(() => { const r = document.querySelector('[data-compose-kind="reply"] [data-compose-identity]');
      return r.querySelector('[data-role="compose-identity-editor"]').hidden || !r.querySelector('[data-role="compose-identity-error"]').hidden; }, null, { timeout: 30000 });
    const changeError = row.locator('[data-role="compose-identity-error"]');
    assert.equal(await changeError.isVisible() ? await changeError.textContent() : '', '', 'Change on a local key must not fail');
    assert.equal(await row.locator('[data-role="compose-identity-name"]').textContent(), 'pip');
    assert.notEqual(await p.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), before, 'a local key is replaced');
    // An inline reply registers the key; on the same page the row now hides Change, since renaming is off.
    const replied = p.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 30000 });
    await p.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]').click();
    const repliedResponse = await replied;
    assert.match(await repliedResponse.text(), /ok/);
    await p.waitForFunction(() => localStorage.getItem('forum_pki_published_fingerprint') === localStorage.getItem('forum_pki_fingerprint'));
    await p.locator('[data-compose-kind="reply"] textarea[name="body"]').fill('Another reply');
    assert.equal(await row.getByRole('button', { name: 'Change' }).isVisible(), false, 'no Change for a registered key where renaming is off');
    assert.deepEqual(seen, { dialogs: [], errors: [] });
    await ctx.close();
  }

  // A first action shows "Creating your account…"; a failed registration shows Retry beside the action and keeps the draft.
  async function visitor() {
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    const seen = { dialogs: [], errors: [] };
    page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
    page.on('pageerror', e => seen.errors.push(e.message));
    return { context, page, seen };
  }
  // Holds the first registration until released, so the creation state can be seen; then fails it.
  async function failRegistrationOnce(page) {
    let release; const held = new Promise(r => { release = r; });
    await page.route('**/api/create_identity', async route => { await held; await route.abort(); });
    return async () => { release(); await page.waitForTimeout(100); await page.unroute('**/api/create_identity'); };
  }

  // Like.
  const liker = await visitor();
  await liker.page.goto(base + '/threads/root-001');
  const releaseLike = await failRegistrationOnce(liker.page);
  const likeButton = liker.page.locator('[data-action="apply-thread-tag"][data-tag="like"]').first();
  await likeButton.locator('xpath=ancestor::article[1]').hover();
  await likeButton.click();
  const likeFeedback = liker.page.locator('[data-role="thread-reaction-feedback"]').first();
  await liker.page.waitForFunction(() => /Creating your account…/.test(document.querySelector('[data-role="thread-reaction-feedback"]')?.textContent || ''));
  await releaseLike();
  const likeRetry = likeFeedback.getByRole('button', { name: 'Retry' });
  await likeRetry.waitFor({ state: 'visible', timeout: 30000 });
  assert.match(await likeFeedback.textContent(), /^Couldn't create your account\./);
  await liker.page.screenshot({ path: '/tmp/first-post-like-retry-390.png' });
  const likeApplied = liker.page.waitForResponse(r => /\/api\/apply_thread_tag$/.test(r.url()), { timeout: 30000 });
  await likeRetry.click();
  assert.match(await (await likeApplied).text(), /status=ok/);
  assert.deepEqual(liker.seen, { dialogs: [], errors: [] });
  await liker.context.close();

  // Board quick compose: the draft stays; Retry posts it.
  const poster = await visitor();
  await poster.page.goto(base + '/');
  const board = poster.page.locator('[data-compose-kind="thread"]').first();
  await board.locator('textarea[name="body"]').fill('My first thread');
  const releasePost = await failRegistrationOnce(poster.page);
  await board.locator('.compose-form-actions > button[type="submit"]').click();
  const composeStatus = poster.page.locator('[data-role="compose-identity-status"]').first();
  await poster.page.waitForFunction(() => /Creating your account…/.test(document.querySelector('[data-role="compose-identity-status"]')?.textContent || ''));
  await releasePost();
  const postRetry = composeStatus.getByRole('button', { name: 'Retry' });
  await postRetry.waitFor({ state: 'visible', timeout: 30000 });
  assert.match(await composeStatus.textContent(), /^Couldn't create your account\./);
  assert.equal(await board.locator('textarea[name="body"]').inputValue(), 'My first thread', 'the draft is kept');
  assert.equal(await board.locator('[data-action="submit-anonymous-compose"]').count(), 1, 'Post anonymously stays');
  await poster.page.screenshot({ path: '/tmp/first-post-compose-retry-390.png' });
  await postRetry.click();
  await poster.page.waitForURL(/\/threads\//, { timeout: 30000 });
  assert.match(await poster.page.content(), /My first thread/);
  assert.deepEqual(poster.seen, { dialogs: [], errors: [] });
  await poster.context.close();

  // Inline reply: the pending reply card offers Retry with the same wording.
  const replier = await visitor();
  await replier.page.goto(base + '/threads/root-001');
  const replyForm = replier.page.locator('[data-compose-kind="reply"]').first();
  await replyForm.locator('textarea[name="body"]').fill('My first reply');
  await replier.page.route('**/api/create_identity', route => route.abort());
  await replyForm.locator('.compose-form-actions > button[type="submit"]').click();
  await replier.page.waitForFunction(() => /Couldn't create your account\./.test(document.body.textContent));
  await replier.page.unroute('**/api/create_identity');
  const replyCreated = replier.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 30000 });
  await replier.page.getByRole('button', { name: 'Retry' }).first().click();
  assert.match(await (await replyCreated).text(), /ok/);
  assert.deepEqual(replier.seen, { dialogs: [], errors: [] });
  await replier.context.close();
  console.log(`first_post_identity: ok (create_identity ${timings[base + '/api/create_identity']} ms on the fixture)`);
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
