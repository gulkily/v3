// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/compose_identity_row.mjs
// Every signed composer shows "Post as {name} · Change", correct before deferred scripts run.
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
const root = await mkdtemp(join(tmpdir(), 'compose-identity-row-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
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
    assert.match(html, /<p class="meta compose-identity" data-compose-identity hidden>Post as <span class="compose-identity__name" data-role="compose-identity-name">guest<\/span>/, composer.name);
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
  // Without JavaScript the row stays hidden and the composer is unchanged.
  const noScript = await browser.newContext({ javaScriptEnabled: false });
  const plain = await noScript.newPage();
  await plain.goto(base + '/compose/thread');
  assert.equal(await plain.locator('[data-compose-identity]').isVisible(), false);
  assert.equal(await plain.locator('textarea[name="body"]').isVisible(), true);
  await noScript.close();
  console.log('PASS compose identity row: six composers × three key states × phone/desktop at parse, static server HTML, live rename, no JavaScript');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
