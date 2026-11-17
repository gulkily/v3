// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/usage_log.mjs
// Usage log (local_telemetry): nothing is recorded until the visitor turns it on;
// then page views, navbar and menu clicks, unhooked links (by label) and form
// submits are recorded with region and destination page kind, typed text never,
// and the log keeps at most 2,000 entries from the last 30 days. The browser's
// page kinds match UsagePageKind on the server.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const env = { ...process.env, FORUM_USAGE_REPORTS_ENABLED: '1' };
const pages = {};
const renderApp = path => pages[path] ??= execFileSync('php', [`${project}/tests/Support/usage_app_page_fixture.php`, path], { encoding: 'utf8', maxBuffer: 1 << 26 });
const render = section => pages[section] ??= execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, section], { encoding: 'utf8', env, maxBuffer: 1 << 26 });
const paths = { '/': 'board', '/recent/': 'recent', '/users/': 'profiles', '/about/': 'about', '/tools/': 'tools', '/calendar': 'calendar' };

const uploads = [];
const uploadReply = [];
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
await context.route('http://usage.test/**', async route => {
  const url = new URL(route.request().url());
  if (url.pathname.startsWith('/assets/')) {
    const file = url.pathname.replace(/\.[0-9a-f]{12}\./, '.');
    try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(`${project}/public${file}`, 'utf8') }); }
    catch { return route.fulfill({ status: 404, body: '' }); }
  }
  if (url.pathname.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
  if (url.pathname === '/tools/usage-log/') return route.fulfill({ contentType: 'text/html', body: renderApp(url.pathname) });
  if (url.pathname === '/tools/usage-log/upload') {
    uploads.push(route.request().postData());
    return route.fulfill(uploadReply.shift() || { status: 201, contentType: 'application/json', body: JSON.stringify({ status: 'stored', id: uploads.length, username: null }) });
  }
  const section = paths[url.pathname];
  return route.fulfill(section ? { contentType: 'text/html', body: render(section) } : { contentType: 'text/html', body: render('about') });
});
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const base = 'http://usage.test';
const log = () => page.evaluate(() => window.forumUsageLog.text());
const lines = async () => (await log()).trim().split('\n').slice(1).map(line => line.split('\t'));

try {
  // Off by default: browsing records nothing.
  await page.goto(base + '/');
  assert.equal(await page.evaluate(() => window.forumUsageLog.available()), true, 'storage available');
  assert.deepEqual(await page.evaluate(() => window.forumUsageLog.state()), { recording: false, count: 0 }, 'fresh browser: off and empty');
  await page.locator('.site-header a[data-tab="recent"]').first().click();
  await page.waitForLoadState('load');
  assert.equal(await log(), 'usage-log v1\n', 'off: nothing recorded');

  // On: page views and named navbar clicks with destination page kinds.
  await page.evaluate(() => window.forumUsageLog.setRecording(true));
  await page.goto(base + '/');
  await page.locator('.site-header a[data-tab="about"]').first().click();
  await page.waitForLoadState('load');
  let rows = await lines();
  assert.deepEqual(rows[0], ['view', '0', '/'], 'view of the board');
  assert.deepEqual(rows[1].slice(0, 2), ['click', rows[1][1]]);
  assert.deepEqual([rows[1][2], rows[1][3], rows[1][4], rows[1][5]], ['/', 'navbar', 'tab:about', '/about'], 'navbar click');
  assert.deepEqual([rows[2][0], rows[2][2]], ['view', '/about'], 'view after the click');

  // Menu toggle, an unhooked link inside a post (named by its text), and a composer submit.
  await page.locator('.account-menu__trigger').first().click();
  await page.evaluate(() => {
    const main = document.querySelector('main');
    main.insertAdjacentHTML('beforeend', '<article class="post-card"><a id="u-link" href="/threads/T9f3a1c/?ref=x">Where do   we keep the keys?</a></article>'
      + '<form id="u-form" action="/api/create_reply" method="post"><textarea name="body"></textarea><input name="title"><button type="submit">Post reply</button></form>');
    document.getElementById('u-form').addEventListener('submit', event => event.preventDefault());
  });
  await page.locator('#u-form textarea').fill('secret typed words');
  await page.locator('#u-form input[name="title"]').fill('hidden title');
  await page.locator('#u-form button').click();
  await page.locator('#u-link').click();
  await page.waitForLoadState('load');
  const text = await log();
  rows = await lines();
  assert.ok(rows.some(row => row[0] === 'click' && row[3] === 'menu'), 'menu toggle recorded in the menu region');
  assert.ok(rows.some(row => row[0] === 'submit' && row[3] === 'composer' && row[4] === 'Post reply'), 'composer submit recorded');
  assert.ok(rows.some(row => row[0] === 'click' && row[3] === 'post' && row[4] === 'Where do we keep the keys?' && row[5] === '/threads/*'), 'unhooked link named by its label, destination redacted');
  assert.ok(!text.includes('secret typed') && !text.includes('hidden title') && !text.includes('T9f3a1c') && !text.includes('ref=x'), 'no typed text, IDs or queries');

  // Caps: entries older than 30 days go, and at most 2,000 stay.
  const now = Math.floor(Date.now() / 1000);
  await page.evaluate(now => {
    const entries = [{ t: now - 31 * 86400, type: 'view', page: '/old' }];
    for (let i = 0; i < 2100; i++) entries.push({ t: now - 2100 + i, type: 'view', page: '/' });
    localStorage.setItem('forum_usage_log', JSON.stringify({ recording: true, entries }));
  }, now);
  const capped = await log();
  assert.equal(capped.trim().split('\n').length - 1, 2000, 'capped at 2,000 entries');
  assert.ok(!capped.includes('/old'), 'entries older than 30 days dropped');

  // Clear and stop.
  await page.evaluate(() => { window.forumUsageLog.clear(); window.forumUsageLog.setRecording(false); });
  await page.goto(base + '/recent/');
  assert.deepEqual(await page.evaluate(() => window.forumUsageLog.state()), { recording: false, count: 0 }, 'cleared and stopped');

  // Tools → Usage log: the switch turns recording on, the page shows the log,
  // records nothing about itself, and Copy and Clear work.
  await page.goto(base + '/tools/usage-log/');
  const status = () => page.locator('[data-role="usage-log-status"]').textContent();
  const cardState = () => page.locator('[data-role="usage-log-status-card"]').getAttribute('data-state');
  const cardText = async () => (await page.locator('[data-role="usage-log-status-card"]').textContent()).replace(/\s+/g, ' ').trim();
  assert.equal(await cardState(), 'off', 'log page: off');
  assert.match(await cardText(), /Not recording 0 steps saved in this browser/, 'log page: off and empty');
  assert.equal(await page.locator('[data-role="usage-log-empty"]').isVisible(), true, 'empty log explained');
  await page.locator('[data-role="usage-log-recording"]').check();
  assert.equal(await cardState(), 'on', 'log page: switch turns recording on');
  assert.match(await cardText(), /^Recording 0 steps/);
  await page.goto(base + '/');
  await page.locator('.site-header a[data-tab="about"]').first().click();
  await page.waitForLoadState('load');
  await page.goto(base + '/tools/usage-log/');
  const shown = await page.locator('[data-role="usage-log-text"]').inputValue();
  assert.ok(shown.startsWith('usage-log v1\nview\t0\t/\n'), 'log page shows the log text');
  assert.ok(!shown.includes('/tools/usage-log'), 'log page records nothing about itself');
  // The latest steps in words, from the same entries as the exact text.
  const steps = await page.locator('.usage-log-steps__step').allTextContents();
  assert.equal(steps.length, shown.trim().split('\n').length - 1, 'one readable step per line of exact text');
  assert.match(steps[0], /ago\s*Opened Board$/, 'view in words');
  assert.match(steps[1], /Clicked About in the navbar$/, 'navbar click in words');
  assert.match(steps[2], /Opened About$/);
  assert.equal(await page.locator('.usage-log-exact').evaluate(details => details.open), false, 'exact text closed by default');
  assert.equal(await page.locator('[data-action="usage-log-upload"]').textContent(), 'Upload 3 steps', 'Upload names the step count');
  await page.evaluate(() => { Object.defineProperty(navigator, 'clipboard', { value: { writeText: async text => { window.__copied = text; } } }); });
  await page.locator('[data-action="usage-log-copy"]').click();
  assert.equal(await page.evaluate(() => window.__copied), shown, 'Copy puts the exact log on the clipboard');
  await page.locator('[data-action="usage-log-upload"]').click();
  await page.waitForFunction(() => /Uploaded/.test(document.querySelector('[data-role="usage-log-status"]').textContent));
  assert.ok(!uploads.at(-1).includes('\n'), 'Upload body has no raw line breaks (hosts may refuse them)');
  assert.equal(JSON.parse(uploads.at(-1)).log, shown, 'Upload sends exactly the reviewed text');
  assert.match(await status(), /Uploaded anonymously\. Your log is still here/, 'anonymous upload confirmed');
  assert.equal(await page.locator('[data-role="usage-log-text"]').inputValue(), shown, 'the log stays after upload');
  uploadReply.push({ status: 429, headers: { 'Retry-After': '1200' }, contentType: 'application/json', body: JSON.stringify({ status: 'error', code: 'rate_limited', error: 'Too many uploads from here in the last hour. Try again in 20 minutes.', retry_after: 1200 }) });
  await page.locator('[data-action="usage-log-upload"]').click();
  await page.waitForFunction(() => /Too many uploads/.test(document.querySelector('[data-role="usage-log-status"]').textContent));
  assert.equal(await page.locator('[data-role="usage-log-text"]').inputValue(), shown, 'the log stays after a refusal');
  uploadReply.push({ status: 403, contentType: 'text/html', body: '<h1>Approval required</h1>' });
  await page.locator('[data-action="usage-log-upload"]').click();
  await page.waitForFunction(() => /only from signed-in members/.test(document.querySelector('[data-role="usage-log-status"]').textContent));
  await page.locator('[data-action="usage-log-clear"]').click();
  assert.equal(await page.locator('[data-role="usage-log-text"]').inputValue(), 'usage-log v1\n', 'Clear empties the log');
  assert.match(await status(), /Cleared\. Recording is still on\./);
  assert.equal(await page.locator('.usage-log-steps__step').count(), 0, 'Clear empties the steps');
  await page.locator('[data-role="usage-log-recording"]').uncheck();
  assert.equal(await cardState(), 'off', 'log page: switch turns recording off');

  // Pauses of 20 s or more show as dividers; only the latest 10 steps show until "Show all".
  await page.evaluate(() => {
    const now = Math.floor(Date.now() / 1000);
    const entries = [];
    for (let i = 0; i < 12; i++) entries.push({ t: now - 400 + i * 5, type: 'click', page: '/threads/*', region: 'menu', name: 'tab:account', to: '-' });
    entries.push({ t: now - 100, type: 'submit', page: '/tools/feature-flags', region: 'main', name: 'Save' });
    entries.push({ t: now - 98, type: 'click', page: '/tools', region: 'tools-bar', name: 'tool:usage', to: '/tools/usage-log' });
    localStorage.setItem('forum_usage_log', JSON.stringify({ recording: false, entries }));
  });
  await page.reload();
  assert.equal(await page.locator('.usage-log-steps__step').count(), 10, 'latest 10 steps');
  assert.deepEqual(await page.locator('.usage-log-steps__pause').allTextContents(), ['paused 4 min'], 'pause divider (245 s)');
  assert.match((await page.locator('.usage-log-steps__step').allTextContents()).join('|'), /Clicked You menu in the menu\|.*Pressed Save on Feature Flags\|.*Clicked Usage Log in the Tools bar$/, 'names from the shared table');
  await page.locator('[data-action="usage-log-show-all"]').click();
  assert.equal(await page.locator('.usage-log-steps__step').count(), 14, 'Show all steps');
  await page.evaluate(() => window.forumUsageLog.clear());

  // Blocked storage: the page says recording is unavailable.
  const blocked = await browser.newContext({ serviceWorkers: 'block' });
  await blocked.addInitScript(() => { Object.defineProperty(window, 'localStorage', { get() { throw new Error('blocked'); } }); });
  const blockedPage = await blocked.newPage();
  await blocked.route('http://usage.test/**', async route => {
    const url = new URL(route.request().url());
    if (url.pathname.startsWith('/assets/')) return route.fulfill({ contentType: 'application/javascript', body: await readFile(`${project}/public${url.pathname.replace(/\.[0-9a-f]{12}\./, '.')}`, 'utf8').catch(() => '') });
    return route.fulfill({ contentType: 'text/html', body: renderApp('/tools/usage-log/') });
  });
  await blockedPage.goto(base + '/tools/usage-log/');
  assert.match(await blockedPage.locator('[data-role="usage-log-status-card"]').textContent(), /Recording is unavailable/, 'blocked storage explained');
  assert.equal(await blockedPage.locator('[data-role="usage-log-status-card"]').getAttribute('data-state'), 'unavailable');
  assert.equal(await blockedPage.locator('[data-role="usage-log-recording"]').isDisabled(), true, 'blocked storage: switch stays disabled');
  await blocked.close();

  // The browser's page kinds match the server's.
  const samples = ['/', '/threads/T1/', '/profiles/ilyag/approve/', '/tools/feature-flags/changes/C1/', '/calendar/rapclub/2026-11-14/', '/docs/reference/agent_api.md', '/tags/news?x=1'];
  const server = JSON.parse(execFileSync('php', ['-r', `require '${project}/autoload.php'; echo json_encode(array_map([ForumRewrite\\UsageReports\\UsagePageKind::class, 'fromPath'], json_decode($argv[1], true)));`, JSON.stringify(samples)], { encoding: 'utf8' }));
  const client = await page.evaluate(samples => samples.map(path => window.forumUsageLog.kind(path)), samples);
  assert.deepEqual(client, server, 'browser and server page kinds agree');

  assert.deepEqual(errors, [], 'no page errors');
  console.log('Usage log: off by default; records views, navbar, menu, labeled links and submits; no typed text or IDs; capped; log page switch, copy, upload and refusals, clear, quiet, blocked storage; matches server page kinds.');
} finally {
  await browser.close();
}
