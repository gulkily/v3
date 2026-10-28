import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const html = mode => execFileSync('php', ['tests/Support/attribution_cutoff_fixture.php', mode], { encoding: 'utf8' });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const shots = process.env.SCREENSHOT_DIR || '/tmp';
async function setup(mode, options = {}) {
  const context = await browser.newContext(options);
  await context.route('http://flags.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path.startsWith('/assets/')) return route.fulfill({ body: await readFile('public' + path), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
    return route.fulfill({ body: html(mode), contentType: 'text/html' });
  });
  const page = await context.newPage();
  await page.goto('http://flags.test/tools/feature-flags/');
  return page;
}
try {
  const page = await setup('on');
  const block = page.locator('[data-attribution-cutoff]');
  assert.equal(await block.locator('time').innerText(), '2026-10-23 15:04:37 UTC');
  assert.equal(await page.getByLabel('Move to (UTC)').inputValue(), '2026-10-23T15:04');
  for (const width of [1150, 375]) {
    await page.setViewportSize({ width, height: 800 });
    for (const theme of ['light', 'word97', 'chicago', 'midnight']) {
      await page.evaluate(theme => { document.documentElement.dataset.theme = theme; }, theme);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${theme}/${width} overflow`);
      for (const control of [page.getByLabel('Move to (UTC)'), page.getByRole('button', { name: 'Move cutoff' })]) {
        const bounds = await control.boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= width, `${theme}/${width} clipped control`);
      }
      await block.screenshot({ path: `${shots}/attribution-cutoff-${theme}-${width}.png` });
    }
  }

  await page.addInitScript(() => {
    window.ForumBrowserSigning = { ensureActionIdentity: async () => {}, signCanonicalRecord: async () => 'fixture-signature' };
  });
  let fail = false;
  await page.route('**/api/prepare_attribution_cutoff', route => {
    assert.equal(new URLSearchParams(route.request().postData()).get('cutoff_at'), '2026-01-01T00:00');
    return route.fulfill({ status: fail ? 400 : 200, json: fail ? { status: 'error', error: 'The attribution cutoff cannot be in the future.' }
      : { status: 'ok', canonical_record: 'fixture', prepare_token: 'token', record_id: 'id', record_path: 'path' } });
  });
  await page.route('**/api/finalize_attribution_cutoff', route => {
    assert.equal(new URLSearchParams(route.request().postData()).get('detached_signature'), 'fixture-signature');
    return route.fulfill({ json: { status: 'ok', attribution_cutoff_at: '2026-01-01T00:00:00Z' } });
  });
  await page.reload();
  const status = page.locator('[data-attribution-cutoff-status]');
  await page.getByLabel('Move to (UTC)').fill('2026-01-01T00:00');
  fail = true;
  await page.getByRole('button', { name: 'Move cutoff' }).click();
  await page.waitForFunction(() => /future/.test(document.querySelector('[data-attribution-cutoff-status]').textContent));
  assert.equal(await block.locator('time').innerText(), '2026-10-23 15:04:37 UTC', 'Failure keeps the cutoff');
  assert.equal(await page.getByLabel('Move to (UTC)').inputValue(), '2026-01-01T00:00', 'Failure keeps the entry');
  fail = false;
  await page.getByRole('button', { name: 'Move cutoff' }).click();
  await page.waitForFunction(() => document.querySelector('[data-attribution-cutoff-status]').textContent === 'Saved.');
  assert.equal(await block.locator('time').innerText(), '2026-01-01 00:00:00 UTC');

  const off = await setup('off');
  assert.equal(await off.locator('[data-attribution-cutoff]').isVisible(), false, 'No cutoff block before first switch-on');
  await off.addInitScript(() => {
    window.ForumBrowserSigning = { ensureActionIdentity: async () => {}, signCanonicalRecord: async () => 'fixture-signature' };
  });
  await off.route('**/api/prepare_feature_flag_change', route => route.fulfill({ json: { status: 'ok', wrote_record: 'yes', canonical_record: 'fixture', prepare_token: 'token', record_id: 'id', record_path: 'path' } }));
  await off.route('**/api/finalize_feature_flag_change', route => route.fulfill({ json: { status: 'ok', effective_value: 'true', source: 'site', attribution_cutoff_at: '2026-10-23T16:00:00Z' } }));
  await off.reload();
  await off.getByRole('switch', { name: 'Verified attribution' }).click();
  await off.waitForFunction(() => !document.querySelector('[data-attribution-cutoff]').hidden);
  assert.equal(await off.locator('[data-attribution-cutoff] time').innerText(), '2026-10-23 16:00:00 UTC', 'First switch-on shows the new cutoff');

  const readonly = await setup('readonly');
  assert.equal(await readonly.locator('[data-attribution-cutoff] time').innerText(), '2026-10-23 15:04:37 UTC');
  assert.equal(await readonly.locator('[data-attribution-cutoff-form]').count(), 0);
  const nojs = await setup('on', { javaScriptEnabled: false });
  assert.equal(await nojs.locator('[data-attribution-cutoff] time').isVisible(), true);
  console.log('PASS cutoff display, move success/failure, first switch-on, read-only and no-JS; light/word97/chicago/midnight at 375/1150px');
} finally {
  await browser.close();
}
