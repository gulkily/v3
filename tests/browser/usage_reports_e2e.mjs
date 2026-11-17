// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/usage_reports_e2e.mjs
// Usage reports end to end (local_telemetry): against the real front controller
// under php -S, a visitor turns recording on, takes three clicks from a thread to
// Feature Flags (never one), and uploads; the stored report then shows on the
// root-only Usage reports page as a shortcut candidate.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { connect, createServer } from 'node:net';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = mkdtempSync(join(tmpdir(), 'usage-e2e-'));
mkdirSync(join(root, 'static'));
const reports = join(root, 'usage_reports.sqlite3');
mkdirSync(join(root, 'images'));
writeFileSync(join(root, 'secrets.php'), `<?php return ['USAGE_REPORTS_DATABASE_PATH' => ${JSON.stringify(reports)}, 'FORUM_IMAGE_ROOT' => ${JSON.stringify(join(root, 'images'))}];`);
const port = await new Promise(done => { const s = createServer().listen(0, '127.0.0.1', () => { const { port } = s.address(); s.close(() => done(port)); }); });
const env = { ...process.env, USAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'secrets.php'), FORUM_USAGE_REPORTS_ENABLED: '1', FORUM_APPROVED_MEMBERS_ONLY: 'false' };
let logs = '';
const server = spawn('php', ['-S', `127.0.0.1:${port}`, join(project, 'tests/Support/usage_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
server.stderr.on('data', data => { logs += data; });
const base = `http://127.0.0.1:${port}`;
const listening = () => new Promise(done => { const socket = connect(port, '127.0.0.1', () => { socket.end(); done(true); }); socket.on('error', () => done(false)); });
for (let i = 0; i < 100 && !(await listening()); i++) await new Promise(r => setTimeout(r, 50));

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
await context.clock.install({ time: new Date('2026-11-14T12:00:00Z') });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const go = async action => { await action(); await page.waitForLoadState('load'); };

try {
  await page.goto(base + '/tools/usage-log/');
  await page.locator('[data-role="usage-log-recording"]').check();

  // Read a thread, pause, then three clicks to Feature Flags, then stay.
  await page.goto(base + '/threads/root-001');
  await context.clock.fastForward('00:40');
  await go(() => page.locator('.site-header a[data-tab="tools"]').first().click());
  await go(() => page.locator('a.tool-launcher-button[href="/tools/outbox/"]').click());
  await go(() => page.locator('.tools-nav a[href="/tools/feature-flags/"]').click());
  await context.clock.fastForward('01:00');

  await page.goto(base + '/tools/usage-log/');
  const log = await page.locator('[data-role="usage-log-text"]').inputValue();
  assert.match(log, /^view\t\d+\t\/threads\/\*$/m, 'thread view recorded with its ID removed');
  assert.ok(!log.includes('root-001'), 'no thread ID in the log');
  await page.locator('[data-action="usage-log-upload"]').click();
  await page.waitForFunction(() => /Uploaded|not|refus|Too many/.test(document.querySelector('[data-role="usage-log-status"]').textContent));
  assert.match(await page.locator('[data-role="usage-log-status"]').textContent(), /Uploaded anonymously\./, 'real upload stored');

  // The root-only summary over the same store.
  const summary = execFileSync('php', ['-r', `
    require ${JSON.stringify(project + '/autoload.php')};
    require ${JSON.stringify(project + '/tests/Support/TestViewerSession.php')};
    putenv('FORUM_SECRETS_PATH=${join(root, 'secrets.php')}');
    putenv('FORUM_USAGE_REPORTS_ENABLED=1');
    $_COOKIE = [];
    TestViewerSession::signIn('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
    (new ForumRewrite\\Application(${JSON.stringify(project)}, ${JSON.stringify(project + '/tests/fixtures/parity_minimal_v1')}, ${JSON.stringify(join(root, 'summary-read.sqlite3'))}))->handle('GET', '/tools/usage-reports/');
  `], { encoding: 'utf8', maxBuffer: 1 << 26 });
  assert.match(summary, /<dt>Reports<\/dt><dd>1<\/dd>/, 'summary counts the upload');
  const card = summary.split('<section class="usage-journey').map(part => part.split('</section>')[0]).find(part => part.includes('data-shortcut-idea') && part.includes('title="/threads/*">Thread</span>') && part.includes('title="/tools/feature-flags">Feature Flags</span>'));
  assert.ok(card, 'Thread → Feature Flags listed as a shortcut idea, in words');
  assert.equal((card.match(/<li title=/g) || []).length, 3, 'three steps shown');
  assert.match(card, /<li title="tab:tools">Tools<\/li>/, 'first step named Tools');
  assert.match(card, />3 clicks</, 'three clicks');
  assert.match(card, /never in one click/, 'never in one click');

  assert.deepEqual(errors, [], 'no page errors');
  console.log('Usage reports end to end: recorded three clicks from a thread to Feature Flags, uploaded through the real app, and the root summary lists it as a shortcut idea in words.');
} catch (error) {
  console.error(logs.slice(-2000));
  throw error;
} finally {
  await browser.close();
  server.kill();
  rmSync(root, { recursive: true, force: true });
}
