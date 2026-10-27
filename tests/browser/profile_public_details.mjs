import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'profile-public-browser-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/profile_public_details_fixture.php'), root], { env, encoding: 'utf8' });
assert.equal(seed.status, 0, seed.stderr);
const { themes, slug, key } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', data => { logs += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const javaScriptEnabled of [false, true]) {
    const context = await browser.newContext({ javaScriptEnabled, serviceWorkers: 'block', viewport: { width: 390, height: 900 } });
    const page = await context.newPage();
    await page.goto(base + '/profiles/' + slug);
    const details = page.locator('[data-public-key-details]');
    assert.equal(await details.getAttribute('open'), null);
    await details.locator('summary').focus(); await page.keyboard.press('Enter');
    assert.equal(await details.evaluate(n => n.open), true);
    assert.equal(await details.locator('pre').textContent(), key);
    if (javaScriptEnabled) {
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {writeText: async text => {window.copiedKey = text;}} }));
      await details.locator('button').click();
      assert.equal(await page.evaluate(() => window.copiedKey), key);
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {writeText: async () => {throw new Error('denied');}} }));
      await details.locator('button').click();
      assert.equal(await details.locator('[role=status]').isVisible(), true);
      assert.match(await details.locator('[role=status]').textContent(), /manually/);
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await context.close();
  }
  console.log('PASS profile key: collapsed default, keyboard, exact copy, denied clipboard recovery, no-JS selection and phone overflow');
} finally {
  await browser.close(); server.kill(); await rm(root, { recursive: true, force: true });
}
