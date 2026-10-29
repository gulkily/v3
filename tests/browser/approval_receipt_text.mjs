import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm, chmod, mkdir } from 'node:fs/promises';
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
let server, browser, logs = '', activityDebug = [];
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
  page.on('pageerror', error => activityDebug.push(error.message));
  page.on('request', request => { if (request.url().includes('forte_activity_')) activityDebug.push(request.url()); });
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  async function target(name) {
    const generated = await keys(name);
    const response = await context.request.post(base + '/api/link_identity', { form: { public_key: generated.publicKey } });
    const values = Object.fromEntries((await response.text()).trim().split('\n').map(line => { const i = line.indexOf('='); return [line.slice(0, i), line.slice(i + 1)]; }));
    assert.ok(values.profile_slug, JSON.stringify(values));
    return values;
  }
  const member = await target('receipt-member');
  const own = await target('browser-inviter');
  const cross = await target('receipt-member');
  const profile = '/tools/user-access/profiles/' + member.profile_slug;
  await page.goto(base + profile);
  const hook = join(root, 'repository/.git/hooks/pre-commit');
  await writeFile(hook, '#!/bin/sh\nexit 1\n'); await chmod(hook, 0o700);
  await page.locator('[data-signed-approval-form] button').click();
  await page.waitForFunction(() => {
    const feedback = document.querySelector('[data-role="signed-approval-feedback"]');
    return feedback && !feedback.hidden && !document.querySelector('[data-signed-approval-form] button').disabled && feedback.textContent.includes('commit');
  });
  await rm(hook);
  await page.locator('[data-signed-approval-form] button').click();
  await page.waitForURL(/approval=success/);
  for (const targetKey of [own, cross]) {
    await page.goto(base + '/tools/user-access/profiles/' + targetKey.profile_slug);
    await page.locator('[data-signed-approval-form] button').click();
    await page.waitForURL(/approval=success/);
  }
  const themes = await page.evaluate(() => Object.keys(window.forumThemeStylesheetPaths).filter(name => name !== 'auto'));
  let receiptPath;
  const themeStorageKey = await page.evaluate(() => window.forumBrowserRuntime.themeStorageKey);
  for (const theme of themes) {
    await page.evaluate(({theme, key}) => localStorage.setItem(key, theme), {theme, key: themeStorageKey});
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 844 });
      for (const route of [profile, '/tools/user-access/accounts/receipt-member', '/forte/user/receipt-member', '/forte/profiles/' + member.profile_slug]) {
        await page.goto(base + route);
        if (!route.startsWith('/forte/')) assert.equal(await page.locator('html').getAttribute('data-theme'), theme, route);
        const link = page.locator('a[href^="/source/current/records/approval-acceptances/"]').first();
        assert.ok(await link.count(), route + ' ' + theme + ' missing receipt');
        receiptPath = await link.getAttribute('href'); assert.match(receiptPath, /approval-acceptances\/[^/]+\.txt$/);
        // Expand evidence as a person would before following the source link.
        await link.evaluate(el => { for (let node = el.parentElement; node; node = node.parentElement) if (node.tagName === 'DETAILS') node.open = true; });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, route + ' ' + theme + ' ' + width);
        if (process.env.RECEIPT_SCREENSHOT_DIR && route === profile && ['light', 'word97'].includes(theme)) {
          await mkdir(process.env.RECEIPT_SCREENSHOT_DIR, { recursive: true });
          await page.screenshot({ path: join(process.env.RECEIPT_SCREENSHOT_DIR, `receipt-${theme}-${width}.png`), fullPage: true });
        }
      }
    }
  }
  await page.locator('a[href^="/source/current/records/approval-acceptances/"]').first().click();
  const raw = await page.locator('body').innerText();
  assert.match(raw, /^Format: approval-acceptance-v2/);
  assert.match(raw, /Context-Classification: new_account/); assert.doesNotMatch(raw, /"context"\s*:/);
  // Rehearse historical conversion with the actual signed grants from this browser flow.
  const repository = join(root, 'repository');
  const before = spawnSync('git', ['-C', repository, 'ls-tree', '-r', 'HEAD', 'records/posts', 'records/public-keys'], { encoding: 'utf8' }).stdout;
  const legacyFixture = join(root, 'legacy-fixture.php');
  await writeFile(legacyFixture, `<?php require $argv[1]; foreach (glob($argv[2] . '/records/approval-acceptances/*.txt') as $file) { $data = \\ForumRewrite\\Security\\ApprovalReceiptText::decode(file_get_contents($file)); file_put_contents(substr($file, 0, -4) . '.json', json_encode($data)); unlink($file); }`);
  for (const [cmd, args] of [
    ['php', [legacyFixture, join(project, 'autoload.php'), repository]],
    ['git', ['-C', repository, 'add', 'records/approval-acceptances']],
    ['git', ['-C', repository, 'commit', '-qm', 'Historical receipt fixture']],
    [join(project, 'v3'), ['approval', 'receipts', 'convert', '--repository-root=' + repository, '--database-path=' + join(root, 'read.sqlite3')]],
  ]) {
    const result = spawnSync(cmd, args, { encoding: 'utf8' }); assert.equal(result.status, 0, result.stdout + result.stderr);
  }
  assert.equal(spawnSync('git', ['-C', repository, 'ls-tree', '-r', 'HEAD', 'records/posts', 'records/public-keys'], { encoding: 'utf8' }).stdout, before);
  const command = spawnSync(join(project, 'v3'), ['approval', 'receipts', 'readiness', '--repository-root=' + join(root, 'repository'), '--database-path=' + join(root, 'read.sqlite3')], { encoding: 'utf8' });
  assert.equal(command.status, 0, command.stdout + command.stderr); assert.match(command.stdout, /Live-Repository-Ready: yes/);
  const rebuild = spawnSync(join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')], { encoding: 'utf8' });
  assert.equal(rebuild.status, 0, rebuild.stderr);
  await page.goto(base + profile); assert.ok(await page.locator('a[href^="/source/current/records/approval-acceptances/"]').count());
  console.log(`PASS signed new/own/cross-account approvals, failed-commit retry, text source, rebuild/readiness; ${themes.length} themes x 2 widths on Tools history, plus standalone Forte histories; signed-evidence conversion`);
} catch (error) { console.error(logs.slice(-3000)); throw error; }
finally {
  if (browser) await browser.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  await rm(root, { recursive: true, force: true });
}
