const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const project = path.resolve(__dirname, '../..');
const output = process.env.ABSENT_SNAPSHOT_EVIDENCE || fs.mkdtempSync(path.join(os.tmpdir(), 'absent-snapshot-browser-'));
fs.mkdirSync(output, { recursive: true });
const fixtures = JSON.parse(execFileSync('php', ['-r', `
require $argv[1] . '/tests/AbsentRetractionSnapshotTest.php';
$fixtures = [];
foreach (['target', 'root-001'] as $target) {
    [$root, $database] = (new AbsentRetractionSnapshotTest())->publicationFixture($target);
    $source = new PDO('sqlite:' . $database);
    $source->exec("UPDATE posts SET body = 'SURVIVING-REPLY ' || '" . str_repeat('longvalue', 70) . "' WHERE post_id='reply-001'");
    $record = $root . '/records/post-retractions/post-retraction-' . $target . '.txt';
    rename($record, $record . '.bak');
    $builder = new ForumRewrite\\Offline\\PublicOfflineSnapshotBuilder();
    $builder->build($database, $root . '/base.sqlite3');
    rename($record . '.bak', $record);
    $builder->buildUpdate($database, $root . '/update.sqlite3');
    $builder->build($database, $root . '/fresh.sqlite3');
    $update = new PDO('sqlite:' . $root . '/update.sqlite3');
    $update->exec("DELETE FROM posts WHERE thread_id='root-001'; DELETE FROM threads WHERE root_post_id='root-001'");
    $source->exec('UPDATE posts SET is_hidden=1');
    $builder->build($database, $root . '/empty.sqlite3');
    $fixtures[$target] = $root;
}
echo json_encode($fixtures, JSON_THROW_ON_ERROR);
`, project], { encoding: 'utf8' }));
const section = execFileSync('php', ['-r', 'include $argv[1];', project + '/templates/pages/offline_reader.php'], { encoding: 'utf8' });

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const evidence = [];
  try {
    for (const width of [390, 1280]) for (const theme of ['default', 'word97', 'chicago', 'midnight']) for (const target of ['target', 'root-001']) {
      const context = await browser.newContext({ viewport: { width, height: 900 } });
      const page = await context.newPage();
      await page.addInitScript(() => document.addEventListener('DOMContentLoaded', () => {
        document.querySelector('[data-offline-reader]').addEventListener('forum-offline-reader-ready', event => { window.acceptanceDatabase = event.detail.database; });
      }));
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      let mode = 'update';
      await page.route('http://offline.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/assets/')) {
          const file = project + '/public' + url.pathname;
          return route.fulfill({ contentType: url.pathname.endsWith('.wasm') ? 'application/wasm' : url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(file) });
        }
        if (url.pathname === '/offline/snapshot.sqlite3') {
          if (mode === 'failure') return route.fulfill({ status: 503, body: 'unavailable' });
          const name = mode === 'empty' ? 'empty' : mode === 'update' ? 'base' : 'fresh';
          return route.fulfill({ body: fs.readFileSync(fixtures[target] + '/' + name + '.sqlite3') });
        }
        if (url.pathname === '/offline/update.sqlite3') {
          if (mode !== 'update') return route.fulfill({ status: 404, body: 'missing' });
          return route.fulfill({ body: fs.readFileSync(fixtures[target] + '/update.sqlite3') });
        }
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html data-theme="${theme}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/site.css">${theme === 'default' ? '' : `<link rel="stylesheet" href="/assets/theme-${theme}.css">`}</head><body><main class="main">${section}</main><script src="/assets/sql-wasm.js"></script><script src="/assets/offline_reader.js"></script></body></html>` });
      });
      await page.goto('http://offline.test/offline/reader/#thread=root-001');
      await page.locator('#post-reply-001').waitFor();
      assert.equal(await page.locator('#post-' + target).count(), 0);
      assert.match(await page.locator('[data-role="offline-reader-content"]').innerText(), /SURVIVING-REPLY/);
      if (target === 'root-001') assert.doesNotMatch(await page.locator('[data-role="offline-reader-content"]').innerText(), /Hello world|First line preview/);
      await page.screenshot({ path: path.join(output, `${width}-${theme}-${target}.png`), fullPage: true });
      if (target === 'root-001') {
        assert.equal(await page.locator('#post-reply-001.thread-root-card').count(), 0);
        assert.match(await page.locator('#post-reply-001 .body').innerText(), /SURVIVING-REPLY/);
        await page.evaluate(() => window.forumOfflineSnapshot.renderThreadDetail({
          database: window.acceptanceDatabase, threadId: 'root-001', content: document.querySelector('[data-role="offline-reader-content"]'),
          setStatus() {}, onQueueReaction(target) { window.acceptanceReaction = target; return Promise.resolve(); }
        }));
        await page.locator('#post-reply-001 [data-action="queue-offline-like"]').click();
        assert.deepEqual(await page.evaluate(() => window.acceptanceReaction), { kind: 'post', id: 'reply-001' });
      }
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
      assert.equal(overflow, false, `${width}/${theme}/${target} overflow`);
      await page.goto('http://offline.test/offline/reader/');
      await page.locator('[data-role="offline-reader-content"] a').first().waitFor();
      if (target === 'root-001') assert.doesNotMatch(await page.locator('[data-role="offline-reader-content"]').innerText(), /Hello world|First line preview/);
      for (const state of ['fresh', 'empty', 'failure', 'fresh']) {
        mode = state;
        await page.reload();
        if (state === 'failure') {
          await page.waitForFunction(() => document.querySelector('[data-role="offline-reader-status"]').dataset.state === 'error');
          assert.match(await page.locator('[data-role="offline-reader-status"]').innerText(), /Open the site while online and try again/);
        } else {
          await page.waitForFunction(() => !document.querySelector('[data-role="offline-reader-content"]').hidden);
          if (state === 'empty') assert.match(await page.locator('[data-role="offline-reader-content"]').innerText(), /No recent public threads/);
        }
      }
      assert.deepEqual(errors, []);
      evidence.push({ width, theme, target, updateOutsideWindow: 'passed', threadAndList: 'passed', freshEmptyFailureRecovery: 'passed', overflow, pageErrors: errors });
      await context.close();
    }
  } finally { await browser.close(); }
  fs.writeFileSync(path.join(output, 'evidence.json'), JSON.stringify({ fixtures, scenarios: evidence }, null, 2));
  console.log(JSON.stringify({ output, passed: evidence.length }));
})().catch(error => { console.error(error); process.exit(1); });
