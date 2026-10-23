// Two browsers: a key that started as "guest" asks to join the holder's "ilya";
// the request survives reload and can be cancelled. Screenshots go to $SHOT_DIR when set.
import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
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
const generate = async name => {
  const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored', date: new Date(Date.now() - 60000) });
  return { name, ...keys, fingerprint: (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint() };
};
const holderKey = await generate('ilya');
const requesterKey = await generate('guest');
const operatorKey = await generate('operator');
const root = await mkdtemp(join(tmpdir(), 'username-join-browser-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/username_join_browser_fixture.php'), root],
  { input: JSON.stringify({ holder: holderKey.publicKey, requester: requesterKey.publicKey, operator: operatorKey.publicKey }), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
const shotDir = process.env.SHOT_DIR || '';
let server, logs = '';
async function start() {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_USERNAME_CHANGES_ENABLED: 'true',
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  const base = `http://127.0.0.1:${port}`;
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); return base; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  throw Error('Server did not start');
}
async function signedIn(base, key, viewport = { width: 1280, height: 900 }) {
  const context = await browser.newContext({ serviceWorkers: 'block', viewport });
  await context.addInitScript(key => {
    if (!localStorage.getItem('forum_pki_private_key')) {
      localStorage.setItem('forum_pki_username', key.name);
      localStorage.setItem('forum_pki_public_key', key.publicKey);
      localStorage.setItem('forum_pki_private_key', key.privateKey);
      localStorage.setItem('forum_pki_fingerprint', key.fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', key.fingerprint.toUpperCase());
    }
  }, key);
  const page = await context.newPage(); page.setDefaultTimeout(20000);
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  return page;
}
async function shoot(page, name) {
  if (!shotDir) return;
  for (const theme of ['light', 'word97', 'chicago', 'midnight']) {
    await page.evaluate(theme => {
      document.documentElement.setAttribute('data-theme', theme);
      document.getElementById('theme-stylesheet').setAttribute('href', window.forumThemeStylesheetPaths[theme]);
    }, theme);
    await page.waitForTimeout(150);
    await page.screenshot({ path: join(shotDir, `${name}-${theme}.png`), fullPage: true });
  }
}
async function assertNoOverflow(page) {
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'page overflows horizontally');
}
async function requestName(page, name) {
  page.once('dialog', dialog => dialog.accept(name));
  await page.locator('[data-action=rename-profile]').click();
}
try {
  const base = await start();
  for (const viewport of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
    const label = viewport.width < 600 ? 'phone' : 'desktop';
    const requester = await signedIn(base, requesterKey, viewport);
    const pending = requester.locator('[data-role=join-request]');
    assert.equal(await pending.isVisible(), false);
    await requestName(requester, 'ilya');
    await pending.waitFor({ state: 'visible' });
    assert.equal(await pending.locator('[data-role=join-request-text]').innerText(), 'Waiting for ilya to accept.');
    assert.equal(await requester.locator('#simple-status').isVisible(), false);
    assert.equal(await requester.locator('[data-role=username-field]').innerText(), 'guest');
    await requester.reload();
    await pending.waitFor({ state: 'visible' });
    await assertNoOverflow(requester);
    await shoot(requester, `requester-pending-${label}`);
    await pending.locator('[data-action=cancel-join-request]').click();
    await pending.waitFor({ state: 'hidden' });
    await requester.reload();
    await requester.waitForLoadState('networkidle');
    assert.equal(await pending.isVisible(), false);
    await requester.context().close();
  }
  console.log('PASS requester: pending request shown, survives reload, cancel clears it');

  const longName = 'a-very-long-requester-name-' + 'x'.repeat(50);
  for (const viewport of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
    const label = viewport.width < 600 ? 'phone' : 'desktop';
    const holder = await signedIn(base, holderKey, viewport);
    const section = holder.locator('[data-role=join-requests]');
    await holder.waitForLoadState('networkidle');
    assert.equal(await section.isVisible(), false);

    const requester = await signedIn(base, requesterKey, viewport);
    if (label === 'desktop') {
      await requestName(requester, longName);
      await requester.locator('[data-role=username-field]').filter({ hasText: longName }).waitFor();
    }
    await requestName(requester, 'ilya');
    await requester.locator('[data-role=join-request]').waitFor({ state: 'visible' });
    const operator = await signedIn(base, operatorKey, viewport);
    await requestName(operator, 'ilya');
    await operator.locator('[data-role=join-request]').waitFor({ state: 'visible' });

    await holder.reload();
    await section.waitFor({ state: 'visible' });
    const items = section.locator('li');
    assert.equal(await items.count(), 2);
    assert.match(await items.first().innerText(), new RegExp(longName + ' asks to use ilya\\. This key will be able to act as ilya\\.'));
    await assertNoOverflow(holder);
    await shoot(holder, `holder-requests-${label}`);

    // The operator withdraws before the holder answers: the holder sees why nothing happened.
    await operator.locator('[data-action=cancel-join-request]').click();
    await operator.locator('[data-role=join-request]').waitFor({ state: 'hidden' });
    await items.nth(1).locator('[data-action=decline-join-request]').click();
    const status = items.nth(1).locator('[data-role=join-request-status]');
    await status.waitFor({ state: 'visible' });
    assert.equal(await status.innerText(), 'This request is no longer pending.');
    await shoot(holder, `holder-error-${label}`);

    if (label === 'desktop') {
      await requestName(operator, 'ilya');
      await operator.locator('[data-role=join-request]').waitFor({ state: 'visible' });
      await holder.reload();
      await items.nth(1).waitFor();
      await items.nth(1).locator('[data-action=decline-join-request]').click();
      await items.nth(1).waitFor({ state: 'detached' });
      await operator.reload();
      await operator.waitForLoadState('networkidle');
      assert.equal(await operator.locator('[data-role=join-request]').isVisible(), false);
      assert.equal(await operator.locator('[data-role=username-field]').innerText(), 'operator');
      await requester.locator('[data-action=cancel-join-request]').click();
      await requester.locator('[data-role=join-request]').waitFor({ state: 'hidden' });
    } else {
      await holder.reload();
      await items.first().locator('[data-action=accept-join-request]').click();
      await section.waitFor({ state: 'hidden' });
      await requester.reload();
      await requester.locator('[data-role=username-field]').filter({ hasText: /^ilya$/ }).waitFor();
      assert.equal(await requester.locator('[data-role=join-request]').isVisible(), false);
      const keys = await (await holder.context().request.get(base + '/api/private_messages/recipient_keys?username_token=ilya')).json();
      assert.deepEqual(keys.keys.map(key => key.identity_id).sort(),
        [holderKey, requesterKey].map(key => 'openpgp:' + key.fingerprint.toLowerCase()).sort());
      const rebuild = spawnSync(join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')], { encoding: 'utf8' });
      assert.equal(rebuild.status, 0, rebuild.stderr);
      const rebuilt = await (await holder.context().request.get(base + '/api/private_messages/recipient_keys?username_token=ilya')).json();
      assert.deepEqual(rebuilt.keys.map(key => key.identity_id).sort(), keys.keys.map(key => key.identity_id).sort());
      await holder.goto(base + '/user/ilya');
      await assertNoOverflow(holder);
    }
    for (const page of [holder, requester, operator]) await page.context().close();
  }
  console.log('PASS holder: hidden when empty, lists requests, stale-request message, decline, accept joins ilya and survives ./v3 rebuild');
} catch (error) {
  console.error(logs);
  throw error;
} finally {
  server?.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
