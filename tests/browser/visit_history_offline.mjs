// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/visit_history_offline.mjs
// Visit history offline (FORUM_VISIT_HISTORY_ENABLED, cycle nav_tree). Pages
// the offline reader draws are page changes like any other: offline, the board
// and a thread opened from it are recorded as a branch, and Tools → History is
// not needed to read them back (the stored tree is checked directly).
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = mkdtempSync(join(tmpdir(), 'visit-history-offline-'));
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
    FORUM_VISIT_HISTORY_ENABLED: 'true' },
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
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise((done) => setTimeout(done, 100)); } }

  // Offline reading is set up by visiting the site once.
  await page.goto(base + '/offline/');
  await until(() => page.evaluate(async () => {
    for (const key of await caches.keys()) {
      const cache = await caches.open(key);
      if (await cache.match('/offline/reader/') && await cache.match('/offline/snapshot.sqlite3')) return true;
    }
    return false;
  }), 'offline pages cached');
  await page.evaluate(() => window.forumVisitHistory && window.forumVisitHistory.clear());
  const stored = () => page.evaluate(() => {
    const visits = JSON.parse(localStorage.getItem('forum_visit_history') || '{"visits":[]}').visits;
    const byId = Object.fromEntries(visits.map((visit) => [visit.id, visit]));
    return visits.map((visit) => [visit.url, visit.parent && byId[visit.parent] ? byId[visit.parent].url : null, visit.title]);
  });

  await context.setOffline(true);
  const titles = page.locator('.main .thread-card h2 a');
  await page.goto(base + '/');
  await until(() => titles.count().then((n) => n > 0), 'the offline board');
  assert.equal(await page.locator('[data-offline-reader]').count(), 1, 'this is the offline reader');
  await until(() => page.evaluate(() => window.forumVisitHistory !== undefined), 'the recorder runs offline');
  const first = titles.first();
  const threadPath = (await first.getAttribute('href')).split('#')[0];
  const threadTitle = (await first.textContent()).trim();
  await first.click();
  await page.waitForURL('**' + threadPath);
  await until(async () => (await stored()).some((visit) => visit[0] === threadPath), 'the thread is recorded offline');
  const visits = await stored();
  const thread = visits.find((visit) => visit[0] === threadPath);
  assert.equal(thread[1], '/', 'offline, the thread hangs under the board');
  await until(async () => (await stored()).find((visit) => visit[0] === threadPath)[2].includes(threadTitle), 'the visit takes the title the offline reader gives the page');
  assert.deepEqual(errors, [], 'no page errors');
  console.log('visit_history_offline: ok');
} finally {
  await browser.close();
  server.kill();
}
