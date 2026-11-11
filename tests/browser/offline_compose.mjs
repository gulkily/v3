// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/offline_compose.mjs
// Offline, writing works as it does online: New post and Reply open the same composers at the same addresses,
// Post keeps the text in the Outbox as "not sent", and it is published once when the connection returns.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = mkdtempSync(join(tmpdir(), 'offline-compose-'));
const repository = join(root, 'repository');
mkdirSync(join(root, 'static'));
cpSync(join(project, 'public/assets'), join(root, 'artifacts/assets'), { recursive: true });
cpSync(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
const git = (...args) => {
  const result = spawnSync('git', ['-C', repository, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', ...args], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return result.stdout.trim();
};
git('init'); git('add', '.'); git('commit', '-m', 'Initialize');
// How many published posts carry this text.
const copies = (text) => spawnSync('git', ['-C', repository, 'grep', '-l', text, 'HEAD', '--', 'records/posts'], { encoding: 'utf8' })
  .stdout.trim().split('\n').filter(Boolean).length;

const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise((done) => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), join(project, 'public/router.php')], {
  cwd: project,
  env: { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_REPOSITORY_ROOT: repository, FORUM_DATABASE_PATH: join(root, 'read.sqlite3'),
    FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
    FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'false', FAST_SCORING_ENABLED: 'false',
    FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
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
const composer = (page) => page.locator('form[data-compose-form]').first();
const body = (page) => composer(page).locator('textarea[name="body"]');
// The board loads its signing scripts once someone starts typing; a person types for longer than that takes.
const signingReady = (page) => until(() => page.evaluate(() => typeof (window.ForumBrowserSigning && window.ForumBrowserSigning.init) === 'function'), 'signing scripts');
const post = async (page) => {
  await signingReady(page);
  await composer(page).locator('button[type=submit]:not([data-action="submit-anonymous-compose"])').first()
    .evaluate((button) => button.closest('form').requestSubmit(button));
};
const status = (page) => page.locator('[data-role="compose-identity-status"]').allTextContents().then((texts) => texts.join(' '));
// The composer's controls in order, as a reader sees them.
const controls = (page) => composer(page).evaluate((form) => Array.from(form.querySelectorAll('textarea, button, summary'))
  .filter((node) => node.offsetParent !== null).map((node) => node.tagName.toLowerCase() + ':' + (node.getAttribute('aria-label') || node.textContent).trim().replace(/\s+/g, ' ')));
async function layout(page, label) {
  const row = await composer(page).evaluate((form) => {
    const postButton = form.querySelector('button[type=submit]');
    const menu = form.querySelector('.compose-action-menu summary, [data-compose-action-menu] summary, details summary');
    return { post: postButton && postButton.getBoundingClientRect().top, menu: menu && menu.getBoundingClientRect().top };
  });
  if (row.menu !== null && row.post !== null) assert.ok(Math.abs(row.post - row.menu) < 4, `${label}: Post and ··· share a row`);
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: no sideways scroll`);
}

try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));

  // Offline reading is set up by visiting the site once.
  await page.goto(base + '/offline/');
  await until(() => page.evaluate(async () => {
    for (const key of await caches.keys()) {
      const cache = await caches.open(key);
      if (await cache.match('/compose/reply') && await cache.match('/offline/reader/')) return true;
    }
    return false;
  }), 'offline pages cached');

  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 900 });

    // Ilya's check, Reply: load a thread online, disconnect, click Reply, write, reload, reconnect.
    await page.goto(base + '/threads/root-001');
    const onlineReply = await controls(page);
    await context.setOffline(true);
    await page.locator('a[href*="/compose/reply"][href*="parent_id=reply-001"]').first().click();
    await page.waitForURL(/\/compose\/reply\?thread_id=root-001&parent_id=reply-001/);
    await until(() => page.locator('.reply-context').count().then((n) => n > 0), 'quoted post offline');
    assert.match(await page.locator('.reply-context-card').innerText(), /Reply to guest[\s\S]*Reply body\./, `${width}: quoted post`);
    await layout(page, `${width} offline reply page`);
    const replyText = `offline reply ${width} ${Date.now()}`;
    await body(page).fill(replyText);
    await page.waitForTimeout(500);
    await page.reload();
    await until(async () => (await body(page).inputValue()) === replyText, 'reply draft after offline reload');
    await post(page);
    await until(async () => (await status(page)).includes('Saved on this device, not sent'), 'reply kept as not sent');
    assert.equal(await body(page).inputValue(), '', `${width}: composer cleared after saving`);

    // The thread, offline: same reply composer as online, and the unsent reply listed above it.
    await page.goto(base + '/threads/root-001');
    await until(() => page.locator('[data-offline-reader] form[data-compose-form]').count().then((n) => n === 1), 'reader reply composer');
    assert.deepEqual(await controls(page), onlineReply, `${width}: offline thread composer matches online`);
    await layout(page, `${width} offline thread`);
    assert.match(await page.locator('[data-role="outbox-unsent-replies"]').innerText(), new RegExp(replyText), `${width}: unsent reply listed`);
    assert.equal(copies(replyText), 0, `${width}: nothing published while offline`);

    await context.setOffline(false);
    await until(() => copies(replyText) === 1, 'reply published after reconnecting');
    await until(() => page.locator('[data-role="outbox-unsent-replies"]').count().then((n) => n === 0), 'unsent list cleared');

    // Ilya's check, New post: from the board, then reconnect on the same page.
    await page.goto(base + '/');
    await body(page).focus();
    const onlineBoard = await controls(page);
    await context.setOffline(true);
    // On a phone New post sits in the sliding bottom bar; activate whichever link is shown.
    await page.locator('a[href="/compose/thread"]:visible').first().evaluate((link) => link.click());
    await page.waitForURL(/\/compose\/thread$/);
    await layout(page, `${width} offline new post page`);
    const threadText = `offline thread ${width} ${Date.now()}`;
    await body(page).fill(threadText);
    await page.waitForTimeout(500);
    await page.reload();
    await until(async () => (await body(page).inputValue()) === threadText, 'new post draft after offline reload');
    await post(page);
    await until(async () => (await status(page)).includes('Saved on this device, not sent'), 'new post kept as not sent');

    // The board, offline: same compact composer as online.
    await page.goto(base + '/');
    await until(() => page.locator('[data-offline-reader] form[data-compose-form]').count().then((n) => n === 1), 'reader board composer');
    await body(page).focus();
    assert.deepEqual(await controls(page), onlineBoard, `${width}: offline board composer matches online`);
    await layout(page, `${width} offline board`);
    await context.setOffline(false);
    await until(() => copies(threadText) === 1, 'new post published after reconnecting');
  }

  // A request that fails while the browser thinks it is online may have reached the server: never queued.
  await page.goto(base + '/threads/root-001');
  const dropped = `dropped while online ${Date.now()}`;
  await body(page).fill(dropped);
  await page.route('**/api/**', (route) => route.request().method() === 'POST' ? route.abort('failed') : route.fallback());
  await post(page);
  await until(async () => (await status(page)).includes("Couldn't reach the server"), 'dropped request reported');
  await page.unroute('**/api/**');
  assert.equal(await body(page).inputValue(), dropped, 'text kept after a dropped request');
  const outbox = await context.newPage();
  await outbox.goto(base + '/tools/outbox/');
  await outbox.waitForTimeout(1500);
  assert.ok(!(await outbox.locator('main').innerText()).includes('dropped while online'), 'dropped request not queued');

  // Anonymous posts can't go through the Outbox: offline, the text stays in the composer.
  await page.goto(base + '/');
  await context.setOffline(true);
  const anonymous = `anonymous offline ${Date.now()}`;
  await body(page).fill(anonymous);
  await signingReady(page);
  await composer(page).locator('[data-action="submit-anonymous-compose"]').evaluate((button) => button.closest('form').requestSubmit(button));
  await until(async () => (await status(page)).includes("You're offline"), 'anonymous offline message');
  assert.equal(await body(page).inputValue(), anonymous, 'anonymous text kept');
  await context.setOffline(false);

  assert.deepEqual(errors.filter((message) => !/fetch|network/i.test(message)), [], 'no page errors');
  console.log('PASS offline compose: Reply and New post offline at 390px and 1280px, published once on reconnect; dropped and anonymous posts stay in the composer');
} finally {
  await browser.close();
  server.kill();
}
