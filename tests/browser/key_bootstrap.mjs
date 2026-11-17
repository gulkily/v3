// Session key bootstrap in a real browser: a signed-in browser loses its stored key (as
// iPhone Safari does after seven days) but keeps its cookies, and the next page load
// gets it a new approved key with one notice. Reloading makes no second key, and a
// browser that removed its key on purpose (which signs out) gets none.
// Run: node tests/browser/key_bootstrap.mjs
import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
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
const keys = { author: await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'bootstrap-member' }], format: 'armored' }) };
const root = await mkdtemp(join(tmpdir(), 'key-bootstrap-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'secrets.php'), SIGNED_REACTION_TEST_ROOT: root,
  VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'), VIEWER_SESSION_DATABASE_PATH: join(root, 'sessions.sqlite3'),
  // The test server speaks plain HTTP, so the HTTP flag is on too.
  FORUM_SESSION_KEY_BOOTSTRAP_ENABLED: '1', FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED: '1' };
const seed = spawnSync('php', [join(project, 'tests/Support/post_retraction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seed.status, 0, seed.stderr);
const { identities } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const stored = (page, name) => page.evaluate(key => localStorage.getItem(key) || '', name);
const waitFor = async (check, label) => {
  for (let n = 0; n < 150; n++) { if (await check()) return; await new Promise(r => setTimeout(r, 100)); }
  throw new Error('Timed out: ' + label + '\n' + serverLog.slice(-2000));
};
try {
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const context = await browser.newContext({ serviceWorkers: 'block' });
  await context.addCookies([{ name: 'identity_hint', value: identities.author, url: base }]);
  const page = await context.newPage();
  const fingerprint = (await openpgp.readKey({ armoredKey: keys.author.publicKey })).getFingerprint().toUpperCase();
  await page.goto(base + '/');
  await page.evaluate(({ key, fingerprint }) => {
    localStorage.setItem('forum_pki_private_key', key.privateKey);
    localStorage.setItem('forum_pki_public_key', key.publicKey);
    localStorage.setItem('forum_pki_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_username', 'bootstrap-member');
  }, { key: keys.author, fingerprint });

  // 1. A browser with a key signs in once so its session records which browser it is.
  await page.reload();
  await waitFor(async () => (await stored(page, 'forum_session_device_recorded')) === fingerprint.toLowerCase(), 'device recorded');

  // 2. Safari's wipe: script storage gone, cookies kept. The next load bootstraps a key.
  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await page.waitForSelector('[data-role="key-bootstrap-notice"]', { timeout: 15000 });
  assert.equal((await page.textContent('[data-role="key-bootstrap-notice"] .app-version-banner__text')).trim(), 'This browser lost its key and has a new one.');
  // Optional: KEY_BOOTSTRAP_SCREENSHOT=/tmp/notice.png saves the notice for a look at the layout.
  if (process.env.KEY_BOOTSTRAP_SCREENSHOT) await page.screenshot({ path: process.env.KEY_BOOTSTRAP_SCREENSHOT });
  const newFingerprint = (await stored(page, 'forum_pki_fingerprint')).toLowerCase();
  assert.ok(/^[a-f0-9]{40}$/.test(newFingerprint) && newFingerprint !== fingerprint.toLowerCase(), 'a new key');
  assert.equal(await stored(page, 'forum_pki_username'), 'bootstrap-member');
  const status = await page.evaluate(async () => (await fetch('/api/auth_status', { credentials: 'same-origin' })).status);
  assert.equal(status, 200, 'the session is signed in as an approved key');
  const profile = await (await fetch(base + '/api/get_profile?profile_slug=openpgp-' + newFingerprint)).text();
  assert.match(profile, /^Approved: yes$/m);

  // 3. Reloading keeps the key and shows no second notice.
  await page.reload();
  await new Promise(r => setTimeout(r, 1500));
  assert.equal((await stored(page, 'forum_pki_fingerprint')).toLowerCase(), newFingerprint);
  assert.equal(await page.$('[data-role="key-bootstrap-notice"]'), null);

  // 4. "Remove this key" signs out first; a browser that removed its key gets none back.
  await page.evaluate(async () => { await fetch('/api/clear_identity', { method: 'POST', credentials: 'same-origin' }); localStorage.clear(); });
  await page.reload();
  await new Promise(r => setTimeout(r, 1500));
  assert.equal(await stored(page, 'forum_pki_fingerprint'), '');
  assert.equal(await page.$('[data-role="key-bootstrap-notice"]'), null);
  console.log('key bootstrap browser check: ok');
} finally {
  await browser.close();
  server.kill();
  await rm(root, { recursive: true, force: true });
}
