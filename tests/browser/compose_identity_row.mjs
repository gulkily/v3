// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/compose_identity_row.mjs
// Every signed composer shows "Post as {name} · Change", correct before deferred scripts run,
// and Change renames in place without touching the draft.
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
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'reaction-browser' }], format: 'armored' });
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const localKeys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'mouse' }], format: 'armored' });
const localFingerprint = (await globalThis.openpgp.readKey({ armoredKey: localKeys.publicKey })).getFingerprint().toUpperCase();
const root = await mkdtemp(join(tmpdir(), 'compose-identity-row-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'),
  FORUM_USERNAME_CHANGES_ENABLED: 'true' };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

// Composers: board quick compose, thread reply, the full compose pages and Forte's reply panel and new post dialog.
const composers = [
  { name: 'board quick compose', path: '/', form: '[data-compose-kind="thread"]' },
  { name: 'thread reply', path: '/threads/root-001', form: '[data-compose-kind="reply"]' },
  { name: 'new post page', path: '/compose/thread', form: '[data-compose-kind="thread"]' },
  { name: 'reply page', path: '/compose/reply?thread_id=root-001&parent_id=root-001', form: '[data-compose-kind="reply"]' },
  { name: 'forte reply panel', path: '/forte?selected=root-001', form: '[data-paned-compose-panel] [data-compose-kind="reply"]' },
  { name: 'forte new post', path: '/forte', form: '[data-compose-kind="thread"]' },
];
const states = [
  { name: 'no key', storage: {}, expected: 'guest' },
  { name: 'local named key', storage: { username: 'mouse', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint }, expected: 'mouse' },
  { name: 'registered key', storage: { username: 'reaction-browser', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint }, expected: 'reaction-browser' },
];
// Capture each row when the document finishes parsing, before deferred scripts run.
const atParse = () => {
  document.addEventListener('DOMContentLoaded', () => {
    window.__rows = Array.from(document.querySelectorAll('[data-compose-identity]')).map(row => ({
      form: row.closest('form') && row.closest('form').dataset.composeKind,
      hidden: row.hidden,
      name: row.querySelector('[data-role="compose-identity-name"]').textContent,
    }));
  }, { once: true, capture: true });
};
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  // Server HTML is the same for every viewer: the row is hidden and says guest.
  for (const composer of composers) {
    const html = await (await fetch(base + composer.path)).text();
    assert.match(html, /<p class="meta compose-identity" data-compose-identity hidden><span data-role="compose-identity-view">Post as <span class="compose-identity__name" data-role="compose-identity-name">guest<\/span>/, composer.name);
  }
  for (const state of states) {
    for (const width of [390, 1280]) {
      const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 844 } });
      await context.addInitScript(storage => { for (const [k, v] of Object.entries(storage)) localStorage.setItem('forum_pki_' + k, v); }, state.storage);
      await context.addInitScript(atParse);
      const page = await context.newPage();
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      const dialogs = []; page.on('dialog', d => { dialogs.push(d.message()); void d.dismiss(); });
      for (const composer of composers) {
        await page.goto(base + composer.path);
        const rows = await page.evaluate(() => window.__rows);
        assert.ok(rows.length >= 1, `${composer.name}: a row`);
        for (const row of rows) assert.deepEqual({ hidden: row.hidden, name: row.name }, { hidden: false, name: state.expected }, `${composer.name} / ${state.name} at parse`);
        const row = page.locator(composer.form + ' [data-compose-identity]').first();
        assert.equal(await row.count(), 1, `${composer.name}: row inside the composer`);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${composer.name} ${width}: overflow`);
        if (width === 390 && state.name === 'local named key' && /board|thread reply/.test(composer.name)) {
          await page.locator(composer.form + ' textarea[name="body"]').first().focus();
          assert.equal(await row.isVisible(), true, `${composer.name}: visible once writing`);
          assert.equal((await row.innerText()).replace(/\s+/g, ' ').trim(), 'Post as mouse · Change');
          await page.screenshot({ path: `/tmp/compose-identity-row-${composer.name.replace(/\W+/g, '-')}-${width}.png` });
        }
      }
      assert.deepEqual(errors, []);
      assert.deepEqual(dialogs, []);
      await context.close();
    }
  }
  // A name change elsewhere updates the row in place.
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  await page.goto(base + '/threads/root-001');
  await page.evaluate(() => { localStorage.setItem('forum_pki_username', 'kim'); window.dispatchEvent(new Event('forum:identity-changed')); });
  assert.equal(await page.locator('[data-compose-kind="reply"] [data-role="compose-identity-name"]').textContent(), 'kim');
  await context.close();
  // Change from the composer.
  const reply = '[data-compose-kind="reply"]';
  const requests = page => { const seen = []; page.on('request', r => { const m = /\/api\/(prepare_identity|create_identity|link_identity|prepare_profile_name|finalize_profile_name|create_reply|prepare_reply)/.exec(r.url()); if (m) seen.push(m[1]); }); return seen; };
  async function writer(storage) {
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
    await context.addInitScript(storage => { if (sessionStorage.getItem('seeded')) return; sessionStorage.setItem('seeded', '1'); for (const [k, v] of Object.entries(storage)) localStorage.setItem('forum_pki_' + k, v); }, storage);
    const page = await context.newPage();
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const dialogs = []; page.on('dialog', d => { dialogs.push(d.message()); void d.dismiss(); });
    await page.goto(base + '/threads/root-001');
    await page.evaluate(() => { window.__writerDocument = true; });
    const body = page.locator(reply + ' textarea[name="body"]');
    await body.fill('A draft that must survive');
    if (storage.published_fingerprint) {
      await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]')?.dataset.authenticatedIdentityId);
      assert.equal(await page.evaluate(() => window.__writerDocument), true, 'authentication preserves the writing document');
      assert.equal(await body.inputValue(), 'A draft that must survive');
    }
    const row = page.locator(reply + ' [data-compose-identity]');
    return { context, page, errors, dialogs, body, row, sent: requests(page) };
  }
  async function change(row, name) {
    await row.getByRole('button', { name: 'Change' }).click();
    await row.locator('[data-role="compose-identity-input"]').fill(name);
    await row.getByRole('button', { name: 'Done' }).click();
  }
  const stored = (page, key) => page.evaluate(key => localStorage.getItem('forum_pki_' + key) || '', key);

  // A new visitor names themselves: nothing is sent; the first reply registers under the new name.
  const fresh = await writer({});
  await change(fresh.row, 'Mouse');
  await fresh.row.locator('[data-role="compose-identity-editor"]').waitFor({ state: 'hidden', timeout: 30000 });
  assert.equal(await fresh.row.locator('[data-role="compose-identity-name"]').textContent(), 'mouse');
  assert.equal(await stored(fresh.page, 'username'), 'mouse');
  assert.equal(await stored(fresh.page, 'published_fingerprint'), '');
  assert.deepEqual(fresh.sent, [], 'renaming a local key sends nothing');
  assert.equal(await fresh.body.inputValue(), 'A draft that must survive');
  await fresh.page.screenshot({ path: '/tmp/compose-identity-row-renamed-390.png' });
  const created = fresh.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 30000 });
  await fresh.page.locator(reply + ' .compose-form-actions > button[type="submit"]').click();
  assert.match(await (await created).text(), /ok/);
  assert.deepEqual(fresh.sent.filter(n => /identity/.test(n)), ['prepare_identity', 'create_identity'], 'the first reply registers once');
  const freshFingerprint = (await stored(fresh.page, 'fingerprint')).toLowerCase();
  assert.match(await (await fetch(base + '/api/get_profile?profile_slug=openpgp-' + freshFingerprint)).text(), /mouse/);
  assert.deepEqual(fresh.dialogs, []); assert.deepEqual(fresh.errors, []);
  await fresh.context.close();

  // A registered key renames on the server, keeping its key; a failure shows in the row and keeps the draft.
  const member = await writer({ username: 'reaction-browser', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint });
  await member.page.route('**/api/prepare_profile_name', route => route.abort());
  await change(member.row, 'kim');
  const error = member.row.locator('[data-role="compose-identity-error"]');
  await error.waitFor({ state: 'visible', timeout: 30000 });
  assert.equal(await error.textContent(), "Couldn't save your name. Check your connection and try again.");
  assert.equal(await member.row.locator('[data-role="compose-identity-input"]').isVisible(), true, 'the editor stays open to retry');
  await member.row.screenshot({ path: '/tmp/compose-identity-row-error-390.png' });
  assert.equal(await member.body.inputValue(), 'A draft that must survive');
  await member.page.unroute('**/api/prepare_profile_name');
  await member.row.getByRole('button', { name: 'Done' }).click();
  await member.row.locator('[data-role="compose-identity-editor"]').waitFor({ state: 'hidden', timeout: 30000 });
  assert.equal(await member.row.locator('[data-role="compose-identity-name"]').textContent(), 'kim');
  assert.equal(await stored(member.page, 'fingerprint'), fingerprint, 'a registered key is renamed, not replaced');
  assert.ok(member.sent.includes('finalize_profile_name'));
  assert.equal(await member.body.inputValue(), 'A draft that must survive');
  // Where renaming is off, a registered key has no Change.
  await member.page.evaluate(() => { window.forumBrowserIdentityOptions.usernameChangesEnabled = false; window.dispatchEvent(new Event('forum:identity-changed')); });
  assert.equal(await member.row.getByRole('button', { name: 'Change' }).isVisible(), false);
  assert.deepEqual(member.dialogs, []); assert.deepEqual(member.errors, []);
  await member.context.close();

  // A Like signed offline before the key was registered carries its public key, so Change can replace the key
  // and the Outbox still sends the Like, registering the key that signed it.
  const queued = await writer({ username: 'mouse', public_key: localKeys.publicKey, private_key: localKeys.privateKey, fingerprint: localFingerprint });
  queued.page.on('request', r => { const m = /\/api\/(apply_signed_reaction)$/.exec(r.url()); if (m) queued.sent.push(m[1]); });
  await queued.page.evaluate(() => window.ForumLazyComposeSigning && window.ForumLazyComposeSigning.load());
  await queued.page.waitForFunction(() => window.ForumBrowserSigning && window.ForumBrowserSigning.signCanonicalRecord && window.__forumBrowserIdentity);
  for (const name of ['outbox_store', 'outbox_storage', 'outbox_intent', 'outbox_sender']) await queued.page.addScriptTag({ url: '/assets/' + name + '.js' });
  const offlineLike = await queued.page.evaluate(() => window.forumOutboxIntent.createSignedIntent({ action: 'reaction', state: 'queued',
    target: { kind: 'thread', id: 'root-001' }, summary: 'Like Hello world', payload: { kind: 'thread_tag', threadId: 'root-001', tag: 'like' } })
    .then(item => window.forumOutboxStorage.save(item).then(() => item)));
  assert.equal(offlineLike.intent.publicKey, localKeys.publicKey, 'a first action carries its public key');
  await change(queued.row, 'squeak');
  await queued.row.locator('[data-role="compose-identity-editor"]').waitFor({ state: 'hidden', timeout: 30000 });
  assert.notEqual(await stored(queued.page, 'fingerprint'), localFingerprint, 'the local key is replaced');
  assert.deepEqual(queued.sent, [], 'Change sends nothing');
  const sent = await queued.page.evaluate(id => window.forumOutboxStorage.load(id).then(item => window.forumOutboxSender.send(item)), offlineLike.id);
  assert.equal(sent.state, 'accepted', JSON.stringify(sent.outcome || sent.error || ''));
  assert.deepEqual(queued.sent, ['link_identity', 'apply_signed_reaction']);
  assert.match(await (await fetch(base + '/api/get_profile?profile_slug=openpgp-' + localFingerprint.toLowerCase())).text(), /mouse/, 'the Like is credited to the key that signed it');
  assert.deepEqual(queued.dialogs, []); assert.deepEqual(queued.errors, []);
  await queued.context.close();

  // Without JavaScript the row stays hidden and the composer is unchanged.
  const noScript = await browser.newContext({ javaScriptEnabled: false });
  const plain = await noScript.newPage();
  await plain.goto(base + '/compose/thread');
  assert.equal(await plain.locator('[data-compose-identity]').isVisible(), false);
  assert.equal(await plain.locator('textarea[name="body"]').isVisible(), true);
  await noScript.close();
  console.log('PASS compose identity row: six composers × three key states × phone/desktop at parse, static server HTML, live rename, local Change then first reply, registered rename with failure/Retry, renaming off, offline Like sent after its key was replaced, no JavaScript');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
