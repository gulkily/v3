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
async function start(privateSite) {
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: String(privateSite),
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
    const base = await start(privateSite);
    const inviter = await contextWithIdentity(base, { keys, fingerprint });
    await inviter.page.goto(base + '/account/key/');
    await inviter.page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
    let lastInvitation;
    async function issue(target = '/threads/root-001?from=invite#reply-001', enabled = true) {
      await inviter.page.goto(base + target);
      await inviter.page.locator('[data-invite-navigation]').first().click();
      const checkbox = inviter.page.locator('[name=include_destination]');
      assert.equal(await checkbox.isChecked(), false);
      assert.equal(await inviter.page.locator('[name=destination]').isDisabled(), true);
      if (enabled) {
        await checkbox.check();
        assert.equal(await inviter.page.locator('[name=destination]').inputValue(), target);
        await inviter.page.locator('[name=destination]').fill(target);
        await checkbox.uncheck(); await checkbox.check();
        assert.equal(await inviter.page.locator('[name=destination]').inputValue(), target);
      }
      const finalResponse = inviter.page.waitForResponse(response => response.url().endsWith('/api/create_prepared_invitation'));
      await inviter.page.locator('[data-invitation-issue-form] button[type=submit]').click();
      lastInvitation = await (await finalResponse).json();
      await inviter.page.waitForFunction(() => document.querySelector('[data-role=invitation-link]').value.includes('#invite='));
      assert.equal(await inviter.page.locator('[data-role=invitation-qr]').isVisible(), true);
      return inviter.page.locator('[data-role=invitation-link]').inputValue();
    }
    const url = await issue();
    const recipient = await contextWithIdentity(base);
    let dialogs = 0; recipient.page.on('dialog', async dialog => { dialogs++; await dialog.dismiss(); });
    await recipient.page.goto(url);
    await recipient.page.waitForURL(base + '/threads/root-001?from=invite#reply-001');
    assert.equal(dialogs, 0);
    assert.match(await recipient.page.locator('main').innerText(), /Hello world/);
    const originalKey = await recipient.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    await recipient.page.goto(url); // same-recipient recovery
    await recipient.page.waitForURL(base + '/threads/root-001?from=invite#reply-001');
    assert.equal(await recipient.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), originalKey);
    const approvedUrl = await issue();
    await recipient.page.goto(approvedUrl);
    await recipient.page.waitForURL(base + '/threads/root-001?from=invite#reply-001');
    const token = approvedUrl.split('#invite=')[1];
    assert.equal((await (await inviter.context.request.post(base + '/api/inspect_invitation', { form: { invite_token: token } })).json()).state, 'available');
    // Choose a name only after arrival, preserving key and approved session.
    await recipient.page.goto(base + '/account/key/');
    recipient.page.removeAllListeners('dialog');
    recipient.page.once('dialog', dialog => dialog.accept(privateSite ? 'named-private-reader' : 'named-public-reader'));
    await recipient.page.locator('[data-action=rename-profile]').click();
    await recipient.page.waitForFunction(() => document.querySelector('[data-role=browser-key-status]').textContent.includes('Username saved'));
    assert.equal(await recipient.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), originalKey);
    await recipient.page.reload();
    assert.match(await recipient.page.locator('[data-role=username-field]').innerText(), /named-(private|public)-reader/);
    const rebuild = spawnSync(join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')], { encoding: 'utf8' });
    assert.equal(rebuild.status, 0, rebuild.stderr);
    await recipient.page.goto(base + '/threads/root-001');
    assert.match(await recipient.page.locator('main').innerText(), /Hello world/);
    // Lost final response: server commits, browser sees failure, reload resumes without a second redemption.
    const lostUrl = await issue();
    const lost = await contextWithIdentity(base);
    await lost.context.route('**/api/create_prepared_invitation', async route => {
      await route.fetch(); await route.abort('failed');
    });
    await lost.page.goto(lostUrl);
    await lost.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    const lostKey = await lost.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    await lost.context.unroute('**/api/create_prepared_invitation');
    await lost.page.reload();
    await lost.page.waitForURL(base + '/threads/root-001?from=invite#reply-001');
    assert.equal(await lost.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), lostKey);
    // An existing pending key is reused rather than silently replaced.
    const pendingUrl = await issue();
    const pending = await contextWithIdentity(base);
    await pending.page.goto(base + '/account/key/');
    await pending.page.evaluate(async () => {
      await window.__forumBrowserIdentity.ensureReadyIdentity(null, null, { temporaryName: 'pending-' + crypto.randomUUID(), verifyPublishedIdentity: false });
    });
    const pendingKey = await pending.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    let pendingDialogs = 0; pending.page.on('dialog', async dialog => { pendingDialogs++; await dialog.dismiss(); });
    await pending.page.goto(pendingUrl);
    await pending.page.waitForURL(base + '/threads/root-001?from=invite#reply-001');
    assert.equal(await pending.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), pendingKey);
    assert.equal(pendingDialogs, 0);
    // A missing target preserves granted membership and normal navigation recovery.
    const missingUrl = await issue('/threads/missing-invite-target');
    const missing = await contextWithIdentity(base);
    await missing.page.goto(missingUrl);
    await missing.page.waitForURL(base + '/threads/missing-invite-target');
    assert.match(await missing.page.locator('main').innerText(), /Not Found/);
    await missing.page.goto(base + '/threads/root-001');
    assert.match(await missing.page.locator('main').innerText(), /Hello world/);
    // Storage denial never consumes a targeted invite.
    const storageUrl = await issue();
    const storage = await contextWithIdentity(base);
    await storage.context.addInitScript(() => { Storage.prototype.getItem = () => { throw Error('Storage disabled'); }; });
    await storage.page.goto(storageUrl);
    await storage.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.equal((await (await inviter.context.request.post(base + '/api/inspect_invitation', { form: { invite_token: storageUrl.split('#invite=')[1] } })).json()).state, 'available');
    const revokedUrl = await issue();
    await inviter.page.evaluate(async issued => {
      const post = async (url, body) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(body) })).json();
      const prepared = await post('/api/prepare_invitation', { action: 'revoke', invitation_id: issued.invitation_id, verification_hash: issued.verification_hash });
      if (prepared.status !== 'ok') throw Error(prepared.error);
      const final = await post('/api/create_prepared_invitation', { ...prepared, author_identity_id: window.__forumBrowserIdentity.currentAuthorIdentityId(), detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record) });
      if (final.status !== 'ok') throw Error(final.error);
    }, lastInvitation);
    const revoked = await contextWithIdentity(base);
    await revoked.page.goto(revokedUrl);
    await revoked.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.equal(await revoked.page.evaluate(() => localStorage.getItem('forum_pki_private_key')), null);
    await inviter.context.route('**/api/prepare_invitation', async route => {
      const body = new URLSearchParams(route.request().postData());
      body.set('expires_at', new Date(Date.now() + 5000).toISOString().replace(/\.\d{3}Z$/, 'Z'));
      await route.continue({ postData: body.toString() });
    });
    const expiredUrl = await issue();
    await inviter.context.unroute('**/api/prepare_invitation');
    await new Promise(resolve => setTimeout(resolve, 5100));
    const expired = await contextWithIdentity(base);
    await expired.page.goto(expiredUrl);
    await expired.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.equal(await expired.page.evaluate(() => localStorage.getItem('forum_pki_private_key')), null);
    const manualUrl = await issue('/threads/root-001', false);
    const manual = await contextWithIdentity(base);
    await manual.page.goto(manualUrl);
    await manual.page.locator('[data-action=redeem-invitation]').waitFor({ state: 'visible' });
    assert.equal(await manual.page.evaluate(() => localStorage.getItem('forum_pki_private_key')), null);
    manual.page.once('dialog', dialog => dialog.accept('manual-reader-' + Number(privateSite)));
    await manual.page.locator('[data-action=redeem-invitation]').click();
    await manual.page.waitForURL(base + '/');
    const invalid = await contextWithIdentity(base);
    await invalid.page.goto(base + '/lobby/#invite=' + '0'.repeat(32));
    await invalid.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.equal(await invalid.page.evaluate(() => localStorage.getItem('forum_pki_private_key')), null);
    const foreign = await contextWithIdentity(base, { keys, fingerprint });
    await foreign.page.goto(url);
    await foreign.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.match(await foreign.page.locator('[data-role=invitation-redemption-feedback]').innerText(), /another identity/);
    console.log(`PASS ${privateSite ? 'members-only' : 'public'}: source opt-in, QR, fresh/approved/recovery, same-key naming/rebuild, pending, lost-response/storage/missing recovery, manual, invalid/expired/revoked/foreign`);
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
