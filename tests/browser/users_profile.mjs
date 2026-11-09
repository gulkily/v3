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

  // Members: thread rows in the board toolbar's page, with a search.
  await page.goto(base + '/users/');
  assert.equal(await page.locator('nav.board-filter[aria-label="Users"] a[aria-current="page"]').textContent(), 'Members');
  const rows = page.locator('[data-users-list] > .thread-row:not([hidden])');
  const before = await rows.count();
  assert.ok(before >= 1);
  await page.getByRole('searchbox', { name: 'Search members' }).fill('browser-in');
  assert.equal(await rows.count(), 1);
  await page.getByRole('searchbox', { name: 'Search members' }).fill('nobody-by-this-name');
  await page.getByText('No members match.').waitFor();
  await page.getByRole('searchbox', { name: 'Search members' }).fill('');
  // Sort is the board's "Sort by ▾" menu: Name reloads the page in alphabetical order, Last active by the row dates, Activity restores the directory's own order.
  const sortMenu = page.locator('.users-page .board-toolbar details.board-sort');
  assert.equal(await sortMenu.locator('summary').textContent(), 'Sort by Activity');
  await sortMenu.locator('summary').click();
  await sortMenu.getByRole('link', { name: 'Name' }).click();
  await page.waitForURL(/\/users\/\?sort=name$/);
  assert.equal(await sortMenu.locator('summary').textContent(), 'Sort by Name');
  assert.equal(await sortMenu.locator('a[aria-current="true"]').textContent(), 'Name');
  const names = await rows.evaluateAll(items => items.map(item => item.dataset.userName));
  assert.ok(names.length >= 2, 'the sorted page lists the members');
  assert.deepEqual(names, [...names].sort((a, b) => a.localeCompare(b)));
  await sortMenu.locator('summary').click();
  await sortMenu.getByRole('link', { name: 'Last active' }).click();
  await page.waitForURL(/\/users\/\?sort=active$/);
  assert.equal(await sortMenu.locator('summary').textContent(), 'Sort by Last active');
  const actives = await rows.evaluateAll(items => items.map(item => item.dataset.userActive));
  const dated = actives.filter(Boolean);
  assert.ok(dated.length >= 1, 'at least one member has posted');
  assert.deepEqual(actives, [...dated].sort().reverse().concat(actives.filter(value => !value)));
  assert.match(await page.locator('[data-users-list] > .thread-row[data-user-active]:not([data-user-active=""]) .thread-row__meta').first().textContent(), new RegExp(' · Active ' + new Date(dated[0]).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' }) + '$'));
  await sortMenu.locator('summary').click();
  await sortMenu.getByRole('link', { name: 'Activity' }).click();
  await page.waitForURL(/\/users\/\?sort=activity$/);
  assert.equal(await sortMenu.locator('summary').textContent(), 'Sort by Activity');
  assert.equal(await rows.count(), before);
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

  // Your own page: Edit in Account, no Message.
  await page.getByRole('searchbox', { name: 'Search members' }).fill('browser-in');
  await rows.first().getByRole('link', { name: 'browser-inviter' }).click();
  await page.waitForURL(/\/user\/browser-inviter/);
  assert.equal(await page.locator('.person-head .chip--approved').textContent(), 'Approved');
  assert.equal(await page.getByRole('button', { name: 'Edit in Account' }).isVisible(), true);
  assert.equal(await page.getByRole('button', { name: 'Message' }).count(), 0);
  assert.equal(await page.locator('.starfield.banner[data-seed="browser-inviter"]').count(), 1);
  // Tabs switch in place (no reload), keep their own URLs, and Back returns to the previous tab.
  await page.evaluate(() => { window.__samePersonPage = true; });
  await page.getByRole('link', { name: 'Replies', exact: true }).click();
  await page.waitForURL(/tab=replies/);
  assert.equal(await page.evaluate(() => window.__samePersonPage), true, 'Replies opened without a reload');
  assert.equal(await page.locator('[data-person-panel="replies"]').isVisible(), true);
  assert.equal(await page.locator('[data-person-panel="threads"]').isVisible(), false);
  await page.goBack();
  await page.waitForURL(url => !url.search.includes('tab='));
  assert.equal(await page.locator('[data-person-panel="threads"]').isVisible(), true);
  assert.equal(await page.locator('nav[data-person-tabs] a[aria-current="page"]').textContent(), 'Threads');
  await page.goForward();
  await page.waitForURL(/tab=replies/);
  assert.equal(await page.locator('[data-person-panel="replies"]').isVisible(), true);
  // Identities: each key with its last-active date, most recently active first, no page overflow.
  await page.getByRole('link', { name: 'Identities', exact: true }).click();
  await page.waitForURL(/tab=identities/);
  assert.equal(await page.evaluate(() => window.__samePersonPage), true, 'Identities opened without a reload');
  const keyRows = page.locator('#account-keys a.person-identity[href^="/profiles/"]');
  assert.ok(await keyRows.count() >= 1, 'keys listed as identities');
  const lastActive = await keyRows.evaluateAll(rows => rows.map(row => row.querySelector('.person-identity__status time')?.getAttribute('datetime') || ''));
  const activeKeys = lastActive.filter(Boolean);
  assert.deepEqual(activeKeys, [...activeKeys].sort((a, b) => Date.parse(b) - Date.parse(a)), 'newest activity first');
  assert.deepEqual(lastActive.slice(0, activeKeys.length), activeKeys, 'keys without posts come last');
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

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
  await rows.first().getByRole('link', { name: 'browser-member' }).click();
  await page.waitForURL(/\/user\/browser-member/);
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

  // Without JavaScript the full list shows and the search stays hidden.
  const plain = await browser.newContext({ javaScriptEnabled: false });
  const plainPage = await plain.newPage();
  await plainPage.goto(base + '/users/');
  assert.equal(await plainPage.getByRole('searchbox', { name: 'Search members' }).isVisible(), false);
  assert.ok(await plainPage.locator('[data-users-list] > .thread-row').count() >= 2);
  // The sort menu still works without JavaScript: its options are links.
  await plainPage.locator('.users-page details.board-sort summary').click();
  await plainPage.locator('.users-page details.board-sort').getByRole('link', { name: 'Name' }).click();
  await plainPage.waitForURL(/\/users\/\?sort=name$/);
  const plainNames = await plainPage.locator('[data-users-list] > .thread-row').evaluateAll(items => items.map(item => item.dataset.userName));
  assert.deepEqual(plainNames, [...plainNames].sort((a, b) => a.localeCompare(b)));
  await plain.close();
  console.log('PASS users and profiles: search, sort menu (Name, Last active, Activity), own page, Replies, approve from Waiting, Message opens the conversation, Waiting for approved members only, no JavaScript');
} catch (error) { console.error(logs.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
