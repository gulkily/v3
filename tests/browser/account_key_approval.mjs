import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
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
  // Build a bounded, isolated history large enough for two Load more clicks.
  const dated = new Date(Date.now() - 120000);
  for (let index = 0; index < 176; index++) {
    const id = 'forte-browser-' + String(index).padStart(3, '0');
    const createdAt = new Date(dated.getTime() - index * 1000).toISOString().replace('.000Z', 'Z').replace(/\.\d{3}Z$/, 'Z');
    await writeFile(join(root, 'repository/records/posts', id + '.txt'), `Post-ID: ${id}\nCreated-At: ${createdAt}\nBoard-Tags: general\nSubject: Forte browser event ${index}\nAuthor-Identity-ID: openpgp:${fingerprint}\n\nIsolated activity browser fixture.\n`);
  }
  for (const [command, args] of [
    ['git', ['-C', join(root, 'repository'), 'add', 'records/posts']],
    ['git', ['-C', join(root, 'repository'), 'commit', '-qm', 'Add isolated Forte activity pagination fixture']],
    [join(project, 'v3'), ['rebuild', join(root, 'repository'), join(root, 'read.sqlite3')]],
  ]) {
    const result = spawnSync(command, args, { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
  }
  for (const [width, theme] of [[1280, 'light'], [390, 'light'], [1280, 'dark'], [390, 'dark']]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + '/activity/?view=all');
    await page.evaluate(theme => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme), theme);
    const classic = await (await context.request.get(base + '/activity/?view=all')).text();
    await page.goto(base + '/forte/activity/?view=all');
    const visibleRows = page.locator('[data-paned-activity-list-body] .paned-list-row:visible');
    assert.equal(await visibleRows.count(), 20);
    const more = page.locator('[data-paned-activity-load-more][data-paned-activity-view="all"]');
    for (let n = 0; n < 2; n++) {
      await Promise.all([
        page.waitForResponse(response => response.url().includes('/api/forte_activity_page?rows_only=1&view=all')),
        more.click(),
      ]);
      await page.waitForFunction(() => !document.querySelector('[data-paned-activity-load-more][data-paned-activity-view="all"]').disabled);
    }
    const ids = await visibleRows.evaluateAll(rows => rows.map(row => row.dataset.panedActivityId));
    assert.equal(new Set(ids).size, ids.length);
    assert.ok(ids.length > 176);
    assert.equal(await more.isVisible(), false);
    const firstLabel = await visibleRows.first().locator('.paned-list-subject').innerText();
    assert.ok(classic.includes(firstLabel), firstLabel);
    await visibleRows.first().click();
    const selectedDetail = page.locator('[data-paned-activity-content-item-id]:visible');
    await selectedDetail.locator('.paned-content-meta').filter({ hasText: 'browser-inviter' }).waitFor();
    assert.equal(await page.getByRole('link', { name: 'RSS', exact: true }).getAttribute('href'), '/activity/?view=all&format=rss');
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.locator('[data-paned-activity-filter-tree] [data-paned-activity-view="approval"]').click();
    await page.waitForFunction(() => document.querySelectorAll('[data-paned-activity-list-body] .paned-list-row:not([hidden])').length === 3);
    assert.equal(await page.getByRole('link', { name: 'RSS', exact: true }).getAttribute('href'), '/activity/?view=approval&format=rss');
    await page.locator('[data-paned-sort-column="kind"]').click();
    await page.waitForURL(/sort=kind/);
    await page.locator('[data-paned-activity-filter-tree] [data-paned-activity-view="security"]').click();
    await page.waitForURL(/view=security/);
    assert.equal(await visibleRows.count(), 3);
    await visibleRows.first().click();
    await page.locator('[data-paned-activity-content-item-id]:visible').getByRole('link', { name: 'Acceptance receipt', exact: true }).waitFor();
    assert.match(await page.locator('.paned-window .activity-security-notice').innerText(), /Current cross-account policy: restricted/);
    assert.equal(await page.locator('[data-paned-activity-rss]').isVisible(), false);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    const filterInView = await page.locator('[data-paned-activity-filter-tree]').evaluate(tree => {
      const selected = tree.querySelector('[aria-selected="true"]').getBoundingClientRect();
      const bounds = tree.getBoundingClientRect();
      return selected.top >= bounds.top && selected.bottom <= bounds.bottom;
    });
    assert.equal(filterInView, true, 'selected category stays visible inside the phone folder list');
    if (process.env.FORTE_SCREENSHOT_DIR) await page.screenshot({ path: join(process.env.FORTE_SCREENSHOT_DIR, `security-${theme}-${width}.png`) });
    const securityId = await visibleRows.first().getAttribute('data-paned-activity-id');
    for (const path of ['/forte/activity/?view=security', '/api/forte_activity_page?view=security&rows_only=1', '/api/forte_activity_detail?view=security&id=' + securityId]) {
      const response = await context.request.get(base + path);
      assert.equal(response.status(), 200);
      assert.match(response.headers()['cache-control'], /no-store/);
    }
    await page.locator('[data-paned-activity-filter-tree] [data-paned-activity-view="all"]').click();
    await page.waitForURL(url => !url.searchParams.has('view'));
    assert.equal(await page.locator('.activity-security-notice').count(), 0);
    assert.equal(await page.getByRole('link', { name: 'Acceptance receipt', exact: true }).count(), 0);
    await page.locator('[data-paned-activity-filter-tree] [data-paned-activity-view="commits"]').click();
    const commits = page.locator('[data-paned-activity-commit-sha]:visible');
    await commits.first().waitFor(); await commits.first().click();
    await page.locator('[data-paned-activity-commit-detail]:visible .activity-commit-manifest').waitFor();
    assert.equal(await page.locator('[data-paned-activity-rss]').isVisible(), false);
    assert.deepEqual(pageErrors, []);
    page.removeAllListeners('pageerror');
  }
  const anonymous = await browser.newContext({ serviceWorkers: 'block' });
  await anonymous.addCookies([{ name: 'identity_hint', value: 'openpgp:' + fingerprint, url: base }]);
  const denied = await anonymous.request.get(base + '/forte/activity/?view=security');
  assert.equal(denied.status(), 403);
  assert.match(denied.headers()['cache-control'], /no-store/);
  assert.equal((await context.request.get(base + '/activity/?view=security&format=rss')).status(), 406);
  await anonymous.close();
  console.log('PASS real HTTP Forte Activity: desktop/phone, light/dark, repeated pagination, author/evidence parity, sort, operator view transitions, no-store/denied access, RSS, full commit details');
  console.log('PASS actual signed approvals: three labels, mobile/desktop, cancel/retry, restricted operator override, history/rebuild, no-store/conditional request');
  console.log('PASS local release rehearsal: repeated rebuild, static user listing, account history, status, approval audit');
} catch (error) { console.error(activityDebug.slice(-20)); console.error(logs.slice(-3000)); throw error; }
finally {
  if (browser) await browser.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  await rm(root, { recursive: true, force: true });
}
