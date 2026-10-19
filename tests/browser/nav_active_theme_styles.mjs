// Self-contained: a theme's selected-nav style must not depend on whether its sheet loads before or after site.css.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, readdir } from 'node:fs/promises';

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const themes = (await readdir('public/assets'))
  .map(name => name.match(/^theme-([a-z0-9]+)\.css$/)?.[1])
  .filter(name => name && name !== 'nav');
await page.route('http://nav.test/**', async route => {
  const url = new URL(route.request().url());
  if (url.pathname.startsWith('/assets/')) return route.fulfill({ contentType: 'text/css', body: await readFile('public' + url.pathname, 'utf8') });
  const theme = url.searchParams.get('theme');
  const themeLink = `<link rel="stylesheet" href="/assets/theme-${theme}.css">`;
  const shared = '<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/theme-nav-icons.css">';
  // Production (templates/layout.php) loads the theme sheet first; "theme-last" is the order a runtime theme switch produces.
  const links = url.searchParams.get('order') === 'production' ? themeLink + shared : shared + themeLink;
  return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html data-theme="${theme}"><head>${links}</head><body><nav class="nav"><a class="nav-link is-active" id="a" href="/">Board</a><a class="nav-link" href="/tags/">Tags</a></nav></body></html>` });
});
const activeStyle = () => page.evaluate(() => {
  const s = getComputedStyle(document.getElementById('a'));
  return { color: s.color, background: s.backgroundColor, border: [s.borderTopColor, s.borderRightColor, s.borderBottomColor, s.borderLeftColor].join(' '), boxShadow: s.boxShadow, fontWeight: s.fontWeight };
});
const styles = {};
for (const theme of themes) {
  const byOrder = {};
  for (const order of ['production', 'theme-last']) {
    await page.goto(`http://nav.test/?theme=${theme}&order=${order}`, { waitUntil: 'load' });
    byOrder[order] = await activeStyle();
  }
  assert.deepEqual(byOrder.production, byOrder['theme-last'], `${theme}: selected nav must match regardless of stylesheet order`);
  styles[theme] = byOrder.production;
}
assert.equal(styles.word97.background, 'rgb(223, 223, 223)', 'word97: selected nav is light gray');
assert.equal(styles.word97.border, 'rgb(128, 128, 128) rgb(255, 255, 255) rgb(255, 255, 255) rgb(128, 128, 128)', 'word97: selected nav is sunken');
assert.equal(styles.midnight.background, 'rgb(60, 60, 60)', 'midnight: selected nav is gray');
assert.equal(styles.midnight.border, 'rgb(0, 0, 0) rgb(106, 106, 106) rgb(106, 106, 106) rgb(0, 0, 0)', 'midnight: selected nav is sunken');
console.log(`Selected nav style checked in both stylesheet orders in ${themes.length} themes.`);
await browser.close();
