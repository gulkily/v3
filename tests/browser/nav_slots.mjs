// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/nav_slots.mjs
// Personal phone tabs (adaptive_nav): a More destination earns a slot after three
// uses, keeps it against lighter use, yields to a clear leader used on two days,
//  Pages are rendered by TemplateRenderer and served from memory.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const pages = {};
const render = section => pages[section] ??= execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, section], { encoding: 'utf8', maxBuffer: 1 << 26 });
const paths = { '/': 'board', '/recent/': 'recent', '/users/': 'profiles', '/search': 'search', '/about/': 'about', '/tools/': 'tools', '/messages': 'messages', '/calendar': 'calendar' };

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
await context.clock.install({ time: new Date('2026-11-05T12:00:00Z') });
await context.route('http://slots.test/**', async route => {
  const url = new URL(route.request().url());
  if (url.pathname.startsWith('/assets/')) {
    const file = url.pathname.replace(/\.[0-9a-f]{12}\./, '.');
    try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(`${project}/public${file}`, 'utf8') }); }
    catch { return route.fulfill({ status: 404, body: '' }); }
  }
  if (url.pathname.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
  const section = paths[url.pathname];
  return section ? route.fulfill({ contentType: 'text/html', body: render(section) }) : route.fulfill({ status: 404, body: '' });
});
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const base = 'http://slots.test';

const slots = () => page.evaluate(() => document.documentElement.getAttribute('data-nav-slots'));
const tabs = () => page.evaluate(() => Array.from(document.querySelectorAll('.site-header--app .site-header-actions > .nav > .nav-link, .site-header--app .site-header-actions > .new-post-button, .site-header--app .site-header-actions > .account-menu > .account-menu__trigger'))
  .filter(node => getComputedStyle(node).display !== 'none').sort((a, b) => a.getBoundingClientRect().x - b.getBoundingClientRect().x).map(node => node.getAttribute('data-tab') || node.className.split(' ')[0]));
async function useMore(section) {
  await page.locator('.account-menu__trigger').click();
  await page.locator(`.account-menu__more a[data-more="${section}"]`).click();
  await page.waitForLoadState('load');
}

try {
  await page.goto(base + '/');
  assert.equal(await slots(), null, 'fresh browser: no slots');
  assert.deepEqual(await tabs(), ['board', 'calendar', 'compose', 'account-menu__trigger'], 'fresh browser: four fixed tabs');

  // Three uses fill the free slot; the tab is there from the next page load.
  await useMore('recent'); await useMore('recent');
  assert.equal(await slots(), null, 'two uses: not yet');
  await useMore('recent');
  await page.goto(base + '/about/');
  assert.equal(await slots(), 'recent', 'three uses: Recent earned the slot');
  assert.deepEqual(await tabs(), ['board', 'calendar', 'recent', 'compose', 'account-menu__trigger'], 'slot sits after the fixed tabs');

  // Lighter use elsewhere does not move it: twenty visits to other destinations in one day.
  for (let i = 0; i < 20; i++) await useMore(['profiles', 'search', 'about', 'tools'][i % 4]);
  assert.equal(await slots(), 'recent', 'twenty other visits in one day: Recent keeps the slot');

  // A clear leader used on two days takes it: Users reaches 6 (Recent has 3) on day two.
  await context.clock.setFixedTime(new Date('2026-11-06T12:00:00Z'));
  await useMore('profiles');
  await page.goto(base + '/');
  assert.equal(await slots(), 'profiles', 'leader on a second day: Users takes the slot');

  // Messages never fills a slot for a browser that is not remembered as approved.
  await page.evaluate(() => localStorage.setItem('forum_nav_slots', JSON.stringify({ slots: ['messages'] })));
  await page.goto(base + '/');
  assert.equal(await slots(), null, 'guest: Messages is never a slot');

  // A feature-flag refresh swaps the Messages link in or out and announces itself;
  // slots re-place against the live nav (replayed here with the same event and markup).
  await page.evaluate(() => {
    ['forum_pki_fingerprint', 'forum_pki_approved_fingerprint'].forEach(key => localStorage.setItem(key, 'a'.repeat(40)));
    ['forum_pki_public_key', 'forum_pki_private_key'].forEach(key => localStorage.setItem(key, 'k'));
    localStorage.setItem('forum_nav_slots', JSON.stringify({ slots: ['messages'] }));
  });
  await page.goto(base + '/');
  assert.equal(await slots(), 'messages', 'member: saved Messages is the slot');
  await page.evaluate(() => {
    const nav = document.querySelector('nav.nav');
    const old = nav.querySelector('[data-private-message-unread]');
    const fresh = old.cloneNode(true);
    old.remove();
    nav.insertBefore(fresh, nav.querySelector('[data-tab="search"]'));
    document.dispatchEvent(new CustomEvent('forum:nav-refreshed'));
  });
  assert.equal(await slots(), 'messages', 'refresh: Messages keeps its slot');
  assert.equal(await page.locator('nav.nav > [data-tab="messages"]').isVisible(), true, 'refresh: Messages tab visible');
  await page.evaluate(() => { document.querySelector('nav.nav [data-tab="messages"]').remove(); document.dispatchEvent(new CustomEvent('forum:nav-refreshed')); });
  assert.equal(await slots(), null, 'messaging off: the slot is released');
  await page.evaluate(() => ['forum_pki_fingerprint', 'forum_pki_approved_fingerprint', 'forum_pki_public_key', 'forum_pki_private_key'].forEach(key => localStorage.removeItem(key)));

  // Storage holds a flat JSON object a person can read in a local-storage editor.
  await page.goto(base + '/');
  const state = await page.evaluate(() => JSON.parse(localStorage.getItem('forum_nav_slots')));
  assert.deepEqual(Object.keys(state).sort(), ['slots']);
  assert.deepEqual(errors, [], 'no page errors');
  console.log('Phone slots: fill after three uses, hold against lighter use, yield to a two-day leader.');
} finally {
  await browser.close();
}
