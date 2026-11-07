// Run with: node tests/browser/gloss_palettes.mjs
// Gloss's colour picker: each palette applies, persists, passes contrast and
// turns the navigation icons; Engagify, which shares Gloss's stylesheet,
// stays violet; LCD keeps its own saved palette.
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'gloss-palettes-'));
const env = { ...process.env, BOOKMARKLET_TEST_ROOT: root, FORUM_SITE_ID: 'zenmemes',
  FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_STATIC_HTML_ROOT: join(root, 'static'),
  FORUM_APP_VERSION_NOTIFICATION: 'false', FAST_SCORING_ENABLED: 'false' };
const fixture = join(project, 'tests/Support/bookmarklet_browser.php');
const seed = spawnSync('php', [fixture], { cwd: project, env, encoding: 'utf8' });
assert.equal(seed.status, 0, seed.stderr);
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, fixture], { cwd: project, env });
let log = '';
server.stderr.on('data', data => { log += data; });

const themeKey = 'forum-zenmemes-theme';
const paletteKey = themeKey + '-gloss-palette';
const toggle = '[data-action="gloss-palette-toggle"]';
const option = value => `[data-gloss-palette-option="${value}"]`;
const palettes = { violet: 'Violet', blue: 'Blue', teal: 'Teal', rose: 'Rose' };
const errors = [];
let browser;

function luminance(rgb) {
  const channels = rgb.map(x => (x /= 255) <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4);
  return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
}
function contrast(pair) {
  const values = pair.map(luminance).sort((a, b) => a - b);
  return (values[1] + .05) / (values[0] + .05);
}
async function openThemeControls(page) {
  await page.evaluate(() => { const menu = document.querySelector('details[data-role="account-menu"]'); if (menu) menu.open = true; });
}
async function theme(page, value) {
  await openThemeControls(page);
  await page.evaluate(value => document.querySelector(`[data-theme-option="${value}"]`).click(), value);
  await page.waitForFunction(value => document.documentElement.dataset.theme === value, value);
}
async function palette(page, value) {
  await openThemeControls(page);
  await page.locator(toggle).click();
  await page.locator(option(value)).click();
}
const attr = page => page.locator('html').getAttribute('data-gloss-palette');

try {
  for (let i = 0; i < 100; i++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(done => setTimeout(done, 50)); }
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  page.setDefaultTimeout(6000);
  page.on('pageerror', error => errors.push(error.stack || error.message));
  await page.goto(base + '/');
  assert.equal(await attr(page), 'violet', 'first palette is the default');
  await theme(page, 'light');
  await openThemeControls(page);
  assert.equal(await page.locator(toggle).isVisible(), false, 'picker hidden outside Gloss');
  await theme(page, 'gloss');

  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 844 });
    for (const [value, label] of Object.entries(palettes)) {
      await palette(page, value);
      assert.equal(await attr(page), value);
      assert.equal(await page.locator(toggle).getAttribute('aria-label'), `Choose Gloss palette: ${label}`);
      assert.equal(await page.locator(option(value)).getAttribute('aria-checked'), 'true');
      await page.reload();
      assert.equal(await attr(page), value, 'palette survives reload');
      await page.goto(base + '/tools/');
      assert.equal(await attr(page), value, 'palette survives navigation');
      const probe = await page.evaluate(() => {
        const icon = document.querySelector('.tool-launcher-button');
        const sample = document.createElement('i'); document.body.append(sample);
        const pairs = [];
        const read = (fg, bg, target) => {
          sample.style.cssText = `color:var(${fg});background:var(${bg})`;
          const style = getComputedStyle(sample);
          pairs.push({ fg, bg, target, pair: [style.color, style.backgroundColor].map(c => c.match(/[\d.]+/g).slice(0, 3).map(Number)) });
        };
        for (const fg of ['--ink', '--ink-soft']) for (const bg of ['--page-bg', '--panel', '--input-bg', '--button-bg', '--button-alt-bg', '--active-bg']) read(fg, bg, 4.5);
        read('--active-ink', '--active-bg', 4.5);
        for (const bg of ['--page-bg', '--panel', '--button-bg']) read('--gloss-accent', bg, 4.5);
        for (const bg of ['--page-bg', '--panel', '--input-bg']) read('--line', bg, 3);
        read('--active-line', '--panel', 3);
        sample.remove();
        return { pairs, iconFilter: icon ? getComputedStyle(icon, '::before').filter : 'missing' };
      });
      for (const pair of probe.pairs) assert.ok(contrast(pair.pair) >= pair.target, `${value}: ${pair.fg} on ${pair.bg} ${contrast(pair.pair).toFixed(2)}`);
      assert.equal(probe.iconFilter === 'none', value === 'violet', `${value}: icon filter ${probe.iconFilter}`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      await openThemeControls(page);
      await page.locator(toggle).click();
      await page.screenshot({ path: join(root, `gloss-${value}-${width}.png`), fullPage: true });
      await page.keyboard.press('Escape');
      assert.equal(await page.locator(toggle).evaluate(e => e === document.activeElement), true, 'Escape returns focus');
    }
  }

  // Keyboard: open, move, choose.
  await openThemeControls(page);
  await page.locator(toggle).focus(); await page.keyboard.press('Enter');
  await page.keyboard.press('Home'); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter');
  assert.equal(await attr(page), 'blue');

  // Engagify shares the stylesheet but keeps violet, whatever Gloss uses.
  await theme(page, 'engagify');
  await openThemeControls(page);
  assert.equal(await page.locator(toggle).isVisible(), false, 'no Gloss picker in Engagify');
  assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--gloss-accent').trim()), '#6d28d9');
  assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.nav-link'), '::before').filter), 'none');

  // LCD keeps its own palette; Gloss's choice is independent.
  await theme(page, 'lcd');
  const lcd = await page.locator('html').getAttribute('data-lcd-palette');
  await openThemeControls(page);
  assert.equal(await page.locator(toggle).isVisible(), false);
  assert.equal(await page.locator('[data-action="lcd-palette-toggle"]').isVisible(), true);
  await theme(page, 'gloss');
  assert.equal(await attr(page), 'blue');
  assert.equal(await page.locator('html').getAttribute('data-lcd-palette'), lcd);

  // A bad saved value falls back to violet.
  await page.evaluate(key => localStorage.setItem(key, 'plaid'), paletteKey);
  await page.reload();
  assert.equal(await attr(page), 'violet');

  assert.deepEqual(errors, []);
  console.log('PASS Gloss palettes: picker, reload, navigation, contrast, icon hue, keyboard, Engagify unaffected, LCD independent, bad value');
} catch (error) {
  console.error(log);
  throw error;
} finally {
  await browser?.close();
  server.kill();
}
