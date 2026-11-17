// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/fast_navigation.mjs
// Fast navigation (FORUM_FAST_NAVIGATION_ENABLED): with the worker in control, navigation
// preload is on, a navbar tap is answered from the fetch started on pointerdown, and the
// tapped item is marked active on click. Off: preload off, no early fetch, no early mark.
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

async function site(fastNavigation) {
  const root = mkdtempSync(join(tmpdir(), 'fast-navigation-'));
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
      FORUM_FAST_NAVIGATION_ENABLED: fastNavigation ? 'true' : 'false',
      FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
    stdio: 'ignore',
  });
  const base = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 100; i++) {
    const ready = await new Promise((done) => { const probe = createConnection(port, '127.0.0.1'); probe.once('connect', () => { probe.destroy(); done(true); }); probe.once('error', () => done(false)); });
    if (ready) break;
    await new Promise((done) => setTimeout(done, 100));
  }
  return { base, server };
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

async function controlledPage(base, width = 1280) {
  const context = await browser.newContext({ viewport: { width, height: 800 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(base + '/');
  await until(() => page.evaluate(() => !!(navigator.serviceWorker && navigator.serviceWorker.controller)).catch(() => false), 'worker in control', 45000);
  await page.reload();
  await page.waitForLoadState('load');
  return { context, page, errors };
}

const preloadState = (page) => page.evaluate(async () => {
  const registration = await navigator.serviceWorker.ready;
  return (await registration.navigationPreload.getState()).enabled;
});
// The worker's requests for a page address, as Playwright reports them (preloads are; early fetches are not reliably).
const workerRequests = (context, path) => {
  const seen = [];
  context.on('request', (request) => {
    if (request.serviceWorker() && new URL(request.url()).pathname === path) seen.push(request.headers()['service-worker-navigation-preload'] ? 'preload' : 'fetch');
  });
  return seen;
};
// Addresses the worker is holding an early fetch for.
const earlyFetches = (context) => context.serviceWorkers()[0].evaluate(() => Array.from(earlyFetches.keys()).map((url) => new URL(url).pathname));
const navLink = (page, href) => page.locator(`.site-header .nav > a.nav-link[href="${href}"]`).first();

try {
  // Flag off: attribute 0, no script, preload stays off, a tap sends nothing early.
  {
    const off = await site(false); servers.push(off.server);
    const { context, page, errors } = await controlledPage(off.base);
    assert.equal(await page.getAttribute('html', 'data-fast-navigation'), '0');
    assert.equal(await page.locator('script[src*="fast_navigation"]').count(), 0, 'flag off loads no script');
    await until(async () => (await preloadState(page)) === false, 'preload off');
    await navLink(page, '/users/').dispatchEvent('pointerdown', { button: 0, isPrimary: true });
    await page.waitForTimeout(1000);
    assert.deepEqual(await earlyFetches(context), [], 'flag off: no early fetch');
    assert.deepEqual(errors, []);
    await context.close();
  }

  const on = await site(true); servers.push(on.server);
  const { context, page, errors } = await controlledPage(on.base);
  assert.equal(await page.getAttribute('html', 'data-fast-navigation'), '1');
  await until(async () => (await preloadState(page)) === true, 'preload on');

  // A navigation without an early fetch is answered from the preload.
  {
    const seen = workerRequests(context, '/about/');
    await page.goto(on.base + '/about/');
    assert.ok(seen.includes('preload'), `preload used: ${seen}`);
    assert.ok(!seen.includes('fetch'), `no second fetch: ${seen}`);
    await page.goto(on.base + '/');
  }

  // The click marks the tapped item active before the next page arrives.
  {
    const before = await page.locator('.site-header .nav-link.is-active').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    await page.evaluate(() => window.addEventListener('click', (event) => event.preventDefault(), { once: true }));
    await navLink(page, '/users/').click();
    const after = await page.locator('.site-header .nav-link.is-active').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    assert.ok(after.length > 0 && after.every((href) => href === '/users/'), `marked on click: ${before} -> ${after}`);
    await page.reload();
  }

  // A tap is answered from the early fetch: it keeps working with the server gone.
  {
    await navLink(page, '/users/').dispatchEvent('pointerdown', { button: 0, isPrimary: true });
    await until(async () => (await earlyFetches(context)).includes('/users/'), 'early fetch started');
    await page.waitForTimeout(1500);
    on.server.kill('SIGKILL');
    await once(on.server, 'exit');
    await navLink(page, '/users/').click();
    await page.waitForURL(on.base + '/users/');
    assert.ok(await page.locator('.site-header .nav-link.is-active[href="/users/"]').count() > 0, 'the Users page itself was served');
    assert.equal(await page.locator('[data-offline-reader]').count(), 0, 'not the offline reader');

    // Without an early fetch and with the server down, the offline fallback still answers.
    const response = await page.goto(on.base + '/threads/').catch((error) => error);
    assert.ok(!(response instanceof Error), `offline fallback answered: ${response}`);
    assert.ok(await page.locator('[data-offline-reader]').count() > 0, 'offline reader shown');
  }
  assert.deepEqual(errors, []);
  await context.close();
  console.log('fast_navigation: ok');
} finally {
  await browser.close();
  for (const server of servers) server.kill('SIGKILL');
}
