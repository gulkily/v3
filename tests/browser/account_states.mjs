// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/account_states.mjs
// The account page shows Guest, Waiting for approval or Approved from first paint.
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
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const root = await mkdtemp(join(tmpdir(), 'account-states-'));
const approved = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'alice' }], format: 'armored' });
const approvedFingerprint = (await pgp.readKey({ armoredKey: approved.publicKey })).getFingerprint();
const unknown = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'bob' }], format: 'armored' });
const unknownFingerprint = (await pgp.readKey({ armoredKey: unknown.publicKey })).getFingerprint();
const envelope = await pgp.encrypt({ message: await pgp.createMessage({ text: 'hello' }), encryptionKeys: await pgp.readKey({ armoredKey: approved.publicKey }), format: 'armored' });
const profiles = [{ name: 'alice', fingerprint: approvedFingerprint, publicKey: approved.publicKey }];
const seeded = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], { input: JSON.stringify({ profiles, incoming: envelope, outgoing: envelope, canonical_identity: profiles[0] }), encoding: 'utf8' });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')], { env: { ...process.env, PRIVATE_MESSAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php'), PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'), FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_USERNAME_CHANGES_ENABLED: 'true' }, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });

// Record which card is visible when the document finishes parsing, before deferred scripts run.
const atParse = () => {
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-account-key-root]');
    const visible = name => { const card = document.querySelector(`[data-account-card="${name}"]`); return Boolean(card && card.getClientRects().length); };
    window.__atParse = { state: root && root.dataset.accountState, guest: visible('guest'), member: visible('member') };
  }, { once: true, capture: true });
};
const withKey = (keys, fingerprint, username, registered = true) => ({ keys, fingerprint, username, registered });
async function open(identity, options = {}) {
  const context = await browser.newContext({ serviceWorkers: 'block', ...options });
  await context.addInitScript(atParse);
  if (identity) {
    await context.addInitScript(({ keys, fingerprint, username, registered }) => {
      if (sessionStorage.getItem('seeded')) return;
      sessionStorage.setItem('seeded', '1');
      for (const [name, value] of Object.entries({ username, private_key: keys.privateKey, public_key: keys.publicKey, fingerprint, published_fingerprint: registered ? fingerprint : '' })) if (value) localStorage.setItem('forum_pki_' + name, value);
    }, identity);
  }
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  page.setDefaultTimeout(15000);
  await page.goto(base + '/account/key/');
  return { context, page, errors };
}
const parsed = page => page.evaluate(() => window.__atParse);
// On public sites the account page signs in without reloading (data-auth-in-place), so actions are never cut off.
const settle = page => page.waitForLoadState('load');
const countNavigations = page => { const seen = { count: 0 }; page.on('framenavigated', frame => { if (frame === page.mainFrame()) seen.count++; }); return seen; };
const state = page => page.locator('[data-account-key-root]').getAttribute('data-account-state');
const shots = process.env.ACCOUNT_SCREENSHOT_DIR;
const shot = (page, name) => shots ? page.screenshot({ path: join(shots, name + '.png'), fullPage: true }) : null;

let browser;
try {
  for (let i = 0; i < 80; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });

  const phone = shots ? { viewport: { width: 390, height: 844 } } : {};
  const fresh = await open(null, phone);
  assert.deepEqual(await parsed(fresh.page), { state: 'guest', guest: true, member: false }, 'no key: guest from first paint');
  assert.equal(await fresh.page.getByRole('heading', { name: 'You’re posting as guest' }).isVisible(), true);
  assert.equal(await fresh.page.getByRole('link', { name: 'Save a copy of this key', exact: true }).isVisible(), false);
  assert.equal(await fresh.page.getByRole('link', { name: 'Use a key from another device' }).isVisible(), true);
  assert.equal(await fresh.page.locator('[data-action="remove-key"]').isVisible(), false);
  assert.equal(await fresh.page.title().then(t => t.startsWith('Account')), true);
  await shot(fresh.page, 'guest');
  assert.deepEqual(fresh.errors, []);
  await fresh.context.close();

  // An automatic key the server doesn't have yet is a guest; once registered, `guest` is a regular user.
  const autoGuest = await open(withKey(unknown, unknownFingerprint, 'guest', false));
  assert.equal((await parsed(autoGuest.page)).state, 'guest', 'unregistered automatic key: guest');
  await autoGuest.context.close();
  const registeredGuest = await open(withKey(unknown, unknownFingerprint, 'guest'), phone);
  const registeredGuestParsed = await parsed(registeredGuest.page);
  assert.ok(registeredGuestParsed.member && !registeredGuestParsed.guest, 'registered guest: member card from first paint');
  await registeredGuest.page.waitForFunction(() => document.querySelector('[data-account-key-root]').dataset.accountState === 'waiting');
  assert.equal(await registeredGuest.page.locator('[data-role="name-row-value"]').textContent(), 'guest');
  assert.equal(await registeredGuest.page.getByRole('button', { name: 'Remove this key' }).isVisible(), true, 'a guest can sign out');
  assert.equal(await registeredGuest.page.getByRole('link', { name: 'Save a copy of this key' }).isVisible(), true);
  await shot(registeredGuest.page, 'registered-guest');
  await registeredGuest.context.close();

  const member = await open(withKey(approved, approvedFingerprint, 'alice'), phone);
  // First visit: the member card from first paint; approval is either known to the server or looked up after paint.
  const first = await parsed(member.page);
  assert.ok(['member', 'approved'].includes(first.state) && first.member && !first.guest, 'named key: member card from first paint');
  await member.page.waitForFunction(() => document.querySelector('[data-account-key-root]').dataset.accountState === 'approved');
  assert.equal(await member.page.locator('[data-role="simple-status-badge"]').textContent(), 'Approved');
  assert.equal(await member.page.locator('[data-role="user-link"]').getAttribute('href'), '/user/alice');
  assert.equal(await member.page.locator('[data-role="user-link"]').textContent(), 'All of alice’s posts');
  assert.match(await member.page.locator('[data-role="fingerprint-field"]').textContent(), /^openpgp-[0-9a-f]{9}\.\.\.$/);
  assert.equal(await member.page.locator('[data-action="remove-key"]').isVisible(), true);
  // Later visits: the identity hint lets the server state approval before paint.
  await member.page.waitForFunction(() => document.cookie.includes('identity_hint='));
  await member.page.reload();
  assert.deepEqual(await parsed(member.page), { state: 'approved', guest: false, member: true }, 'approved from first paint once the server knows the key');
  // The Save row shows the date of the last saved copy, and nothing before one exists.
  assert.equal(await member.page.locator('[data-role="saved-copy-date"]').textContent(), '');
  await member.page.getByRole('link', { name: 'Save a copy of this key', exact: true }).click();
  assert.equal(await member.page.locator('#key-backup').evaluate(d => d.open), true);
  assert.equal(await member.page.getByRole('dialog', { name: 'Save a copy of this key' }).isVisible(), true);
  assert.equal(await member.page.locator('[data-backup-password]').isVisible(), true);
  await member.page.locator('[data-backup-password]').fill('correct horse battery staple');
  await shot(member.page, 'sheet');
  await member.page.keyboard.press('Escape');
  assert.equal(await member.page.locator('#key-backup').evaluate(d => d.open), false);
  await member.page.evaluate(fp => { localStorage.setItem('forum_pki_backup_verified_' + fp, '2026-10-28'); window.dispatchEvent(new Event('forum:key-backup-recorded')); }, approvedFingerprint.toLowerCase());
  assert.equal(await member.page.locator('[data-role="saved-copy-date"]').textContent(), 'Saved Oct 28');
  await shot(member.page, 'approved-saved');
  await shot(member.page, 'approved');
  assert.deepEqual(member.errors, []);
  await member.context.close();

  const waiting = await open(withKey(unknown, unknownFingerprint, 'bob'), phone);
  await waiting.page.waitForFunction(() => document.querySelector('[data-account-key-root]').dataset.accountState === 'waiting');
  assert.equal(await waiting.page.locator('[data-role="simple-status-badge"]').textContent(), 'Waiting for approval');
  assert.equal(await waiting.page.getByText('You can post now.').isVisible(), true);
  assert.equal(await waiting.page.locator('[data-role="user-link"]').isVisible(), false);
  await shot(waiting.page, 'waiting');
  await waiting.context.close();

  // Naming without prompts: a fresh guest names a key kept in this browser; nothing is sent until a first signed action.
  const naming = await open(null, phone);
  let dialogs = 0; naming.page.on('dialog', d => { dialogs++; d.dismiss(); });
  const identityRequests = []; naming.page.on('request', r => { if (/\/api\/(prepare_identity|create_identity|link_identity|set_identity_hint)/.test(r.url())) identityRequests.push(r.url()); });
  await naming.page.locator('[data-role="account-name-input"]').fill('Alice');
  await naming.page.getByRole('button', { name: 'Continue' }).click();
  await naming.page.waitForFunction(() => localStorage.getItem('forum_pki_username') === 'alice', null, { timeout: 30000 });
  await settle(naming.page);
  assert.equal(await state(naming.page), 'guest');
  assert.equal(await naming.page.getByRole('heading', { name: 'You’re posting as alice' }).isVisible(), true);
  assert.deepEqual(identityRequests, [], 'naming a new key sends nothing');
  assert.equal(dialogs, 0, 'no browser prompt while naming');
  await shot(naming.page, 'guest-named');
  await naming.context.close();

  // A local named key shows as a guest under its name from first paint; Continue replaces it locally.
  const local = await open(withKey(unknown, unknownFingerprint, 'mouse', false), phone);
  assert.deepEqual(await parsed(local.page), { state: 'guest', guest: true, member: false });
  assert.equal(await local.page.getByRole('heading', { name: 'You’re posting as mouse' }).isVisible(), true);
  const localRequests = []; local.page.on('request', r => { if (/\/api\/(prepare_identity|create_identity|link_identity|set_identity_hint)/.test(r.url())) localRequests.push(r.url()); });
  await local.page.locator('[data-role="account-name-input"]').fill('kim');
  await local.page.getByRole('button', { name: 'Continue' }).click();
  await local.page.waitForFunction(() => localStorage.getItem('forum_pki_username') === 'kim', null, { timeout: 30000 });
  assert.notEqual((await local.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'))).toLowerCase(), unknownFingerprint.toLowerCase(), 'a local key is replaced, not renamed');
  assert.equal(await local.page.getByRole('heading', { name: 'You’re posting as kim' }).isVisible(), true);
  assert.deepEqual(localRequests, []);
  assert.deepEqual(local.errors, []);
  await local.context.close();

  // A registered key waiting for approval under a name approved members use shows the join hint.
  const joining = await open(withKey(unknown, unknownFingerprint, 'alice'), phone);
  await joining.page.waitForFunction(() => document.querySelector('[data-account-key-root]').dataset.accountState === 'waiting');
  await joining.page.locator('[data-role="join-hint"]').waitFor({ state: 'visible' });
  assert.equal(await joining.page.locator('[data-role="join-hint"]').textContent(), 'Approved members already use alice. Posts from this key join alice once a member approves it.');
  await shot(joining.page, 'waiting-join-hint');
  await joining.context.close();

  // A registered key named guest is renamed in place like any other, keeping its fingerprint.
  const named = await open(withKey(unknown, unknownFingerprint, 'guest'));
  const namedNavigations = countNavigations(named.page);
  await settle(named.page);
  await named.page.getByRole('button', { name: 'Change' }).click();
  await named.page.locator('[data-role="name-editor-input"]').fill('carol');
  await named.page.getByRole('button', { name: 'Save' }).click();
  await named.page.waitForFunction(() => localStorage.getItem('forum_pki_username') === 'carol', null, { timeout: 30000 });
  await settle(named.page);
  assert.equal((await named.page.evaluate(() => localStorage.getItem('forum_pki_fingerprint'))).toLowerCase(), unknownFingerprint.toLowerCase());
  assert.equal(await state(named.page), 'waiting');
  assert.equal(namedNavigations.count, 0, 'naming does not reload the page');
  assert.equal(await named.page.locator('[data-role="join-hint"]').isVisible(), false, 'no hint for a name nobody approved uses');
  await named.context.close();

  // Rename in place, Escape cancels, a failure offers Retry.
  const renaming = await open(withKey(unknown, unknownFingerprint, 'carol'));
  await settle(renaming.page);
  await renaming.page.getByRole('button', { name: 'Change' }).click();
  assert.equal(await renaming.page.locator('[data-role="name-editor-input"]').inputValue(), 'carol');
  await renaming.page.keyboard.press('Escape');
  assert.equal(await renaming.page.locator('[data-role="name-editor"]').isVisible(), false);
  await renaming.page.getByRole('button', { name: 'Change' }).click();
  await renaming.page.locator('[data-role="name-editor-input"]').fill('carol-two');
  await renaming.page.route('**/api/prepare_profile_name', route => route.abort());
  await renaming.page.getByRole('button', { name: 'Save' }).click();
  await renaming.page.getByRole('button', { name: 'Retry' }).waitFor({ state: 'visible' });
  await renaming.page.unroute('**/api/prepare_profile_name');
  await renaming.page.getByRole('button', { name: 'Retry' }).click();
  await renaming.page.waitForFunction(() => localStorage.getItem('forum_pki_username') === 'carol-two', null, { timeout: 30000 });
  await settle(renaming.page);
  assert.equal(await renaming.page.locator('[data-role="name-editor"]').isVisible(), false);
  assert.equal(await renaming.page.getByRole('button', { name: 'Retry' }).isVisible(), false);
  assert.equal(await renaming.page.locator('[data-role="name-row-value"]').textContent(), 'carol-two');
  assert.deepEqual(renaming.errors, []);
  await renaming.context.close();

  // Remove this key: asks first, offers Save a copy first for an approved key without a copy, and Undo works.
  const removing = await open(withKey(approved, approvedFingerprint, 'alice'), phone);
  await removing.page.waitForFunction(() => document.querySelector('[data-account-key-root]').dataset.accountState === 'approved');
  await removing.page.getByRole('button', { name: 'Remove this key' }).click();
  const removeDialog = removing.page.getByRole('dialog', { name: 'Remove this key from this browser?' });
  assert.equal(await removeDialog.isVisible(), true);
  assert.equal(await removing.page.locator('[data-role="remove-consequence"]').textContent(), 'Your posts stay under alice. To post as alice again, a new key will wait for a member’s approval, and messages sent to this key can’t be read.');
  await shot(removing.page, 'remove');
  await removeDialog.getByRole('button', { name: 'Cancel' }).click();
  assert.equal(await removeDialog.isVisible(), false);
  assert.notEqual(await removing.page.evaluate(() => localStorage.getItem('forum_pki_private_key')), null, 'Cancel keeps the key');
  await removing.page.getByRole('button', { name: 'Remove this key' }).click();
  await removeDialog.getByRole('button', { name: 'Save a copy first' }).click();
  assert.equal(await removeDialog.isVisible(), false);
  assert.equal(await removing.page.getByRole('dialog', { name: 'Save a copy of this key' }).isVisible(), true);
  await removing.page.keyboard.press('Escape');
  await removing.page.getByRole('button', { name: 'Remove this key' }).click();
  await removeDialog.getByRole('button', { name: 'Remove' }).click();
  await removing.page.waitForFunction(() => localStorage.getItem('forum_pki_private_key') === null);
  assert.equal(await state(removing.page), 'guest');
  await removing.page.getByRole('button', { name: 'Undo' }).click();
  await removing.page.waitForFunction(() => localStorage.getItem('forum_pki_private_key') !== null);
  await removing.page.waitForFunction(() => document.querySelector('[data-role="username-field"]').textContent === 'alice');
  assert.notEqual(await state(removing.page), 'guest');
  assert.deepEqual(removing.errors, []);
  await removing.context.close();

  const noScript = await open(null, { javaScriptEnabled: false });
  assert.equal(await noScript.page.locator('[data-account-card="guest"]').isVisible(), false, 'without JavaScript the cards are hidden');
  await noScript.page.locator('#key-advanced > summary').click();
  await noScript.page.locator('#key-link > summary').click();
  assert.equal(await noScript.page.locator('textarea[name="public_key"]').isVisible(), true, 'Link a public key still works');
  await noScript.context.close();
  console.log('PASS account states: guest, automatic guest (local), registered guest as a member with Remove, member → approved, approved on first paint, waiting, local naming, local key replaced, join hint, rename and Retry, remove with Undo, no JavaScript');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { if (browser) await browser.close(); server.kill(); }
