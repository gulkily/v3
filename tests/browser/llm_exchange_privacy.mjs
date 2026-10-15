import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, mkdir, writeFile } from 'node:fs/promises';
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
const keys = {};
for (const role of ['author', 'operator']) keys[role] = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: `retraction-${role}` }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'post-retraction-browser-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/post_retraction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seed.status, 0, seed.stderr);
const { identities, posts } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  const setup = await browser.newContext();
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const authorKey = await openpgp.readPrivateKey({ armoredKey: keys.author.privateKey });
  for (const prepared of Object.values(posts)) {
    const signature = await openpgp.sign({ message: await openpgp.createMessage({ text: prepared.canonical_record }), signingKeys: authorKey, detached: true });
    const response = await setup.request.post(base + '/api/create_prepared_post', { form: { prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path, canonical_record: prepared.canonical_record, author_identity_id: identities.author, detached_signature: signature } });
    const result = await response.json(); assert.equal(result.status, 'ok', JSON.stringify(result));
  }
  const rows = [];
  for (const scenario of ['author', 'operator']) {
    const postId = posts[scenario].post_id;
    rows.push({ post_id: postId, dependencies: [postId], marker: `PRIVATE-EXCHANGE-${scenario}` });
    rows.push({ post_id: 'root-001', dependencies: ['root-001', postId], marker: `PRIVATE-CONTEXT-${scenario}` });
  }
  rows.push({ post_id: 'root-001', dependencies: ['root-001'], marker: 'UNRELATED-EXCHANGE' });
  rows.push({ post_id: 'root-001', marker: 'UNKNOWN-LEGACY-EXCHANGE' });
  const record = spawnSync('php', [join(project, 'tests/Support/llm_exchange_privacy_fixture.php'), root], {
    input: JSON.stringify({ approve_author: identities.author, exchanges: rows }), encoding: 'utf8', env,
  });
  assert.equal(record.status, 0, record.stderr);
  const exchangeIds = JSON.parse(record.stdout).ids;
  for (const id of exchangeIds) {
    const directory = join(root, 'static/tools/llm-exchanges', String(id));
    await mkdir(directory, { recursive: true });
    await writeFile(join(directory, 'index.html'), 'STALE-EXCHANGE-ARTIFACT');
  }

  await setup.close();
  for (const scenario of ['author', 'operator']) {
    const role = scenario === 'author' ? 'author' : 'operator';
    const context = await browser.newContext(scenario === 'operator' ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, serviceWorkers: 'block' } : { serviceWorkers: 'block' });
    const fingerprint = (await openpgp.readKey({ armoredKey: keys[role].publicKey })).getFingerprint().toUpperCase();
    await context.addCookies([{ name: 'identity_hint', value: identities[role], url: base }]);
    await context.addInitScript(({ key, fingerprint, role }) => {
      localStorage.setItem('forum_pki_private_key', key.privateKey);
      localStorage.setItem('forum_pki_public_key', key.publicKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_username', `retraction-${role}`);
    }, { key: keys[role], fingerprint, role });
    const page = await context.newPage(); page.setDefaultTimeout(15000);
    let confirmations = 0;
    page.on('dialog', dialog => { assert.match(dialog.message(), /^Retract this post\?/); confirmations++; return confirmations === 1 ? dialog.dismiss() : dialog.accept(); });
    const post = posts[scenario];
    const pair = scenario === 'author' ? exchangeIds.slice(0, 2) : exchangeIds.slice(2, 4);
    const original = await context.request.get(base + '/tools/llm-exchanges/' + pair[0]);
    assert.equal(original.status(), 200);
    assert.match(original.headers()['cache-control'], /no-store/);
    const etag = original.headers().etag;
    const originalText = await original.text();
    assert.match(originalText, /PRIVATE-EXCHANGE-/);
    assert.doesNotMatch(originalText, /STALE-EXCHANGE-ARTIFACT/);
    const unknown = await context.request.get(base + '/tools/llm-exchanges/' + exchangeIds[5]);
    assert.equal(unknown.status(), 404);
    assert.doesNotMatch(await unknown.text(), /UNKNOWN-LEGACY-EXCHANGE/);

    await page.goto(base + '/posts/' + post.post_id);
    const control = page.locator('[data-role="retraction-control"]').first();
    await control.locator('summary').click();
    await control.getByRole('button', { name: 'Retract post', exact: true }).click();
    assert.equal(confirmations, 1);
    const completed = page.waitForResponse(response => response.url().endsWith('/api/create_prepared_retraction'));
    await control.getByRole('button', { name: 'Retract post', exact: true }).click();
    const response = await completed;
    const fields = new URLSearchParams(response.request().postData());
    assert.equal(fields.get('actor_identity_id'), identities[role]);
    assert.match(fields.get('detached_signature'), /BEGIN PGP SIGNATURE/);
    const recordPath = fields.get('record_path');
    const source = await context.request.get(base + '/source/current/' + recordPath);
    assert.equal(source.headers()['x-forum-signature-status'], 'verified');
    const record = await readFile(join(root, 'repository', recordPath), 'utf8');
    assert.match(record, new RegExp(`Author-Identity-ID: ${identities.author}`));
    if (role === 'operator') assert.match(record, new RegExp(`Actor-Identity-ID: ${identities.operator}`));
    await page.waitForFunction(role => document.body.textContent.includes(role === 'operator' ? 'retracted by an operator' : 'retracted by its author'), role);
    for (const id of pair) {
      const denied = await context.request.get(base + '/tools/llm-exchanges/' + id, { headers: { 'If-None-Match': etag } });
      assert.equal(denied.status(), 404);
      assert.match(denied.headers()['cache-control'], /no-store/);
      assert.doesNotMatch(await denied.text(), /PRIVATE-(EXCHANGE|CONTEXT)-/);
    }
    await page.goto(base + '/tools/llm-exchanges/');
    for (const id of pair) assert.equal(await page.locator(`a[href="/tools/llm-exchanges/${id}/"]`).count(), 0);
    assert.equal(await page.locator(`a[href="/tools/llm-exchanges/${exchangeIds[4]}/"]`).count(), 1);
    await page.goto(base + '/posts/' + post.post_id);
    assert.equal(await page.locator(`a[href="/tools/llm-exchanges/${pair[0]}"]`).count(), 0);
    const safe = await context.request.get(base + '/tools/llm-exchanges/' + exchangeIds[4]);
    assert.equal(safe.status(), 200);
    assert.match(await safe.text(), /UNRELATED-EXCHANGE/);
    console.log(`PASS browser ${scenario} exchange suppression, links, saved URLs and cache headers`);
    await context.close();
  }
  const late = spawnSync('php', [join(project, 'tests/Support/llm_exchange_privacy_fixture.php'), root], {
    input: JSON.stringify({ exchanges: [
      { post_id: posts.operator.post_id, dependencies: [posts.operator.post_id], marker: 'LATE-PROVIDER-PROSE' },
      { post_id: posts.operator.post_id, dependencies: [posts.operator.post_id], marker: 'FAILED-PROVIDER-PROSE', status: 'provider_error' },
    ] }), encoding: 'utf8', env,
  });
  assert.equal(late.status, 0, late.stderr);
  const lateIds = JSON.parse(late.stdout).ids;
  const review = await browser.newContext();
  await review.addCookies([{ name: 'identity_hint', value: identities.operator, url: base }]);
  for (const id of lateIds) {
    const denied = await review.request.get(base + '/tools/llm-exchanges/' + id);
    assert.equal(denied.status(), 404);
    assert.doesNotMatch(await denied.text(), /(LATE|FAILED)-PROVIDER-PROSE/);
  }
  const evidence = join(root, 'repository/records/post-retractions', `post-retraction-${posts.operator.post_id}.txt`);
  const originalEvidence = await readFile(evidence);
  await writeFile(evidence, 'malformed');
  assert.equal((await review.request.get(base + '/tools/llm-exchanges/' + exchangeIds[4])).status(), 404);
  await writeFile(evidence, originalEvidence);
  assert.equal((await review.request.get(base + '/tools/llm-exchanges/' + exchangeIds[4])).status(), 200);
  await review.close();
  console.log('PASS late/error records, state failure/recovery and stale-artifact bypass prevention');
  const retained = spawnSync('php', [join(project, 'tests/Support/llm_exchange_privacy_fixture.php'), root], { input: JSON.stringify({}), encoding: 'utf8', env });
  assert.equal(retained.status, 0, retained.stderr);
  assert.equal(JSON.parse(retained.stdout).count, 8);
} catch (error) { console.error(serverLog.slice(-6000)); throw error; }
finally { await browser.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true }); }
