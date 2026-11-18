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

  assert.deepEqual(errors, [], 'no page errors');
  console.log(`open_beside (${browserName}): ok`);
} finally {
  if (browser) await browser.close();
  servers.forEach(server => server.stop());
  await rm(root, { recursive: true, force: true });
}
