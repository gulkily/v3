// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/offline_card_extra_line.cjs [screenshot-dir]
// Offline board cards show one more line of the root post's body than its first line (the online list keeps one).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const project = path.resolve(__dirname, '../..');
const shots = process.argv[2] || null;
// The shared fixture has a two-line root (root-001) and a root whose first line repeats its title
// (thread-zenmemes-rules); add a one-line root and one whose second line is an image reference.
const snapshot = execFileSync('php', ['-r', `
require $argv[1] . '/tests/AbsentRetractionSnapshotTest.php';
[$root, $database] = (new AbsentRetractionSnapshotTest())->publicationFixture('target');
$path = $root . '/snapshot.sqlite3';
(new ForumRewrite\\Offline\\PublicOfflineSnapshotBuilder())->build($database, $path);
$pdo = new PDO('sqlite:' . $path);
$add = function (string $id, string $subject, string $body) use ($pdo): void {
    $preview = strtok($body, "\\n");
    $pdo->prepare("INSERT INTO threads VALUES (?, '2026-11-01T00:00:00Z', '2026-11-01T00:00:00Z', ?, ?, 0, ?, '[]', '[]', 0, 'guest', NULL)")
        ->execute([$id, $subject, $preview, $id]);
    $pdo->prepare("INSERT INTO posts VALUES (?, '2026-11-01T00:00:00Z', ?, NULL, ?, ?, '[]', NULL, NULL, 'guest', NULL, 1)")
        ->execute([$id, $id, $subject, $body]);
};
$add('one-line', 'One line', "Only line here.\\n");
$add('image-line', 'With image', "Intro line.\\nImage-v1: " . str_repeat('a', 64) . " png 10 10 100\\n\\nAfter the image.\\n");
echo $path;
`, project], { encoding: 'utf8' });
const section = execFileSync('php', ['-r', 'include $argv[1];', project + '/templates/pages/offline_reader.php'], { encoding: 'utf8' });
const styles = ['site', 'sqlite', 'thread-list', 'tags'].map(name => `<link rel="stylesheet" href="/assets/${name}.css">`).join('');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  try {
    for (const width of [390, 1280]) {
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
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">${styles}</head><body><main class="main">${section}</main><script src="/assets/sql-wasm.js"></script><script src="/assets/offline_reader.js"></script></body></html>` });
      });
      await page.goto('http://offline.test/threads/');
      await page.locator('.thread-card').first().waitFor();
      const preview = title => page.locator('.thread-card', { has: page.locator('h2 a', { hasText: title }) })
        .evaluate(card => { const p = card.querySelector('.thread-card__preview'); return p ? p.innerText : null; });
      const label = `${width}px`;
      assert.equal(await preview('Hello world'), 'First line preview.\nSecond line body.', `${label}: first and second lines`);
      assert.equal(await preview('The Rules of ZenMemes.com'), '1st Rule: You do not overthink the meme.', `${label}: title line skipped, next line shown`);
      assert.equal(await preview('One line'), 'Only line here.', `${label}: one-line body unchanged`);
      assert.equal(await preview('With image'), 'Intro line.\nAfter the image.', `${label}: image reference skipped`);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: no page overflow`);
      if (shots) await page.screenshot({ path: path.join(shots, `offline-cards-${width}.png`), fullPage: true });
      assert.deepEqual(errors, [], `${label}: no page errors`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
  console.log('offline card extra line: pass');
})().catch(error => { console.error(error); process.exit(1); });
