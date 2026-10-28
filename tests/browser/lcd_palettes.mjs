// Run with: node tests/browser/lcd_palettes.mjs
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'lcd-palettes-'));
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
let browser;
const paletteToggle = '[data-action="lcd-palette-toggle"]';
const paletteOption = value => `[data-lcd-palette-option="${value}"]`;
const themeOption = value => `[data-theme-option="${value}"]`;
const themeKey = 'forum-zenmemes-theme';
const paletteKey = themeKey + '-lcd-palette';
const errors = [];
const baselineComposeErrors = [];
// The app shell keeps the theme controls in the account menu; open it first.
async function openThemeControls(page) {
  const menu = page.locator('details[data-role="account-menu"]');
  if (await menu.count() && !(await menu.evaluate(details => details.open))) {
    await menu.locator('summary').click();
  }
}
async function theme(page, value) {
  await openThemeControls(page);
  await page.locator('[data-action="theme-toggle"]').click();
  await page.locator(themeOption(value)).click();
  await page.waitForFunction(value => document.documentElement.dataset.theme === value, value);
}
async function palette(page, value) {
  await openThemeControls(page);
  await page.locator(paletteToggle).click();
  await page.locator(paletteOption(value)).click();
}
async function savedContext(value = 'green', blockedMethod = null) {
  const context = await browser.newContext({ serviceWorkers: 'block' });
  await context.addInitScript(({ themeKey, paletteKey, value, blockedMethod }) => {
    localStorage.setItem(themeKey, 'lcd');
    if (value !== null) localStorage.setItem(paletteKey, value);
    if (blockedMethod) for (const name of blockedMethod === 'both' ? ['getItem', 'setItem'] : [blockedMethod]) {
      Storage.prototype[name] = function () { throw new Error('Blocked storage access'); };
    }
  }, { themeKey, paletteKey, value, blockedMethod });
  const page = await context.newPage();
  page.setDefaultTimeout(6000);
  page.on('pageerror', error => {
    const stack = error.stack || error.message;
    // Reproduced at pre-feature c10c305b: board composer reads its identity without a storage guard.
    if (blockedMethod && stack.includes('currentAuthorIdentityId') && stack.includes('browser_signing.')) baselineComposeErrors.push(stack);
    else errors.push(stack);
  });
  return { context, page };
}
function luminance(rgb) {
  const channels = rgb.map(x => (x /= 255) <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4);
  return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
}
function contrast(pair) {
  const values = pair.map(luminance).sort((a, b) => a - b);
  return (values[1] + .05) / (values[0] + .05);
}
try {
  for (let i = 0; i < 100; i++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 50)); }
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  page.setDefaultTimeout(6000);
  page.on('pageerror', error => errors.push(error.stack || error.message));
  await page.goto(base + '/');
  await theme(page, 'lcd');
  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 844 });
    for (const value of ['green', 'amber', 'purple', 'gray']) {
      await palette(page, value);
      assert.equal(await page.locator(paletteToggle).getAttribute('aria-label'), `Choose LCD palette: ${value[0].toUpperCase() + value.slice(1)}`);
      assert.equal(await page.locator(paletteOption(value)).getAttribute('aria-checked'), 'true');
      await page.reload();
      assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), value);
      await theme(page, 'light');
      assert.equal(await page.locator(paletteToggle).isVisible(), false);
      await theme(page, 'lcd');
      assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), value);
      await page.goto(base + '/threads/?view=all');
      assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), value);
      // Compare the exact same rendered header with/without reserved palette space.
      await openThemeControls(page);
      const before = await page.locator('.theme-menu').boundingBox();
      await page.addStyleTag({ content: ':root[data-theme="lcd"] .theme-menu {margin-left:0!important}.lcd-palette{display:none!important}' });
      const baseline = await page.locator('.theme-menu').boundingBox();
      assert.equal(before.x, baseline.x, 'theme right anchor');
      assert.equal(before.y, baseline.y, 'theme top anchor');
      await page.reload();
      const thread = page.locator('a[href^="/threads/thread-"]').first();
      await thread.click();
      assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), value);
      // Sample the actual inherited theme variables across shared component backgrounds.
      const pairs = await page.evaluate(() => {
        const probe = document.createElement('i'); document.body.append(probe);
        const sample = (fg, bg, target) => {
          probe.style.cssText = `color:var(${fg});background:var(${bg})`;
          const style = getComputedStyle(probe);
          return { fg, bg, target, pair: [style.color, style.backgroundColor].map(c => c.match(/[\d.]+/g).slice(0, 3).map(Number)) };
        };
        const pairs = [];
        for (const fg of ['--ink', '--ink-soft']) for (const bg of ['--page-bg', '--panel', '--input-bg', '--button-bg', '--button-alt-bg', '--active-bg', '--feedback-ok-bg', '--feedback-error-bg']) pairs.push(sample(fg, bg, 4.5));
        for (const fg of ['--status-ok', '--status-error', '--status-warning']) for (const bg of ['--page-bg', '--panel', '--feedback-ok-bg', '--feedback-error-bg']) pairs.push(sample(fg, bg, 4.5));
        for (const fg of ['--line', '--ink']) for (const bg of ['--page-bg', '--panel', '--input-bg']) pairs.push(sample(fg, bg, 3));
        probe.remove(); return pairs;
      });
      for (const pair of pairs) assert.ok(contrast(pair.pair) >= pair.target, `${value}: ${pair.fg}/${pair.bg} contrast`);
      await page.locator('textarea').first().click(); // expand the real reply composer
      assert.ok(await page.locator('button[type="submit"]:visible').count());
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      await openThemeControls(page);
      await page.locator(paletteToggle).click();
      await page.screenshot({ path: join(root, `${value}-${width}.png`), fullPage: true });
      await page.keyboard.press('Escape');
      assert.equal(await page.locator(paletteToggle).evaluate(e => e === document.activeElement), true);
    }
    await page.locator(paletteToggle).focus(); await page.keyboard.press('Enter');
    await page.keyboard.press('Home'); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter');
    assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'amber');
    await page.locator(paletteToggle).click(); await page.keyboard.press('Tab');
    assert.equal(await page.locator('[data-action="theme-cycle"]').evaluate(e => e === document.activeElement), true);
    assert.equal(await page.locator('#lcd-palette-popover').isVisible(), false);
    await page.locator(paletteToggle).click(); await page.locator('h1').first().click({ position: { x: 2, y: 2 } });
    assert.equal(await page.locator('#lcd-palette-popover').isVisible(), false);
    await openThemeControls(page); // the outside click above also closed the account menu
    await page.locator(paletteToggle).click(); await page.locator('[data-action="theme-toggle"]').click();
    assert.equal(await page.locator('#lcd-palette-popover').isVisible(), false);
    await page.keyboard.press('Escape');
    // Cycle into LCD through the ordinary cycle control.
    await theme(page, 'console'); await page.locator('[data-action="theme-cycle"]').click();
    await page.waitForFunction(() => document.documentElement.dataset.theme === 'lcd');
    assert.equal(await page.locator(paletteToggle).isVisible(), true);
    assert.equal(await page.locator(themeOption('lcd')).count(), 1);
    const cycle = page.locator('[data-action="theme-cycle"]');
    for (const [value, next] of [['green', 'LCD Amber'], ['amber', 'LCD Purple'], ['purple', 'LCD Gray'], ['gray', 'Chicago']]) {
      assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), value);
      assert.equal(await page.evaluate(key => localStorage.getItem(key), paletteKey), value);
      assert.ok((await cycle.getAttribute('aria-label')).includes('switch to ' + next));
      assert.ok((await cycle.getAttribute('title')).includes('switch to ' + next));
      await cycle.focus(); await page.keyboard.press('Enter');
    }
    await page.waitForFunction(() => document.documentElement.dataset.theme === 'chicago');
    await theme(page, 'lcd');
    assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'gray', 'dropdown restores remembered palette');
    await palette(page, 'amber'); await cycle.click();
    assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'purple', 'direct selection sets rotation stop');
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'purple');
    await openThemeControls(page); await cycle.focus(); await page.keyboard.press('Space');
    assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'gray');
    // All permitted non-LCD themes retain their ordinary selection and hide palettes.
    const themes = await page.locator('[data-theme-option]').evaluateAll(es => es.map(e => e.dataset.themeOption).filter(t => !['lcd', 'auto'].includes(t)));
    for (const name of themes) { await theme(page, name); assert.equal(await page.locator(paletteToggle).isVisible(), false); }
    await theme(page, 'lcd');
  }
  // Foreign namespace and malformed/missing preferences never affect this site's choice.
  await page.evaluate(({ themeKey, paletteKey }) => { localStorage.setItem(themeKey, 'lcd'); localStorage.removeItem(paletteKey); localStorage.setItem('forum-qdb-theme-lcd-palette', 'purple'); }, { themeKey, paletteKey });
  await page.reload(); assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'green');
  await page.evaluate(key => localStorage.setItem(key, 'invalid'), paletteKey);
  await page.reload(); assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'green');
  await palette(page, 'gray');
  assert.equal(await page.evaluate(() => localStorage.getItem('forum-qdb-theme-lcd-palette')), 'purple');
  await page.goto(base + '/forte/');
  assert.equal(await page.locator('[data-role="theme-menu"]').count(), 0);
  assert.equal(await page.locator('html').getAttribute('data-forte-scheme'), 'light');
  await page.goto(base + '/tools/'); assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), 'gray');
  console.log('PASS palettes, contrast, layout anchors, keyboard, navigation, reload, cycling, other themes, namespaces, standalone');

  for (const method of ['getItem', 'setItem', 'both']) {
    const blocked = await savedContext('amber', method);
    await blocked.page.goto(base + '/');
    await theme(blocked.page, 'lcd');
    await palette(blocked.page, 'purple');
    assert.equal(await blocked.page.locator('html').getAttribute('data-lcd-palette'), 'purple');
    await theme(blocked.page, 'light'); await theme(blocked.page, 'lcd');
    assert.equal(await blocked.page.locator('html').getAttribute('data-lcd-palette'), 'purple');
    await blocked.page.locator('[data-action="theme-cycle"]').click();
    assert.equal(await blocked.page.locator('html').getAttribute('data-lcd-palette'), 'gray');
    await blocked.context.close();
  }
  assert.ok(baselineComposeErrors.length <= 2, 'unexpected additional baseline composer errors');
  console.log(`PASS palette recovery with throwing storage; ${baselineComposeErrors.length} separately recorded baseline composer errors`);

  const paint = await savedContext('purple');
  await paint.context.addInitScript(() => {
    window.paletteAtPaint = [];
    new PerformanceObserver(list => {
      for (const entry of list.getEntries()) window.paletteAtPaint.push({ name: entry.name, palette: document.documentElement.dataset.lcdPalette });
    }).observe({ type: 'paint', buffered: true });
  });
  await paint.page.goto(base + '/');
  await paint.page.waitForFunction(() => window.paletteAtPaint.length > 0);
  assert.ok((await paint.page.evaluate(() => window.paletteAtPaint)).every(entry => entry.palette === 'purple'));
  await paint.context.close();
  console.log('PASS saved palette at first paint');

  // Prevent idle warmup so every network case tests an actual selection request.
  const recovery = await browser.newContext({ serviceWorkers: 'block' });
  await recovery.addInitScript(() => { window.requestIdleCallback = () => 0; });
  const recoverPage = await recovery.newPage(); recoverPage.setDefaultTimeout(6000);
  recoverPage.on('pageerror', error => errors.push(error.stack || error.message));
  await recoverPage.goto(base + '/'); await theme(recoverPage, 'light');
  const lcdCss = '**/theme-lcd*.css';
  await recoverPage.route(lcdCss, route => route.abort());
  await recoverPage.locator('[data-action="theme-toggle"]').click(); await recoverPage.locator(themeOption('lcd')).click();
  await recoverPage.waitForFunction(() => !document.documentElement.hasAttribute('data-theme-loading'));
  assert.equal(await recoverPage.locator('html').getAttribute('data-theme'), 'light', 'failed stylesheet retains usable theme');
  await recoverPage.unroute(lcdCss);
  await theme(recoverPage, 'lcd'); await palette(recoverPage, 'purple');
  console.log('PASS failed stylesheet retains theme and allows retry');
  await recovery.close();
  const delayed = await browser.newContext({ serviceWorkers: 'block' });
  await delayed.addInitScript(({ paletteKey }) => {
    window.requestIdleCallback = () => 0;
    localStorage.setItem(paletteKey, 'purple');
  }, { paletteKey });
  const delayedPage = await delayed.newPage(); delayedPage.setDefaultTimeout(6000);
  delayedPage.on('pageerror', error => errors.push(error.stack || error.message));
  await delayedPage.goto(base + '/'); await theme(delayedPage, 'light');
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  await delayedPage.route(lcdCss, async route => { await gate; await route.continue(); });
  await delayedPage.locator('[data-action="theme-toggle"]').click(); await delayedPage.locator(themeOption('lcd')).click();
  await delayedPage.waitForFunction(() => document.documentElement.hasAttribute('data-theme-loading'));
  assert.equal(await delayedPage.locator('html').getAttribute('data-theme'), 'light');
  await theme(delayedPage, 'dark');
  release();
  await delayedPage.waitForFunction(() => Array.from(document.querySelectorAll('link[data-theme-name="lcd"]')).some(link => link.sheet));
  assert.equal(await delayedPage.locator('html').getAttribute('data-theme'), 'dark', 'late LCD load must not override newer choice');
  await theme(delayedPage, 'lcd');
  assert.equal(await delayedPage.locator('html').getAttribute('data-lcd-palette'), 'purple');
  await delayed.close();
  console.log('PASS delayed stylesheet, rapid switching, and saved palette after load');

  const replaced = await browser.newContext({ serviceWorkers: 'block' });
  await replaced.addInitScript(({ paletteKey }) => { window.requestIdleCallback = () => 0; localStorage.setItem(paletteKey, 'purple'); }, { paletteKey });
  const replacedPage = await replaced.newPage(); replacedPage.setDefaultTimeout(6000);
  replacedPage.on('pageerror', error => errors.push(error.stack || error.message));
  await replacedPage.goto(base + '/'); await theme(replacedPage, 'console');
  let finishRotation;
  const rotationGate = new Promise(resolve => { finishRotation = resolve; });
  await replacedPage.route(lcdCss, async route => { await rotationGate; await route.continue(); });
  await replacedPage.locator('[data-action="theme-cycle"]').click();
  await replacedPage.waitForFunction(() => document.documentElement.hasAttribute('data-theme-loading'));
  assert.equal(await replacedPage.locator('html').getAttribute('data-lcd-palette'), 'purple', 'pending rotation does not change saved palette');
  await replacedPage.locator('[data-action="theme-toggle"]').click();
  await replacedPage.locator(themeOption('lcd')).click();
  finishRotation();
  await replacedPage.waitForFunction(() => document.documentElement.dataset.theme === 'lcd');
  assert.equal(await replacedPage.locator('html').getAttribute('data-lcd-palette'), 'purple', 'later dropdown selection cancels pending Green rotation');
  await replaced.close();
  console.log('PASS dropdown supersedes pending LCD rotation without resetting its saved palette');

  const initialFailure = await savedContext('gray');
  await initialFailure.context.addInitScript(() => { window.requestIdleCallback = () => 0; });
  await initialFailure.page.route(lcdCss, route => route.abort());
  await initialFailure.page.goto(base + '/');
  await theme(initialFailure.page, 'light');
  await initialFailure.page.unroute(lcdCss);
  await theme(initialFailure.page, 'lcd');
  await palette(initialFailure.page, 'purple');
  await initialFailure.context.close();
  console.log('PASS initial stylesheet failure, choosing another theme, and retry');
  assert.deepEqual(errors, []);
  console.log(`PASS LCD palette browser checks; screenshots: ${root}`);
} catch (error) { console.error(log.slice(-3000)); throw error; }
finally { if (browser) await browser.close(); server.kill(); }
