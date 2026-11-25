// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/owa_resume.mjs
// Reopen where you left off (FORUM_OWA_RESUME_ENABLED, owa_resume): in the installed app, a fresh
// launch at / returns to the page and scroll position left in the last 6 hours. A launch at
// another page, a later visit to / in the same launch, an old page, a browser tab and the flag
// off all stay where they are. A new Playwright page stands in for a fresh app launch, since it
// starts with an empty sessionStorage and shares localStorage.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createConnection, createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const THREAD = '/threads/root-001';

async function freePort() {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise((done) => socket.close(done));
  return port;
}

async function site(enabled) {
  const root = mkdtempSync(join(tmpdir(), 'owa-resume-'));
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
      FORUM_OWA_RESUME_ENABLED: enabled ? 'true' : 'false',
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

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
const standalone = () => Object.defineProperty(window.navigator, 'standalone', { value: true, configurable: true });
// A short window so the thread can scroll; workers off so only this script decides the page.
const contextOptions = { viewport: { width: 375, height: 240 }, serviceWorkers: 'block' };
const path = (page) => new URL(page.url()).pathname;

const on = await site(true);
const off = await site(false);
try {
  const app = await browser.newContext(contextOptions);
  await app.addInitScript(standalone);

  // Open the thread, scroll, leave (pagehide), then launch fresh at /.
  const first = await app.newPage();
  await first.goto(on.base + THREAD);
  await first.evaluate(() => { document.body.style.minHeight = '3000px'; window.scrollTo(0, 400); });
  await first.goto('about:blank');
  const launch = await app.newPage();
  await launch.goto(on.base + '/');
  await launch.waitForURL(on.base + THREAD);
  await launch.waitForLoadState('load');
  await launch.waitForFunction(() => window.scrollY > 0, null, { timeout: 5000 });
  assert.equal(path(launch), THREAD, 'a fresh launch at / returns to the page left');

  // Within the same launch, going home stays home.
  await launch.goto(on.base + '/');
  assert.equal(path(launch), '/', 'the home page within a launch is not redirected');

  // A launch at another page (a notification tap) stays there.
  const tapped = await app.newPage();
  await tapped.goto(on.base + '/threads/');
  assert.equal(path(tapped), '/threads/', 'a launch at another page stays there');
  const key = await tapped.evaluate(() => Object.keys(localStorage).find((name) => name.endsWith('-resume')));
  assert.ok(key, 'the page left is remembered under the site namespace');

  // A page left more than 6 hours ago is forgotten.
  await tapped.close();
  const late = await app.newPage();
  // Written before the page's own scripts, after the previous page's pagehide.
  await late.addInitScript(([name, address]) => localStorage.setItem(name, JSON.stringify({ address, y: 0, at: Date.now() - 7 * 3600 * 1000 })), [key, THREAD]);
  await late.goto(on.base + '/');
  await late.waitForLoadState('load');
  assert.equal(path(late), '/', 'an old page is not reopened');
  await late.evaluate(([name, address]) => localStorage.setItem(name, JSON.stringify({ address, y: 0, at: Date.now() })), [key, THREAD]);
  await late.close();

  // A browser tab is not the installed app.
  const tabContext = await browser.newContext(contextOptions);
  const tab = await tabContext.newPage();
  await tab.goto(on.base + '/');
  await tab.evaluate(([name, address]) => localStorage.setItem(name, JSON.stringify({ address, y: 0, at: Date.now() })), [key, THREAD]);
  const tabLaunch = await tabContext.newPage();
  await tabLaunch.goto(on.base + '/');
  await tabLaunch.waitForLoadState('load');
  assert.equal(path(tabLaunch), '/', 'a browser tab is never redirected');
  await tabContext.close();

  // Flag off: no script, no redirect.
  const offContext = await browser.newContext(contextOptions);
  await offContext.addInitScript(standalone);
  const offPage = await offContext.newPage();
  await offPage.goto(off.base + '/');
  assert.equal(await offPage.evaluate(() => document.documentElement.outerHTML.includes('owa_resume')), false, 'flag off renders no resume script');
  await offContext.close();

  await app.close();
  console.log('owa_resume: all checks passed');
} finally {
  on.server.kill();
  off.server.kill();
  await browser.close();
}
