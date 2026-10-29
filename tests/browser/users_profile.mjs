// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/users_profile.mjs
// Users and person pages: find a member, open their page, approve someone waiting, then message them.
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
const fingerprint = (await pgp.readKey({ armoredKey: operator.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'users-profile-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
  env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false',
    FORUM_PRIVATE_MESSAGES_ENABLED: 'true', PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', d => { logs += d; });
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // The operator signs in once (Account signs in without reloading).
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  await context.addInitScript(({ operator, fingerprint }) => {
    for (const [key, value] of Object.entries({ username: 'browser-inviter', public_key: operator.publicKey, private_key: operator.privateKey, fingerprint, published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
  }, { operator, fingerprint });
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  const member = await keys('browser-member');
  const linked = await context.request.post(base + '/api/link_identity', { form: { public_key: member.publicKey } });
  const memberSlug = /profile_slug=(.+)/.exec(await linked.text())[1];

  // Members: compact rows, search and sort.
  await page.goto(base + '/users/');
  assert.equal(await page.locator('nav.board-filter[aria-label="Users"] a[aria-current="page"]').textContent(), 'Members');
  const rows = page.locator('[data-users-list] > a:not([hidden])');
  const before = await rows.count();
  assert.ok(before >= 1);
  await page.getByRole('searchbox', { name: 'Search members' }).fill('browser-in');
  assert.equal(await rows.count(), 1);
  await page.getByRole('searchbox', { name: 'Search members' }).fill('nobody-by-this-name');
  await page.getByText('No members match.').waitFor();
  await page.getByRole('searchbox', { name: 'Search members' }).fill('');
  await page.getByRole('combobox', { name: 'Sort members' }).selectOption('name');
  const names = await rows.evaluateAll(items => items.map(item => item.dataset.userName));
  assert.deepEqual(names, [...names].sort((a, b) => a.localeCompare(b)));
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

  // Your own page: Edit in Account, no Message.
  await page.getByRole('searchbox', { name: 'Search members' }).fill('browser-in');
  await rows.first().click();
  await page.getByRole('heading', { name: 'browser-inviter', exact: true }).waitFor();
  assert.equal(await page.locator('.person-head .chip--approved').textContent(), 'Approved');
  assert.equal(await page.getByRole('button', { name: 'Edit in Account' }).isVisible(), true);
  assert.equal(await page.getByRole('button', { name: 'Message' }).count(), 0);
  assert.ok(await page.locator('#account-keys a[href^="/profiles/"]').count() >= 1, 'keys listed as identities');
  assert.equal(await page.locator('.starfield.banner[data-seed="browser-inviter"]').count(), 1);
  await page.getByRole('link', { name: 'Replies', exact: true }).click();
  await page.waitForURL(/tab=replies/);

  // Waiting: approve in place from Users.
  await page.goto(base + '/users/');
  await page.getByRole('link', { name: /^Waiting \(\d+\)$/ }).click();
  await page.waitForURL(/\/users\/pending/);
  assert.match(await page.locator('nav.board-filter[aria-label="Users"] a[aria-current="page"]').textContent(), /^Waiting \(\d+\)$/);
  const row = page.locator(`[data-role="pending-approval-row"][data-profile-slug="${memberSlug}"]`);
  await row.waitFor();
  page.on('dialog', dialog => void dialog.accept());
  await row.getByRole('button', { name: 'Approve' }).click();
  // Approved in place: the row goes, the count follows, and the confirmation shows beside the list.
  await page.getByText('Approved user browser-member.').waitFor();
  assert.equal(await row.count(), 0);
  assert.match(await page.locator('nav.board-filter[aria-label="Users"] a[aria-current="page"]').textContent(), /^Waiting \(\d+\)$/);
  assert.equal(await page.locator('nav.board-filter[aria-label="Users"] a[aria-current="page"]').textContent(), 'Waiting (' + await page.locator('[data-role="pending-approval-row"]').count() + ')');
  await page.screenshot({ path: '/tmp/users-approved-390.png', fullPage: true });

  // The new member is now in the directory; their page offers Message, which opens the conversation.
  await page.goto(base + '/users/');
  await page.getByRole('searchbox', { name: 'Search members' }).fill('browser-member');
  await rows.first().click();
  await page.getByRole('heading', { name: 'browser-member', exact: true }).waitFor();
  await page.screenshot({ path: '/tmp/users-person-390.png', fullPage: true });
  await page.getByRole('button', { name: 'Message' }).click();
  await page.waitForURL(/\/messages\/conversation\/browser-member/);
  assert.deepEqual(errors, []);
  await context.close();

  // A visitor who isn't signed in gets the same page with the tabs hidden; Waiting is for approved members only.
  const anonymous = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const visitor = await anonymous.newPage();
  await visitor.goto(base + '/users/');
  assert.equal(await visitor.locator('nav.board-filter[aria-label="Users"]').isVisible(), false, 'no Waiting tab for visitors who cannot open it');
  const forbidden = await visitor.goto(base + '/users/pending');
  assert.equal(forbidden.status(), 403);
  await anonymous.close();

  // Without JavaScript the full list shows and the search and sort controls stay hidden.
  const plain = await browser.newContext({ javaScriptEnabled: false });
  const plainPage = await plain.newPage();
  await plainPage.goto(base + '/users/');
  assert.equal(await plainPage.getByRole('searchbox', { name: 'Search members' }).isVisible(), false);
  assert.ok(await plainPage.locator('[data-users-list] > a').count() >= 2);
  await plain.close();
  console.log('PASS users and profiles: search, sort, own page, Replies, approve from Waiting, Message opens the conversation, Waiting for approved members only, no JavaScript');
} catch (error) { console.error(logs.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
