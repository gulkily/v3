import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const html = mode => execFileSync('php', ['tests/Support/listing_query_fixture.php', mode], { encoding: 'utf8' });
const database = execFileSync('php', ['-r', '$f = tempnam(sys_get_temp_dir(), "query-test-"); unlink($f); $p = new PDO("sqlite::memory:"); $p->exec("VACUUM INTO " . $p->quote($f)); echo file_get_contents($f); unlink($f);']);
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => console.error(error.message));
  let downloads = 0, fail = true;
  await page.route('http://query.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path.startsWith('/assets/')) {
      const body = await readFile(`public${path}`);
      await route.fulfill({ body, contentType: path.endsWith('.wasm') ? 'application/wasm' : path.endsWith('.css') ? 'text/css' : 'application/javascript' });
    } else if (path === '/downloads/read_model.sqlite3') {
      downloads++;
      await route.fulfill({ status: fail ? 503 : 200, body: fail ? 'unavailable' : database });
    } else { await route.fulfill({ body: html(path.includes('standalone') ? 'standalone' : ''), contentType: 'text/html' }); }
  });
  await page.goto('http://query.test/codebase');
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
  assert.equal(await page.getByLabel('Background draft').inputValue(), 'keep me');
  await page.goto('http://query.test/standalone');
  await page.getByRole('button', { name: 'Load Database', exact: true }).click();
  await page.locator('[data-role="sqlite-query-panel"]:visible').waitFor();
  await page.locator('[data-role="sqlite-query-input"]').fill('SELECT 123 AS standalone');
  await page.getByRole('button', { name: 'Run Query', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="sqlite-query-results"]').textContent.includes('123'));
  console.log('PASS codebase dialog load retry, auto-run, edit, close/reopen, background and standalone');
} finally { await browser.close(); }
