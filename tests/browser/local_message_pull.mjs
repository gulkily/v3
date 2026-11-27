// Two real instances: a source with Alice's history and a local development clone.
// The local browser registers its own key on the source, gets approved there, imports
// the approval, pulls Alice's mail, verifies it, confirms it on the source and reads it.
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
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
const root = await mkdtemp(join(tmpdir(), 'local-message-pull-'));
const sourceRoot = join(root, 'source'), localRoot = join(root, 'local');
await mkdir(sourceRoot); await mkdir(localRoot);
const fixture = input => {
  const result = spawnSync('php', [join(project, 'tests/Support/private_message_history_sync_fixture.php'), sourceRoot],
    { input: JSON.stringify(input), encoding: 'utf8', env: { ...process.env, FORUM_SECRETS_PATH: join(root, 'unused.php') } });
  assert.equal(result.status, 0, result.stderr); return result.stdout;
};
const php = code => {
  const result = spawnSync('php', ['-r', 'require ' + JSON.stringify(join(project, 'autoload.php')) + ';' + code], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr + result.stdout); return result.stdout;
};
async function key(name) {
  const pair = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  const publicKey = await pgp.readKey({ armoredKey: pair.publicKey });
  return { ...pair, name, fingerprint: publicKey.getFingerprint(), public: publicKey, private: await pgp.readPrivateKey({ armoredKey: pair.privateKey }) };
}
async function freePort() {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(done => socket.close(done)); return port;
}
const sign = (who, text) => pgp.createMessage({ text }).then(message => pgp.sign({ message, signingKeys: who.private, detached: true }));
async function json(response, status = 200) { assert.equal(response.status(), status, await response.text()); return response.json(); }

const [aliceOld, bob, aliceLocal] = await Promise.all(['alice', 'bob', 'alice'].map(key));
fixture({ action: 'init' });
const sourcePort = await freePort(), localPort = await freePort();
const base = `http://127.0.0.1:${sourcePort}`, local = `http://127.0.0.1:${localPort}`;
const servers = [];
servers.push(spawn('php', ['-d', `session.save_path=${sourceRoot}/sessions`, '-S', `127.0.0.1:${sourcePort}`, join(project, 'tests/Support/private_message_browser_router.php')], {
  env: { ...process.env, PRIVATE_MESSAGE_TEST_ROOT: sourceRoot, FORUM_SECRETS_PATH: join(root, 'unused.php'),
    PRIVATE_MESSAGE_DATABASE_PATH: join(sourceRoot, 'state/private/messages.sqlite3'),
    PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH: join(sourceRoot, 'state/private/message_history_sync.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(sourceRoot, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] }));

php(`ForumRewrite\\Support\\LocalRepositoryBootstrap::initializeLocalRepository(${JSON.stringify(project)}, ${JSON.stringify(join(localRoot, 'repository'))});`);
await writeFile(join(localRoot, 'secrets.php'), '<?php return [];');
const localEnv = { ...process.env, V3_DEV_LOG: '1', FORUM_REPOSITORY_ROOT: join(localRoot, 'repository'),
    FORUM_DATABASE_PATH: join(localRoot, 'cache/index.sqlite3'), FORUM_STATIC_HTML_ROOT: join(localRoot, 'static'),
    FORUM_TASK_QUEUE_DATABASE_PATH: join(localRoot, 'private/tasks.sqlite3'),
    FORUM_SECRETS_PATH: join(localRoot, 'secrets.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false', FAST_SCORING_ENABLED: 'false',
    PRIVATE_MESSAGE_DATABASE_PATH: join(localRoot, 'private/messages.sqlite3'),
    PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH: join(localRoot, 'private/message_history_sync.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(localRoot, 'visitors.sqlite3') };
servers.push(spawn('php', ['-S', `127.0.0.1:${localPort}`, join(project, 'public/router.php')], {
  cwd: join(project, 'public'), env: localEnv, stdio: ['ignore', 'ignore', 'pipe'] }));
let log = ''; for (const server of servers) server.stderr.on('data', data => { log += data; });
let browser, page;
const errors = [];
async function waitFor(url) { for (let n = 0; n < 100; n++) { try { await fetch(url); return; } catch { await new Promise(done => setTimeout(done, 100)); } } throw Error('No server at ' + url); }
async function authenticate(ctx, at, who) {
  const challenge = (await (await ctx.request.get(at + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
  const response = await ctx.request.post(at + '/api/authenticate_identity', { data: { challenge, identity_id: 'openpgp:' + who.fingerprint, detached_signature: await sign(who, challenge) } });
  assert.equal(response.status(), 200, await response.text());
  await ctx.request.get(at + '/api/set_identity_hint?identity_hint=' + encodeURIComponent('openpgp:' + who.fingerprint));
}
async function device(who, at) {
  const ctx = await browser.newContext({ viewport: { width: 1100, height: 800 }, serviceWorkers: 'block' });
  await ctx.addInitScript(({ name, publicKey, privateKey, fingerprint, origin }) => {
    if (location.origin !== origin) return;
    for (const [k, v] of Object.entries({ username: name, public_key: publicKey, private_key: privateKey, fingerprint: fingerprint.toUpperCase(), published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + k, v);
  }, { name: who.name, publicKey: who.publicKey, privateKey: who.privateKey, fingerprint: who.fingerprint, origin: at });
  ctx.on('page', page => page.on('pageerror', error => errors.push(error.message)));
  return ctx;
}
const status = page => page.locator('[data-local-pull-status]');
const summary = page => page.locator('[data-local-pull-summary]');

try {
  await waitFor(base + '/api/version'); await waitFor(local + '/api/version');
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const anonymous = await browser.newContext();
  for (const who of [aliceOld, bob]) {
    const prepared = await json(await anonymous.request.post(base + '/api/prepare_identity', { data: { public_key: who.publicKey } }));
    await json(await anonymous.request.post(base + '/api/create_identity', { data: { prepare_token: prepared.prepare_token, canonical_record: prepared.canonical_record, detached_signature: await sign(who, prepared.canonical_record) } }));
  }
  fixture({ action: 'seed-approvals', fingerprints: [aliceOld.fingerprint, bob.fingerprint] });
  const messages = [];
  for (let n = 1; n <= 8; n++) {
    const sender = n % 2 ? bob : aliceOld;
    const envelope = await pgp.encrypt({ message: await pgp.createMessage({ text: 'Pulled secret ' + n }), encryptionKeys: [aliceOld.public, bob.public], signingKeys: sender.private, format: 'armored' });
    messages.push({ id: 'pull-' + n, time: `2026-11-10T12:0${n}:00Z`, sender: sender.name, recipient: sender === bob ? 'alice' : 'bob', fingerprint: sender.fingerprint, envelope });
  }
  messages.push({ id: 'carol-only', time: '2026-11-10T12:30:00Z', sender: 'bob', recipient: 'carol', fingerprint: bob.fingerprint, envelope: messages[0].envelope });
  fixture({ action: 'messages', messages });

  // 1. The local page reports an unregistered key, then registers it through the relay.
  const localCtx = await device(aliceLocal, local);
  page = await localCtx.newPage();
  await page.goto(local + '/local/?source=' + encodeURIComponent(base));
  await page.getByRole('button', { name: /Register this key on/ }).waitFor();
  await page.getByRole('button', { name: /Register this key on/ }).click();
  await page.getByText('This key is registered on 127.0.0.1:' + sourcePort + ' as alice.', { exact: false }).waitFor();
  await page.getByText('open this key on').waitFor();
  await page.getByRole('button', { name: /Register this key on/ }).waitFor({ state: 'hidden' });
  assert.equal(await page.getByRole('button', { name: /Pull my messages/ }).isVisible(), false, 'No pull before approval');

  // 2. Alice approves the new key on the source; her old device visits and donates history.
  const oldCtx = await device(aliceOld, base);
  await authenticate(oldCtx, base, aliceOld);
  const prepared = await json(await oldCtx.request.post(base + '/api/prepare_approval', { data: { profile_slug: 'openpgp-' + aliceLocal.fingerprint } }));
  await json(await oldCtx.request.post(base + '/api/create_prepared_approval', { data: { prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path, author_identity_id: 'openpgp:' + aliceOld.fingerprint, canonical_record: prepared.canonical_record, detached_signature: await sign(aliceOld, prepared.canonical_record) } }));
  const donor = await oldCtx.newPage(); await donor.goto(base + '/');
  await donor.waitForFunction(() => window.ForumPrivateMessageUnread?.historyEligibility() === true);
  await donor.waitForFunction(() => { const s = window.ForumPrivateMessageHistorySync?.state(); return s && !s.pending && s.contributed > 0; }, null, { timeout: 120000 });
  assert.equal(Number(fixture({ action: 'contributions', source: aliceOld.fingerprint, target: aliceLocal.fingerprint })), 8);
  await donor.close();
  // Bob's device donates too (sender assistance), so Bob's four messages get a second history key.
  const bobCtx = await device(bob, base);
  await authenticate(bobCtx, base, bob);
  const bobDonor = await bobCtx.newPage(); await bobDonor.goto(base + '/');
  await bobDonor.waitForFunction(() => window.ForumPrivateMessageUnread?.historyEligibility() === true);
  await bobDonor.waitForFunction(() => { const s = window.ForumPrivateMessageHistorySync?.state(); return s && !s.pending && s.contributed > 0; }, null, { timeout: 120000 });
  assert(Number(fixture({ action: 'contributions', source: bob.fingerprint, target: aliceLocal.fingerprint })) > 0, 'Bob donated for the new key');
  await bobDonor.close();

  // 3. Approved on the source but not yet here: the page asks for the import.
  await page.getByRole('button', { name: 'Check again' }).click();
  await page.getByText('Approved on 127.0.0.1:' + sourcePort).waitFor();
  // Replay: pulling before the import gets the mail but cannot trust unknown keys.
  await page.getByRole('button', { name: /Pull my messages/ }).click();
  await status(page).filter({ hasText: 'Import public content above, then pull again.' }).waitFor({ timeout: 120000 });
  assert.match(await summary(page).textContent(), /8 new/);
  // The fixture writes approval seeds straight into the source repository; commit them as a real instance would have.
  spawnSync('git', ['-C', join(sourceRoot, 'repository'), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qam', 'Fixture approvals'], { encoding: 'utf8' });
  spawnSync('git', ['-C', join(sourceRoot, 'repository'), 'add', '-A'], { encoding: 'utf8' });
  spawnSync('git', ['-C', join(sourceRoot, 'repository'), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'Fixture approvals'], { encoding: 'utf8' });
  await page.locator('[data-local-import] button[type="submit"]').click();
  // Like `./v3 start`, run the task worker for the queued import.
  await page.getByText('Waiting for the task worker').waitFor({ timeout: 30000 }).catch(() => {});
  const worker = spawnSync('php', [join(project, 'scripts/task_queue.php'), 'run', '--limit=1'], { cwd: project, env: localEnv, encoding: 'utf8', timeout: 240000 });
  assert.equal(worker.status, 0, worker.stderr + worker.stdout);
  // The import is a queued task; wait for the card's last-import line, then reload for a fresh key check.
  await page.locator('[data-import-last]').waitFor({ timeout: 240000 });
  await page.goto(local + '/local/?source=' + encodeURIComponent(base));
  await status(page).filter({ hasText: 'This key is ready.' }).waitFor({ timeout: 120000 });

  // 4. Pull, verify, confirm.
  await page.getByRole('button', { name: /Pull my messages/ }).click();
  // The clone imported keys but no approvals; one click trusts the keys the source approves.
  await page.getByRole('button', { name: 'Trust these keys here' }).waitFor({ timeout: 120000 });
  // One row per person, opening to fingerprints.
  const groups = await page.locator('[data-local-pull-trust-list] > li > details > summary').allTextContents();
  assert.deepEqual(groups.sort(), ['alice: 2 keys, including this browser', 'bob: 1 key'], groups.join('\n'));
  await page.locator('[data-local-pull-trust-list] details').first().evaluate(node => { node.open = true; });
  await page.locator('[data-local-pull-trust]').screenshot({ path: join(root, 'trust-list-desktop.png') });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('[data-local-pull-trust]').screenshot({ path: join(root, 'trust-list-mobile.png') });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.setViewportSize({ width: 1100, height: 800 });
  // Record the button's text while it works, to see the per-key progress.
  await page.evaluate(() => {
    const button = document.querySelector('[data-local-pull-trust-button]');
    window.__trustTexts = [];
    new MutationObserver(() => window.__trustTexts.push(button.textContent + (button.getAttribute('aria-busy') === 'true' ? ' [busy]' : ''))).observe(button, { childList: true, characterData: true, subtree: true, attributes: true });
  });
  await page.getByRole('button', { name: 'Trust these keys here' }).click();
  await status(page).filter({ hasText: 'Pull finished.' }).waitFor({ timeout: 120000 });
  const trustTexts = await page.evaluate(() => window.__trustTexts);
  for (const expected of ['Trusting 1 of 3… [busy]', 'Trusting 2 of 3… [busy]', 'Trusting 3 of 3… [busy]']) assert(trustTexts.includes(expected), trustTexts.join('\n'));
  const first = await summary(page).textContent();
  assert.match(first, /0 new · 8 already here/); assert.match(first, /8 confirmed on/); assert.doesNotMatch(first, /not readable/);
  const localCount = php(`echo (new PDO('sqlite:${join(localRoot, 'private/messages.sqlite3')}'))->query('SELECT COUNT(*) FROM private_messages')->fetchColumn();`);
  assert.equal(Number(localCount), 8, 'Only Alice\'s own mail is pulled');
  const coverage = php(`$q=(new PDO('sqlite:${join(sourceRoot, 'state/private/message_history_sync.sqlite3')}'))->prepare('SELECT COUNT(*) FROM history_sync_coverage WHERE target=?');$q->execute([${JSON.stringify(aliceLocal.fingerprint)}]);echo $q->fetchColumn();`);
  assert.equal(Number(coverage), 8, 'The source records each verified transfer as confirmed');

  // 5. A second pull adds nothing.
  await page.getByRole('button', { name: /Pull my messages/ }).click();
  await summary(page).filter({ hasText: /^Pulled: 0 new · 8 already here/ }).waitFor({ timeout: 120000 });
  await page.screenshot({ path: join(root, 'local-pull-desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: join(root, 'local-pull-mobile.png'), fullPage: true });

  // 6. Local Messages reads every pulled message with a verified sender.
  await authenticate(localCtx, local, aliceLocal);
  const reader = await localCtx.newPage();
  await reader.goto(local + '/messages/conversation/bob');
  await reader.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled === '1', null, { timeout: 120000 });
  await reader.waitForFunction(count => document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length === count, 8, { timeout: 120000 });
  assert.deepEqual(errors, []);
  // 7. Names differ: the clone holds this key under another name. The card says so before Pull, with Details.
  const index = join(localRoot, 'cache/index.sqlite3');
  php(`$p=new PDO('sqlite:${index}');$q=$p->prepare('UPDATE profiles SET username=? WHERE identity_id=?');$q->execute(['alice-here','openpgp:${aliceLocal.fingerprint}']);`);
  await page.goto(local + '/local/?source=' + encodeURIComponent(base));
  await status(page).filter({ hasText: 'has this key as "alice", this clone as "alice-here"' }).waitFor({ timeout: 60000 });
  assert.equal(await page.getByRole('button', { name: /Pull my messages/ }).isVisible(), false, 'No Pull while the names differ');
  const details = page.locator('[data-local-pull-details]');
  assert.equal(await details.evaluate(node => node.open), false, 'Details start closed');
  await details.locator('summary').click();
  const detailText = await details.locator('pre').textContent();
  for (const line of ['step: local pull: key_status', 'source_username: alice', 'local_username: alice-here', 'key: openpgp:' + aliceLocal.fingerprint, 'browser: ']) {
    assert(detailText.includes(line), 'Details include ' + line + '\n' + detailText);
  }
  assert.doesNotMatch(detailText, /PRIVATE KEY|v3_session|BEGIN PGP MESSAGE/);
  await details.getByRole('button', { name: 'Copy' }).waitFor();
  await page.locator('[data-local-pull]').screenshot({ path: join(root, 'mismatch.png') });
  console.log('PASS register, approve, import, pull, verify, confirm, repeat, read, name mismatch. Artifacts: ' + root);
} catch (error) {
  console.error(log.split('\n').filter(line => !/Accepted|Closing|\[200\]|V3DEV\t200/.test(line)).slice(-60).join('\n'));
  if (page) console.error('PAGE:\n' + await page.locator('main').innerText().catch(() => '(no page)'));
  throw error;
} finally {
  if (browser) await browser.close();
  for (const server of servers) server.kill();
}
