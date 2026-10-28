// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tab_bar_scroll.mjs
// The ZenMemes phone tab bar hides while scrolling down and returns on scroll up,
// near either page end, on focus and while the account menu is open.
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'tab-bar-scroll-'));
const env = { ...process.env, BOOKMARKLET_TEST_ROOT: root, FORUM_SITE_ID: 'zenmemes',
  FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_STATIC_HTML_ROOT: join(root, 'static'),
  FORUM_APP_VERSION_NOTIFICATION: 'false', FAST_SCORING_ENABLED: 'false' };
const fixture = join(project, 'tests/Support/bookmarklet_browser.php');
const seed = spawnSync('php', [fixture], { cwd: project, env, encoding: 'utf8' });
assert.equal(seed.status, 0, seed.stderr);
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, fixture], { cwd: project, env });
let log = '';
server.stderr.on('data', data => { log += data; });
const bar = '.site-header--app .site-header-actions';
let browser;

async function open(options = {}) {
  const context = await browser.newContext({ serviceWorkers: 'block', hasTouch: true, viewport: { width: 390, height: 844 }, ...options });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(base + '/', { waitUntil: 'load' });
  // A long page, so there is room to scroll between the two ends.
  await page.evaluate(() => {
    const spacer = document.createElement('div');
    spacer.style.height = '4000px';
    document.querySelector('.main').append(spacer);
  });
  return { context, page, errors };
}
const frames = page => page.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
async function scrollTo(page, y) {
  await page.evaluate(y => window.scrollTo({ top: y, behavior: 'instant' }), y);
  await frames(page);
}
// Pretend the browser reports a position past an end, as iOS bounce does.
async function overscroll(page, beyond) {
  await page.evaluate(beyond => {
    const max = document.documentElement.scrollHeight - innerHeight;
    Object.defineProperty(window, 'scrollY', { configurable: true, get: () => beyond < 0 ? beyond : max + beyond });
    dispatchEvent(new Event('scroll'));
  }, beyond);
  await frames(page);
  await page.evaluate(() => {
    Object.defineProperty(window, 'scrollY', { configurable: true, get: () => document.scrollingElement.scrollTop });
  });
}
const hidden = page => page.evaluate(() => document.querySelector('[data-shell="app"]').dataset.tabbar === 'hidden');
const onScreen = page => page.evaluate(bar => {
  const rect = document.querySelector(bar).getBoundingClientRect();
  return rect.top < innerHeight - 1;
}, bar);

try {
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/'); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

  const phone = await open({ reducedMotion: 'reduce' });
  const { page } = phone;
  assert.equal(await page.evaluate(bar => getComputedStyle(document.querySelector(bar)).transitionDuration, bar), '0s', 'reduced motion: no slide');
  assert.equal(await hidden(page), false, 'shown on load');
  await scrollTo(page, 1000);
  assert.equal(await hidden(page), true, 'hidden after scrolling down');
  assert.equal(await onScreen(page), false, 'off screen while hidden');
  await scrollTo(page, 1004);
  await scrollTo(page, 998);
  assert.equal(await hidden(page), true, 'small jitter keeps it hidden');
  await scrollTo(page, 980);
  assert.equal(await hidden(page), false, 'shown after scrolling up');
  assert.equal(await page.evaluate(bar => getComputedStyle(document.querySelector(bar)).transform, bar), 'none', 'shown bar has no transform');
  assert.equal(await onScreen(page), true, 'on screen when shown');
  await scrollTo(page, 1500);
  await overscroll(page, 60);
  assert.equal(await hidden(page), false, 'bounce past the bottom shows it, never toggles it');
  await scrollTo(page, 2000);
  await scrollTo(page, 2500);
  await overscroll(page, -60);
  assert.equal(await hidden(page), false, 'bounce past the top shows it');
  await scrollTo(page, 1500);
  await scrollTo(page, 2000);
  await scrollTo(page, 999999);
  assert.equal(await hidden(page), false, 'shown at the end of the page');
  await scrollTo(page, 1000);
  await scrollTo(page, 1500);
  assert.equal(await hidden(page), true);
  await page.locator(`${bar} a`).first().focus();
  assert.equal(await hidden(page), false, 'focus brings it back');
  await page.evaluate(() => document.activeElement.blur());
  await page.locator('details[data-role="account-menu"] summary').click();
  await scrollTo(page, 2000);
  assert.equal(await hidden(page), false, 'stays while the account menu is open');
  // Bottom-pinned parts follow the visible bar: the post-send reply box sits on
  // the bar while shown and at the screen bottom while hidden.
  await page.evaluate(() => { document.activeElement.blur(); document.querySelector('details[data-role="account-menu"]').open = false; });
  await page.evaluate(() => {
    const panel = document.createElement('div');
    panel.setAttribute('data-reply-panel', '');
    panel.className = 'reply-panel-reading';
    panel.style.position = 'fixed';
    panel.textContent = 'Reply sent';
    document.querySelector('.main').append(panel);
  });
  const dependents = () => page.evaluate(() => ({
    offset: getComputedStyle(document.querySelector('[data-shell="app"]')).getPropertyValue('--app-tabbar-offset').trim(),
    panel: getComputedStyle(document.querySelector('.reply-panel-reading')).bottom,
  }));
  await scrollTo(page, 1000);
  await scrollTo(page, 1500);
  assert.equal(await hidden(page), true);
  assert.deepEqual(await dependents(), { offset: '0px', panel: '8px' }, 'hidden: reply box at the screen bottom');
  await scrollTo(page, 1400);
  assert.equal(await hidden(page), false);
  assert.equal((await dependents()).panel, '64px', 'shown: reply box above the bar');
  assert.deepEqual(phone.errors, []);
  await phone.context.close();
  console.log('PASS phone: hide, show, jitter, bounce, ends, focus, menu, reduced motion, dependents');

  const sliding = await open();
  assert.notEqual(await sliding.page.evaluate(bar => getComputedStyle(document.querySelector(bar)).transitionDuration, bar), '0s', 'slides by default');
  await sliding.context.close();

  const desktop = await open({ hasTouch: false, viewport: { width: 1280, height: 800 } });
  await scrollTo(desktop.page, 500);
  await scrollTo(desktop.page, 1500);
  assert.equal(await hidden(desktop.page), false, 'desktop never hides');
  await desktop.context.close();

  const noScript = await open({ javaScriptEnabled: false });
  await noScript.page.mouse.move(195, 400);
  await noScript.page.mouse.wheel(0, 1500);
  await noScript.page.waitForTimeout(200);
  assert.ok(await noScript.page.evaluate(() => document.scrollingElement.scrollTop) > 500, 'no-JavaScript page scrolled');
  assert.equal(await onScreen(noScript.page), true, 'without JavaScript the bar stays');
  await noScript.context.close();
  console.log('PASS desktop unchanged, sliding by default, no-JavaScript fallback');
} catch (error) { console.error(log.slice(-3000)); throw error; }
finally { if (browser) await browser.close(); server.kill(); }
