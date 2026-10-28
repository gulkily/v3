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
  console.log(`first_post_identity: ok (create_identity ${timings[base + '/api/create_identity']} ms on the fixture)`);
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
