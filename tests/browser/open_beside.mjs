import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const playwright = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');

// Open beside (FORUM_OPEN_BESIDE_ENABLED).
// A page shown in the pane's frame has no site header, ruler or status bar
// from the first frame; in any other frame, at top level, and with the flag
// off it keeps them.
// The Beside toggle on the ruler turns the mode on. With the mode on and room
// beside the column, a click on a content link opens the page in the pane and
// leaves the main page where it was; with no room, on a phone, or with the
// mode off the click navigates. Turning the mode off removes the pane.
// Usage: node tests/browser/open_beside.mjs [screenshot-dir]
// BROWSER=firefox runs it in Firefox (needs `npx playwright-core install firefox`).
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const browserName = process.env.BROWSER || 'chromium';
const root = await mkdtemp(join(tmpdir(), 'open-beside-browser-'));
const repo = join(root, 'repo');
const db = join(root, 'cache', 'read-model.sqlite3');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repo, { recursive: true });
await mkdir(join(root, 'cache'));
if (shots) await mkdir(shots, { recursive: true });
const baseEnv = { ...process.env, FORUM_REPOSITORY_ROOT: repo, FORUM_DATABASE_PATH: db,
  FORUM_APP_VERSION_NOTIFICATION: 'false', FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_READING_MARGINS_ENABLED: 'true' };
const rebuild = spawnSync(join(project, 'v3'), ['rebuild', repo, db], { cwd: project, env: baseEnv, encoding: 'utf8' });
assert.equal(rebuild.status, 0, rebuild.stderr + rebuild.stdout);

async function startServer(flagOn) {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(r => socket.close(r));
  const env = { ...baseEnv, FORUM_OPEN_BESIDE_ENABLED: flagOn ? 'true' : 'false' };
  const server = spawn(join(project, 'v3'), ['start', `127.0.0.1:${port}`, '--quiet'], { cwd: project, env, detached: true });
  const base = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 200; i++) { try { await (await fetch(base + '/')).text(); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  return { base, stop: () => { try { process.kill(-server.pid); } catch {} } };
}

// The site chrome a framed page hides.
const CHROME = ['.site-header', '.reading-margins', '.site-status-bar'];

let browser;
const servers = [];
try {
  browser = browserName === 'firefox'
    ? await playwright.firefox.launch({ headless: true })
    : await playwright.chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const on = await startServer(true); servers.push(on);
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 2048, height: 1280 } });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  const thread = on.base + '/threads/root-001';

  // A host page with two frames of the same thread: one with the pane's name, one without.
  async function frames(base) {
    await page.goto(base + '/threads/root-001');
    // The host watches each frame and notes whether each piece of chrome was
    // visible the first time it could be seen at all.
    await page.evaluate(([url, selectors]) => {
      document.body.innerHTML = '';
      window.__first = {};
      for (const name of ['forum-open-beside', 'something-else']) {
        const frame = document.createElement('iframe');
        frame.name = name; frame.src = url; frame.style.cssText = 'width:700px;height:600px';
        document.body.appendChild(frame);
        const first = window.__first[name] = {};
        const watch = setInterval(() => {
          const inner = frame.contentDocument;
          if (!inner || frame.contentWindow.location.href === 'about:blank') return;
          selectors.forEach(selector => {
            const node = inner.querySelector(selector);
            if (node && !(selector in first)) first[selector] = frame.contentWindow.getComputedStyle(node).display !== 'none';
          });
          if (inner.readyState === 'complete') clearInterval(watch);
        }, 0);
      }
    }, [base + '/threads/root-001', CHROME]);
    await page.waitForFunction(() => Array.from(document.querySelectorAll('iframe')).every(frame =>
      frame.contentWindow.location.href !== 'about:blank' && frame.contentDocument.readyState === 'complete'));
    const pane = page.frames().find(frame => frame.name() === 'forum-open-beside');
    const other = page.frames().find(frame => frame.name() === 'something-else');
    const read = frame => frame.evaluate(selectors => ({
      framed: document.documentElement.hasAttribute('data-open-beside-framed'),
      now: Object.fromEntries(selectors.map(selector => [selector, getComputedStyle(document.querySelector(selector)).display !== 'none'])),
      main: document.querySelector('.main .card') !== null,
    }), CHROME);
    const first = await page.evaluate(() => window.__first);
    return { pane: { ...await read(pane), first: first['forum-open-beside'] }, other: { ...await read(other), first: first['something-else'] } };
  }

  let seen = await frames(on.base); if (process.env.DEBUG) console.log(JSON.stringify(seen));
  assert.equal(seen.pane.framed, true, 'the pane\'s frame is marked');
  for (const selector of CHROME) {
    assert.equal(seen.pane.first[selector], false, `${selector} hidden in the pane at first paint`);
    assert.equal(seen.pane.now[selector], false, `${selector} hidden in the pane after load`);
  }
  // The status bar belongs to one theme (Word97) and is hidden in the others anyway.
  for (const selector of ['.site-header', '.reading-margins']) assert.equal(seen.other.now[selector], true, `${selector} shown in another frame`);
  assert.equal(seen.pane.main, true, 'the pane still shows the page\'s content');
  assert.equal(seen.other.framed, false, 'another frame is not marked');
  if (shots) await page.screenshot({ path: join(shots, 'stage1-frames.png') });

  // At top level a page is never marked, whatever the window is called.
  await page.goto(thread);
  await page.evaluate(() => { window.name = 'forum-open-beside'; });
  await page.reload();
  assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-open-beside-framed')), false, 'top level is not marked');
  await page.evaluate(() => { window.name = ''; });

  // Flag off: the pane's frame name means nothing.
  const off = await startServer(false); servers.push(off);
  seen = await frames(off.base);
  assert.equal(seen.pane.framed, false, 'flag off: not marked');
  for (const selector of ['.site-header', '.reading-margins']) assert.equal(seen.pane.now[selector], true, `flag off: ${selector} shown`);

  // --- The mode and the pane ---
  const sideways = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  const toggle = page.locator('[data-open-beside-toggle]');
  const paneFrame = () => page.frames().find(frame => frame.name() === 'forum-open-beside' && frame.parentFrame() === page.mainFrame());
  const paneBox = () => page.evaluate(() => {
    const pane = document.querySelector('[data-open-beside-pane]');
    if (!pane || pane.hidden) return null;
    const box = pane.getBoundingClientRect(); const main = document.querySelector('.main').getBoundingClientRect();
    return { left: box.left, right: box.right, top: box.top, bottom: box.bottom, width: box.width, mainRight: main.right, window: document.documentElement.clientWidth, viewport: innerHeight };
  });
  // The pane's page, once it has loaded.
  const paneAt = async () => {
    await page.waitForFunction(() => {
      const frame = document.querySelector('.open-beside__frame');
      return frame && frame.contentWindow.location.href !== 'about:blank' && frame.contentDocument.readyState === 'complete';
    });
    return page.evaluate(() => { const at = document.querySelector('.open-beside__frame').contentWindow.location; return at.pathname + at.search; });
  };
  // Narrow the column to the left of the window: its edges at stops -8... whatever fits, then 0.
  const narrowLeft = async () => {
    const stops = await page.locator('[data-reading-margin-stop]:not([hidden])').evaluateAll(all => all.map(stop => Number(stop.value)));
    const left = Math.min(...stops);
    await page.locator(`[data-reading-margin-stop][value="${left}"]`).click();
    await page.locator(`[data-reading-margin-stop][value="${left + 5}"]`).click();
    return left;
  };

  await page.setViewportSize({ width: 2048, height: 1280 });
  await page.goto(on.base + '/threads/');
  assert.equal(await toggle.getAttribute('aria-pressed'), 'false', 'the mode starts off');
  assert.equal(await sideways(), false, 'the toggle adds no sideways scrolling');
  const firstThread = page.locator('.main a[href^="/threads/root-"]').first();
  const threadPath = await firstThread.getAttribute('href');

  // Mode off: a click navigates as always.
  await firstThread.click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'mode off: no pane');
  await page.goto(on.base + '/threads/');

  // Mode on with the column narrowed to the left: the click opens the pane.
  await narrowLeft();
  await toggle.click();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
  assert.equal(await page.evaluate(key => localStorage.getItem(key), (await page.evaluate(() => window.forumBrowserRuntime)).openBesideStorageKey), 'on', 'the mode is saved');
  await page.evaluate(() => window.scrollTo(0, 40));
  const before = await page.evaluate(() => ({ url: location.href, y: scrollY }));
  await firstThread.click();
  assert.equal(await paneAt(), threadPath.split('#')[0], 'the thread opened in the pane');
  assert.deepEqual(await page.evaluate(() => ({ url: location.href, y: scrollY })), before, 'the main page stayed where it was');
  let box = await paneBox();
  assert.ok(box.left >= box.mainRight + 8, 'the pane starts right of the column');
  assert.ok(box.right <= box.window - 8, 'the pane ends inside the window');
  assert.ok(box.width >= 320 && box.width <= 1000, `the pane is a page wide (${box.width}px)`);
  assert.ok(box.bottom <= box.viewport && box.bottom - box.top > box.viewport * 0.8, 'the pane is nearly as tall as the window');
  assert.equal(await sideways(), false, 'no sideways scrolling with a pane');
  assert.equal(await paneFrame().evaluate(() => document.documentElement.hasAttribute('data-open-beside-framed')), true, 'the pane\'s page is framed');
  assert.equal(await paneFrame().locator('[data-open-beside-pane]').count(), 0, 'no pane inside the pane');
  if (shots) await page.screenshot({ path: join(shots, 'stage2-pane-2048.png') });

  // The ruler's toggle stays reachable above the pane at the top of the page.
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(100);
  box = await paneBox();
  const toggleBox = await toggle.boundingBox();
  assert.ok(box.top >= toggleBox.y + toggleBox.height, 'the pane starts below the ruler');

  // A reload restores the mode before paint.
  await page.reload();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'the mode survives a reload');

  // No room (the normal column at 1280px): the mode stays on and the click navigates.
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.evaluate(() => window.forumResetReadingMargins());
  await page.reload();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'no room: the mode stays on');
  assert.equal(await sideways(), false, '1280px: no sideways scrolling');
  if (shots) await page.screenshot({ path: join(shots, 'stage2-noroom-1280.png') });
  await firstThread.click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await page.locator('[data-open-beside-pane]:not([hidden])').count(), 0, 'no room: no pane');

  // Turning the mode off removes the pane and forgets it.
  await page.setViewportSize({ width: 2048, height: 1280 });
  await page.goto(on.base + '/threads/');
  await narrowLeft();
  await firstThread.click();
  assert.ok(await paneBox(), 'a pane again');
  await toggle.click();
  assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'mode off removes the pane');
  assert.equal(await toggle.getAttribute('aria-pressed'), 'false');

  // A phone has no ruler and no mode.
  await page.evaluate(key => localStorage.setItem(key, 'on'), (await page.evaluate(() => window.forumBrowserRuntime)).openBesideStorageKey);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(on.base + '/threads/');
  assert.equal(await toggle.isVisible(), false, 'phone: no toggle');
  await firstThread.click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await page.locator('[data-open-beside-pane]:not([hidden])').count(), 0, 'phone: no pane');
  await page.evaluate(key => localStorage.removeItem(key), (await page.evaluate(() => window.forumBrowserRuntime)).openBesideStorageKey);

  assert.deepEqual(errors, [], 'no page errors');
  console.log(`open_beside (${browserName}): ok`);
} finally {
  if (browser) await browser.close();
  servers.forEach(server => server.stop());
  await rm(root, { recursive: true, force: true });
}
