// Two real instances: a source with Alice's mail and a local development clone, in one browser.
// Alice's key is in the browser on the source only. On the clone she presses Connect, confirms
// in the source's window, and the page imports, pulls, trusts and turns on saved copies; a page
// reload falls in the middle of the import. Then Messages reads her mail from saved copies, a
// second pull adds nothing, and no log holds a private key.
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

const [alice, bob] = await Promise.all(['alice', 'bob'].map(key));
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
  for (const who of [alice, bob]) {
    const prepared = await json(await anonymous.request.post(base + '/api/prepare_identity', { data: { public_key: who.publicKey } }));
    await json(await anonymous.request.post(base + '/api/create_identity', { data: { prepare_token: prepared.prepare_token, canonical_record: prepared.canonical_record, detached_signature: await sign(who, prepared.canonical_record) } }));
  }
  fixture({ action: 'seed-approvals', fingerprints: [alice.fingerprint, bob.fingerprint] });
  // The fixture writes approval seeds straight into the source repository; commit them as a real instance would have.
  spawnSync('git', ['-C', join(sourceRoot, 'repository'), 'add', '-A'], { encoding: 'utf8' });
  spawnSync('git', ['-C', join(sourceRoot, 'repository'), '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'Fixture approvals'], { encoding: 'utf8' });
  const messages = [];
  for (let n = 1; n <= 8; n++) {
    const sender = n % 2 ? bob : alice;
    const envelope = await pgp.encrypt({ message: await pgp.createMessage({ text: 'Pulled secret ' + n }), encryptionKeys: [alice.public, bob.public], signingKeys: sender.private, format: 'armored' });
    messages.push({ id: 'pull-' + n, time: `2026-11-10T12:0${n}:00Z`, sender: sender.name, recipient: sender === bob ? 'alice' : 'bob', fingerprint: sender.fingerprint, envelope });
  }
  messages.push({ id: 'carol-only', time: '2026-11-10T12:30:00Z', sender: 'bob', recipient: 'carol', fingerprint: bob.fingerprint, envelope: messages[0].envelope });
  fixture({ action: 'messages', messages });

  // One browser: Alice's key on the source origin only; the clone starts with no key.
  const ctx = await device(alice, base);
  page = await ctx.newPage();
  await page.goto(local + '/local/?source=' + encodeURIComponent(base));
  await status(page).filter({ hasText: 'Press Connect' }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Pull new messages' }).isVisible(), false, 'No Pull before Connect');
  await page.screenshot({ path: join(root, 'connect-desktop.png'), fullPage: true });

  // 1. Connect, then Use it there in the source's window.
  const [popup] = await Promise.all([page.waitForEvent('popup'), page.getByRole('button', { name: /^Connect to / }).click()]);
  await popup.getByRole('button', { name: 'Use it there' }).click();
  await page.waitForFunction(fingerprint => (localStorage.getItem('forum_pki_fingerprint') || '').toLowerCase() === fingerprint, alice.fingerprint);

  // 2. The clone does not know the key, so Connect imports first. Run the task worker, like
  // `./v3 start`, and reload the page while the import is queued: Connect must resume.
  await status(page).filter({ hasText: 'Importing public content' }).waitFor({ timeout: 60000 });
  await page.reload();
  await status(page).filter({ hasText: /Continuing Connect|Importing public content/ }).waitFor({ timeout: 30000 });
  const worker = spawn('php', [join(project, 'scripts/task_queue.php'), 'run', '--limit=1'], { cwd: project, env: localEnv, stdio: ['ignore', 'pipe', 'pipe'] });
  let workerOutput = ''; worker.stdout.on('data', d => { workerOutput += d; }); worker.stderr.on('data', d => { workerOutput += d; });
  const [workerCode] = await once(worker, 'exit');
  assert.equal(workerCode, 0, workerOutput);
  // import_control.js reloads the page when the import ends; Connect resumes after that too.
  await status(page).filter({ hasText: 'Connected. Open Messages' }).waitFor({ timeout: 240000 });
  // The summary is the last pass's: a reload after the pull resumes and pulls again, adding nothing.
  const [, added, existing] = (await summary(page).textContent()).match(/^Pulled: (\d+) new · (\d+) already here/);
  assert.equal(Number(added) + Number(existing), 8);
  assert.equal(await page.evaluate(() => sessionStorage.getItem('forum-local-connect')), null, 'Connect clears its stage when done');
  const localCount = php(`echo (new PDO('sqlite:${join(localRoot, 'private/messages.sqlite3')}'))->query('SELECT COUNT(*) FROM private_messages')->fetchColumn();`);
  assert.equal(Number(localCount), 8, 'Only Alice\'s own mail is pulled');
  const flags = readFileSync(join(localRoot, 'repository/records/instance/feature-flags.txt'), 'utf8');
  assert.match(flags, /FORUM_PRIVATE_MESSAGE_CACHE_ENABLED/);
  await page.screenshot({ path: join(root, 'connected-desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: join(root, 'connected-mobile.png'), fullPage: true });
  await page.setViewportSize({ width: 1100, height: 800 });

  // 3. Local Messages reads every pulled message with a verified sender, then from saved copies on three reloads.
  const reader = await ctx.newPage();
  await reader.goto(local + '/messages/conversation/bob');
  const settled = async () => {
    await reader.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled === '1', null, { timeout: 120000 });
    await reader.waitForFunction(count => document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length === count, 8, { timeout: 120000 });
  };
  await settled();
  const savedIds = async () => reader.evaluate(async () => {
    const cache = window.ForumPrivateMessageCache;
    if (!cache || !cache.enabled()) return -1;
    let found = 0;
    for (const card of document.querySelectorAll('[data-private-message-id]')) if (await cache.get(card.dataset.privateMessageId) !== null) found++;
    return found;
  });
  for (let n = 0; n < 50 && await savedIds() !== 8; n++) await reader.waitForTimeout(200);
  assert.equal(await savedIds(), 8, 'Saved copies are on and hold every message');
  for (let n = 0; n < 3; n++) { await reader.reload(); await settled(); }
  assert.equal(await savedIds(), 8);
  await reader.close();

  // 4. Pull again adds nothing, and needs no window.
  await page.goto(local + '/local/?source=' + encodeURIComponent(base));
  await status(page).filter({ hasText: 'Connected as alice' }).waitFor({ timeout: 60000 });
  assert.equal(await page.getByRole('button', { name: 'Pull new messages' }).isVisible(), true);
  await page.getByRole('button', { name: 'Pull new messages' }).click();
  await status(page).filter({ hasText: 'Connected. Open Messages' }).waitFor({ timeout: 120000 });
  assert.match(await summary(page).textContent(), /^Pulled: 0 new · 8 already here/);
  assert.deepEqual(errors, []);

  // 5. Names differ: the clone holds this key under another name. Pull stops with both names, with Details.
  const index = join(localRoot, 'cache/index.sqlite3');
  php(`$p=new PDO('sqlite:${index}');$q=$p->prepare('UPDATE profiles SET username=? WHERE identity_id=?');$q->execute(['alice-here','openpgp:${alice.fingerprint}']);`);
  await page.getByRole('button', { name: 'Pull new messages' }).click();
  await status(page).filter({ hasText: 'has this key as "alice", this clone as "alice-here"' }).waitFor({ timeout: 60000 });
  assert.doesNotMatch(await status(page).textContent(), /Press Connect/, 'A name mismatch is not fixed by retrying');
  assert.equal(await summary(page).isVisible(), false, 'No stale summary under an error');
  const details = page.locator('[data-local-pull-details]');
  assert.equal(await details.evaluate(node => node.open), false, 'Details start closed');
  await details.locator('summary').click();
  const detailText = await details.locator('pre').textContent();
  for (const line of ['step: local pull: connect key_status', 'source_username: alice', 'local_username: alice-here', 'browser: ']) {
    assert(detailText.includes(line), 'Details include ' + line + '\n' + detailText);
  }
  assert.doesNotMatch(detailText, /PRIVATE KEY|v3_session|BEGIN PGP MESSAGE/);
  await page.locator('[data-local-pull]').screenshot({ path: join(root, 'mismatch.png') });

  // 6. No log holds a private key: the handoff goes page to page, never through a server.
  // Both servers' stderr, which includes the local dev request log (V3_DEV_LOG).
  assert.doesNotMatch(log, /PRIVATE KEY BLOCK/);
  console.log('PASS connect, handoff, import with a reload, pull, trust, saved copies, three reloads, pull again, name mismatch, no key in logs. Artifacts: ' + root);
} catch (error) {
  console.error(log.split('\n').filter(line => !/Accepted|Closing|\[200\]|V3DEV\t200/.test(line)).slice(-60).join('\n'));
  if (page) console.error('PAGE:\n' + await page.locator('main').innerText().catch(() => '(no page)'));
  throw error;
} finally {
  if (browser) await browser.close();
  for (const server of servers) server.kill();
}
