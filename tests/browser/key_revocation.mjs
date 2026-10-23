import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm, readdir } from 'node:fs/promises';
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
async function keys(name) { return pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored', date: new Date(Date.now() - 60000) }); }
const operator = await keys('browser-inviter');
const fingerprint = (await pgp.readKey({ armoredKey: operator.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'approval-browser-'));
let server, browser, logs = '', activityDebug = [];
try {
  const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
  assert.equal(fixture.status, 0, fixture.stderr);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false',
      FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL: 'false', PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  await context.addInitScript(({ operator, fingerprint }) => {
    for (const [key, value] of Object.entries({ username: 'browser-inviter', public_key: operator.publicKey, private_key: operator.privateKey, fingerprint, published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
  }, { operator, fingerprint });
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  page.on('pageerror', error => activityDebug.push(error.message));
  page.on('request', request => { if (request.url().includes('forte_activity_')) activityDebug.push(request.url()); });
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  async function target(name) {
    const generated = await keys(name);
    const response = await context.request.post(base + '/api/link_identity', { form: { public_key: generated.publicKey } });
    const values = Object.fromEntries((await response.text()).trim().split('\n').map(line => { const i = line.indexOf('='); return [line.slice(0, i), line.slice(i + 1)]; }));
    assert.ok(values.profile_slug, JSON.stringify(values));
    return { ...values, ...generated };
  }
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 844 });
    const username = width === 390 ? 'r'.repeat(80) : 'revoke-' + width;
    const member = await target(username);
    await page.goto(base + '/tools/user-access/profiles/' + member.profile_slug);
    page.once('dialog', dialog => dialog.accept());
    await page.locator('[data-signed-approval-form] button').click();
    await page.waitForURL(/approval=success/);
    const affected = await browser.newContext({ viewport: { width, height: 844 }, serviceWorkers: 'block' });
    const memberFingerprint = (await pgp.readKey({ armoredKey: member.publicKey })).getFingerprint();
    await affected.addInitScript(({ member, memberFingerprint, width }) => {
      for (const [key, value] of Object.entries({ username: 'revoke-' + width, public_key: member.publicKey, private_key: member.privateKey, fingerprint: memberFingerprint, published_fingerprint: memberFingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
    }, { member, memberFingerprint, width });
    const affectedPage = await affected.newPage();
    await affectedPage.goto(base + '/account/key/');
    await affectedPage.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
    const account = base + '/tools/user-access/accounts/' + username;
    let response = await page.goto(account); assert.match(response.headers()['cache-control'], /no-store/);
    const themes = (await readdir(join(project, 'public/assets'))).map(name => name.match(/^theme-([a-z0-9]+)\.css$/)?.[1]).filter(Boolean);
    for (const theme of themes) {
      await page.evaluate(theme => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme), theme);
      await page.reload();
      await page.locator('[data-revoke-key]').click();
      await page.locator('[data-key-revocation-review]').waitFor({ state: 'visible' });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${theme} ${width}: page overflow`);
      assert.ok((await page.locator('[data-revocation-confirm]').boundingBox()).height >= 44, `${theme} ${width}: signing target`);
      await page.locator('[data-revocation-cancel]').click();
    }
    const button = page.locator('[data-revoke-key]');
    await button.click();
    const review = page.locator('[data-key-revocation-review]'); await review.waitFor({ state: 'visible' });
    assert.equal(await review.locator('[data-revocation-key]').innerText(), member.identity_id);
    assert.match(await review.locator('[data-revocation-cutoff]').innerText(), /^\d{4}-\d{2}-\d{2}T/);
    await review.locator('[data-revocation-last-key]').waitFor({ state: 'visible' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.REVOCATION_SCREENSHOT_DIR) await page.screenshot({ path: join(process.env.REVOCATION_SCREENSHOT_DIR, width + '-review.png'), fullPage: true });
    await review.locator('[data-revocation-cancel]').click();
    assert.equal(await review.isVisible(), false);
    assert.equal(await button.count(), 1);
    await button.click(); await review.waitFor({ state: 'visible' });
    if (width === 390) {
      await target(username); // Another pending key changes the reviewed account context.
      await review.locator('[data-revocation-confirm]').click();
      await page.getByRole('button', { name: 'Review again', exact: true }).waitFor();
      await page.getByRole('button', { name: 'Review again', exact: true }).click();
      await page.getByRole('button', { name: 'Sign & revoke key', exact: true }).waitFor();
    } else {
      await page.route('**/api/create_prepared_key_revocation', async route => { await route.fetch(); await route.abort('failed'); });
      await review.locator('[data-revocation-confirm]').click();
      await page.getByRole('button', { name: 'Retry', exact: true }).waitFor();
      assert.match(await review.locator('[data-revocation-feedback]').innerText(), /Could not confirm the outcome/);
      assert.equal(await review.locator('[data-revocation-cancel]').innerText(), 'Close');
      await page.unroute('**/api/create_prepared_key_revocation');
    }
    const finalize = page.waitForResponse(r => r.url().endsWith('/api/create_prepared_key_revocation'));
    await review.locator('[data-revocation-confirm]').click();
    response = await finalize; assert.equal(response.status(), 200);
    assert.match(response.headers()['cache-control'], /no-store/);
    await page.waitForLoadState('networkidle');
    await page.waitForFunction(() => document.querySelector('main').innerText.includes('Revoked'));
    assert.equal(await page.locator('[data-revoke-key]').count(), 0);
    assert.match(await page.locator('.account-key-history').innerText(), /Revoked/);
    await page.getByText('Revocation evidence', { exact: true }).click();
    assert.match(await page.locator('.account-key-history').innerText(), /Revocation cutoff:/);
    const oldSession = await affected.request.get(base + '/api/auth_status'); assert.equal(oldSession.status(), 401); assert.match(await oldSession.text(), /code=key_revoked/);
    await affectedPage.goto(base + '/account/key/'); await affectedPage.locator('[data-key-revoked-notice]').waitFor();
    assert.equal(await affectedPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    const securityDenied = await affected.request.get(base + '/activity/?view=security'); assert.equal(securityDenied.status(), 403);
    const fresh = await browser.newContext();
    const challenge = (await (await fresh.request.get(base + '/api/auth_challenge')).text()).match(/^challenge=(.+)$/m)[1];
    const detached = await pgp.sign({ message: await pgp.createMessage({ text: challenge }), signingKeys: await pgp.readPrivateKey({ armoredKey: member.privateKey }), detached: true });
    const badProof = await fresh.request.post(base + '/api/authenticate_identity', { form: { identity_id: member.identity_id, challenge, detached_signature: 'invalid signature' } });
    assert.equal(badProof.status(), 403); assert.doesNotMatch(await badProof.text(), /code=key_revoked/);
    const login = await fresh.request.post(base + '/api/authenticate_identity', { form: { identity_id: member.identity_id, challenge, detached_signature: detached } });
    assert.equal(login.status(), 403); assert.match(await login.text(), /code=key_revoked/);
    await fresh.close(); await affected.close();

    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  }
  await page.goto(base + '/tools/user-access/accounts/browser-inviter');
  assert.equal(await page.locator('[data-revoke-key]').count(), 0);
  console.log('PASS real signed revocation on desktop/phone: exact key/cutoff, last-key warning, cancel, one acceptance, no-store, revoked state, root exclusion, retained history, old-session denial, fresh-login denial, affected-device recovery, live stale-context review, committed lost-response retry, 16-theme review/overflow');
} catch (error) { console.error(logs.slice(-12000)); throw error; }
finally { if (browser) await browser.close(); if (server) server.kill(); await rm(root, { recursive: true, force: true }); }
