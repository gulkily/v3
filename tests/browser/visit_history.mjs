import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const playwright = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');

// Visit history (FORUM_VISIT_HISTORY_ENABLED, cycle nav_tree).
// Each page change becomes a visit whose parent is the tab's current visit.
// The reported flow: Board, a thread, back, a second thread gives the Board
// with two child threads. Back and forward, page-cache restores and reloads
// add nothing. A second tab starts its own branch from the referrer, or as a
// new top when there is none. Storage that throws leaves pages working. With
// the flag off nothing is written.
// Usage: node tests/browser/visit_history.mjs
// BROWSER=firefox or BROWSER=webkit runs it there (needs `npx playwright-core install firefox webkit`).
const project = resolve(new URL('../..', import.meta.url).pathname);
const browserName = process.env.BROWSER || 'chromium';
const root = await mkdtemp(join(tmpdir(), 'visit-history-browser-'));
const repo = join(root, 'repo');
const db = join(root, 'cache', 'read-model.sqlite3');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repo, { recursive: true });
await mkdir(join(root, 'cache'));
const baseEnv = { ...process.env, FORUM_REPOSITORY_ROOT: repo, FORUM_DATABASE_PATH: db,
  FORUM_APP_VERSION_NOTIFICATION: 'false', FORUM_SECRETS_PATH: join(root, 'unused.php') };
const rebuild = spawnSync(join(project, 'v3'), ['rebuild', repo, db], { cwd: project, env: baseEnv, encoding: 'utf8' });
assert.equal(rebuild.status, 0, rebuild.stderr + rebuild.stdout);

async function startServer(flagOn, extra = {}) {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(r => socket.close(r));
  const env = { ...baseEnv, FORUM_VISIT_HISTORY_ENABLED: flagOn ? 'true' : 'false', ...extra };
  const server = spawn(join(project, 'v3'), ['start', `127.0.0.1:${port}`, '--quiet'], { cwd: project, env, detached: true });
  const base = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 200; i++) { try { await (await fetch(base + '/')).text(); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  return { base, stop: () => { try { process.kill(-server.pid); } catch {} } };
}

async function launch() {
  if (browserName === 'firefox') return playwright.firefox.launch({ headless: true });
  if (browserName === 'webkit') return playwright.webkit.launch({ headless: true });
  return playwright.chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
}

// The stored tree, as [address, parent's address] pairs, oldest first.
const tree = page => page.evaluate(() => {
  const visits = JSON.parse(localStorage.getItem('forum_visit_history') || '{"visits":[]}').visits;
  const byId = Object.fromEntries(visits.map(visit => [visit.id, visit]));
  return visits.map(visit => [visit.url, visit.parent && byId[visit.parent] ? byId[visit.parent].url : null]);
});
const cursorUrl = page => page.evaluate(() => {
  const current = window.forumVisitHistory && window.forumVisitHistory.current();
  return current ? current.url : null;
});
async function settle(page) {
  await page.waitForLoadState('load');
  await page.waitForFunction(() => window.forumVisitHistory !== undefined);
}

let browser;
const servers = [];
try {
  browser = await launch();
  const on = await startServer(true); servers.push(on);
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));

  // --- The reported flow ---
  await page.goto(on.base + '/threads/');
  await settle(page);
  await page.click('a[href="/threads/root-001"]');
  await settle(page);
  await page.goBack();
  await settle(page);
  assert.equal(await cursorUrl(page), '/threads/', 'back moves to the Board visit');
  await page.click('a[href="/threads/thread-zenmemes-rules"]');
  await settle(page);
  assert.deepEqual(await tree(page), [
    ['/threads/', null],
    ['/threads/root-001', '/threads/'],
    ['/threads/thread-zenmemes-rules', '/threads/'],
  ], 'Board with two child threads; back added nothing');
  const titles = await page.evaluate(() => window.forumVisitHistory.visits().map(visit => visit.title));
  assert.ok(titles.every(name => typeof name === 'string' && name.length > 0 && name.length <= 120), 'every visit has a title');

  // Back then forward: the cursor follows; nothing is added.
  await page.goBack(); await settle(page);
  await page.goForward(); await settle(page);
  assert.equal(await cursorUrl(page), '/threads/thread-zenmemes-rules', 'forward moves to the child');
  assert.equal((await tree(page)).length, 3, 'back and forward add nothing');

  // A reload adds nothing.
  await page.reload(); await settle(page);
  assert.equal((await tree(page)).length, 3, 'a reload adds nothing');

  // --- A second tab ---
  // Opened from a link: empty session storage, so the referrer is the parent.
  await page.goto(on.base + '/threads/root-001'); await settle(page);
  const before = (await tree(page)).length;
  await page.evaluate(() => {
    const link = document.createElement('a');
    link.href = '/threads/'; link.target = '_blank'; link.id = 'visit-history-new-tab'; link.textContent = 'new tab';
    document.body.appendChild(link);
  });
  const [second] = await Promise.all([context.waitForEvent('page'), page.click('#visit-history-new-tab')]);
  await settle(second);
  let all = await tree(second);
  assert.equal(all.length, before + 1, 'the new tab adds one visit');
  assert.deepEqual(all[all.length - 1], ['/threads/', '/threads/root-001'], 'a new tab from a link hangs under the page it came from');
  // Typed into a fresh tab: no referrer, so a new top.
  const third = await context.newPage();
  await third.goto(on.base + '/threads/thread-zenmemes-rules'); await settle(third);
  all = await tree(third);
  assert.deepEqual(all[all.length - 1], ['/threads/thread-zenmemes-rules', null], 'a fresh tab starts its own branch');
  // Each tab keeps its own cursor.
  await second.click('a[href="/threads/root-001"]'); await settle(second);
  all = await tree(second);
  assert.deepEqual(all[all.length - 1], ['/threads/root-001', '/threads/'], 'the second tab grows its own branch');
  await second.close(); await third.close();

  // --- The cap ---
  await page.evaluate(() => {
    const visits = [];
    for (let i = 0; i < 2000; i++) visits.push({ id: 'old-' + i, t: 1, url: '/old/' + i, title: 'Old ' + i, parent: i ? 'old-' + (i - 1) : null });
    localStorage.setItem('forum_visit_history', JSON.stringify({ v: 1, visits }));
  });
  await page.goto(on.base + '/threads/'); await settle(page);
  all = await page.evaluate(() => window.forumVisitHistory.visits());
  assert.equal(all.length, 2000, 'history keeps 2,000 visits');
  assert.equal(all[0].id, 'old-1', 'the oldest visit is dropped first');
  assert.equal(all[all.length - 1].url, '/threads/', 'the new visit is kept');

  // --- A damaged key and storage that throws ---
  await page.evaluate(() => localStorage.setItem('forum_visit_history', '{not json'));
  await page.goto(on.base + '/threads/root-001'); await settle(page);
  assert.deepEqual(await tree(page), [['/threads/root-001', null]], 'a damaged key is replaced');
  const broken = await browser.newContext({ serviceWorkers: 'block' });
  await broken.addInitScript(() => {
    const fail = () => { throw new DOMException('full', 'QuotaExceededError'); };
    Storage.prototype.setItem = fail;
  });
  const brokenPage = await broken.newPage();
  const brokenErrors = []; brokenPage.on('pageerror', e => brokenErrors.push(e.message));
  await brokenPage.goto(on.base + '/threads/'); await settle(brokenPage);
  await brokenPage.click('a[href="/threads/root-001"]'); await settle(brokenPage);
  assert.ok(await brokenPage.locator('.main').count() > 0, 'pages still work when storage throws');
  assert.deepEqual(brokenErrors, [], 'no page errors when storage throws');
  await broken.close();

  // --- Flag off ---
  const off = await startServer(false); servers.push(off);
  const offContext = await browser.newContext({ serviceWorkers: 'block' });
  const offPage = await offContext.newPage();
  await offPage.goto(off.base + '/threads/'); await offPage.waitForLoadState('load');
  await offPage.click('a[href="/threads/root-001"]'); await offPage.waitForLoadState('load');
  assert.equal(await offPage.evaluate(() => localStorage.getItem('forum_visit_history')), null, 'flag off: nothing is written');
  assert.equal(await offPage.evaluate(() => window.forumVisitHistory), undefined, 'flag off: no recorder');
  await offContext.close();

  assert.deepEqual(errors, [], 'no page errors');
  console.log(`visit history browser test passed (${browserName})`);
} finally {
  if (browser) await browser.close();
  servers.forEach(server => server.stop());
}
