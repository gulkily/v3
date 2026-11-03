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
    const base = await start(privateSite);
    const inviter = await contextWithIdentity(base, { keys, fingerprint });
    await inviter.page.goto(base + '/account/key/');
    await inviter.page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
    async function issue(targeted = true) {
      return inviter.page.evaluate(async targeted => {
        const token = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));
        const hash = 'sha256:' + Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2, '0')).join('');
        const post = async (url, values) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(values) })).json();
        const prepared = await post('/api/prepare_invitation', { verification_hash: hash, max_redemptions: '2',
          expires_at: new Date(Date.now() + 3600000).toISOString().replace(/\.\d{3}Z$/, 'Z'), destination: targeted ? '/threads/root-001' : '' });
        if (prepared.status !== 'ok') throw Error(prepared.error);
        if (!prepared.canonical_record.includes('Max-Redemptions: 2\n')) throw Error('Missing signed limit');
        const issued = await post('/api/create_prepared_invitation', { ...prepared,
          author_identity_id: window.__forumBrowserIdentity.currentAuthorIdentityId(),
          detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record) });
        if (issued.status !== 'ok') throw Error(issued.error);
        return { token, issued };
      }, targeted);
    }
    async function revoke(issued) {
      await inviter.page.evaluate(async issued => {
        const post = async (url, values) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(values) })).json();
        const prepared = await post('/api/prepare_invitation', { action: 'revoke', invitation_id: issued.invitation_id, verification_hash: issued.verification_hash });
        if (prepared.status !== 'ok') throw Error(prepared.error);
        const result = await post('/api/create_prepared_invitation', { ...prepared,
          author_identity_id: window.__forumBrowserIdentity.currentAuthorIdentityId(),
          detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record) });
        if (result.status !== 'ok') throw Error(result.error);
      }, issued);
    }
    const invite = await issue();
    const url = base + '/lobby/#invite=' + invite.token;
    const first = await contextWithIdentity(base);
    await first.context.route('**/api/create_prepared_invitation', async route => { await route.fetch(); await route.abort('failed'); });
    await first.page.goto(url);
    await first.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    const key = await first.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'));
    await first.context.unroute('**/api/create_prepared_invitation');
    await first.page.reload(); await first.page.waitForURL(base + '/threads/root-001');
    assert.equal(await first.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), key);
    const second = await contextWithIdentity(base);
    await second.page.goto(url); await second.page.waitForURL(base + '/threads/root-001');
    const third = await contextWithIdentity(base);
    await third.page.goto(url); await third.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    assert.match(await third.page.locator('[data-role=invitation-redemption-feedback]').innerText(), /another identity/);
    await revoke(invite.issued);
    await first.page.goto(url); await first.page.waitForURL(base + '/threads/root-001');
    const partial = await issue();
    const partialUrl = base + '/lobby/#invite=' + partial.token;
    const partialRecipient = await contextWithIdentity(base);
    await partialRecipient.page.goto(partialUrl); await partialRecipient.page.waitForURL(base + '/threads/root-001');
    await revoke(partial.issued);
    await partialRecipient.page.goto(partialUrl); await partialRecipient.page.waitForURL(base + '/threads/root-001');
    await third.page.goto(partialUrl); await third.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
    const manual = await issue(false);
    for (let n = 0; n < 2; n++) {
      const recipient = await contextWithIdentity(base);
      await recipient.page.goto(base + '/lobby/#invite=' + manual.token);
      await recipient.page.locator('[data-action=redeem-invitation]').waitFor({ state: 'visible' });
      await recipient.page.locator('[data-invitation-username]').fill('multi-manual-' + Number(privateSite) + '-' + n);
      await recipient.page.locator('[data-action=redeem-invitation]').click();
      await recipient.page.waitForURL(base + '/');
      assert.equal(await recipient.page.evaluate(() => localStorage.getItem('forum_pki_username')), 'multi-manual-' + Number(privateSite) + '-' + n);
    }
    console.log(`PASS ${privateSite ? 'members-only' : 'public'}: API cap 2, unchanged targeted/manual Lobby, two recipients, third refused, lost response, full/partial revocation recovery`);
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
