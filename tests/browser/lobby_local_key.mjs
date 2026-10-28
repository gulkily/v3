// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/lobby_local_key.mjs
// On a members-only site, signing in still registers a key kept in this browser, without dialogs.
import assert from 'node:assert/strict';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const keys = name => pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored', date: new Date(Date.now() - 60000) });
const operator = await keys('browser-inviter');
const visitor = await keys('visitor');
const visitorFingerprint = (await pgp.readKey({ armoredKey: visitor.publicKey })).getFingerprint().toUpperCase();
const root = await mkdtemp(join(tmpdir(), 'lobby-local-key-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
  env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'true',
    PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', d => { logs += d; });
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
async function open(storage) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  await context.addInitScript(storage => { if (sessionStorage.getItem('seeded')) return; sessionStorage.setItem('seeded', '1'); for (const [k, v] of Object.entries(storage)) localStorage.setItem('forum_pki_' + k, v); }, storage);
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  const seen = { dialogs: [], errors: [], requests: [] };
  page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  page.on('request', r => { const m = /\/api\/(prepare_identity|create_identity|authenticate_identity)$/.exec(r.url()); if (m) seen.requests.push(m[1]); });
  return { context, page, seen };
}
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // A key kept in this browser signs in from the Lobby: registered once, then authenticated, waiting for approval.
  const local = await open({ username: 'visitor', public_key: visitor.publicKey, private_key: visitor.privateKey, fingerprint: visitorFingerprint });
  await local.page.goto(base + '/lobby/');
  await local.page.getByText('Approval pending.').waitFor();
  assert.deepEqual(local.seen.requests.slice(0, 3), ['prepare_identity', 'create_identity', 'authenticate_identity']);
  assert.equal(await local.page.evaluate(() => localStorage.getItem('forum_pki_published_fingerprint')), visitorFingerprint);
  assert.deepEqual(local.seen.dialogs, []); assert.deepEqual(local.seen.errors, []);
  await local.context.close();

  // A new visitor goes from the Lobby to Account, names a key and is registered there, because sign-in needs it.
  const fresh = await open({});
  await fresh.page.goto(base + '/lobby/');
  await fresh.page.getByRole('link', { name: 'Sign in or set up your account' }).click();
  await fresh.page.locator('[data-role="account-name-input"]').fill('newbie');
  await fresh.page.getByRole('button', { name: 'Continue' }).click();
  await fresh.page.waitForFunction(() => localStorage.getItem('forum_pki_published_fingerprint'));
  assert.ok(fresh.seen.requests.includes('create_identity'));
  await fresh.page.goto(base + '/lobby/');
  await fresh.page.getByText('Approval pending.').waitFor();
  assert.deepEqual(fresh.seen.dialogs, []); assert.deepEqual(fresh.seen.errors, []);
  await fresh.context.close();
  console.log('PASS members-only Lobby: local key registers and signs in; new visitor registers from Account; no dialogs');
} catch (error) { console.error(logs.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
