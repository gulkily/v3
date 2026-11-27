// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/owa_instant_launch.mjs
// Installed app opens instantly (FORUM_OWA_INSTANT_LAUNCH_ENABLED, owa_instant_launch): in the
// installed app, pages the offline reader serves are kept as copies; when the connection
// stalls (a server that accepts and never answers), a kept page shows at once and a page never
// opened shows the offline reader within a few seconds instead of a blank screen. A browser tab
// writes no marker, and the flag off leaves no copy cache.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createConnection, createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const KEPT_THREAD = '/threads/root-001';
const NEW_THREAD = '/threads/thread-zenmemes-rules';

async function freePort() {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise((done) => socket.close(done));
  return port;
}

async function site(enabled) {
  const root = mkdtempSync(join(tmpdir(), 'owa-instant-'));
  const repository = join(root, 'repository');
  mkdirSync(join(root, 'static'));
  cpSync(join(project, 'public/assets'), join(root, 'artifacts/assets'), { recursive: true });
  cpSync(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
  for (const args of [['init'], ['add', '.'], ['commit', '-m', 'Initialize']]) {
    const result = spawnSync('git', ['-C', repository, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', ...args], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
  }
  const port = await freePort();
  const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), join(project, 'public/router.php')], {
    cwd: project,
    env: { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_REPOSITORY_ROOT: repository, FORUM_DATABASE_PATH: join(root, 'read.sqlite3'),
      FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
      FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'false', FAST_SCORING_ENABLED: 'false',
      FORUM_OWA_RESUME_ENABLED: 'false', FORUM_NAV_CACHED_COPY_ENABLED: 'false',
      FORUM_OWA_INSTANT_LAUNCH_ENABLED: enabled ? 'true' : 'false',
      FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
    stdio: 'ignore',
  });
  for (let i = 0; i < 100; i++) {
    const ready = await new Promise((done) => { const probe = createConnection(port, '127.0.0.1'); probe.once('connect', () => { probe.destroy(); done(true); }); probe.once('error', () => done(false)); });
    if (ready) break;
    await new Promise((done) => setTimeout(done, 100));
  }
  return { base: `http://127.0.0.1:${port}`, port, server };
}

const until = async (check, label, timeout = 30000) => {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    if (await check()) return;
    await new Promise((done) => setTimeout(done, 200));
  }
  assert.fail(`timed out: ${label}`);
};

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
const standalone = () => Object.defineProperty(window.navigator, 'standalone', { value: true, configurable: true });
const servers = [];
const copies = (page) => page.evaluate(async () => {
  const name = window.forumBrowserRuntime.offlineCachePrefix + 'nav-copy';
  if (!(await caches.has(name))) return null;
  return (await (await caches.open(name)).keys()).map((request) => new URL(request.url).pathname).sort();
});

async function controlledPage(base, installed) {
  const context = await browser.newContext({ viewport: { width: 375, height: 667 } });
  if (installed) await context.addInitScript(standalone);
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(base + '/');
  await until(() => page.evaluate(() => !!(navigator.serviceWorker && navigator.serviceWorker.controller)).catch(() => false), 'worker in control', 45000);
  // The full reader refresh (shell, assets, snapshot) runs after the first install.
  await until(() => page.evaluate(async () => !!(await caches.match(location.origin + '/offline/reader/'))), 'reader shell cached', 45000);
  await page.reload();
  await page.waitForLoadState('load');
  return { context, page, errors };
}

try {
  // Flag off: no marker, no copy cache, the page attribute stays off.
  {
    const off = await site(false); servers.push(off.server);
    const { context, page, errors } = await controlledPage(off.base, true);
    assert.equal(await page.getAttribute('html', 'data-owa-instant-launch'), null);
    assert.equal(await page.getAttribute('html', 'data-nav-cached-copy'), '0');
    await page.goto(off.base + KEPT_THREAD); await page.waitForTimeout(500);
    assert.equal(await copies(page), null, 'flag off: no copy cache');
    assert.deepEqual(errors, []);
    await context.close();
  }

  const on = await site(true); servers.push(on.server);

  // A browser tab: the flag is on but nothing is kept.
  {
    const { context, page, errors } = await controlledPage(on.base, false);
    assert.equal(await page.getAttribute('html', 'data-owa-instant-launch'), '1');
    assert.equal(await page.getAttribute('html', 'data-nav-cached-copy'), '0', 'a tab keeps reloading on a new version');
    await page.goto(on.base + KEPT_THREAD); await page.waitForTimeout(500);
    assert.equal(await copies(page), null, 'browser tab: no copy cache');
    assert.deepEqual(errors, []);
    await context.close();
  }

  // The installed app keeps copies of the pages it opens.
  const { context, page, errors } = await controlledPage(on.base, true);
  assert.equal(await page.getAttribute('html', 'data-nav-cached-copy'), '1', 'installed app: copies behave as navbar copies');
  await page.goto(on.base + KEPT_THREAD); await page.waitForLoadState('load');
  await page.goto(on.base + '/'); await page.waitForLoadState('load');
  await until(async () => {
    const list = await copies(page);
    return list && list.includes('/') && list.includes(KEPT_THREAD) && list.includes('/__instant_launch__');
  }, 'copies of / and the thread, and the marker');
  assert.equal((await copies(page)).includes(NEW_THREAD), false);

  // The connection stalls: the port accepts and never answers.
  on.server.kill('SIGKILL'); await once(on.server, 'exit');
  const stall = createServer(() => {}).listen(on.port, '127.0.0.1'); await once(stall, 'listening');
  try {
    let started = Date.now();
    await page.goto(on.base + KEPT_THREAD, { timeout: 2000 });
    assert.ok(Date.now() - started < 2000, 'kept thread shown at once');
    assert.equal(await page.locator('[data-offline-reader]').count(), 0, 'the kept copy, not the reader');
    assert.ok((await page.content()).includes('root-001') || (await page.title()) !== '', 'thread page content');

    started = Date.now();
    await page.goto(on.base + '/', { timeout: 2000 });
    assert.equal(await page.locator('[data-offline-reader]').count(), 0, 'board from its copy');

    started = Date.now();
    await page.goto(on.base + NEW_THREAD, { timeout: 8000 });
    const waited = Date.now() - started;
    assert.ok(waited >= 2500 && waited < 6000, `reader after the 3-second limit, took ${waited} ms`);
    assert.ok(await page.locator('[data-offline-reader]').count() > 0, 'a page never opened gets the reader');
  } finally {
    stall.close();
  }

  assert.deepEqual(errors.filter((message) => !/Failed to fetch|NetworkError|network/i.test(message)), []);
  await context.close();
  console.log('owa_instant_launch: ok');
} finally {
  await browser.close();
  for (const server of servers) server.kill('SIGKILL');
}
