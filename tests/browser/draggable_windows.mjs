import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Draggable windows (FORUM_DRAGGABLE_WINDOWS_ENABLED) in the Bubblegum theme.
// A post collapsed in this browser is collapsed from the first frame
// (no jump once the page settles), every window shows its title bar, and nothing is collapsed with the flag off,
// in another theme, or with window controls turned off in Preferences.
// Every window gets one collapse button (mouse, keyboard, tap, or a double-click
// on the title bar); a post's collapse is saved and shows its author; a
// composer keeps its draft; switching theme removes every button and switching
// back restores them; with the flag off the script isn't even loaded.
// Clicking or tabbing into a window makes it active and fades the other title
// bars (labels stay readable); a window pops open unless motion is reduced.
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
  // Working collapse buttons.
  await setUp('bubblegum', null, null);
  await page.goto(url);
  await page.waitForSelector('[data-window-titlebar]');
  const cards = await page.locator('.card').count();
  assert.equal(await page.locator('.card > [data-window-titlebar]').count(), cards, 'every window has one title bar');
  const button = id => page.locator(`.card[data-post-id="${id}"] > [data-window-titlebar] > [data-window-collapse]`).first();
  const saved = () => page.evaluate(k => localStorage.getItem(k), runtime.collapsedWindowsStorageKey);
  assert.equal(await button(rootId).getAttribute('aria-expanded'), 'true');
  assert.match(await button(rootId).getAttribute('aria-label'), /^Window: /, 'button named after its window');
  await button(rootId).click();
  assert.ok(await height(rootId) < 40, 'click collapses');
  assert.equal(await button(rootId).getAttribute('aria-expanded'), 'false');
  assert.equal(await saved(), rootId, 'post collapse saved');
  assert.equal(await page.locator(`.card[data-post-id="${rootId}"] [data-window-label]`).first().evaluate(l => getComputedStyle(l).visibility), 'visible', 'collapsed post shows its label');
  assert.equal(await page.locator(`.card[data-post-id="${rootId}"] [data-window-label]`).first().textContent(),
    await page.locator(`.card[data-post-id="${rootId}"]`).first().getAttribute('data-author'), 'label is the author');
  if (shots) await page.screenshot({ path: join(shots, 'bubblegum-buttons-desktop.png') });
  await page.reload();
  await page.waitForSelector('[data-window-titlebar]');
  assert.ok(await height(rootId) < 40, 'still collapsed after reload');
  assert.equal(await page.evaluate(() => document.getElementById('window-controls-collapsed')), null, 'script replaced the pre-paint style');
  await page.locator(`.card[data-post-id="${rootId}"] > [data-window-titlebar]`).first().dblclick({ position: { x: 200, y: 8 } });
  assert.ok(await height(rootId) > 60, 'double-click on the title bar expands');
  assert.equal(await saved(), null, 'expanded post forgotten');
  await button(replyId).focus();
  await page.keyboard.press('Enter');
  assert.ok(await height(replyId) < 40, 'Enter collapses');
  await page.keyboard.press('Space');
  assert.ok(await height(replyId) > 60, 'Space expands');

  // Active window: clicking or tabbing into a window fades the other title bars.
  const barColour = id => page.locator(`.card[data-post-id="${id}"]`).first().evaluate(c => getComputedStyle(c, '::before').backgroundColor);
  const activeColour = 'rgb(228, 91, 156)'; // --bubblegum-bar
  await page.locator(`.card[data-post-id="${replyId}"] .body, .card[data-post-id="${replyId}"] p`).first().click();
  assert.equal(await page.locator('[data-window-active]').count(), 1, 'one active window');
  assert.equal(await page.locator(`.card[data-post-id="${replyId}"]`).first().getAttribute('data-window-active'), '', 'clicked window is active');
  assert.equal(await barColour(replyId), activeColour, 'active title bar keeps its colour');
  assert.notEqual(await barColour(rootId), activeColour, 'other title bars fade');
  await button(rootId).focus();
  assert.equal(await page.locator(`.card[data-post-id="${rootId}"]`).first().getAttribute('data-window-active'), '', 'tabbing in activates');
  // A collapsed, inactive window's label keeps 4.5:1 contrast on the faded bar.
  // (Keyboard here: the root post shows its byline while focused, so moving focus
  // out of it by pointer shifts the reply mid-click. That's existing behaviour.)
  await button(replyId).focus();
  await page.keyboard.press('Enter');
  assert.ok(await height(replyId) < 40, 'reply collapsed');
  await button(rootId).focus();
  const contrast = await page.locator(`.card[data-post-id="${replyId}"]`).first().evaluate(card => {
    const rgb = value => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
    const lum = ([r, g, b]) => { const f = c => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
    const a = lum(rgb(getComputedStyle(card.querySelector('[data-window-label]')).color));
    const b = lum(rgb(getComputedStyle(card, '::before').backgroundColor));
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
  });
  assert.ok(contrast >= 4.5, `faded label contrast ${contrast.toFixed(2)}`);
  if (shots) await page.screenshot({ path: join(shots, 'bubblegum-active-window.png') });
  // Pop on expand.
  // Animations started on the window, recorded as they start (the pop lasts 180ms).
  await page.evaluate(() => { window.__pops = []; document.addEventListener('animationstart', e => window.__pops.push(e.animationName)); });
  await button(replyId).focus();
  await page.keyboard.press('Enter');
  await page.waitForTimeout(300);
  assert.deepEqual(await page.evaluate(() => window.__pops), ['bubblegum-window-pop'], 'window pops open');
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.keyboard.press('Enter');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(300);
  assert.deepEqual(await page.evaluate(() => window.__pops), ['bubblegum-window-pop'], 'no pop with reduced motion');
  await page.emulateMedia({ reducedMotion: 'no-preference' });

  // A composer keeps its draft through collapse and expand.
  const composer = page.locator('.card:has(textarea)').last();
  await composer.locator('textarea').fill('draft text');
  await composer.locator('> [data-window-titlebar] > [data-window-collapse]').click();
  assert.equal(await composer.locator('textarea').isVisible(), false, 'composer collapsed');
  await composer.locator('> [data-window-titlebar] > [data-window-collapse]').click();
  assert.equal(await composer.locator('textarea').inputValue(), 'draft text', 'draft kept');

  // Switching theme removes everything; switching back restores it, saved posts included.
  await button(rootId).click();
  await page.evaluate(() => window.forumExitTheme('gloss'));
  assert.equal(await page.locator('[data-window-titlebar]').count(), 0, 'gloss: no title bars');
  assert.equal(await page.locator('[data-window], [data-window-collapsed]').count(), 0, 'gloss: no window marks');
  assert.ok(await height(rootId) > 60, 'gloss: nothing collapsed');
  await page.evaluate(() => window.forumExitTheme('bubblegum'));
  await page.waitForSelector('[data-window-titlebar]');
  assert.ok(await height(rootId) < 40, 'back in bubblegum: saved collapse restored');

  // Other pages: every window gets a button.
  for (const path of ['/tools/', '/tools/preferences/', '/about/']) {
    await page.goto(on.base + path);
    await page.waitForFunction(() => window.forumThemeScripts && window.forumThemeScripts.active() === 'bubblegum' && document.querySelector('[data-window]'));
    assert.equal(await page.locator('.card > [data-window-titlebar]').count(), await page.locator('.card').count(), `${path}: every window has a title bar`);
  }
  assert.deepEqual(errors, [], 'no page errors with the flag on');

  // Phone width, by tap.
  const phone = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 380, height: 800 }, hasTouch: true, isMobile: true });
  const phonePage = await phone.newPage();
  await phonePage.goto(url);
  await phonePage.evaluate(([rt]) => { localStorage.clear(); localStorage.setItem(rt.themeStorageKey, 'bubblegum'); }, [runtime]);
  await phonePage.goto(url);
  await phonePage.waitForSelector('[data-window-titlebar]');
  await phonePage.locator(`.card[data-post-id="${replyId}"] > [data-window-titlebar] > [data-window-collapse]`).first().tap();
  assert.ok(await phonePage.locator(`.card[data-post-id="${replyId}"]`).first().evaluate(c => c.getBoundingClientRect().height) < 40, 'tap collapses on a phone');
  assert.equal(await phonePage.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth), false, 'no sideways scroll on a phone');
  if (shots) await phonePage.screenshot({ path: join(shots, 'bubblegum-buttons-phone.png'), fullPage: true });
  await phone.close();
  on.stop();

  // Flag off: nothing is written before paint.
  const off = await startServer(false); servers.push(off);
  await page.goto(off.base + '/threads/root-001');
  await setUp('bubblegum', rootId, null);
  await page.goto(off.base + '/threads/root-001');
  assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-window-controls')), false, 'flag off: no controls attribute');
  assert.equal(await page.evaluate(() => document.getElementById('window-controls-collapsed')), null, 'flag off: no collapsed style');
  assert.ok(await height(rootId) > 60, 'flag off: not collapsed');
  assert.equal(await page.evaluate(() => 'bubblegum' in (window.forumThemeScriptPaths || {})), false, 'flag off: script not offered');
  assert.equal(await page.locator('[data-window-titlebar]').count(), 0, 'flag off: no title bars');
  assert.deepEqual(errors, [], 'no page errors');
  console.log('draggable_windows: ok');
} finally {
  if (browser) await browser.close();
  servers.forEach(s => s.stop());
  await rm(root, { recursive: true, force: true });
}
