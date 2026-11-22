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
// Guest visibility: every composer says "Posting as guest · Choose a name" until this browser's key has a name;
// the link opens naming on Account, and a registered guest key's Name row reads "Choose a name".
const shots = process.env.GUEST_NAME_SHOTS || '';
try {
  const base = await start(false, false);
  const guest = await contextWithIdentity(base);
  await guest.page.setViewportSize({ width: 390, height: 844 });
  await guest.page.goto(base + '/compose/thread');
  const note = guest.page.locator('[data-compose-kind="thread"] .compose-guest-note');
  assert.equal(await note.isVisible(), true, 'fresh browser sees the guest line');
  assert.match(await note.innerText(), /Posting as guest · Choose a name/);
  if (shots) await guest.page.screenshot({ path: join(shots, 'compose-guest.png') });
  await guest.page.goto(base + '/threads/root-001');
  // A collapsed reply box shows only its body until focused, like the rest of its controls.
  await guest.page.locator('[data-compose-kind="reply"] textarea[name="body"]').first().click();
  assert.equal(await guest.page.locator('[data-compose-kind="reply"] .compose-guest-note').first().isVisible(), true, 'reply composer too');
  await guest.page.goto(base + '/compose/thread');
  await note.getByRole('link', { name: 'Choose a name' }).click();
  await guest.page.waitForURL(base + '/account/key/#choose-name');
  await guest.page.waitForFunction(() => document.activeElement && document.activeElement.matches('[data-role="account-name-input"]'));

  // A registered key still named guest: Account says "Choose a name" and opens an empty editor from the link.
  await guest.page.evaluate(async () => {
    await window.__forumBrowserIdentity.ensureActionIdentity(null, null, { temporaryName: 'guest' });
  });
  await guest.page.evaluate(() => window.PrivateSiteAuth.authenticate({ navigate: false, force: true }));
  await guest.page.goto(base + '/compose/thread');
  assert.equal(await note.isVisible(), true, 'registered guest key still sees the line');
  await guest.page.goto(base + '/account/key/#choose-name');
  const change = guest.page.locator('[data-action=rename-profile]');
  await change.waitFor({ state: 'visible' });
  assert.equal((await change.innerText()).trim(), 'Choose a name');
  const editor = guest.page.locator('[data-role=name-editor-input]');
  await editor.waitFor({ state: 'visible' });
  assert.equal(await editor.inputValue(), '', 'editor starts empty for a guest');
  if (shots) await guest.page.screenshot({ path: join(shots, 'account-guest.png') });
  await editor.fill('named-guest');
  await guest.page.locator('[data-role=name-editor] [type=submit]').click();
  await guest.page.waitForFunction(() => localStorage.getItem('forum_pki_username') === 'named-guest');
  await guest.page.goto(base + '/compose/thread');
  assert.equal(await note.isVisible(), false, 'named browser does not see the line');
  assert.equal(await guest.page.evaluate(() => document.documentElement.hasAttribute('data-viewer-named')), true);

  // A named key from the start never sees it, on first paint included.
  const named = await contextWithIdentity(base, { keys, fingerprint });
  await named.page.goto(base + '/compose/thread');
  assert.equal(await named.page.locator('.compose-guest-note').first().isVisible(), false);
  console.log('PASS guest name visibility: composer line, Choose a name link, Account label and empty editor, hidden once named');
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
