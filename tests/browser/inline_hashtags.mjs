// CHROMIUM_PATH=/path/to/Brave node tests/browser/inline_hashtags.mjs
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, cp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const tagLinks = process.env.FORUM_TAG_LINKS_ENABLED !== 'false';
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'inline-hashtags-'));
const repository = join(root, 'repository');
await mkdir(repository);
await mkdir(join(root, 'artifacts'));
await cp(join(project, 'public/assets'), join(root, 'artifacts/assets'), { recursive: true });
await cp(join(project, 'tests/fixtures/parity_minimal_v1/records'), join(repository, 'records'), { recursive: true });
for (const args of [['init', '-q'], ['config', 'user.name', 'Hashtag Test'], ['config', 'user.email', 'test@example.invalid'], ['add', '.'], ['-c', 'commit.gpgsign=false', 'commit', '-qm', 'Fixture']]) {
  execFileSync('git', ['-C', repository, ...args]);
}
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const database = join(root, 'cache/index.sqlite3');
const env = { ...process.env, FORUM_REPOSITORY_ROOT: repository, FORUM_DATABASE_PATH: database,
  FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
  FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'tasks.sqlite3'), FORUM_IMAGE_ROOT: join(root, 'images'),
  FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'false',
  FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'none.php'), FAST_SCORING_ENABLED: 'false' };
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), join(project, 'public/router.php')], { cwd: project, env, stdio: ['ignore', 'ignore', 'pipe'] });
let log = '';
server.stderr.on('data', data => { log += data; });
let browser;
try {
  for (let attempt = 0; attempt < 100; attempt++) {
    try { if ((await fetch(base + '/api/version')).ok) break; } catch {}
    await new Promise(done => setTimeout(done, 50));
  }
  async function post(path, fields, key) {
    const response = await fetch(base + path, { method: 'POST', body: new URLSearchParams(fields) });
    const body = await response.text();
    assert.equal(response.status, 200, body);
    const id = new RegExp(`^${key}=(.+)$`, 'm').exec(body)?.[1];
    assert.ok(id, body);
    return id;
  }
  const threads = [];
  for (const format of ['plain', 'markdown']) {
    const thread = await post('/api/create_thread', { subject: `${format} hashtags`, body_format: format, body: '**#meta** (#needs-review).\n\n`#code`\n\n```\n#fenced\n```\n\n[#label](https://example.test/#fragment)' }, 'thread_id');
    const reply = await post('/api/create_reply', { thread_id: thread, parent_id: thread, body_format: format, body: 'Reply #meta' }, 'post_id');
    threads.push({ thread, reply });
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser', headless: true });
  for (const width of (process.env.HASHTAG_OFFLINE_ONLY ? [] : [390, 1280])) for (const javaScriptEnabled of [false, true]) for (const theme of ['light', 'dark', 'word97', 'chicago', 'midnight']) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, javaScriptEnabled, serviceWorkers: 'block' });
    await context.addCookies([{ name: 'theme-hint', value: theme, url: base }]);
    await context.addInitScript(value => localStorage.setItem('forum-zenmemes-theme', value), theme);
    const page = await context.newPage();
    const assetFailures = [];
    page.on('response', response => { if (response.url().includes('/assets/') && !response.ok()) assetFailures.push(response.url()); });
    for (const { thread, reply } of threads) {
      await page.goto(base + '/threads/' + thread);
      const rootBody = page.locator('.thread-root-card .body');
      assert.equal(await rootBody.locator('a[href^="/tags/"]').count(), tagLinks ? 2 : 0);
      assert.equal(await page.locator(`[data-post-id="${reply}"] .body a[href="/tags/meta"]`).count(), tagLinks ? 1 : 0);
      assert.equal(await page.locator('a a, code a').count(), 0);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      const labels = page.locator('[data-post-byline]').filter({ hasText: 'Labels:' });
      assert.ok((await labels.innerText()).includes('Labels:'));
      assert.equal(await labels.locator('a[href="/tags/meta"]').count(), 1);
      if (!tagLinks) {
        assert.ok((await rootBody.innerText()).includes('#meta'));
      }
      await labels.locator('a[href="/tags/meta"]').click();
      await page.waitForURL(base + '/tags/meta');
      assert.ok(await page.locator(`a[href="/threads/${thread}"]`).count());
      await page.goto(base + '/posts/' + reply);
      assert.equal(await page.locator('.body a[href="/tags/meta"]').count(), tagLinks ? 1 : 0);
      if (tagLinks) {
        await page.locator('.body a[href="/tags/meta"]').click();
        await page.waitForURL(base + '/tags/meta');
      }
    }
    if (theme === 'light' && javaScriptEnabled) {
      await page.goto(base + '/threads/' + threads[1].thread);
      await page.screenshot({ path: join(root, `online-${width}.png`), fullPage: true });
    }
    assert.deepEqual(assetFailures, [], 'All theme and script assets must load');
    await context.close();
  }

  execFileSync('php', ['-r', 'require $argv[1]."/autoload.php"; (new ForumRewrite\\Offline\\PublicOfflineSnapshotBuilder())->build($argv[2], $argv[3]);', project, database, join(root, 'snapshot.sqlite3')], { env });
  const offlineResponse = await fetch(base + '/offline/reader/');
  assert.equal(offlineResponse.status, 200);
  const shell = await offlineResponse.text();
  assert.ok(shell.includes('data-offline-reader'));
  for (const width of [390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
    await context.route(base + '/**', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/offline/snapshot.sqlite3') return route.fulfill({ body: await readFile(join(root, 'snapshot.sqlite3')) });
      if (path === '/offline/update.sqlite3') return route.fulfill({ status: 404, body: 'missing' });
      if (path.startsWith('/threads/') || path === '/tags/meta') return route.fulfill({ contentType: 'text/html', body: shell });
      return route.continue();
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/threads/' + threads[0].thread);
    const link = page.locator('.thread-root-card .body a[href="/tags/meta"]');
    if (!tagLinks) {
      await page.locator('.thread-root-card .body').waitFor();
      assert.ok((await page.locator('.thread-root-card .body').innerText()).includes('#meta'));
      assert.equal(await page.locator('.body a[href^="/tags/"]').count(), 0);
      await context.close();
      continue;
    }
    await link.waitFor({ timeout: 10000 }).catch(async error => {
      console.error(await page.locator('main').innerText());
      console.error(await page.locator('.thread-root-card .body').allInnerTexts());
      console.error(errors);
      throw error;
    });
    assert.equal(await page.locator('.body a[href^="/tags/"]').count(), 3);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: join(root, `offline-${width}.png`), fullPage: true });
    await link.click();
    await page.waitForURL(base + '/tags/meta');
    await page.locator(`a[href="/threads/${threads[0].thread}"]`).waitFor();
    assert.deepEqual(errors, []);
    await context.close();
  }
  console.log(`PASS: tag links ${tagLinks ? "enabled" : "disabled"}; ${process.env.HASHTAG_OFFLINE_ONLY ? "offline navigation" : "plain/Markdown roots and replies; JS on/off; five themes; offline navigation"}; phone/desktop. Screenshots: ${root}`);
} catch (error) {
  console.error(log.slice(-5000));
  throw error;
} finally {
  if (browser) await browser.close();
  server.kill();
}
