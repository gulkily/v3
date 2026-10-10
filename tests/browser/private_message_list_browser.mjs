import assert from 'node:assert/strict';
import { mkdtemp, writeFile } from 'node:fs/promises';
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
const openpgp = globalThis.openpgp;
const root = await mkdtemp(join(tmpdir(), 'private-message-browser-'));
const profiles = [];
async function identity(name) {
  const keys = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  const publicKey = await openpgp.readKey({ armoredKey: keys.publicKey });
  const result = { ...keys, name, fingerprint: publicKey.getFingerprint(), public: publicKey,
    private: await openpgp.readPrivateKey({ armoredKey: keys.privateKey }) };
  profiles.push({ name, fingerprint: result.fingerprint, publicKey: keys.publicKey });
  return result;
}
const alice = await identity('alice'), bob = await identity('bob'), outsider = await identity('outsider');
for (let i = 1; i <= 60; i++) profiles.push({ name: 'user-' + String(i).padStart(2, '0'), fingerprint: bob.fingerprint, publicKey: bob.publicKey });
profiles.push({ name: 'pending', fingerprint: bob.fingerprint, publicKey: bob.publicKey });
profiles.push({ name: 'keyless', fingerprint: bob.fingerprint, publicKey: '' });
const encrypt = (signer, text) => openpgp.createMessage({ text }).then(message => openpgp.encrypt({
  message, encryptionKeys: [alice.public, bob.public], signingKeys: signer.private, format: 'armored',
}));
const incoming = await encrypt(bob, 'Incoming fixture preview'), outgoing = await encrypt(alice, 'Outgoing fixture preview');
function seed(data) {
  const result = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], {
    input: JSON.stringify(data), encoding: 'utf8', env: { ...process.env, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php') },
  });
  assert.equal(result.status, 0, result.stderr);
}
seed({ profiles, incoming, outgoing });
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')], {
  env: { ...process.env, PRIVATE_MESSAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php'),
    PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
  stdio: ['ignore', 'ignore', 'pipe'],
});
let serverLog = '';
server.stderr.on('data', data => { serverLog += data; });
let browser;
try {
  for (let attempt = 0; attempt < 50; attempt++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1100, height: 800 }, timezoneId: 'America/New_York', serviceWorkers: 'block' });
  const unauthenticated = await context.request.get(base + '/api/private_messages/conversations');
  assert.equal(unauthenticated.status(), 401);
  assert.match(unauthenticated.headers()['cache-control'], /no-store/);
  async function authenticate(ctx, who) {
    const challenge = (await (await ctx.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
    const signature = await openpgp.sign({ message: await openpgp.createMessage({ text: challenge }), signingKeys: who.private, detached: true });
    const response = await ctx.request.post(base + '/api/authenticate_identity', { data: {
      challenge, detached_signature: signature, identity_id: 'openpgp:' + who.fingerprint,
    } });
    assert.equal(response.status(), 200, await response.text());
  }
  await authenticate(context, alice);
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(({ publicKey, privateKey, fingerprint }) => {
    if (!localStorage.getItem('forum_pki_private_key')) {
      for (const [key, value] of Object.entries({ username: 'alice', public_key: publicKey, private_key: privateKey,
        fingerprint: fingerprint.toUpperCase(), published_fingerprint: fingerprint.toUpperCase() })) {
        localStorage.setItem('forum_pki_' + key, value);
      }
    }
  }, { publicKey: alice.publicKey, privateKey: alice.privateKey, fingerprint: alice.fingerprint });
  await page.goto(base + '/');
  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent === '25 conversations loaded.');
  const list = page.locator('[data-role="rows"] [data-conversation-row]');
  assert.equal(await list.count(), 25);
  assert.match(await list.first().innerText(), /Outgoing fixture preview/);
  seed({ action: 'arrive', envelope: incoming });
  let failLoad = true;
  await page.route('**/api/private_messages/conversations?*', route => failLoad ? route.abort() : route.continue());
  await page.getByRole('button', { name: 'Load more', exact: true }).click();
  await page.getByRole('button', { name: 'Retry loading' }).waitFor({ state: 'visible' });
  failLoad = false;
  await page.getByRole('button', { name: 'Retry loading' }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent === '50 conversations loaded.');
  await page.getByRole('button', { name: 'Load more', exact: true }).click();
  await page.getByText('All conversations loaded.', { exact: true }).waitFor();
  assert.equal(await list.count(), 60);
  assert.equal(new Set(await list.evaluateAll(rows => rows.map(row => row.dataset.counterpart))).size, 60);
  assert.equal(await list.last().getAttribute('data-message-id'), 'fixture-01');
  await page.reload();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent === '25 conversations loaded.');
  assert.equal(await list.first().getAttribute('data-message-id'), 'arrival');
  await page.locator('summary').filter({ hasText: 'New message' }).click();
  for (const username of ['alice', 'not a username', 'pending', 'keyless']) {
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByRole('button', { name: 'Open conversation' }).click();
    await page.locator('[data-role="recipient-feedback"].feedback-error').waitFor();
    assert.equal(await page.getByLabel('Username', { exact: true }).inputValue(), username);
  }
  await page.getByLabel('Username', { exact: true }).fill('bob');
  await page.getByRole('button', { name: 'Open conversation' }).click();
  await page.waitForURL('**/messages/conversation/bob');
  await page.locator('textarea[name="plaintext"]').fill('Browser first message');
  let failSend = true;
  await page.route('**/api/private_messages', route => failSend ? route.fulfill({ status: 503, json: { status: 'error', error: 'Test delivery failure' } }) : route.continue());
  await page.getByRole('button', { name: 'Send private message' }).click();
  await page.getByText('Test delivery failure', { exact: true }).waitFor();
  assert.equal(await page.locator('textarea').inputValue(), 'Browser first message');
  failSend = false;
  await page.getByRole('button', { name: 'Send private message' }).click();
  await page.locator('[data-role="private-message-plaintext"]').filter({ hasText: 'Browser first message' }).waitFor();
  await page.locator('textarea').fill('Browser follow-up');
  await page.getByRole('button', { name: 'Send private message' }).click();
  await page.locator('[data-role="private-message-plaintext"]').filter({ hasText: 'Browser follow-up' }).waitFor();
  await page.getByRole('link', { name: 'Back to Messages' }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent === '25 conversations loaded.');
  assert.equal(await list.first().getAttribute('data-counterpart'), 'bob');
  assert.match(await list.first().innerText(), /Browser follow-up/);
  await page.screenshot({ path: join(root, 'messages-desktop.png') });
  await page.setViewportSize({ width: 375, height: 812 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'List must fit mobile width');
  await list.first().locator('a').focus();
  assert.equal(await list.first().locator('a').evaluate(node => node === document.activeElement), true);
  await page.screenshot({ path: join(root, 'messages-mobile.png') });
  seed({ action: 'revoke' });
  await page.goto(base + '/messages/conversation/user-60');
  await page.getByRole('heading', { name: 'Conversation Unavailable' }).waitFor();
  await page.getByRole('link', { name: 'Back to Messages' }).click();
  const unavailableRow = page.locator('[data-role="rows"] [data-counterpart="user-60"]');
  await unavailableRow.waitFor({ state: 'visible' });
  // A revoked recipient's old outgoing message can still verify against Alice's key.
  let failKey = true;
  await page.route('**/api/private_messages/recipient_keys?username_token=user-57', route => failKey ? route.abort() : route.continue());
  await page.reload();
  const retryRow = page.locator('[data-role="rows"] [data-counterpart="user-57"]');
  await retryRow.getByRole('button', { name: 'Retry preview' }).waitFor();
  failKey = false;
  await retryRow.getByRole('button', { name: 'Retry preview' }).click();
  await retryRow.locator('[data-role="verification"]').waitFor({ state: 'visible' });
  seed({ action: 'bad-signature', envelope: await encrypt(outsider, 'Unverified secret') });
  await page.reload();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent === '25 conversations loaded.');
  const bad = page.locator('[data-role="rows"] [data-counterpart="user-59"]');
  assert.match(await bad.innerText(), /signature.*not.*verified/i);
  assert.doesNotMatch(await bad.innerText(), /Unverified secret/);
  for (const legacy of ['inbox', 'sent']) {
    const response = await context.request.get(base + '/messages/' + legacy, { maxRedirects: 0 });
    assert.equal(response.status(), 302);
    assert.equal(response.headers().location, '/messages');
  }
  const api = await context.request.get(base + '/api/private_messages/conversations');
  assert.match(api.headers()['cache-control'], /no-store/);
  assert.doesNotMatch(await api.text(), /Browser follow-up/);
  const invalidCursor = await context.request.get(base + '/api/private_messages/conversations?cursor[]=bad');
  assert.equal(invalidCursor.status(), 400);
  assert.equal((await invalidCursor.json()).restart, true);
  assert.equal((await context.request.post(base + '/api/private_messages/conversations')).status(), 405);
  const outsiderContext = await browser.newContext();
  await authenticate(outsiderContext, outsider);
  assert.equal((await (await outsiderContext.request.get(base + '/api/private_messages/conversations')).json()).conversations.length, 0);
  assert.deepEqual(errors, []);
  await writeFile(join(root, 'report.json'), JSON.stringify({ passed: true, checks: 'normal entry, real encrypted first send/reply, recovery, snapshot pagination, signatures, authorization, redirects, mobile', screenshots: ['messages-desktop.png', 'messages-mobile.png'] }, null, 2));
  console.log(`Browser checks passed. Artifacts: ${root}`);
} catch (error) {
  console.error(`Browser artifacts: ${root}`);
  console.error(error);
  process.exitCode = 1;
} finally {
  if (browser) await browser.close();
  server.kill();
  await writeFile(join(root, 'server.log'), serverLog);
}
