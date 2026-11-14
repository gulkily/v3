// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/nav_slots.mjs
// Phone tabs (adaptive_nav): every tab is earned. Board and Calendar are where a
// fresh browser starts; a destination fills a free slot after three uses, tabs hold
// against lighter use, and the least-used tab yields to a clear leader used on two days,
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
  assert.equal(await slots(), 'board calendar', 'fresh browser: the server\'s starting tabs');
  assert.deepEqual(await tabs(), ['board', 'calendar', 'compose', 'account-menu__trigger'], 'fresh browser: Board and Calendar to start');

  // Three uses fill the free slot; the tab is there from the next page load, in bar order.
  await useMore('recent'); await useMore('recent');
  assert.equal(await slots(), 'board calendar', 'two uses: not yet');
  await useMore('recent');
  await page.goto(base + '/about/');
  assert.equal(await slots(), 'board calendar recent', 'three uses: Recent earned the free slot');
  assert.deepEqual(await tabs(), ['board', 'recent', 'calendar', 'compose', 'account-menu__trigger'], 'tabs follow bar order');

  // Lighter use elsewhere does not move anything: twenty visits to other destinations in one day.
  for (let i = 0; i < 20; i++) await useMore(['profiles', 'search', 'about', 'tools'][i % 4]);
  assert.equal(await slots(), 'board calendar recent', 'twenty other visits in one day: the tabs hold');

  // A clear leader used on two days takes the least-used tab: Users reaches 6 on day two,
  // and Calendar (never used, earned after Board) gives way.
  await context.clock.setFixedTime(new Date('2026-11-06T12:00:00Z'));
  await useMore('profiles');
  await page.goto(base + '/');
  assert.equal(await slots(), 'board profiles recent', 'leader on a second day: Users takes Calendar\'s slot');

  // Board is no different: Tools reaches 6 on day two and takes the unused Board tab.
  await useMore('tools');
  await page.goto(base + '/about/');
  assert.equal(await slots(), 'tools profiles recent', 'Board yields to a leader too');
  assert.deepEqual(await tabs(), ['recent', 'profiles', 'tools', 'compose', 'account-menu__trigger'], 'Board left the bar');
  await useMore('board');
  assert.equal(await page.locator('.account-menu__trigger .account-menu__label--current').innerText(), 'Board', 'on Board, the More tab names it');

  // State saved while Board and Calendar were fixed keeps them, in front, as used as the busiest destination.
  await page.evaluate(() => localStorage.setItem('forum_nav_slots', JSON.stringify({ slots: ['recent'], visits: { recent: { count: 4, first: '2026-11-01', last: '2026-11-05' } } })));
  await page.goto(base + '/');
  assert.equal(await slots(), 'board calendar recent', 'old state: Board and Calendar kept');
  await useMore('about');
  const moved = await page.evaluate(() => JSON.parse(localStorage.getItem('forum_nav_slots')));
  assert.equal(moved.v, 2, 'old state: rewritten in the new form');
  assert.deepEqual(moved.slots, ['board', 'calendar', 'recent'], 'old state: no tab moved');
  assert.equal(moved.visits.board.count, 4, 'old state: Board starts as used as Recent');

  // Messages never fills a slot for a browser that is not remembered as approved.
  await page.evaluate(() => localStorage.setItem('forum_nav_slots', JSON.stringify({ v: 2, slots: ['messages'] })));
  await page.goto(base + '/');
  assert.equal(await slots(), null, 'guest: Messages is never a slot');

  // A feature-flag refresh swaps the Messages link in or out and announces itself;
  // slots re-place against the live nav (replayed here with the same event and markup).
  await page.evaluate(() => {
    ['forum_pki_fingerprint', 'forum_pki_approved_fingerprint'].forEach(key => localStorage.setItem(key, 'a'.repeat(40)));
    ['forum_pki_public_key', 'forum_pki_private_key'].forEach(key => localStorage.setItem(key, 'k'));
    localStorage.setItem('forum_nav_slots', JSON.stringify({ v: 2, slots: ['messages'] }));
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
  assert.deepEqual(Object.keys(state).sort(), ['slots', 'v']);
  assert.deepEqual(errors, [], 'no page errors');
  console.log('Phone tabs: start at Board and Calendar, fill after three uses, hold against lighter use, least-used yields to a two-day leader.');
} finally {
  await browser.close();
}
