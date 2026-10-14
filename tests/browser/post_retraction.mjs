import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
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
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
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
  await setup.close();
  for (const scenario of ['author', 'operator', 'partial']) {
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
    await page.goto(base + '/posts/' + post.post_id);
    const control = page.locator('[data-role="retraction-control"]').first();
    await control.locator('summary').click();
    await control.getByRole('button', { name: 'Retract post', exact: true }).click();
    assert.equal(confirmations, 1);
    let simulated = false;
    if (scenario === 'partial') {
      await page.route('**/api/create_prepared_retraction', async route => {
        const response = await route.fetch();
        const data = await response.json(); assert.equal(data.retraction_status, 'complete');
        await route.fulfill({ response, json: { ...data, retraction_status: 'partial', failed_step: 'artifact_invalidate' } });
        simulated = true;
      });
    }
    const completed = page.waitForResponse(response => response.url().endsWith('/api/create_prepared_retraction'));
    await control.getByRole('button', { name: 'Retract post', exact: true }).click();
    const response = await completed;
    const fields = new URLSearchParams(response.request().postData());
    assert.equal(fields.get('actor_identity_id'), identities[role]);
    assert.match(fields.get('detached_signature'), /BEGIN PGP SIGNATURE/);
    const recordPath = fields.get('record_path');
    const record = await readFile(join(root, 'repository', recordPath), 'utf8');
    assert.match(record, new RegExp(`Author-Identity-ID: ${identities.author}`));
    if (role === 'operator') assert.match(record, new RegExp(`Actor-Identity-ID: ${identities.operator}`));
    if (scenario === 'partial') {
      await control.getByRole('button', { name: 'Retry retraction', exact: true }).waitFor({ state: 'visible' });
      assert.equal(await control.locator('details').getAttribute('open'), null);
      assert.equal(simulated, true);
      await page.unroute('**/api/create_prepared_retraction');
      const before = spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout;
      await control.getByRole('button', { name: 'Retry retraction', exact: true }).click();
      await page.waitForFunction(() => document.body.textContent.includes('retracted by an operator'));
      const after = spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout;
      assert.equal(after, before);
    } else {
      await page.waitForFunction(role => document.body.textContent.includes(role === 'operator' ? 'retracted by an operator' : 'retracted by its author'), role);
    }
    console.log(`PASS real browser ${scenario} retraction, signing and attribution`);
    await context.close();
  }
} catch (error) { console.error(serverLog.slice(-6000)); throw error; }
finally { await browser.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true }); }
