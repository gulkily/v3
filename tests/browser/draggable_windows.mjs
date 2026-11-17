import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Draggable windows (FORUM_DRAGGABLE_WINDOWS_ENABLED) in the Bubblegum theme.
// Stage 1: a post collapsed in this browser is collapsed from the first frame
// (no jump once the page settles), every window shows its title bar, and nothing is collapsed with the flag off,
// in another theme, or with window controls turned off in Preferences.
// Usage: node tests/browser/draggable_windows.mjs [screenshot-dir]
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const root = await mkdtemp(join(tmpdir(), 'draggable-windows-browser-'));
const repo = join(root, 'repo');
const db = join(root, 'cache', 'read-model.sqlite3');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repo, { recursive: true });
await mkdir(join(root, 'cache'));
if (shots) await mkdir(shots, { recursive: true });
const baseEnv = { ...process.env, FORUM_REPOSITORY_ROOT: repo, FORUM_DATABASE_PATH: db,
  FORUM_APP_VERSION_NOTIFICATION: 'false', FORUM_SECRETS_PATH: join(root, 'unused.php') };
const rebuild = spawnSync(join(project, 'v3'), ['rebuild', repo, db], { cwd: project, env: baseEnv, encoding: 'utf8' });
assert.equal(rebuild.status, 0, rebuild.stderr + rebuild.stdout);

async function startServer(flagOn) {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(r => socket.close(r));
  const env = { ...baseEnv, FORUM_DRAGGABLE_WINDOWS_ENABLED: flagOn ? 'true' : 'false' };
  const server = spawn(join(project, 'v3'), ['start', `127.0.0.1:${port}`, '--quiet'], { cwd: project, env, detached: true });
  const base = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 200; i++) { try { await (await fetch(base + '/')).text(); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  return { base, stop: () => { try { process.kill(-server.pid); } catch {} } };
}

let browser;
const servers = [];
try {
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const on = await startServer(true); servers.push(on);
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
  // Record each post card's height the moment it is parsed, before any later script runs.
  await context.addInitScript(() => {
    window.__firstHeights = {};
    new MutationObserver(() => {
      document.querySelectorAll('.card[data-post-id]').forEach(card => {
        const id = card.getAttribute('data-post-id');
        if (!(id in window.__firstHeights) && card.nextElementSibling !== null) window.__firstHeights[id] = card.getBoundingClientRect().height;
      });
    }).observe(document, { childList: true, subtree: true });
  });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  const url = on.base + '/threads/root-001';
  await page.goto(url);
  const runtime = await page.evaluate(() => window.forumBrowserRuntime);
  assert.ok(runtime.windowControlsStorageKey && runtime.collapsedWindowsStorageKey, 'storage keys in the runtime');
  const postIds = await page.locator('.card[data-post-id]').evaluateAll(all => all.map(card => card.getAttribute('data-post-id')));
  assert.ok(postIds.length >= 2, 'thread has a root and a reply');
  const [rootId, replyId] = postIds;

  const setUp = (theme, collapsed, controls) => page.evaluate(([rt, theme, collapsed, controls]) => {
    localStorage.clear();
    localStorage.setItem(rt.themeStorageKey, theme);
    if (collapsed) localStorage.setItem(rt.collapsedWindowsStorageKey, collapsed);
    if (controls) localStorage.setItem(rt.windowControlsStorageKey, controls);
  }, [runtime, theme, collapsed, controls]);
  const height = id => page.locator(`.card[data-post-id="${id}"]`).first().evaluate(card => card.getBoundingClientRect().height);
  const firstHeight = id => page.evaluate(id => window.__firstHeights[id], id);

  // Bubblegum, root collapsed: small from the first frame, and it stays that size.
  await setUp('bubblegum', `${rootId} not/a"valid-id`, null);
  await page.goto(url);
  assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-window-controls')), 'on', 'controls on by default');
  const collapsedHeight = await height(rootId);
  assert.ok(collapsedHeight < 40, `collapsed post is only a title bar (${collapsedHeight}px)`);
  assert.ok(Math.abs(await firstHeight(rootId) - collapsedHeight) < 1, 'collapsed from the first frame, no jump');
  assert.ok(await height(replyId) > 60, 'other posts stay open');
  assert.equal(await page.locator(`.card[data-post-id="${rootId}"] p`).first().count(), 1, 'contents kept in the page');
  if (shots) await page.screenshot({ path: join(shots, 'bubblegum-collapsed-root.png') });

  // Every window shows its title bar, including posts that start a run (.meta-deferred).
  assert.deepEqual(await page.locator('.card').evaluateAll(all => [...new Set(all.map(card => getComputedStyle(card, '::before').opacity))]), ['1'], 'every title bar visible');

  // Another theme ignores saved collapses.
  await setUp('gloss', rootId, null);
  await page.goto(url);
  assert.ok(await height(rootId) > 60, 'gloss: not collapsed');

  // Window controls turned off in Preferences ignore saved collapses.
  await setUp('bubblegum', rootId, 'off');
  await page.goto(url);
  assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-window-controls')), false, 'off: no controls attribute');
  assert.ok(await height(rootId) > 60, 'off: not collapsed');
  assert.deepEqual(errors, [], 'no page errors with the flag on');
  on.stop();

  // Flag off: nothing is written before paint.
  const off = await startServer(false); servers.push(off);
  await page.goto(off.base + '/threads/root-001');
  await setUp('bubblegum', rootId, null);
  await page.goto(off.base + '/threads/root-001');
  assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-window-controls')), false, 'flag off: no controls attribute');
  assert.equal(await page.evaluate(() => document.getElementById('window-controls-collapsed')), null, 'flag off: no collapsed style');
  assert.ok(await height(rootId) > 60, 'flag off: not collapsed');
  assert.deepEqual(errors, [], 'no page errors');
  console.log('draggable_windows: ok');
} finally {
  if (browser) await browser.close();
  servers.forEach(s => s.stop());
  await rm(root, { recursive: true, force: true });
}
