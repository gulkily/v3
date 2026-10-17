import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, readdir } from 'node:fs/promises';

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const themes = (await readdir('public/assets'))
  .map(name => name.match(/^theme-([a-z0-9]+)\.css$/)?.[1])
  .filter(name => name && name !== 'nav');
await page.route('http://nav.test/**', async route => {
  const path = new URL(route.request().url()).pathname;
  if (path.startsWith('/assets/')) return route.fulfill({ contentType: 'text/css', body: await readFile('public' + path, 'utf8') });
  return route.fulfill({ contentType: 'text/html', body: '<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/theme-nav-icons.css"><link id="theme" rel="stylesheet"><nav class="nav"><a class="nav-link" href="/">Board</a><a class="nav-link" id="m" href="/messages" data-private-message-unread data-viewer="alice" aria-label="Messages" aria-describedby="s">Messages<span id="s" hidden>Unread status loading</span></a><a class="nav-link" id="i" href="/invites/">Invite</a></nav>' });
});
await page.goto('http://nav.test/');
const boxes = () => page.evaluate(() => ['m', 'i'].map(id => { const r = document.getElementById(id).getBoundingClientRect(); return [r.x, r.y, r.width, r.height]; }));
for (const theme of themes) {
  await page.evaluate(theme => { document.documentElement.dataset.theme = theme; document.getElementById('theme').href = `/assets/theme-${theme}.css`; }, theme);
  await page.waitForLoadState('load');
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => document.getElementById('m').removeAttribute('data-unread-count'));
  const base = await boxes();
  const outline = () => page.evaluate(() => { const s = getComputedStyle(document.getElementById('m')); return [s.outlineStyle, s.outlineWidth, s.outlineColor, s.outlineOffset].join(' '); });
  const resting = await outline();
  for (const value of ['0', '3']) {
    await page.evaluate(v => document.getElementById('m').setAttribute('data-unread-count', v), value);
    assert.deepEqual(await boxes(), base, `${theme}: unread ${value} must not move or resize Messages/Invite`);
    if (value === '3') {
      const unread = await outline();
      assert.notEqual(unread, resting, `${theme}: unread outline must differ from the resting style`);
      assert.ok(!unread.startsWith('none'), `${theme}: unread outline must be visible`);
    } else {
      assert.equal(await outline(), resting, `${theme}: no highlight at zero unread`);
    }
  }
  assert.equal(await page.locator('#m').innerText(), 'Messages');
}
console.log(`Unread outline checked without layout shift in ${themes.length} themes.`);
await browser.close();
