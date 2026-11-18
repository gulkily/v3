import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { readFileSync } from 'node:fs';
import { runInThisContext } from 'node:vm';
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
// The pane's bar shows the page's title, Open alone and Close; focus stays on
// the clicked link and the opening is announced. Links inside the pane load
// in the pane, and so does a reply posted there. Ctrl-click, middle-click,
// new-tab, download and same-page links are left alone; a link to another
// site takes the whole tab, from the page or from the pane.
// The pane and its scroll position come back after the main page changes or
// reloads, without moving the main column; it hides while there is no room,
// follows a theme change, and ignores a saved value that is not a site path.
// With the ruler's flag off, the Preferences row alone switches the mode, and
// Reset all preferences turns it off and forgets the pane.
// Last, the reported case: at 2048px in Gloss Dark with the column narrowed to
// the left, a prompt's title clicked on the Prompts page opens its thread in
// the space beside the list. With a screenshot directory, every theme is shot
// with a pane at 2048px and 1280px and without one on a phone.
// In every theme the Beside toggle is filled and check-marked when on,
// outlined when off, and its label is readable in both states.
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

async function startServer(flagOn, extra = {}) {
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(r => socket.close(r));
  const env = { ...baseEnv, FORUM_OPEN_BESIDE_ENABLED: flagOn ? 'true' : 'false', ...extra };
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

  let seen = await frames(on.base);
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
  // Waits until the pane shows this page.
  const paneShows = async (address, message) => {
    try {
      await page.waitForFunction(expected => {
        const frame = document.querySelector('.open-beside__frame');
        if (!frame || frame.contentDocument.readyState !== 'complete') return false;
        const at = frame.contentWindow.location;
        return at.pathname + at.search === expected;
      }, address, { timeout: 15000 });
    } catch (error) {
      assert.fail(`${message || 'the pane shows ' + address} (pane at ${await paneAt().catch(() => 'nothing')})`);
    }
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
  await paneShows(threadPath.split('#')[0], 'the thread opened in the pane');
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

  // --- The bar and the kinds of link ---
  const keys = await page.evaluate(() => window.forumBrowserRuntime);
  const stored = key => page.evaluate(k => localStorage.getItem(k), key);
  const paneCount = () => page.locator('[data-open-beside-pane]:not([hidden])').count();
  // Another site, answered locally so the test needs no network.
  await context.route('https://elsewhere.example/**', route => route.fulfill({ contentType: 'text/html', body: '<title>Elsewhere</title><p id="elsewhere">elsewhere</p>' }));
  const addLink = (where, attrs) => where.evaluate(attributes => {
    const link = document.createElement('a');
    Object.entries(attributes).forEach(([name, value]) => { if (name === 'text') link.textContent = value; else link.setAttribute(name, value); });
    link.setAttribute('data-test-link', attributes.text);
    document.querySelector('.main').prepend(link, ' ');
  }, attrs);
  const testLink = (where, text) => where.locator(`[data-test-link="${text}"]`);
  const openBoardWithPane = async () => {
    await page.goto(on.base + '/threads/');
    if (await toggle.getAttribute('aria-pressed') !== 'true') await toggle.click();
    // A pane restored from the last page is about to be loaded again by the click; wait for that load.
    await page.evaluate(() => { const frame = document.querySelector('.open-beside__frame'); if (frame) frame.contentWindow.__stale = true; });
    await firstThread.click();
    await page.waitForFunction(expected => {
      const frame = document.querySelector('.open-beside__frame');
      return frame && frame.contentDocument.readyState === 'complete' && !frame.contentWindow.__stale && frame.contentWindow.location.pathname === expected;
    }, threadPath);
  };

  await page.goto(on.base + '/threads/');
  await toggle.click();
  await firstThread.click();
  await paneShows(threadPath);
  // The bar names the page; focus stayed on the link; the opening was announced.
  const bar = page.locator('.open-beside__bar');
  await page.waitForFunction(() => document.querySelector('.open-beside__title').textContent !== '');
  const threadTitle = await paneFrame().title();
  assert.equal(await bar.locator('.open-beside__title').textContent(), threadTitle, 'the bar shows the page\'s title');
  assert.equal(await page.evaluate(() => document.activeElement && document.activeElement.getAttribute('href')), threadPath, 'focus stays on the clicked link');
  assert.equal(await page.locator('[data-open-beside-status]').textContent(), 'Opened beside: ' + threadTitle, 'the opening is announced');
  assert.equal(await page.locator('[data-open-beside-pane]').getAttribute('aria-label'), 'Beside');
  if (shots) await page.screenshot({ path: join(shots, 'stage3-bar-2048.png') });

  // A link followed inside the pane loads in the pane; the bar and the saved page follow.
  await paneFrame().locator('a[href="/tags/bug"]').first().click();
  await page.waitForFunction(() => document.querySelector('.open-beside__frame').contentWindow.location.pathname === '/tags/bug');
  await page.waitForFunction(() => document.querySelector('[data-open-beside-alone]').getAttribute('href') === '/tags/bug');
  assert.equal(await stored(keys.openBesidePaneStorageKey), '/tags/bug', 'the saved page follows the pane');
  assert.equal(new URL(page.url()).pathname, '/threads/', 'the main page did not move');
  assert.notEqual(await bar.locator('.open-beside__title').textContent(), threadTitle, 'the bar\'s title follows the pane');

  // Ctrl-click and middle-click keep their browser meaning: no pane change, main page stays.
  await page.goto(on.base + '/threads/');
  await paneShows('/tags/bug', 'the pane came back on the next page');
  await page.locator('[data-open-beside-close]').click();
  for (const how of [{ modifiers: ['Control'] }, { button: 'middle' }]) {
    const opened = context.waitForEvent('page', { timeout: 5000 }).catch(() => null);
    await firstThread.click(how);
    const tab = await opened; if (tab) await tab.close();
    assert.equal(await paneCount(), 0, `${JSON.stringify(how)}: no pane`);
    assert.equal(new URL(page.url()).pathname, '/threads/', `${JSON.stringify(how)}: main page stays`);
  }

  // Links that ask for something else are left alone.
  await addLink(page, { href: threadPath, target: '_blank', text: 'new-tab' });
  const newTab = context.waitForEvent('page');
  await testLink(page, 'new-tab').click();
  await (await newTab).close();
  assert.equal(await paneCount(), 0, 'a new-tab link opens no pane');
  await addLink(page, { href: '#somewhere', text: 'same-page' });
  await testLink(page, 'same-page').click();
  assert.equal(await paneCount(), 0, 'a same-page link opens no pane');
  assert.equal(new URL(page.url()).hash, '#somewhere', 'a same-page link still scrolls the main page');
  await addLink(page, { href: '/llms.txt', download: '', text: 'download' });
  const download = page.waitForEvent('download');
  await testLink(page, 'download').click();
  await (await download).cancel();
  assert.equal(await paneCount(), 0, 'a download link opens no pane');

  // A file that is not a page, and a compose page, open in the pane.
  await addLink(page, { href: '/llms.txt', text: 'text-file' });
  await testLink(page, 'text-file').click();
  await paneShows('/llms.txt', 'a text file opens in the pane');
  assert.equal(await bar.locator('.open-beside__title').textContent(), '/llms.txt', 'a page without a title shows its address');
  await addLink(page, { href: '/compose/thread', text: 'compose' });
  await testLink(page, 'compose').click();
  await paneShows('/compose/thread', 'a compose page opens in the pane');

  // Header links change the main page.
  await page.locator('.site-header a[href^="/recent"], .site-header a[href*="recent"]').first().click();
  await page.waitForURL('**/recent**');

  // A link to another site: from the main page it navigates as always; from the pane it takes the whole tab.
  await openBoardWithPane();
  await addLink(page, { href: 'https://elsewhere.example/a', text: 'external' });
  await testLink(page, 'external').click();
  await page.waitForURL('https://elsewhere.example/a');
  await openBoardWithPane();
  await addLink(paneFrame(), { href: 'https://elsewhere.example/b', text: 'external' });
  await testLink(paneFrame(), 'external').click();
  await page.waitForURL('https://elsewhere.example/b');
  assert.equal(await page.locator('#elsewhere').count(), 1, 'the other site took the whole tab');

  // Open alone: the pane's page becomes the main page and the pane is forgotten.
  await openBoardWithPane();
  await page.waitForFunction(() => document.querySelector('[data-open-beside-alone]').getAttribute('href') !== null);
  await page.locator('[data-open-beside-alone]').click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await stored(keys.openBesidePaneStorageKey), null, 'Open alone forgets the pane');
  assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-open-beside-framed')), false, 'opened alone, the page has its header');

  // Close, by keyboard.
  await openBoardWithPane();
  await page.locator('[data-open-beside-close]').focus();
  await page.keyboard.press('Enter');
  assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'Close removes the pane');
  assert.equal(await stored(keys.openBesidePaneStorageKey), null, 'Close forgets the pane');
  await toggle.click();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'false');

  // --- The pane stays ---
  {
    // Where the main column sat the moment it was parsed, on every page from here on.
    await context.addInitScript(() => {
      if (window.top !== window || location.href === 'about:blank') return;
      new MutationObserver((records, observer) => {
        const main = document.querySelector('.main');
        if (!main) return;
        const box = main.getBoundingClientRect();
        window.__mainFirst = [Math.round(box.left), Math.round(box.width)];
        observer.disconnect();
      }).observe(document, { childList: true, subtree: true });
    });
    const mainNow = () => page.evaluate(() => { const box = document.querySelector('.main').getBoundingClientRect(); return { first: window.__mainFirst, now: [Math.round(box.left), Math.round(box.width)] }; });
    const paneScroll = () => page.evaluate(() => Math.round(document.querySelector('.open-beside__frame').contentWindow.scrollY));
    await page.setViewportSize({ width: 2048, height: 420 });
    await openBoardWithPane();
    await narrowLeft();
    // Scroll the pane, then change the main page from the header.
    await page.evaluate(() => document.querySelector('.open-beside__frame').contentWindow.scrollTo(0, 120));
    await page.waitForFunction(key => /"y":120/.test(localStorage.getItem(key + '-scroll') || ''), keys.openBesidePaneStorageKey);
    await page.locator('.site-header a[href^="/recent"], .site-header a[href*="recent"]').first().click();
    await page.waitForURL('**/recent**');
    await paneShows(threadPath, 'the pane is back after the main page changed');
    await page.waitForFunction(() => document.querySelector('.open-beside__frame').contentWindow.scrollY > 0);
    assert.equal(await paneScroll(), 120, 'the pane is scrolled where it was');
    let main = await mainNow();
    assert.deepEqual(main.now, main.first, 'the main column did not move when the pane came back');
    assert.equal(await page.locator('[data-open-beside-status]').textContent(), '', 'a restored pane is not announced');
    await page.waitForFunction(() => document.querySelector('.open-beside__title').textContent !== '');

    // A reload keeps it too.
    await page.reload();
    await paneShows(threadPath, 'the pane is back after a reload');
    main = await mainNow();
    assert.deepEqual(main.now, main.first, 'reload: the main column did not move');

    // No room hides the pane without unloading it; room brings it back.
    await page.evaluate(() => { document.querySelector('.open-beside__frame').contentWindow.__kept = true; });
    await page.setViewportSize({ width: 900, height: 420 });
    await page.waitForFunction(() => document.querySelector('[data-open-beside-pane]').hidden);
    assert.equal(await sideways(), false, 'a hidden pane adds no sideways scrolling');
    await page.setViewportSize({ width: 2048, height: 420 });
    await page.waitForFunction(() => !document.querySelector('[data-open-beside-pane]').hidden);
    assert.equal(await page.evaluate(() => document.querySelector('.open-beside__frame').contentWindow.__kept), true, 'the pane was hidden, not reloaded');

    // A page that loads with no room builds its pane once there is room.
    await page.setViewportSize({ width: 900, height: 420 });
    await page.reload();
    assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'no room at load: no pane yet');
    await page.setViewportSize({ width: 2048, height: 420 });
    await paneShows(threadPath, 'the pane appears once there is room');

    // A theme change in the main page reaches the pane.
    const themeBefore = await paneFrame().evaluate(() => document.documentElement.getAttribute('data-theme'));
    const other = themeBefore === 'word97' ? 'gloss' : 'word97';
    await page.evaluate(theme => window.forumSelectTheme(theme), other);
    await page.waitForFunction(theme => {
      const frame = document.querySelector('.open-beside__frame');
      return frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentDocument.documentElement.getAttribute('data-theme') === theme;
    }, other);
    await paneShows(threadPath, 'the pane kept its page through the theme change');
    if (shots) await page.screenshot({ path: join(shots, 'stage4-word97-2048.png') });
    await page.evaluate(theme => window.forumSelectTheme(theme), themeBefore);
    // Let the pane finish following the theme back before the saved page is changed by hand below.
    await page.waitForFunction(theme => {
      const frame = document.querySelector('.open-beside__frame');
      return frame.contentDocument && frame.contentDocument.readyState === 'complete' && frame.contentDocument.documentElement.getAttribute('data-theme') === theme;
    }, themeBefore);
    await page.waitForTimeout(300);

    // A saved page that no longer exists shows the site's not-found page, and Close forgets it.
    await page.evaluate(key => localStorage.setItem(key, '/threads/no-such-thread'), keys.openBesidePaneStorageKey);
    await page.reload();
    await paneShows('/threads/no-such-thread');
    assert.equal(await paneFrame().evaluate(() => document.documentElement.hasAttribute('data-open-beside-framed')), true, 'the not-found page is framed too');
    await page.locator('[data-open-beside-close]').click();
    assert.equal(await stored(keys.openBesidePaneStorageKey), null);
    // A saved value that is not a path on this site is ignored.
    for (const bad of ['https://elsewhere.example/x', '//elsewhere.example/x', 'javascript:alert(1)']) {
      await page.evaluate(([key, value]) => localStorage.setItem(key, value), [keys.openBesidePaneStorageKey, bad]);
      await page.reload();
      await page.waitForTimeout(300);
      assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, `${bad} is not opened`);
    }
    await page.evaluate(key => localStorage.removeItem(key), keys.openBesidePaneStorageKey);
    await page.evaluate(() => window.forumResetReadingMargins());
    await toggle.click();
    assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
  }

  // A phone has no ruler and no mode.
  await page.evaluate(key => localStorage.setItem(key, 'on'), (await page.evaluate(() => window.forumBrowserRuntime)).openBesideStorageKey);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(on.base + '/threads/');
  assert.equal(await toggle.isVisible(), false, 'phone: no toggle');
  await firstThread.click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await page.locator('[data-open-beside-pane]:not([hidden])').count(), 0, 'phone: no pane');
  await page.evaluate(key => localStorage.removeItem(key), (await page.evaluate(() => window.forumBrowserRuntime)).openBesideStorageKey);

  // --- Without the ruler: Preferences alone switches the mode ---
  {
    const bare = await startServer(true, { FORUM_READING_MARGINS_ENABLED: 'false' }); servers.push(bare);
    await page.setViewportSize({ width: 2048, height: 1280 });
    await page.goto(bare.base + '/tools/preferences/');
    assert.equal(await page.locator('[data-open-beside-toggle]').count(), 0, 'no ruler, no toggle');
    await page.locator('#preference-open-beside').selectOption('on');
    await page.goto(bare.base + '/threads/');
    await page.locator('.main a[href^="/threads/root-"]').first().click();
    await paneShows(threadPath, 'the mode works without the ruler');
    await page.goto(bare.base + '/tools/preferences/');
    await paneShows(threadPath);
    // Reset all preferences turns the mode off and forgets the pane.
    await Promise.all([page.waitForEvent('load'), page.locator('[data-preference-reset="all"]').click()]);
    assert.equal(await page.locator('#preference-open-beside').inputValue(), 'off');
    assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'Reset all: no pane');
    assert.equal(await page.evaluate(() => Object.keys(localStorage).filter(key => key.includes('open-beside')).length), 0, 'Reset all: nothing of the mode is left');
  }

  // --- Posting in the pane (the signing fixture, which can accept posts) ---
  {
    runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
    const seedKeys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
    const postRoot = await mkdtemp(join(tmpdir(), 'open-beside-post-'));
    const postEnv = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: postRoot,
      FORUM_SECRETS_PATH: join(postRoot, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(postRoot, 'visitors.sqlite3'),
      FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'true', FORUM_READING_MARGINS_ENABLED: 'true', FORUM_OPEN_BESIDE_ENABLED: 'true' };
    const seeded = spawnSync('php', [join(project, 'tests/Support/prompt_card_like_browser_fixture.php'), postRoot], { input: JSON.stringify(seedKeys), encoding: 'utf8', env: postEnv });
    assert.equal(seeded.status, 0, seeded.stderr);
    const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
    const port = socket.address().port; await new Promise(r => socket.close(r));
    const postBase = `http://127.0.0.1:${port}`;
    const postServer = spawn('php', ['-d', `session.save_path=${postRoot}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env: postEnv, stdio: 'ignore' });
    servers.push({ stop: () => { postServer.kill(); return rm(postRoot, { recursive: true, force: true }); } });
    for (let i = 0; i < 60; i++) { try { await fetch(postBase + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
    await page.setViewportSize({ width: 2048, height: 1280 });
    // The normal column at this width leaves room for a pane without touching the ruler.
    await page.goto(postBase + '/threads/root-001');
    await toggle.click();
    await addLink(page, { href: '/threads/root-001', text: 'thread' });
    await testLink(page, 'thread').click();
    await paneShows('/threads/root-001');
    const pane = paneFrame();
    assert.equal(await pane.evaluate(() => document.documentElement.hasAttribute('data-open-beside-framed')), true);
    const replyForm = pane.locator('[data-compose-kind="reply"]').first();
    // The fixture makes a guest key on first visit; wait for it as first_post_identity.mjs does.
    await pane.waitForFunction(() => window.__forumBrowserIdentity && window.__forumBrowserIdentity.storedVoteIdentityReadinessState() !== 'idle');
    // Click and type as a reader would: the form shows its buttons once the box has focus.
    await replyForm.locator('textarea[name="body"]').click();
    await page.keyboard.type('Written beside the thread');
    const created = page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 45000 });
    created.catch(() => {}); // a failure before the await below reports itself, not this
    await replyForm.locator('.compose-form-actions > button[type="submit"]').click();
    assert.match(await (await created).text(), /ok/, 'the reply posted from the pane was accepted');
    await page.waitForFunction(() => /Written beside the thread/.test(document.querySelector('.open-beside__frame').contentDocument.body.textContent), null, { timeout: 30000 });
    assert.equal(await page.evaluate(() => document.querySelector('.open-beside__frame').contentDocument.documentElement.hasAttribute('data-open-beside-framed')), true, 'after posting, the pane\'s page is still framed');
    assert.equal(new URL(page.url()).pathname, '/threads/root-001', 'posting in the pane leaves the main page alone');
    assert.equal(await page.locator('.main').evaluate(main => /Written beside the thread/.test(main.textContent)), false, 'the main page is not refreshed (known limitation)');
    // A like in the pane stays in the pane.
    const like = page.frames().find(frame => frame.name() === 'forum-open-beside').locator('button[data-tag="like"]').first();
    await like.evaluate(button => button.click());
    await page.waitForFunction(() => {
      const button = document.querySelector('.open-beside__frame').contentDocument.querySelector('button[data-tag="like"]');
      return button && button.getAttribute('aria-pressed') === 'true';
    }, null, { timeout: 30000 });
    assert.equal(new URL(page.url()).pathname, '/threads/root-001', 'a like in the pane leaves the main page alone');
    await page.locator('[data-open-beside-close]').click();

    // --- The reported case (thread-20261116070645-8b937ee9) ---
    // A 2048px-wide desktop, Gloss Dark, the column narrowed to the left, the
    // Prompts page: the empty space to the right fills with the clicked page.
    await page.setViewportSize({ width: 2048, height: 1280 });
    await page.evaluate(() => window.forumSelectTheme('glossdark'));
    await page.goto(postBase + '/agent/');
    assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-theme')), 'glossdark');
    await narrowLeft();
    const emptyBefore = await page.evaluate(() => document.documentElement.clientWidth - document.querySelector('.main').getBoundingClientRect().right);
    assert.ok(emptyBefore > 1000, `the reported condition: ${Math.round(emptyBefore)}px empty beside the column`);
    const promptTitle = page.locator('.prompt-card__title a').first();
    const promptThread = await promptTitle.getAttribute('href');
    await promptTitle.click();
    await paneShows(promptThread, 'the prompt\'s thread opened beside the list');
    assert.equal(new URL(page.url()).pathname, '/agent/', 'the Prompts page stayed');
    const reported = await paneBox();
    assert.ok(reported.left >= reported.mainRight && reported.width >= 600, `the pane fills the space beside the column (${Math.round(reported.width)}px)`);
    assert.equal(await page.evaluate(() => document.querySelector('.open-beside__frame').contentDocument.documentElement.getAttribute('data-theme')), 'glossdark', 'the pane is in the same theme');
    assert.equal(await sideways(), false);
    if (shots) await page.screenshot({ path: join(shots, `reported-${browserName}-glossdark-2048.png`) });

    // --- The Beside toggle shows its state in every theme ---
    // Off is an outlined pill on the page's background; on is filled, with a
    // check mark, and its label must be readable against the fill.
    const themes = await page.locator('[data-theme-option]').evaluateAll(all => [...new Set(all.map(node => node.value || node.getAttribute('data-theme-option')).filter(name => name && name !== 'auto'))]);
    assert.ok(themes.length >= 8, `themes found: ${themes}`);
    const toggleLook = () => toggle.evaluate(button => {
      const rgb = value => { const probe = document.createElement('canvas').getContext('2d'); probe.fillStyle = value; probe.fillRect(0, 0, 1, 1); return Array.from(probe.getImageData(0, 0, 1, 1).data.slice(0, 3)); };
      const luminance = ([r, g, b]) => [r, g, b].map(c => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; }).reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
      const label = button.querySelector('.reading-margins__beside-label');
      // On, the fill is a layer under the label (button::before); off, it is the button's background.
      const layer = getComputedStyle(button, '::before');
      const fill = rgb(layer.content !== 'none' && layer.content !== 'normal' ? layer.backgroundColor : getComputedStyle(button).backgroundColor);
      const ink = rgb(getComputedStyle(label).color);
      const [light, dark] = [luminance(fill), luminance(ink)].sort((a, b) => b - a);
      return { fill: fill.join(','), contrast: (light + 0.05) / (dark + 0.05), mark: getComputedStyle(label, '::before').content, pressed: button.getAttribute('aria-pressed') };
    });
    const toggleShots = [];
    await page.setViewportSize({ width: 1280, height: 900 });
    for (const theme of themes) {
      await page.evaluate(name => window.forumSelectTheme(name), theme);
      await page.goto(postBase + '/agent/');
      if (await toggle.getAttribute('aria-pressed') !== 'true') await toggle.click();
      await page.mouse.move(10, 400);
      const onLook = await toggleLook();
      if (shots) toggleShots.push([theme, 'on', await toggle.screenshot()]);
      // Hovered, the button stays under the pointer (a theme's hover effect must not move it away).
      await toggle.hover();
      await page.waitForTimeout(150);
      assert.equal(await toggle.evaluate(button => button.matches(':hover')), true, `${theme}: the toggle stays under the pointer`);
      await toggle.click();
      await page.mouse.move(10, 400);
      const offLook = await toggleLook();
      if (shots) toggleShots.push([theme, 'off', await toggle.screenshot()]);
      assert.equal(onLook.pressed, 'true'); assert.equal(offLook.pressed, 'false');
      assert.notEqual(onLook.fill, offLook.fill, `${theme}: on and off are filled differently`);
      assert.ok(onLook.contrast >= 3, `${theme}: the pressed label is readable on its fill (${onLook.contrast.toFixed(2)}:1)`);
      assert.ok(offLook.contrast >= 3, `${theme}: the unpressed label is readable (${offLook.contrast.toFixed(2)}:1)`);
      assert.match(onLook.mark, /\u2713/, `${theme}: on shows a check mark`);
      assert.ok(offLook.mark === 'none' || offLook.mark === 'normal' || offLook.mark === '""', `${theme}: off shows no mark (${offLook.mark})`);
      await toggle.click();
    }
    if (shots) {
      const { writeFile } = await import('node:fs/promises');
      for (const [theme, state, image] of toggleShots) await writeFile(join(shots, `toggle-${theme}-${state}.png`), image);
    }
    // Turning the mode off closed the pane; open it again for the screenshots below.
    await page.setViewportSize({ width: 2048, height: 1280 });
    await page.goto(postBase + '/agent/');
    await promptTitle.click();
    await paneShows(promptThread);

    // --- Every theme, with a pane at 2048px and 1280px, and a phone ---
    if (shots) {
      for (const theme of themes) {
        await page.evaluate(name => window.forumSelectTheme(name), theme);
        for (const [width, height] of [[2048, 1280], [1280, 900]]) {
          await page.setViewportSize({ width, height });
          await page.goto(postBase + '/agent/');
          // The saved column may not fit this theme or width; narrow it again from the stops that do.
          if (await page.evaluate(() => !document.documentElement.hasAttribute('data-reading-margins'))) await narrowLeft();
          await paneShows(promptThread, `${theme} ${width}: the pane is back`);
          assert.equal(await sideways(), false, `${theme} ${width}: no sideways scrolling`);
          const shown = await paneBox();
          assert.ok(shown && shown.right <= shown.window, `${theme} ${width}: the pane is inside the window`);
          await page.waitForTimeout(250);
          await page.screenshot({ path: join(shots, `theme-${theme}-${width}.png`) });
        }
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(postBase + '/agent/');
        assert.equal(await page.locator('[data-open-beside-pane]:not([hidden])').count(), 0, `${theme} phone: no pane`);
        assert.equal(await sideways(), false, `${theme} phone: no sideways scrolling`);
        await page.screenshot({ path: join(shots, `theme-${theme}-390.png`) });
      }
    }
  }

  assert.deepEqual(errors, [], 'no page errors');
  console.log(`open_beside (${browserName}): ok`);
} finally {
  if (browser) await browser.close();
  for (const server of servers) await server.stop();
  await rm(root, { recursive: true, force: true });
}
