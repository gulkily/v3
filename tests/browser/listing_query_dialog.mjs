import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const html = mode => execFileSync('php', ['tests/Support/listing_query_fixture.php', mode], { encoding: 'utf8' });
const database = execFileSync('php', ['-r', `require "autoload.php"; $f = tempnam(sys_get_temp_dir(), "query-test-"); $p = new PDO("sqlite:" . $f); foreach (ForumRewrite\\ReadModel\\ReadModelSchema::statements() as $sql) { $p->exec($sql); } $p->exec("INSERT INTO metadata VALUES ('repository_head', 'test-revision')"); echo file_get_contents($f); unlink($f);`]);
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => console.error(error.message));
  let downloads = 0, fail = true;
  await page.route('https://query.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path.startsWith('/assets/')) {
      const body = await readFile(`public${path}`);
      await route.fulfill({ body, contentType: path.endsWith('.wasm') ? 'application/wasm' : path.endsWith('.css') ? 'text/css' : 'application/javascript' });
    } else if (path === '/downloads/read_model.sqlite3') {
      downloads++;
      await route.fulfill({ status: fail ? 503 : 200, body: fail ? 'unavailable' : database });
    } else { await route.fulfill({ body: html(path.includes('standalone') ? 'standalone' : ''), contentType: 'text/html' }); }
  });
  await page.goto('https://query.test/codebase');
  await page.locator('[data-query-key="one"]').click();
  await page.locator('[data-query-dialog][open]').waitFor();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-status"]').textContent.includes('could not'));
  fail = false;
  await page.getByRole('button', { name: 'Load Database', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('42'));
  const input = page.locator('[data-role="sqlite-query-input"]');
  await input.fill('SELECT 99 AS edited');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('99'));
  await page.keyboard.press('Escape');
  const before = downloads;
  await page.locator('[data-query-key="one"]').click();
  assert.equal(await input.inputValue(), 'SELECT 99 AS edited');
  assert.equal(downloads, before);
  await page.getByRole('button', { name: 'Close', exact: true }).click();
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('7'));
  await input.fill('SELECT 88 AS second_draft');
  await page.keyboard.press('Escape');
  await page.locator('[data-query-key="one"]').click();
  assert.equal(await input.inputValue(), 'SELECT 99 AS edited');
  await page.keyboard.press('Escape');
  await page.reload();
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('88'));
  assert.equal(await input.inputValue(), 'SELECT 88 AS second_draft');
  await page.keyboard.press('Escape');
  assert.equal(await page.getByLabel('Background draft').inputValue(), 'keep me');
  fail = true;
  await page.goto('https://query.test/standalone');
  await page.getByRole('button', { name: 'Load Database', exact: true }).click();
  await page.locator('[data-role="sqlite-query-panel"]:visible').waitFor();
  assert.equal(downloads, before, 'standalone reuses the saved database');
  await page.locator('[data-role="sqlite-query-input"]').fill('SELECT 123 AS standalone');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('123'));
  await page.evaluate(async () => {
    const cache = await caches.open('forum-query-read-model-v1');
    await cache.put('/downloads/read_model.sqlite3', new Response('broken database'));
  });
  await page.goto('https://query.test/codebase');
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-status"]').dataset.state === 'error');
  assert.equal(downloads, before, 'corrupt saved data is rejected without silent replacement');
  await page.evaluate(() => caches.delete('forum-query-read-model-v1'));
  await page.addInitScript(() => Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('storage denied'); } }));
  fail = false;
  await page.goto('https://query.test/codebase');
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('42'));
  await input.fill('SELECT 55 AS memory_draft');
  await page.keyboard.press('Escape');
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('7'));
  await page.keyboard.press('Escape');
  await page.locator('[data-query-key="one"]').click();
  assert.equal(await input.inputValue(), 'SELECT 55 AS memory_draft');
  console.log('PASS separate drafts and navigation recovery, saved-data reuse, corruption rejection, codebase dialog load retry, auto-run, edit, close/reopen, background and standalone');
} finally { await browser.close(); }
