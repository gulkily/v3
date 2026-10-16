import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
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
async function keys(name) { return pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored', date: new Date(Date.now() - 60000) }); }
const operator = await keys('browser-inviter');
const fingerprint = (await pgp.readKey({ armoredKey: operator.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'approval-browser-'));
let server, browser, logs = '';
try {
  const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
  assert.equal(fixture.status, 0, fixture.stderr);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false',
      FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL: 'false', PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  await context.addInitScript(({ operator, fingerprint }) => {
    for (const [key, value] of Object.entries({ username: 'browser-inviter', public_key: operator.publicKey, private_key: operator.privateKey, fingerprint, published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
  }, { operator, fingerprint });
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  async function target(name) {
    const generated = await keys(name);
    const response = await context.request.post(base + '/api/link_identity', { form: { public_key: generated.publicKey } });
    const values = Object.fromEntries((await response.text()).trim().split('\n').map(line => { const i = line.indexOf('='); return [line.slice(0, i), line.slice(i + 1)]; }));
    assert.ok(values.profile_slug, JSON.stringify(values));
    return values;
  }
  const first = await target('browser-member');
  const own = await target('browser-inviter');
  const cross = await target('browser-member');
  async function approve(target, label, cancel = false) {
    await page.goto(base + '/profiles/' + target.profile_slug);
    assert.equal(await page.locator('[data-signed-approval-form] button').innerText(), label);
    page.once('dialog', async dialog => {
      assert.match(dialog.message(), new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
      assert.ok(dialog.message().includes(target.identity_id));
      if (label.startsWith('Grant')) assert.match(dialog.message(), /already has 1 approved keys/);
      await (cancel ? dialog.dismiss() : dialog.accept());
    });
    await page.locator('[data-signed-approval-form] button').click();
    if (cancel) {
      await page.locator('[data-role=signed-approval-feedback]').filter({ hasText: 'cancelled' }).waitFor();
      const profile = await context.request.get(base + '/api/get_profile?profile_slug=' + target.profile_slug);
      assert.match(await profile.text(), /Approved: no/);
    } else {
      await page.waitForURL(/approval=success/);
      assert.match(await page.locator('.account-key-history').innerText(), /Accepted:/);
    }
  }
  await approve(first, 'Approve new member browser-member', true);
  await approve(first, 'Approve new member browser-member');
  await page.setViewportSize({ width: 1280, height: 800 });
  await approve(own, 'Add this key to my account');
  await page.setViewportSize({ width: 390, height: 844 });
  await approve(cross, 'Grant this key access as browser-member');
  await page.goto(base + '/user/browser-member');
  assert.match(await page.locator('.account-key-history').innerText(), /operator override/);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  const review = await context.request.get(base + '/activity/?view=security');
  assert.equal(review.status(), 200);
  assert.match(review.headers()['cache-control'], /no-store/);
  if (review.headers().etag) assert.equal((await context.request.get(base + '/activity/?view=security', { headers: { 'If-None-Match': review.headers().etag } })).status(), 200);
  const rebuild = spawnSync(join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')], { encoding: 'utf8' });
  assert.equal(rebuild.status, 0, rebuild.stderr);
  await page.reload();
  assert.match(await page.locator('.account-key-history').innerText(), /operator override/);
  for (const args of [
    ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')],
    ['build-static', join(root, 'repository'), join(root, 'read.sqlite3'), join(root, 'static')],
    ['status', '--repository-root=' + join(root, 'repository'), '--database-path=' + join(root, 'read.sqlite3')],
    ['approval', 'audit', '--repository-root=' + join(root, 'repository'), '--database-path=' + join(root, 'read.sqlite3'), '--summary'],
  ]) {
    const result = spawnSync(join(project, 'v3'), args, { encoding: 'utf8' });
    assert.equal(result.status, 0, args.join(' ') + '\n' + result.stdout + result.stderr);
  }
  const published = await readFile(join(root, 'static/current/users.html'), 'utf8');
  assert.match(published, /browser-member/);
  await page.reload();
  assert.match(await page.locator('.account-key-history').innerText(), /operator override/);
  console.log('PASS actual signed approvals: three labels, mobile/desktop, cancel/retry, restricted operator override, history/rebuild, no-store/conditional request');
  console.log('PASS local release rehearsal: repeated rebuild, static user listing, account history, status, approval audit');
} catch (error) { console.error(logs.slice(-8000)); throw error; }
finally {
  if (browser) await browser.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  await rm(root, { recursive: true, force: true });
}
