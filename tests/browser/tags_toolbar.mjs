// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tags_toolbar.mjs [screenshot-dir]
// /tags/ uses the board's toolbar (filter, sort, Tags), with Tags current, in every theme at phone and desktop width.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'tags-toolbar-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const themes = ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight'];
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const width of [390, 1280]) {
    for (const theme of themes) {
      const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 844 } });
      await context.addInitScript(theme => { for (const key of ['zenmemes-theme', ...Object.keys(localStorage).filter(k => k.endsWith('-theme'))]) localStorage.setItem(key, theme); }, theme);
      const page = await context.newPage();
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      await page.goto(base + '/tags/');
      const toolbar = page.locator('main .board-toolbar');
      assert.equal(await toolbar.count(), 1, `${theme} ${width}px: one toolbar`);
      assert.equal(await toolbar.locator('[aria-current]').count(), 1, `${theme} ${width}px: only Tags is current`);
      assert.equal(await toolbar.locator('a[aria-current="page"]').textContent(), 'Tags');
      assert.equal(await page.locator('main .board-controls-nav').count(), 0, `${theme} ${width}px: old controls gone`);
      // Board toolbar geometry: filter and sort on one row, Tags at the right edge.
      const box = s => toolbar.locator(s).boundingBox();
      const [filter, sort, tags, bar] = [await box('.board-filter'), await box('.board-sort'), await box('.board-tags-link'), await toolbar.boundingBox()];
      assert.equal(Math.round(filter.y), Math.round(sort.y), `${theme} ${width}px: filter and sort share a row`);
      assert.ok(Math.abs(tags.x + tags.width - (bar.x + bar.width)) < 2, `${theme} ${width}px: Tags at the right`);
      await toolbar.locator('.board-sort > summary').click();
      assert.equal(await toolbar.locator('.board-sort__menu a').count(), 3);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme} ${width}px: no page overflow`);
      if (shots) await page.screenshot({ path: join(shots, `${theme}-${width}.png`), clip: { x: 0, y: 0, width, height: 360 } });
      await toolbar.locator('.board-sort__menu a', { hasText: 'Top' }).click();
      await page.waitForURL(/\/threads\/\?view=all&sort=top/);
      assert.deepEqual(errors, [], `${theme} ${width}px: no page errors`);
      await context.close();
    }
  }
  console.log('tags_toolbar: ok');
} finally {
  await browser.close();
  server.kill();
  if (process.exitCode) console.error(serverLog);
}
