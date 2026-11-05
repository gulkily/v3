// Screenshot the ZenMemes app layout without a server, for navigation work.
//   node tests/Support/nav_shot.mjs out.png [theme=light] [width=375] [section=board] [options]
// Options (any order): member (browser remembered as approved), more (open the More/You panel),
//   slots=recent,profiles (seed saved phone slots), unread=3 (set the unread count), events=0, messages=0.
// Prints the visible tab labels and widths. Pages come from TemplateRenderer via app_layout_fixture.php;
// assets are served from public/. Needs CHROMIUM_PATH or Playwright's own Chromium.
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const [out, theme = 'light', width = '375', section = 'board', ...rest] = process.argv.slice(2);
if (!out) { console.error('usage: node tests/Support/nav_shot.mjs out.png [theme] [width] [section] [member] [more] [slots=a,b] [unread=N] [events=0] [messages=0]'); process.exit(2); }
const option = name => rest.find(item => item === name || item.startsWith(name + '='))?.split('=')[1] ?? (rest.includes(name) ? '1' : '');
const project = resolve(new URL('../..', import.meta.url).pathname);
const html = execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, section, option('events') === '0' ? '0' : '1', option('messages') === '0' ? '0' : '1'], { encoding: 'utf8', maxBuffer: 1 << 26 });

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: Number(width), height: 760 }, deviceScaleFactor: 1 });
await page.route('http://nav.test/**', async route => {
  const url = new URL(route.request().url());
  if (url.pathname.startsWith('/assets/')) {
    const file = url.pathname.replace(/\.[0-9a-f]{12}\./, '.');
    try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(`${project}/public${file}`, 'utf8') }); }
    catch { return route.fulfill({ status: 404, body: '' }); }
  }
  if (url.pathname.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
  return route.fulfill({ contentType: 'text/html', body: html });
});
await page.addInitScript(({ theme, member, slots }) => {
  localStorage.setItem('forum-zenmemes-theme', theme);
  if (slots) localStorage.setItem('forum_nav_slots', JSON.stringify({ slots: slots.split(',') }));
  if (member) {
    localStorage.setItem('forum_pki_fingerprint', 'a'.repeat(40));
    localStorage.setItem('forum_pki_approved_fingerprint', 'a'.repeat(40));
    localStorage.setItem('forum_pki_public_key', 'k');
    localStorage.setItem('forum_pki_private_key', 'k');
  }
}, { theme, member: option('member') === '1', slots: option('slots') });
await page.goto('http://nav.test/', { waitUntil: 'load' });
if (option('unread')) await page.evaluate(n => document.querySelectorAll('[data-private-message-unread], [data-private-message-unread-mirror]').forEach(e => { e.dataset.unreadCount = n; }), option('unread'));
if (option('more') === '1') await page.evaluate(() => { document.querySelector('.account-menu').open = true; });
await page.waitForTimeout(150);
await page.screenshot({ path: out });
const tabs = await page.evaluate(() => Array.from(document.querySelectorAll('.site-header--app .site-header-actions > .nav > .nav-link, .site-header--app .site-header-actions > .new-post-button, .site-header--app .site-header-actions > .account-menu > .account-menu__trigger'))
  .filter(node => getComputedStyle(node).display !== 'none')
  .sort((a, b) => a.getBoundingClientRect().x - b.getBoundingClientRect().x)
  .map(node => {
    const label = Array.from(node.querySelectorAll('.account-menu__label, .new-post-button__label')).find(span => getComputedStyle(span).display !== 'none') || node;
    return `${label.textContent.trim().replace(/\s+/g, ' ')}:${Math.round(node.getBoundingClientRect().width)}`;
  }));
console.log(`${theme} ${width}px ${section}: ${tabs.join(' | ')}`);
await browser.close();
