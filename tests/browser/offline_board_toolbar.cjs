// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/offline_board_toolbar.cjs [screenshot-dir]
// The offline reader's board and Tags views use the board toolbar (filter, sort menu, Tags), like the online pages.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const project = path.resolve(__dirname, '../..');
const shots = process.argv[2] || null;
const snapshot = execFileSync('php', ['-r', `
require $argv[1] . '/tests/AbsentRetractionSnapshotTest.php';
[$root, $database] = (new AbsentRetractionSnapshotTest())->publicationFixture('target');
(new ForumRewrite\\Offline\\PublicOfflineSnapshotBuilder())->build($database, $root . '/snapshot.sqlite3');
echo $root . '/snapshot.sqlite3';
`, project], { encoding: 'utf8' });
const section = execFileSync('php', ['-r', 'include $argv[1];', project + '/templates/pages/offline_reader.php'], { encoding: 'utf8' });
const styles = ['site', 'sqlite', 'thread-list', 'tags'].map(name => `<link rel="stylesheet" href="/assets/${name}.css">`).join('') + '<style>body { padding-top: 3.5rem; }</style>';

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  try {
    for (const width of [390, 1280]) for (const theme of ['default', 'word97', 'chicago', 'midnight']) {
      const context = await browser.newContext({ viewport: { width, height: 900 } });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('http://offline.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/assets/')) {
          return route.fulfill({ contentType: url.pathname.endsWith('.wasm') ? 'application/wasm' : url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(project + '/public' + url.pathname) });
        }
        if (url.pathname === '/offline/snapshot.sqlite3') return route.fulfill({ body: fs.readFileSync(snapshot) });
        if (url.pathname === '/offline/update.sqlite3') return route.fulfill({ status: 404, body: 'missing' });
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html data-theme="${theme}"><head><meta name="viewport" content="width=device-width, initial-scale=1">${styles}${theme === 'default' ? '' : `<link rel="stylesheet" href="/assets/theme-${theme}.css">`}</head><body><main class="main">${section}</main><script src="/assets/sql-wasm.js"></script><script src="/assets/offline_reader.js"></script></body></html>` });
      });
      const label = `${theme} ${width}px`;
      const toolbar = page.locator('[data-role="offline-reader-content"] > .board-toolbar');
      const current = () => toolbar.locator('[aria-current]').evaluateAll(nodes => nodes.map(node => node.textContent));
      const layout = async () => {
        const box = selector => toolbar.locator(selector).boundingBox();
        const [filter, sort, tags, bar] = [await box('.board-filter'), await box('.board-sort'), await box('.board-tags-link'), await toolbar.boundingBox()];
        assert.equal(Math.round(filter.y), Math.round(sort.y), `${label}: filter and sort share a row`);
        assert.ok(Math.abs(tags.x + tags.width - (bar.x + bar.width)) < 2, `${label}: Tags at the right`);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: no page overflow`);
      };

      // Board: All and Newest current; choosing a sort updates the toolbar in place.
      await page.goto('http://offline.test/threads/');
      await toolbar.waitFor();
      assert.equal(await page.locator('.board-controls-nav').count(), 0, `${label}: old controls gone`);
      assert.deepEqual(await current(), ['All', 'Newest']);
      assert.equal(await toolbar.locator('.board-sort > summary').textContent(), 'Sort by Newest');
      await layout();
      if (shots) await page.screenshot({ path: path.join(shots, `before-${theme}-${width}.png`), fullPage: true });
      await toolbar.locator('.board-sort > summary').click();
      if (shots) await page.screenshot({ path: path.join(shots, `board-${theme}-${width}.png`), clip: { x: 0, y: 0, width, height: 360 } });
      await toolbar.locator('.board-sort__menu a', { hasText: 'Top' }).click();
      await page.waitForURL(/\/threads\/\?view=all&sort=top/);
      assert.deepEqual(await current(), ['All', 'Top']);
      assert.equal(await toolbar.locator('.board-sort').evaluate(details => details.open), false, `${label}: sort menu closes`);

      // Tags: only Tags is current.
      await page.goto('http://offline.test/tags/');
      await page.locator('.tags-section-card').waitFor();
      assert.deepEqual(await current(), ['Tags']);
      assert.equal(await toolbar.locator('button').count(), 0, `${label}: no disabled New Post`);
      await layout();
      if (shots) await page.screenshot({ path: path.join(shots, `tags-${theme}-${width}.png`), clip: { x: 0, y: 0, width, height: 360 } });
      assert.deepEqual(errors, [], `${label}: no page errors`);
      await context.close();
    }
    console.log('offline_board_toolbar: ok');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
