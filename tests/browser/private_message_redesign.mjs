// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/private_message_redesign.mjs [screenshot-dir] [theme,theme,...]
// The redesigned conversation (ui_messages): name and privacy line, bubbles by day, no per-message check mark,
// the Send button, theme hooks, in each theme at phone and desktop width.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const themes = (process.argv[3] || 'light,word97,chicago,midnight').split(',');
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const openpgp = globalThis.openpgp;
const root = await mkdtemp(join(tmpdir(), 'private-message-redesign-'));
const profiles = [];
async function identity(name) {
  const keys = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  const publicKey = await openpgp.readKey({ armoredKey: keys.publicKey });
  profiles.push({ name, fingerprint: publicKey.getFingerprint(), publicKey: keys.publicKey });
  return { ...keys, fingerprint: publicKey.getFingerprint(), public: publicKey, private: await openpgp.readPrivateKey({ armoredKey: keys.privateKey }) };
}
const alice = await identity('alice'), bob = await identity('bob'), outsider = await identity('outsider');
for (let i = 1; i <= 60; i++) profiles.push({ name: 'user-' + String(i).padStart(2, '0'), fingerprint: bob.fingerprint, publicKey: bob.publicKey });
const encrypt = (signer, text) => openpgp.createMessage({ text }).then(message => openpgp.encrypt({
  message, encryptionKeys: [alice.public, bob.public], signingKeys: signer.private, format: 'armored',
}));
function seed(data) {
  const result = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], {
    input: JSON.stringify(data), encoding: 'utf8', env: { ...process.env, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php') },
  });
  assert.equal(result.status, 0, result.stderr);
}
seed({ profiles, incoming: await encrypt(bob, 'Incoming fixture preview'), outgoing: await encrypt(alice, 'Outgoing fixture preview') });
const day = offset => new Date(Date.now() - offset * 86400000).toISOString().replace(/\.\d+Z$/, 'Z');
seed({ action: 'chat', messages: [
  { id: 'redesign-1', time: day(3), sender: 'bob', envelope: await encrypt(bob, 'did you see my thread about the textarea') },
  { id: 'redesign-2', time: day(3), sender: 'alice', recipient: 'bob', envelope: await encrypt(alice, 'Yes, it’s the next thing I’m fixing.') },
  { id: 'redesign-3', time: day(1), sender: 'bob', envelope: await encrypt(outsider, 'Unverifiable fixture') },
  { id: 'redesign-4', time: day(0), sender: 'bob', envelope: await encrypt(bob, 'ok the new reply box is way better on my phone ' + 'averylongwordwithoutspaces'.repeat(6)) },
] });

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
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (let attempt = 0; attempt < 50; attempt++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  for (const width of [390, 1280]) for (const theme of themes) {
    const label = `${theme} ${width}px`;
    const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
    const challenge = (await (await context.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
    const signature = await openpgp.sign({ message: await openpgp.createMessage({ text: challenge }), signingKeys: alice.private, detached: true });
    assert.equal((await context.request.post(base + '/api/authenticate_identity', { data: { challenge, detached_signature: signature, identity_id: 'openpgp:' + alice.fingerprint } })).status(), 200);
    await context.addInitScript(({ keys, theme }) => {
      for (const [key, value] of Object.entries(keys)) localStorage.setItem('forum_pki_' + key, value);
      for (const key of ['zenmemes-theme', ...Object.keys(localStorage).filter(name => name.endsWith('-theme'))]) localStorage.setItem(key, theme);
    }, { theme, keys: { username: 'alice', public_key: alice.publicKey, private_key: alice.privateKey,
      fingerprint: alice.fingerprint.toUpperCase(), published_fingerprint: alice.fingerprint.toUpperCase() } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/messages/conversation/bob');
    const root = page.locator('[data-mailbox="conversation"]');
    const headTop = () => page.locator('.conversation-head').evaluate(node => node.getBoundingClientRect().top + scrollY);
    const headBefore = await headTop();
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
    assert.equal(Math.round(await headTop()), Math.round(headBefore), `${label}: header stays put while messages decrypt`);

    assert.equal(await page.title().then(title => title.startsWith('bob')), true, `${label}: the tab is titled by the person`);
    assert.equal(await page.locator('.conversation-head h1').innerText(), 'bob');
    assert.equal(await page.getByRole('link', { name: 'Back to Messages' }).isVisible(), true);
    assert.equal(await page.getByText('Only you and bob can read these, on your approved devices.').isVisible(), true);
    assert.equal(await page.getByText('Conversation with', { exact: false }).count(), 0);
    assert.equal(await page.locator('[data-role="private-message-verification"]:visible').count(), 0, `${label}: no check marks`);
    assert.deepEqual(await page.locator('[data-role="message-date"]').allInnerTexts(), [
      await page.evaluate(value => window.ForumMessageTime.day(value), day(3)), 'Yesterday', 'Today'].map(text => text.trim()));
    assert.equal(await page.locator('[data-private-message-id][data-incoming]').count(), 3);
    assert.equal(await page.locator('.is-outgoing[data-private-message-id]').count(), 1);
    assert.match(await root.getAttribute('style'), /--person: \d+/);
    assert.equal(await page.getByRole('button', { name: 'Send', exact: true }).isVisible(), true);
    const unreadable = page.locator('[data-private-message-id="redesign-3"]');
    assert.equal(await unreadable.locator('[data-role="private-message-reader-error"]').innerText(), 'Couldn’t verify this message');
    assert.equal(await unreadable.getByRole('button', { name: 'Retry reading message' }).isVisible(), true);
    await unreadable.locator('summary').click();
    assert.match(await unreadable.locator('[data-role="private-message-unavailable-explanation"]').innerText(), /signature/);
    assert.equal(await page.getByText('All history loaded.').isVisible().then(visible => visible && page.getByText('All history loaded.').evaluate(node => node.getBoundingClientRect().width > 2)), false, `${label}: no routine history notice`);
    assert.equal(await page.getByRole('button', { name: 'Restart history' }).isVisible(), false, `${label}: Restart history waits under ···`);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${label}: no page overflow`);
    if (shots) await page.screenshot({ path: join(shots, `conversation-${theme}-${width}.png`), fullPage: true });
    assert.deepEqual(errors, [], `${label}: no page errors`);
    await context.close();
  }
  console.log('private_message_redesign: ok');
} finally {
  await browser.close();
  server.kill();
  if (process.exitCode) console.error(serverLog);
}
