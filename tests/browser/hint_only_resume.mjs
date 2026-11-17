// A public-site browser that holds a registered key and an identity hint cookie, but no
// session, signs in on its next page load. Member-only tools need that session.
// Run: node tests/browser/hint_only_resume.mjs
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
const keys = {};
for (const role of ['author', 'operator']) keys[role] = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: `retraction-${role}` }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'hint-only-resume-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/post_retraction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seed.status, 0, seed.stderr);
const { identities } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const fingerprint = (await openpgp.readKey({ armoredKey: keys.operator.publicKey })).getFingerprint().toUpperCase();
  await context.addCookies([{ name: 'identity_hint', value: identities.operator, url: base }]);
  await context.addInitScript(({ key, fingerprint }) => {
    localStorage.setItem('forum_pki_private_key', key.privateKey);
    localStorage.setItem('forum_pki_public_key', key.publicKey);
    localStorage.setItem('forum_pki_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_username', 'retraction-operator');
  }, { key: keys.operator, fingerprint });
  // The hint names an approved operator, but nothing is signed in yet.
  assert.equal((await context.request.get(base + '/api/auth_status')).status(), 401);
  const page = await context.newPage(); page.setDefaultTimeout(20000);
  const signedIn = page.waitForResponse(response => response.url().endsWith('/api/authenticate_identity'));
  signedIn.catch(() => {});
  await page.goto(base + '/threads/root-001');
  assert.equal(await page.locator('[data-public-auth-resume="true"], [data-private-site-auth-state]').count() > 0, true);
  assert.equal((await signedIn).status(), 200);
  assert.equal((await context.request.get(base + '/api/auth_status')).status(), 200);
  // Once signed in, pages stop asking the browser to resume.
  await page.goto(base + '/threads/root-001');
  assert.equal(await page.locator('[data-public-auth-resume="true"]').count(), 0);
  await context.close();
  console.log('PASS hint-only browser signs in on a public page; signed-in pages do not resume');
} catch (error) { console.error(serverLog.slice(-4000)); throw error; }
finally { await browser.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true }); }
