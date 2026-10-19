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
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  const before = downloads;
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-input"]').value === 'SELECT 99 AS edited');
  assert.equal(await input.inputValue(), 'SELECT 99 AS edited');
  assert.equal(downloads, before);
  await page.getByRole('button', { name: 'Close', exact: true }).click();
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('7'));
  await input.fill('SELECT 88 AS second_draft');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-input"]').value === 'SELECT 99 AS edited');
  assert.equal(await input.inputValue(), 'SELECT 99 AS edited');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  await page.reload();
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('88'));
  assert.equal(await input.inputValue(), 'SELECT 88 AS second_draft');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
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
  const preservedSql = await input.inputValue();
  await page.getByRole('button', { name: 'Refresh saved database', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-status"]').textContent.includes('could not'));
  assert.equal(await input.inputValue(), preservedSql);
  fail = false;
  await page.getByRole('button', { name: 'Refresh saved database', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-source-details"]').textContent.includes('test-revision'));
  await input.fill('SELECT FROM broken');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-status"]').dataset.state === 'error');
  assert.equal(await input.inputValue(), 'SELECT FROM broken');
  await input.fill('SELECT 64 AS recovered');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('64'));
  fail = true;
  await page.getByRole('button', { name: 'Refresh saved database', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-status"]').dataset.state === 'error');
  await input.fill('SELECT 65 AS still_usable');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('65'));
  await page.evaluate(() => caches.delete('forum-query-read-model-v1'));
  await page.addInitScript(() => Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('storage denied'); } }));
  fail = false;
  await page.goto('https://query.test/codebase');
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('42'));
  await input.fill('SELECT 55 AS memory_draft');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  await page.locator('[data-query-key="two"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('7'));
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  await page.locator('[data-query-key="one"]').click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-input"]').value === 'SELECT 55 AS memory_draft');
  assert.equal(await input.inputValue(), 'SELECT 55 AS memory_draft');
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  for (const width of [1280, 390]) {
    await page.setViewportSize({ width, height: 844 });
    await page.evaluate(() => {
      const range = document.createRange();
      range.selectNodeContents(document.querySelector('#background-selection'));
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
      window.scrollTo(0, 0);
    });
    const selection = await page.evaluate(() => window.getSelection().toString());
    await page.locator('[data-query-key="one"]').click();
    await page.locator('[data-query-dialog][open]').waitFor();
    for (let i = 0; i < 20; i++) {
      await page.keyboard.press('Tab');
      assert.equal(await page.evaluate(() => document.querySelector('[data-query-dialog]').contains(document.activeElement) || document.activeElement === document.body), true);
    }
    assert.equal(await page.evaluate(() => {
      const d = document.querySelector('[data-query-dialog]');
      return d.getBoundingClientRect().width <= innerWidth && d.scrollWidth <= d.clientWidth + 1;
    }), true, 'dialog fits narrow screens without horizontal page overflow');
    const savedScroll = await page.evaluate(() => {
      const d = document.querySelector('[data-query-dialog]');
      d.scrollTop = 200;
      return d.scrollTop;
    });
    await page.keyboard.press('Escape');
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
    assert.equal(await page.evaluate(() => window.scrollY), 0);
    assert.equal(await page.evaluate(() => window.getSelection().toString()), selection);
    assert.equal(await page.locator('[data-query-key="one"]').evaluate(el => el === document.activeElement), true);
    assert.equal(await page.getByLabel('Background draft').inputValue(), 'keep me');
    await page.locator('[data-query-key="one"]').click();
    await page.locator('[data-query-dialog][open]').waitFor();
    assert.equal(await page.locator('[data-query-dialog]').evaluate(el => el.scrollTop), savedScroll);
    await page.getByRole('button', { name: 'Close', exact: true }).click();
  await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
  }
  console.log('PASS separate drafts and navigation recovery, saved-data reuse, corruption rejection, codebase dialog load retry, auto-run, edit, close/reopen, background and standalone');
} finally { await browser.close(); }
