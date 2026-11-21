import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Ochre on a ported thread nested nine deep, set by an operator's `theme: ochre`
// reply. A reader with no saved theme sees Ochre before first paint. Each reply
// gets parent/next links and a [–] toggle; folding hides exactly its branch as
// [+N], and unfolding an outer branch keeps inner folds. Picking Gloss removes
// every Ochre control without a reload, and a revisit stays on Gloss. A thread
// without the setting shows the usual theme. With JavaScript off nothing folds
// and every reply shows. On a phone the toggle stays on the grey line.
// Usage: node tests/browser/theme_ochre.mjs [screenshot-dir]
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const root = await mkdtemp(join(tmpdir(), 'theme-ochre-browser-'));
const repo = join(root, 'repo');
const db = join(root, 'cache', 'read-model.sqlite3');
const pub = join(root, 'public');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repo, { recursive: true });
await mkdir(join(root, 'cache'));
await mkdir(pub);
if (shots) await mkdir(shots, { recursive: true });
for (const args of [['init', '-q'], ['config', 'user.name', 'Forum Rewrite'], ['config', 'user.email', 'forum-rewrite@example.invalid'], ['add', '.'], ['-c', 'commit.gpgsign=false', 'commit', '-qm', 'init']]) {
  assert.equal(spawnSync('git', args, { cwd: repo }).status, 0);
}
const env = { ...process.env, FORUM_REPOSITORY_ROOT: repo, FORUM_DATABASE_PATH: db,
  FORUM_APP_VERSION_NOTIFICATION: 'false', FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_THREAD_PORT_ENABLED: 'true' };
const rebuild = spawnSync(join(project, 'v3'), ['rebuild', repo, db], { cwd: project, env, encoding: 'utf8' });
assert.equal(rebuild.status, 0, rebuild.stderr + rebuild.stdout);
const fixture = spawnSync('php', [join(project, 'tests/browser/theme_ochre_fixture.php'), repo, db, pub], { cwd: project, env, encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr + fixture.stdout);
const ported = '/threads/' + fixture.stdout.trim().split('\n').pop();

const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(r => socket.close(r));
const server = spawn(join(project, 'v3'), ['start', `127.0.0.1:${port}`, '--quiet'], { cwd: project, env, detached: true });
const base = `http://127.0.0.1:${port}`;
for (let i = 0; i < 200; i++) { try { await (await fetch(base + '/')).text(); break; } catch { await new Promise(r => setTimeout(r, 50)); } }

const toggles = page => page.locator('.ochre-toggle');
const ochreLeft = page => page.evaluate(() => document.querySelectorAll('.ochre-nav, .ochre-line, .ochre-keep, .ochre-hidden, .ochre-collapsed').length);
const hiddenCount = page => page.evaluate(() => document.querySelectorAll('.post-card.ochre-hidden').length);

let browser;
try {
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

  // Desktop reader with no saved theme.
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
  await context.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => { window.__firstTheme = document.documentElement.getAttribute('data-theme'); });
  });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + ported, { waitUntil: 'networkidle' });
  assert.equal(await page.evaluate(() => window.__firstTheme), 'ochre', 'Ochre is applied before first paint');
  await page.waitForSelector('.ochre-toggle');
  // Fifteen ported replies plus the operator's theme reply.
  assert.equal(await toggles(page).count(), 16);
  assert.equal(await toggles(page).nth(0).textContent(), '[–]');

  // The second reply's branch: itself and eight below it.
  await toggles(page).nth(1).click();
  assert.equal(await toggles(page).nth(1).textContent(), '[+9]');
  assert.equal(await toggles(page).nth(1).getAttribute('aria-expanded'), 'false');
  assert.equal(await hiddenCount(page), 8);
  // A folded reply keeps only its grey line.
  assert.equal(await page.locator('.post-card.ochre-collapsed > .body').first().isVisible(), false);
  await toggles(page).nth(0).click();
  assert.equal(await toggles(page).nth(0).textContent(), '[+11]');
  assert.equal(await hiddenCount(page), 10);
  await toggles(page).nth(0).click();
  assert.equal(await hiddenCount(page), 8, 'unfolding the outer branch keeps the inner fold');
  await toggles(page).nth(1).press('Enter');
  assert.equal(await hiddenCount(page), 0, 'the toggle works from the keyboard');
  if (shots) await page.screenshot({ path: join(shots, 'ochre-thread-desktop.png'), fullPage: true });

  // Parent and next: the deepest reply links to its parent; parent jumps there.
  const deepest = page.locator('.post-card').nth(9);
  assert.deepEqual(await deepest.locator('.ochre-link').allTextContents(), ['parent']);
  const parentHref = await deepest.locator('.ochre-link').getAttribute('href');
  await deepest.locator('.ochre-link').click();
  assert.equal(await page.evaluate(() => location.hash), parentHref);
  assert.equal(await page.evaluate(h => document.querySelector(h).matches(':target'), parentHref), true);
  const second = page.locator('.post-card').nth(2);
  assert.deepEqual(await second.locator('.ochre-link').allTextContents(), ['parent', 'next']);

  // Picking Gloss leaves the page theme without a reload; a revisit stays on Gloss.
  await page.evaluate(() => window.forumExitTheme('gloss'));
  await page.waitForFunction(() => document.documentElement.getAttribute('data-theme') === 'gloss');
  assert.equal(await ochreLeft(page), 0);
  await page.goto(base + ported, { waitUntil: 'networkidle' });
  assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-theme')), 'gloss');
  assert.equal(await toggles(page).count(), 0);
  assert.deepEqual(errors, []);
  await context.close();

  // A thread without the setting shows the usual theme.
  const plain = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
  const plainPage = await plain.newPage();
  await plainPage.goto(base + '/threads/root-001', { waitUntil: 'networkidle' });
  assert.notEqual(await plainPage.evaluate(() => document.documentElement.getAttribute('data-theme')), 'ochre');
  await plain.close();

  // Without JavaScript nothing folds and every reply shows.
  const noScript = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
  const noScriptPage = await noScript.newPage();
  await noScriptPage.goto(base + ported, { waitUntil: 'load' });
  assert.equal(await noScriptPage.locator('.ochre-toggle').count(), 0);
  assert.equal(await noScriptPage.locator('.post-card:visible').count(), 17);
  await noScript.close();

  // On a phone the toggle stays on the grey line and is a 44px target.
  const phone = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 380, height: 800 }, hasTouch: true, isMobile: true });
  const phonePage = await phone.newPage();
  await phonePage.goto(base + ported, { waitUntil: 'networkidle' });
  await phonePage.waitForSelector('.ochre-toggle');
  const box = await phonePage.locator('.ochre-toggle').nth(1).boundingBox();
  const line = await phonePage.locator('.post-card').nth(2).locator('.ochre-keep').boundingBox();
  assert.ok(box.width >= 44, `toggle width ${box.width}`);
  assert.ok(box.y + box.height / 2 > line.y && box.y + box.height / 2 < line.y + line.height, 'the toggle sits on the grey line');
  await phonePage.locator('.ochre-toggle').nth(1).tap();
  assert.equal(await hiddenCount(phonePage), 8);
  if (shots) await phonePage.screenshot({ path: join(shots, 'ochre-thread-phone.png'), fullPage: true });
  await phone.close();

  console.log('PASS Ochre folds whole branches, links parent and next, leaves cleanly and reads without JavaScript');
} finally {
  if (browser) await browser.close();
  try { process.kill(-server.pid); } catch {}
}
