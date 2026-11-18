// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/open_beside_offline.mjs [screenshot-dir]
// Open beside works offline. With Beside on and room, a thread title on the
// offline board opens in the pane and the board stays; the pane's bar shows
// the thread's title; the offline mode bar is not repeated in the pane; a page
// with no offline copy shows a plain message; and a reply written in the pane
// is kept in the Outbox. With Beside off the title loads the thread as before.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
if (shots) mkdirSync(shots, { recursive: true });
const root = mkdtempSync(join(tmpdir(), 'open-beside-offline-'));
const repository = join(root, 'repository');
mkdirSync(join(root, 'static'));
cpSync(join(project, 'public/assets'), join(root, 'artifacts/assets'), { recursive: true });
cpSync(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
const git = (...args) => {
  const result = spawnSync('git', ['-C', repository, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', ...args], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
};
git('init'); git('add', '.'); git('commit', '-m', 'Initialize');

const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise((done) => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), join(project, 'public/router.php')], {
  cwd: project,
  env: { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_REPOSITORY_ROOT: repository, FORUM_DATABASE_PATH: join(root, 'read.sqlite3'),
    FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
    FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'false', FAST_SCORING_ENABLED: 'false',
    FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'),
    FORUM_READING_MARGINS_ENABLED: 'true', FORUM_OPEN_BESIDE_ENABLED: 'true' },
  stdio: 'ignore',
});
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

const until = async (check, label, ms = 30000) => {
  for (const started = Date.now(); Date.now() - started < ms;) {
    if (await check()) return;
    await new Promise((done) => setTimeout(done, 300));
  }
  assert.fail(`timed out: ${label}`);
};

try {
  const context = await browser.newContext({ viewport: { width: 2048, height: 1200 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise((done) => setTimeout(done, 100)); } }

  // Offline reading is set up by visiting the site once.
  await page.goto(base + '/offline/');
  await until(() => page.evaluate(async () => {
    for (const key of await caches.keys()) {
      const cache = await caches.open(key);
      if (await cache.match('/offline/reader/') && await cache.match('/offline/snapshot.sqlite3') && await cache.match('/compose/reply')) return true;
    }
    return false;
  }), 'offline pages cached');

  const toggle = page.locator('[data-open-beside-toggle]');
  const titles = page.locator('.main .thread-card h2 a');
  const offlineBoard = async () => {
    await page.goto(base + '/');
    await until(() => titles.count().then((n) => n > 0), 'the offline board');
    assert.equal(await page.locator('[data-offline-reader]').count(), 1, 'this is the offline reader');
  };
  // What the pane shows, read from the page that owns it.
  const pane = () => page.evaluate(() => {
    const frame = document.querySelector('.open-beside__frame');
    if (!frame) return null;
    const shown = { bar: document.querySelector('.open-beside__title').textContent, note: document.querySelector('.open-beside__note').hidden ? null : document.querySelector('.open-beside__note').textContent,
      frameShown: getComputedStyle(frame).display !== 'none' };
    try {
      const inner = frame.contentDocument;
      const visible = (selector) => { const node = inner.querySelector(selector); return !!node && getComputedStyle(node).display !== 'none' && !node.hidden; };
      return { ...shown, at: frame.contentWindow.location.pathname, framed: inner.documentElement.hasAttribute('data-open-beside-framed'),
        header: visible('.site-header'), offlineBar: visible('.offline-mode-bar'), text: inner.querySelector('.main') ? inner.querySelector('.main').innerText : '' };
    } catch (error) {
      return { ...shown, at: null };
    }
  });
  const paneFrame = () => page.frames().find((frame) => frame.name() === 'forum-open-beside');

  await context.setOffline(true);

  // Beside off: a thread title loads the thread as the whole page, as it always has.
  await offlineBoard();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
  const first = titles.first();
  const threadPath = await first.getAttribute('href');
  const threadTitle = (await first.textContent()).trim();
  await first.click();
  await page.waitForURL('**' + threadPath);
  assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'Beside off: no pane');

  // Beside on: the thread opens in the pane and the board stays.
  await offlineBoard();
  await toggle.click();
  assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
  assert.equal(await page.locator('.offline-mode-bar').first().isVisible(), true, 'the main page shows the offline mode bar');
  await titles.first().click();
  await until(async () => { const shown = await pane(); return shown && shown.at === threadPath && shown.text.includes(threadTitle); }, 'the thread in the pane');
  assert.equal(new URL(page.url()).pathname, '/', 'the offline board stayed');
  assert.ok(await titles.count() > 0, 'the board is still drawn');
  let shown = await pane();
  assert.equal(shown.framed, true);
  assert.equal(shown.header, false, 'no site header in the pane');
  assert.equal(shown.offlineBar, false, 'the offline mode bar is not repeated in the pane');
  await until(async () => (await pane()).bar.includes(threadTitle), 'the bar shows the thread\'s title');
  assert.equal((await pane()).note, null);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth), false, 'no sideways scrolling');
  if (shots) await page.screenshot({ path: join(shots, 'offline-pane-2048.png') });

  // Ctrl-click on a title is still left to the browser.
  const before = await pane();
  const opened = context.waitForEvent('page', { timeout: 4000 }).catch(() => null);
  await titles.last().click({ modifiers: ['Control'] });
  const tab = await opened; if (tab) await tab.close();
  assert.equal((await pane()).at, before.at, 'Ctrl-click does not change the pane');
  assert.equal(new URL(page.url()).pathname, '/', 'Ctrl-click does not change the board');

  // A reply written in the pane while offline is kept in the Outbox.
  const frame = paneFrame();
  const replyText = 'Written beside the board, offline';
  const composer = frame.locator('form[data-compose-form]').first();
  await composer.locator('textarea[name="body"]').click();
  await page.keyboard.type(replyText);
  await until(() => frame.evaluate(() => typeof (window.ForumBrowserSigning && window.ForumBrowserSigning.init) === 'function'), 'signing scripts in the pane');
  await composer.locator('button[type=submit]:not([data-action="submit-anonymous-compose"])').first().evaluate((button) => button.closest('form').requestSubmit(button));
  await until(async () => (await frame.locator('[data-role="compose-identity-status"]').allTextContents()).join(' ').includes('Saved on this device, not sent'), 'the reply kept as not sent');
  assert.equal(new URL(page.url()).pathname, '/', 'posting in the pane leaves the board alone');
  const outbox = await context.newPage();
  await outbox.goto(base + '/tools/outbox/');
  await until(() => outbox.locator('main').innerText().then((text) => /reply — queued/.test(text) && text.includes(threadPath.split('/').pop()), () => false), 'the reply is queued in the Outbox');
  assert.match((await frame.locator('[data-role="outbox-unsent-replies"]').allInnerTexts()).join(' '), new RegExp(replyText), 'the pane lists the unsent reply above its composer');
  await outbox.close();

  // A page with no offline copy: a plain message, and Close still works.
  await page.evaluate(() => { const link = document.createElement('a'); link.href = '/recent/'; link.textContent = 'Recent'; link.setAttribute('data-test-link', ''); document.querySelector('.main').prepend(link); });
  await page.locator('[data-test-link]').click();
  await until(async () => (await pane()).note !== null, 'the no-offline-copy message');
  shown = await pane();
  assert.match(shown.note, /could not be loaded/);
  assert.equal(shown.frameShown, false, 'the browser\'s error page is not shown');
  assert.equal(shown.bar, 'Not available');
  assert.equal(await page.locator('[data-open-beside-status]').textContent(), shown.note, 'the failure is announced');
  if (shots) await page.screenshot({ path: join(shots, 'offline-no-copy-2048.png') });
  // The next page that does load clears the message.
  await titles.first().click();
  await until(async () => { const now = await pane(); return now.at === threadPath && now.note === null && now.frameShown; }, 'a page that loads replaces the message');
  await page.locator('[data-open-beside-close]').click();
  assert.equal(await page.locator('[data-open-beside-pane]').count(), 0, 'Close works offline');

  // A saved tag's thread list uses the same titles.
  await page.goto(base + '/tags/bug');
  await until(() => titles.count().then((n) => n > 0), 'the offline tag page');
  const tagged = await titles.first().getAttribute('href');
  await titles.first().click();
  await until(async () => { const now = await pane(); return now && now.at === tagged; }, 'a tagged thread in the pane');
  assert.equal(new URL(page.url()).pathname, '/tags/bug', 'the tag page stayed');

  assert.deepEqual(errors, [], 'no page errors');
  console.log('open_beside_offline: ok');
} finally {
  await browser.close();
  server.kill();
}
