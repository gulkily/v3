// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/nav_cached_copy.mjs
// Navbar pages from last copy (FORUM_NAV_CACHED_COPY_ENABLED): a navbar page opened before is
// answered from its copy, refreshed in the background for the next visit; a newer version
// raises the notice and never replaces the page. Off: no copies are kept.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createConnection, createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);

async function freePort() {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise((done) => socket.close(done));
  return port;
}

async function site(copies) {
  const root = mkdtempSync(join(tmpdir(), 'nav-cached-copy-'));
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
      FORUM_FAST_NAVIGATION_ENABLED: 'true', FORUM_NAV_CACHED_COPY_ENABLED: copies ? 'true' : 'false', FORUM_APP_VERSION_NOTIFICATION: 'true',
      FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
    stdio: 'ignore',
  });
  const base = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 100; i++) {
    const ready = await new Promise((done) => { const probe = createConnection(port, '127.0.0.1'); probe.once('connect', () => { probe.destroy(); done(true); }); probe.once('error', () => done(false)); });
    if (ready) break;
    await new Promise((done) => setTimeout(done, 100));
  }
  const commit = (name) => {
    for (const args of [['commit', '--allow-empty', '-m', name]]) {
      const result = spawnSync('git', ['-C', repository, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', ...args], { encoding: 'utf8' });
      assert.equal(result.status, 0, result.stderr);
    }
    return spawnSync('git', ['-C', repository, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout.trim();
  };
  return { base, server, commit };
}

const until = async (check, label, ms = 30000) => {
  for (const started = Date.now(); Date.now() - started < ms;) {
    if (await check()) return;
    await new Promise((done) => setTimeout(done, 200));
  }
  assert.fail(`timed out: ${label}`);
};

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const servers = [];

async function controlledPage(base) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(base + '/');
  await until(() => page.evaluate(() => !!(navigator.serviceWorker && navigator.serviceWorker.controller)).catch(() => false), 'worker in control', 45000);
  await page.reload();
  await page.waitForLoadState('load');
  return { context, page, errors };
}
// Paths with a kept copy, and whether the copy cache exists at all.
const kept = (page) => page.evaluate(async () => {
  const name = window.forumBrowserRuntime.offlineCachePrefix + 'nav-copy';
  if (!(await caches.has(name))) return null;
  const cache = await caches.open(name);
  return (await cache.keys()).map((request) => new URL(request.url).pathname).filter((path) => path !== '/__nav_copy_paths__').sort();
});
const version = (page) => page.getAttribute('meta[name="app-version"]', 'content');
const bannerShown = (page) => page.evaluate(() => { const banner = document.querySelector('[data-role="app-version-banner"]'); return !!banner && !banner.hidden; });
const navLink = (page, href) => page.locator(`.site-header .nav > a.nav-link[href="${href}"]`).first();
const go = async (page, href) => { await navLink(page, href).click(); await page.waitForURL((url) => url.pathname === href); await page.waitForLoadState('load'); };

try {
  // Flag off: no copies, even after visiting navbar pages.
  {
    const off = await site(false); servers.push(off.server);
    const { context, page, errors } = await controlledPage(off.base);
    assert.equal(await page.getAttribute('html', 'data-nav-cached-copy'), '0');
    await go(page, '/users/'); await go(page, '/');
    await page.waitForTimeout(500);
    assert.equal(await kept(page), null, 'flag off: no copy cache');
    assert.deepEqual(errors, []);
    await context.close();
  }

  const on = await site(true); servers.push(on.server);
  const { context, page, errors } = await controlledPage(on.base);
  assert.equal(await page.getAttribute('html', 'data-nav-cached-copy'), '1');
  await go(page, '/users/');
  await go(page, '/');
  await until(async () => JSON.stringify(await kept(page)) === JSON.stringify(['/', '/users/']), 'copies of / and /users/');
  const before = await version(page);

  // New content: the next visit shows the older copy, offers the notice, and keeps the page.
  const after = on.commit('New post');
  await go(page, '/users/');
  assert.equal(await version(page), before, 'served from the older copy');
  await until(() => bannerShown(page), 'notice raised by the background copy');
  await page.waitForTimeout(1500);
  assert.equal(new URL(page.url()).search, '', 'the page was not replaced');

  // A remembered newer version does not redirect an older copy either.
  await go(page, '/');
  assert.equal(await version(page), before, 'board from its older copy');
  await page.waitForTimeout(2000);
  assert.equal(new URL(page.url()).search, '', 'no ?__v redirect');
  assert.ok(await bannerShown(page), 'notice shown instead of a redirect');

  // The refreshed copy is what the next visit gets.
  await go(page, '/users/');
  assert.equal(await version(page), after, 'next visit gets the refreshed copy');

  // Reload in the notice opens the current page from the network.
  const latest = on.commit('Another post');
  await go(page, '/');
  assert.equal(await version(page), after, 'board copy refreshed on the previous visit');
  await until(() => bannerShown(page), 'notice on the board');
  await page.click('[data-action="reload-for-new-version"]');
  await page.waitForURL((url) => url.searchParams.has('__v'));
  await page.waitForLoadState('load');
  assert.equal(await version(page), latest, 'Reload shows the current version');

  // A key change drops every copy; the next visits make new ones.
  await page.evaluate(() => window.dispatchEvent(new Event('forum:identity-changed')));
  await until(async () => JSON.stringify(await kept(page)) === '[]', 'copies dropped');
  await go(page, '/users/'); await go(page, '/');
  await until(async () => JSON.stringify(await kept(page)) === JSON.stringify(['/', '/users/']), 'copies made again');

  // Copies answer with the server gone; pages outside the navbar never come from a copy.
  on.server.kill('SIGKILL'); await once(on.server, 'exit');
  await go(page, '/users/');
  assert.ok(await page.locator('.site-header .nav-link.is-active[href="/users/"]').count() > 0, 'Users from its copy');
  assert.equal(await page.locator('[data-offline-reader]').count(), 0, 'not the offline reader');
  const about = await page.goto(on.base + '/about/').catch((error) => error);
  assert.ok(about instanceof Error, 'About is not a navbar page and has no copy');

  assert.deepEqual(errors, []);
  await context.close();
  console.log('nav_cached_copy: ok');
} finally {
  await browser.close();
  for (const server of servers) server.kill('SIGKILL');
}
