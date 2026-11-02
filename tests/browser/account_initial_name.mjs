import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
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
const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'browser-inviter' }], format: 'armored', date: new Date(Date.now() - 60000) });
const fingerprint = (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'invite-browser-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
let server, logs = '', lastPage;
const contexts = [];
async function start(privateSite, usernameChanges = true) {
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: String(privateSite), FORUM_USERNAME_CHANGES_ENABLED: String(usernameChanges),
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  const base = `http://127.0.0.1:${port}`;
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); return base; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  throw Error('Server did not start');
}
async function contextWithIdentity(base, identity) {
  const context = await browser.newContext({ serviceWorkers: 'block' }); contexts.push(context);
  if (identity) await context.addInitScript(({ keys, fingerprint }) => {
    if (!localStorage.getItem('forum_pki_private_key')) {
      localStorage.setItem('forum_pki_username', 'browser-inviter');
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint.toUpperCase());
    }
  }, identity);
  const page = await context.newPage(); lastPage = page; page.setDefaultTimeout(20000);
  return { context, page };
}
try {
  for (const privateSite of [false, true]) {
    const base = await start(privateSite, false);
    const reader = await contextWithIdentity(base);
    await reader.page.goto(base + '/account/key/');
    await reader.page.evaluate(async () => {
      await window.__forumBrowserIdentity.ensureActionIdentity(null, null, { temporaryName: 'guest' });
    });
    const fingerprint = await reader.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    await reader.page.evaluate(() => window.PrivateSiteAuth.authenticate({ navigate: false, force: true }));
    await reader.page.reload();
    const change = reader.page.locator('[data-action=rename-profile]');
    await change.waitFor({ state: 'visible' });
    await change.click();
    const editor = reader.page.locator('[data-role=name-editor-input]');
    await editor.fill('account-initial-' + Number(privateSite));
    await reader.page.locator('[data-action=cancel-name]').click();
    assert.equal(await reader.page.evaluate(() => localStorage.getItem('forum_pki_username')), 'guest');
    await change.click();
    await editor.fill('account-initial-' + Number(privateSite));
    await reader.context.route('**/api/finalize_profile_name', async route => { await route.fetch(); await route.abort('failed'); });
    await reader.page.locator('[data-role=name-editor] button[type=submit]').click();
    await reader.page.locator('[data-action=retry-account-action]').waitFor({ state: 'visible' });
    assert.equal(await editor.inputValue(), 'account-initial-' + Number(privateSite));
    await reader.context.unroute('**/api/finalize_profile_name');
    await reader.page.reload();
    await change.click();
    await editor.fill('account-initial-' + Number(privateSite));
    await reader.page.locator('[data-role=name-editor] button[type=submit]').click();
    await reader.page.waitForFunction(name => localStorage.getItem('forum_pki_username') === name, 'account-initial-' + Number(privateSite));
    assert.equal(await reader.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), fingerprint);
    await change.waitFor({ state: 'hidden' });
    await reader.page.reload();
    assert.equal(await change.isVisible(), false);
    assert.equal(await reader.page.evaluate(() => localStorage.getItem('forum_pki_username')), 'account-initial-' + Number(privateSite));
    console.log('PASS Account initial naming, flag off, same key, cancel and lost-response reload: ' + (privateSite ? 'private' : 'public'));
  }
} catch (error) {
  console.error('Root:', root, 'Page:', lastPage?.url());
  if (lastPage) console.error((await lastPage.locator('body').innerText()).slice(0, 2400));
  await writeFile(join(root, 'server.log'), logs);
  throw error;
} finally {
  await browser.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  if (process.env.KEEP_INVITE_FIXTURE !== '1') await rm(root, { recursive: true, force: true });
}
