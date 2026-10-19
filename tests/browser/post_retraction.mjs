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
for (const role of ['author', 'operator', 'account']) keys[role] = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: role === 'account' ? 'RETRACTION-AUTHOR' : `retraction-${role}` }], format: 'armored' });
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
  for (const scenario of (process.env.RETRACTION_SCENARIO ? [process.env.RETRACTION_SCENARIO] : ['author', 'operator', 'account', 'partial'])) {
    const role = scenario === 'partial' ? 'account' : scenario;
    const notice = role === 'operator' ? 'retracted by an operator' : 'retracted by its author';
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
    // Authenticate before opening the page; automatic session restoration navigates
    // and would discard mocked failures/menu state while this test interacts.
    const challenge = (await (await context.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
    const authSignature = await openpgp.sign({ message: await openpgp.createMessage({ text: challenge }), signingKeys: await openpgp.readPrivateKey({ armoredKey: keys[role].privateKey }), detached: true });
    const authenticated = await context.request.post(base + '/api/authenticate_identity', { form: { challenge, detached_signature: authSignature, identity_id: identities[role] } });
    assert.equal(authenticated.status(), 200, await authenticated.text());
    const page = await context.newPage(); page.setDefaultTimeout(15000);
    page.on('pageerror', error => console.error('Browser error:', error.message));
    let confirmations = 0;
    page.on('dialog', dialog => { assert.match(dialog.message(), /^Retract this post\?/); confirmations++; return confirmations === 1 ? dialog.dismiss() : dialog.accept(); });
    const post = posts[scenario];
    await page.goto(base + '/posts/' + post.post_id);
    await page.waitForFunction(() => !document.querySelector('[data-public-auth-resume="true"]'));
    await page.waitForFunction(() => typeof window.ForumBrowserSigning?.submitSignedRetraction === 'function');
    const control = page.locator('[data-role="retraction-control"]').first();
    await control.locator('.post-more-actions > summary').click();
    await control.getByRole('button', { name: 'Retract post', exact: true }).click({ noWaitAfter: true });
    assert.equal(confirmations, 1);
    if (scenario === 'operator') {
      // Long transport errors remain readable even on a narrow screen.
      await page.evaluate(() => {
        window.originalRetraction = window.ForumBrowserSigning.submitSignedRetraction;
        window.ForumBrowserSigning.submitSignedRetraction = async () => ({ ok: false, error: 'x'.repeat(1500) });
      });
      if (await control.locator('details.post-more-actions').getAttribute('open') === null) await control.locator('.post-more-actions > summary').click();
      await control.getByRole('button', { name: 'Retract post', exact: true }).click({ noWaitAfter: true });
      await control.locator('[data-role="retraction-details"]').getByText('Details', { exact: true }).click();
      const fits = await control.evaluate(node => {
        const card = node.closest('article') || node.closest('.post-card') || node.parentElement;
        const text = node.querySelector('[data-role="retraction-details"] p');
        return text.scrollWidth <= text.clientWidth + 1 && node.getBoundingClientRect().right <= card.getBoundingClientRect().right + 1;
      });
      assert.equal(fits, true, 'Long error details must fit within the post');
      await page.evaluate(() => { window.ForumBrowserSigning.submitSignedRetraction = window.originalRetraction; });
      await control.locator('.post-more-actions > summary').click();
    }
    let simulated = false;
    if (scenario === 'partial' || scenario === 'operator') {
      await page.route('**/api/create_prepared_retraction', async route => {
        const response = await route.fetch();
        const data = await response.json(); assert.equal(data.retraction_status, 'complete');
        await route.fulfill({ response, json: scenario === 'partial' ? { ...data, retraction_status: 'partial', failed_step: 'artifact_invalidate' } : { ...data, cleanup_status: 'failed' } });
        simulated = true;
      });
    }
    if (await control.locator('details.post-more-actions').getAttribute('open') === null) await control.locator('.post-more-actions > summary').click();
    const completed = page.waitForResponse(response => response.url().endsWith('/api/create_prepared_retraction'));
    completed.catch(() => {});
    await control.getByRole('button', { name: 'Retract post', exact: true }).click({ noWaitAfter: true });
    const response = await completed.catch(async error => { console.error('Control state:', await control.textContent()); throw error; });
    if (scenario === 'partial' || scenario === 'operator') {
      await control.getByRole('button', { name: 'Retry retraction', exact: true }).waitFor({ state: 'visible' });
      assert.equal(await control.locator('details.post-more-actions').getAttribute('open'), null);
      assert.equal(await control.locator('[data-role="retraction-feedback"]').textContent(), scenario === 'partial' ? 'Retracted. Site update incomplete.' : 'Retracted. Cleanup could not be queued.');
      assert.equal(simulated, true);
      await page.unroute('**/api/create_prepared_retraction');
      const before = spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout;
      await control.getByRole('button', { name: 'Retry retraction', exact: true }).click({ noWaitAfter: true });
      await page.waitForFunction(notice => document.body.textContent.includes(notice), notice);
      const after = spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout;
      assert.equal(after, before);
    } else {
      await page.waitForFunction(notice => document.body.textContent.includes(notice), notice);
    }
    await page.getByText('Local cleanup queued.', { exact: true }).waitFor({ state: 'visible' });
    const fields = new URLSearchParams(response.request().postData());
    assert.equal(fields.get('actor_identity_id'), identities[role]);
    assert.match(fields.get('detached_signature'), /BEGIN PGP SIGNATURE/);
    const recordPath = fields.get('record_path');
    const source = await context.request.get(base + '/source/current/' + recordPath);
    assert.equal(source.headers()['x-forum-signature-status'], 'verified');
    const record = await readFile(join(root, 'repository', recordPath), 'utf8');
    assert.match(record, new RegExp(`Author-Identity-ID: ${identities.author}`));
    if (role !== 'author') {
      assert.match(record, new RegExp(`Actor-Identity-ID: ${identities[role]}`));
      assert.match(record, /Retraction-Class: operator/);
    }
    if (scenario === 'account') {
      const rebuild = spawnSync(join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')],
        { env: { ...env, FORUM_STATIC_HTML_ROOT: join(root, 'static') }, encoding: 'utf8' });
      assert.equal(rebuild.status, 0, rebuild.stdout + rebuild.stderr);
      await page.reload();
      await page.waitForFunction(notice => document.body.textContent.includes(notice), notice);
    }
    const acceptedCommit = spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' });
    assert.equal(acceptedCommit.status, 0, acceptedCommit.stderr);
    for (const path of ['/source/current/' + post.record_path, '/source/blob/' + acceptedCommit.stdout.trim() + '/' + post.record_path]) {
      const blocked = await context.request.get(base + path);
      assert.equal(blocked.status(), 410);
      assert.match(await blocked.text(), new RegExp(notice));
    }
    console.log(`PASS real browser ${scenario} retraction, signing and attribution`);
    await context.close();
  }
} catch (error) { console.error(serverLog.slice(-6000)); throw error; }
finally { await browser.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true }); }
