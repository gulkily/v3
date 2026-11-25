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

  // --- Address changes from page scripts ---
  await page.goto(on.base + '/threads/root-001'); await settle(page);
  let start = (await tree(page)).length;
  await page.evaluate(() => history.pushState(null, '', '/threads/root-001?view=a'));
  await page.evaluate(() => history.pushState(null, '', '/threads/root-001?view=a#spot'));
  let all = await tree(page);
  assert.equal(all.length, start + 1, 'pushState to a new address adds one visit; a #spot adds none');
  assert.deepEqual(all[all.length - 1], ['/threads/root-001?view=a', '/threads/root-001'], 'a pushed address hangs under the page');
  await page.goBack();
  await page.waitForFunction(() => location.hash === '');
  await page.goBack();
  await page.waitForFunction(() => location.search === '');
  assert.equal(await cursorUrl(page), '/threads/root-001', 'popstate back moves to the page');
  await page.goForward();
  await page.waitForFunction(() => location.search === '?view=a');
  assert.equal(await cursorUrl(page), '/threads/root-001?view=a', 'popstate forward moves to the pushed visit');
  assert.equal((await tree(page)).length, start + 1, 'popstate adds nothing');
  await page.evaluate(() => history.replaceState(null, '', '/threads/root-001?view=b'));
  all = await tree(page);
  assert.equal(all.length, start + 1, 'replaceState adds nothing');
  assert.equal(all[all.length - 1][0], '/threads/root-001?view=b', 'replaceState moves the current visit');
  await page.evaluate(() => history.replaceState(null, '', '/threads/root-001?view=b&__v=123'));
  assert.equal((await tree(page)).at(-1)[0], '/threads/root-001?view=b', 'one-time hints are never kept');
  // The paned board reader (/forte) changes the address when a row is chosen.
  const forte = await page.goto(on.base + '/forte');
  if (forte && forte.ok() && await page.locator('.paned-list-row').count() > 0) {
    await settle(page);
    start = (await tree(page)).length;
    await page.locator('.paned-list-row').first().click();
    await page.waitForFunction(() => location.search.includes('selected='));
    all = await tree(page);
    assert.equal(all.length, start + 1, 'choosing a row on /forte adds a visit');
    assert.ok(all.at(-1)[0].startsWith('/forte?') && all.at(-1)[1] === '/forte', 'the row visit hangs under /forte');
    // Let the reader's detail request finish; WebKit reports a cancelled one as a page error.
    await page.waitForLoadState('networkidle');
  } else {
    console.log('note: /forte not available with this fixture; paned reader check skipped');
  }

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
  all = await tree(second);
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

  // --- Tools → History ---
  // Fresh history: Board, two child threads, the second opened from the Board after back.
  await page.evaluate(() => window.forumVisitHistory.clear());
  await page.goto(on.base + '/threads/'); await settle(page);
  await page.click('a[href="/threads/root-001"]'); await settle(page);
  await page.goBack(); await settle(page);
  await page.click('a[href="/threads/thread-zenmemes-rules"]'); await settle(page);
  const recorded = (await tree(page)).length;
  // The tree is drawn while the page is read, before anything after it: watch for
  // the Tools card that follows it and note whether the tree already had rows.
  await context.addInitScript(() => {
    new MutationObserver((records, observer) => {
      const card = document.querySelector('.tools-nav-card');
      if (!card) return;
      const list = document.querySelector('[data-role="visit-history-tree"]');
      window.__treeRowsBeforeCard = list ? list.querySelectorAll('a').length : -1;
      observer.disconnect();
    }).observe(document, { childList: true, subtree: true });
  });
  await page.goto(on.base + '/tools/history/'); await settle(page);
  assert.equal(await page.evaluate(() => window.__treeRowsBeforeCard), 3, 'the tree is drawn before the rest of the page');
  assert.equal((await tree(page)).length, recorded, 'the History page is never recorded');
  const shape = await page.evaluate(() => {
    const top = document.querySelector('[data-role="visit-history-tree"] > .visit-tree__branch > ul > li');
    return {
      top: top.querySelector(':scope > a').getAttribute('href'),
      branches: Array.from(top.querySelectorAll(':scope > ul > .visit-tree__branch > ul > li > a')).map(link => link.getAttribute('href')),
      when: top.querySelector('.visit-tree__when').textContent,
      empty: document.querySelector('[data-role="visit-history-empty"]').hidden,
      clear: document.querySelector('[data-action="visit-history-clear"]').hidden,
    };
  });
  assert.equal(shape.top, '/threads/', 'the Board heads the branch');
  assert.deepEqual(shape.branches, ['/threads/thread-zenmemes-rules', '/threads/root-001'], 'two branches under the Board, newest first');
  assert.ok(shape.when.length > 0, 'each visit shows its time');
  assert.equal(shape.empty, true, 'no empty note while there are visits');
  assert.equal(shape.clear, false, 'Clear is offered');
  // A visit in the tree opens its page.
  await page.click('[data-role="visit-history-tree"] a[href="/threads/root-001"]');
  await page.waitForURL('**/threads/root-001'); await settle(page);
  await page.goto(on.base + '/tools/history/'); await settle(page);
  await page.click('[data-action="visit-history-clear"]');
  assert.equal(await page.locator('[data-role="visit-history-tree"] a').count(), 0, 'Clear empties the tree');
  assert.equal(await page.locator('[data-role="visit-history-empty"]').isVisible(), true, 'the empty note shows');
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_visit_history')), null, 'Clear removes the stored visits');
  await page.goto(on.base + '/threads/'); await settle(page);
  assert.deepEqual(await tree(page), [['/threads/', null]], 'the next page starts a fresh tree');

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

  // --- The Beside pane ---
  const beside = await startServer(true, { FORUM_OPEN_BESIDE_ENABLED: 'true', FORUM_READING_MARGINS_ENABLED: 'true' }); servers.push(beside);
  const wide = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 2048, height: 1280 } });
  const desk = await wide.newPage();
  desk.on('pageerror', e => errors.push(e.message));
  const paneFrame = () => desk.frames().find(frame => frame.name() === 'forum-open-beside' && frame.parentFrame() === desk.mainFrame());
  const paneShows = expected => desk.waitForFunction(path => {
    const frame = document.querySelector('.open-beside__frame');
    return frame && frame.contentDocument && frame.contentDocument.readyState === 'complete'
      && frame.contentWindow.location.pathname === path && frame.contentWindow.forumVisitHistory !== undefined;
  }, expected, { timeout: 15000 });
  const visitsOn = p => p.evaluate(() => {
    const visits = JSON.parse(localStorage.getItem('forum_visit_history') || '{"visits":[]}').visits;
    const byId = Object.fromEntries(visits.map(visit => [visit.id, visit]));
    return visits.map(visit => [visit.url, visit.parent && byId[visit.parent] ? byId[visit.parent].url : null, visit.beside === true]);
  });
  await desk.goto(beside.base + '/threads/'); await settle(desk);
  // Room beside the column: narrow it to the left, then turn the mode on.
  const stops = await desk.locator('[data-reading-margin-stop]:not([hidden])').evaluateAll(all => all.map(stop => Number(stop.value)));
  const left = Math.min(...stops);
  await desk.locator(`[data-reading-margin-stop][value="${left}"]`).click();
  await desk.locator(`[data-reading-margin-stop][value="${left + 5}"]`).click();
  await desk.locator('[data-open-beside-toggle]').click();
  await desk.locator('.main a[href="/threads/root-001"]').first().click();
  await paneShows('/threads/root-001');
  let pane = await visitsOn(desk);
  assert.deepEqual(pane.at(-1), ['/threads/root-001', '/threads/', true], 'a page opened beside hangs under the main page, marked beside');
  assert.equal(new URL(desk.url()).pathname, '/threads/', 'the main page stayed');
  // A link followed in the pane hangs under the pane's last visit.
  await paneFrame().evaluate(() => {
    const link = document.createElement('a');
    link.href = '/threads/thread-zenmemes-rules'; link.id = 'visit-history-pane-link'; link.textContent = 'next';
    document.querySelector('.main').appendChild(link);
  });
  await paneFrame().click('#visit-history-pane-link');
  await paneShows('/threads/thread-zenmemes-rules');
  pane = await visitsOn(desk);
  assert.deepEqual(pane.at(-1), ['/threads/thread-zenmemes-rules', '/threads/root-001', true], 'a link in the pane follows the pane');
  // The main page still grows its own branch: the main cursor was not moved by the pane.
  await desk.goto(beside.base + '/threads/root-001');
  await settle(desk);
  await paneShows('/threads/thread-zenmemes-rules');
  pane = await visitsOn(desk);
  assert.deepEqual(pane.filter(visit => !visit[2]).at(-1), ['/threads/root-001', '/threads/', false], 'the main page hangs under the main branch, not the pane');
  assert.equal(pane.filter(visit => visit[2]).length, 2, 'the restored pane adds no visit');
  // A theme change reloads the pane: still no visit.
  const beforeTheme = (await visitsOn(desk)).length;
  await desk.evaluate(() => {
    const now = document.documentElement.getAttribute('data-theme');
    document.documentElement.setAttribute('data-theme', now === 'word97' ? 'gloss' : 'word97');
  });
  await desk.waitForTimeout(500);
  await paneShows('/threads/thread-zenmemes-rules');
  assert.equal((await visitsOn(desk)).length, beforeTheme, 'the theme reload adds no visit');
  // Open alone: the main page becomes the pane's page, already a visit.
  const paneVisit = await paneFrame().evaluate(() => window.forumVisitHistory.current().id);
  await desk.locator('[data-open-beside-alone]').click();
  await desk.waitForURL('**/threads/thread-zenmemes-rules'); await settle(desk);
  assert.equal((await visitsOn(desk)).length, beforeTheme, 'Open alone adds no visit');
  assert.equal(await desk.evaluate(() => window.forumVisitHistory.current().id), paneVisit, 'Open alone moves the tab to the pane\'s visit');
  // Another frame (the tour) records nothing.
  const beforeFrame = (await visitsOn(desk)).length;
  await desk.evaluate(() => {
    const frame = document.createElement('iframe');
    frame.name = 'tour-snapshot'; frame.src = '/threads/root-001'; frame.id = 'visit-history-other-frame';
    document.body.appendChild(frame);
  });
  await desk.waitForFunction(() => {
    const frame = document.getElementById('visit-history-other-frame');
    return frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentWindow.location.pathname === '/threads/root-001';
  });
  await desk.waitForTimeout(300);
  assert.equal((await visitsOn(desk)).length, beforeFrame, 'another frame records nothing');
  await wide.close();

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
