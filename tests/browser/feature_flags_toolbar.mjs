import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const html = mode => execFileSync('php', ['tests/Support/feature_flags_toolbar_fixture.php', mode], { encoding: 'utf8' });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
async function setup(context, mode = '', themed = true) {
  await context.route('http://flags.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path.startsWith('/assets/')) return route.fulfill({ body: await readFile('public' + path), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
    return route.fulfill({ body: html(mode), contentType: 'text/html' });
  });
  const page = await context.newPage();
  await page.goto('http://flags.test/tools/feature-flags/');
  if (themed) for (const theme of ['word97', 'chicago', 'midnight']) await page.addStyleTag({ url: '/assets/theme-' + theme + '.css' });
  return page;
}
try {
  const context = await browser.newContext();
  const page = await setup(context);
  const search = page.getByRole('searchbox', { name: 'Search flags' });
  const chip = status => page.locator(`[data-flag-filter="${status}"]`);
  const visible = () => page.locator('[data-feature-flag-row]:visible').evaluateAll(rows => rows.map(row => row.dataset.flagKey));
  const counts = () => page.locator('[data-flag-filter-count]').allTextContents();
  assert.deepEqual(await counts(), ['4', '3', '1', '2']);
  for (const [status, expected] of [
    ['enabled', ['TEST_ALPHA', 'TEST_BETA', 'TEST_DELTA']],
    ['overridden', ['TEST_ALPHA']], ['locked', ['TEST_GAMMA', 'TEST_DELTA']],
  ]) {
    await chip(status).click();
    assert.deepEqual(await visible(), expected);
    assert.equal(await chip(status).getAttribute('aria-pressed'), 'true');
    assert.equal(await page.locator('[data-flag-filter][aria-pressed="true"]').count(), 1);
  }
  await chip('all').click();
  for (const query of ['  ALPHA  ', 'test_alpha', 'apricot']) {
    await search.fill(query);
    assert.deepEqual(await visible(), ['TEST_ALPHA']);
    assert.deepEqual(await counts(), ['1', '1', '1', '0']);
    assert.equal(await page.locator('.feature-flag-group:visible').count(), 1);
  }
  await chip('locked').click();
  assert.deepEqual(await visible(), []);
  assert.equal(await page.getByText('No flags match.', { exact: true }).isVisible(), true);
  assert.equal(await page.locator('.feature-flag-group:visible').count(), 0);
  await search.fill('shared');
  assert.deepEqual(await visible(), ['TEST_GAMMA', 'TEST_DELTA']);
  assert.deepEqual(await counts(), ['3', '2', '0', '2']);
  await search.fill(''); await chip('all').click();
  assert.equal((await visible()).length, 4);
  assert.equal(await page.locator('[data-feature-flags-results]').innerText(), '4 of 4 flags shown');
  // Badge/status text must not leak into the search index.
  await search.fill('overridden'); assert.deepEqual(await visible(), []);
  await search.fill('');
  await page.locator('h1').click(); await page.keyboard.press('/');
  assert.equal(await search.evaluate(node => node === document.activeElement), true);
  await search.press('/'); assert.equal(await search.inputValue(), '/');
  await search.fill('');
  for (const target of ['#other-input', '#editor']) {
    await page.locator(target).focus(); await page.keyboard.press('/');
    assert.equal(await page.locator(target).evaluate(node => node === document.activeElement), true);
  }
  await chip('all').focus(); await page.keyboard.press('Control+/');
  assert.equal(await chip('all').evaluate(node => node === document.activeElement), true);
  await page.keyboard.press('Tab');
  assert.equal(await chip('enabled').evaluate(node => node === document.activeElement && getComputedStyle(node).outlineStyle !== 'none'), true);

  for (const width of [1150, 375]) {
    await page.setViewportSize({ width, height: 800 });
    for (const theme of ['light', 'dark', 'console', 'word97', 'chicago', 'midnight']) {
      await page.evaluate(theme => { document.documentElement.dataset.theme = theme; window.scrollTo(0, 0); }, theme);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${theme}/${width} overflow`);
      for (const button of await page.locator('[data-flag-filter]').all()) {
        const bounds = await button.boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= width, `${theme}/${width} clipped chip`);
      }
      await page.evaluate(() => window.scrollTo(0, document.querySelector('[data-feature-flags-toolbar]').getBoundingClientRect().top + scrollY + 20));
      assert(Math.abs((await page.locator('[data-feature-flags-toolbar]').boundingBox()).y) < 1);
      await page.evaluate(() => { document.querySelector('.app-version-banner').hidden = false; });
      await page.waitForFunction(() => parseFloat(document.querySelector('[data-feature-flags-toolbar]').style.top) > 0);
      const banner = await page.locator('.app-version-banner').boundingBox();
      const toolbar = await page.locator('[data-feature-flags-toolbar]').boundingBox();
      assert(toolbar.y >= banner.y + banner.height - 1, `${theme}/${width} banner overlap`);
      await page.screenshot({ path: `/tmp/feature-flags-${theme}-${width}.png`, fullPage: true });
      await page.evaluate(() => { document.querySelector('.app-version-banner').hidden = true; });
      await page.waitForFunction(() => document.querySelector('[data-feature-flags-toolbar]').style.top === '0px');
    }
  }
  // Exercise the existing signed-save lifecycle with controlled success, failure,
  // and delayed responses; real signatures are covered by the operator harness.
  await page.addInitScript(() => {
    window.ForumBrowserSigning = { ensureActionIdentity: async () => {}, signCanonicalRecord: async () => 'fixture-signature' };
  });
  let failSave = false, holdSave = false, releaseSave;
  let pendingValue, prepared = 0, finalized = 0;
  await page.route('**/api/prepare_feature_flag_change', route => {
    prepared++;
    pendingValue = new URLSearchParams(route.request().postData()).get('value');
    return route.fulfill({ status: failSave ? 403 : 200, json: failSave
      ? { status: 'error', error: 'Rejected fixture save' }
      : { status: 'ok', wrote_record: 'yes', canonical_record: 'fixture', prepare_token: 'token', record_id: 'id', record_path: 'path' } });
  });
  await page.route('**/api/finalize_feature_flag_change', async route => {
    finalized++;
    assert.equal(new URLSearchParams(route.request().postData()).get('detached_signature'), 'fixture-signature');
    if (holdSave) await new Promise(resolve => { releaseSave = resolve; });
    return route.fulfill({ json: { status: 'ok', effective_value: pendingValue, source: 'site' } });
  });
  await page.reload();
  const alpha = page.locator('[data-flag-key="TEST_ALPHA"]');
  const beta = page.locator('[data-flag-key="TEST_BETA"]');
  const settled = () => page.waitForFunction(() => !document.querySelector('[data-flag-key="TEST_BETA"] .switch').disabled);
  await beta.getByRole('switch').click(); await settled();
  assert.deepEqual(await counts(), ['4', '2', '2', '2']);
  assert.equal(await beta.locator('.feature-flag-reset-form').isVisible(), true, 'New override exposes reset without reload');
  assert.equal(await beta.locator('[data-role="feature-flag-status"]').isVisible(), false, 'Changed switch communicates success without a notice');
  await chip('overridden').click();
  await beta.getByRole('button', { name: 'Reset to default' }).click(); await settled();
  assert.deepEqual(await visible(), ['TEST_ALPHA']);
  assert.deepEqual(await counts(), ['4', '3', '1', '2']);
  assert.equal(await search.evaluate(node => node === document.activeElement), true, 'Disappearing reset row recovers focus');
  await chip('enabled').click();
  await alpha.getByRole('switch').click();
  await page.waitForFunction(() => !document.querySelector('[data-flag-key="TEST_ALPHA"] .switch').disabled);
  assert.deepEqual(await visible(), ['TEST_BETA', 'TEST_DELTA']);
  assert.deepEqual(await counts(), ['4', '2', '0', '2']);
  assert.equal(await search.evaluate(node => node === document.activeElement), true, 'Disappearing toggled row recovers focus');
  failSave = true;
  await beta.getByRole('switch').click(); await settled();
  assert.equal(await beta.getByRole('switch').getAttribute('aria-checked'), 'true');
  assert.match(await beta.locator('[data-role="feature-flag-status"]').innerText(), /Rejected fixture save/);
  assert.deepEqual(await counts(), ['4', '2', '0', '2']);
  assert.equal(await beta.getByRole('switch').evaluate(node => node === document.activeElement), true);
  failSave = false; holdSave = true;
  await beta.getByRole('switch').click();
  for (let attempt = 0; attempt < 1000 && !releaseSave; attempt++) await new Promise(resolve => setTimeout(resolve, 10));
  assert(releaseSave, 'Delayed save reached finalization');
  await search.fill('beta');
  releaseSave(); await settled();
  assert.deepEqual(await visible(), []);
  assert.deepEqual(await counts(), ['1', '0', '1', '0']);
  assert.equal(await search.evaluate(node => node === document.activeElement), true, 'Pending saves do not steal focus');
  await search.fill(''); await chip('all').click();
  releaseSave = null;
  await alpha.getByRole('switch').click();
  for (let attempt = 0; attempt < 1000 && !releaseSave; attempt++) await new Promise(resolve => setTimeout(resolve, 10));
  assert(releaseSave);
  await page.locator('#other-input').focus();
  await page.locator('h1').click();
  assert.equal(await page.evaluate(() => document.activeElement === document.body), true);
  releaseSave();
  await page.waitForFunction(() => !document.querySelector('[data-flag-key="TEST_ALPHA"] .switch').disabled);
  assert.equal(await page.evaluate(() => document.activeElement === document.body), true, 'Moving focus away and then to the body relinquishes recovery');
  assert.equal(prepared, 6); assert.equal(finalized, 5);
  console.log('PASS confirmed toggle/reset refresh, newly overridden reset, failure preservation, disappearing-row focus, and delayed-save focus');

  const readonly = await setup(await browser.newContext(), 'readonly');
  assert.equal(await readonly.getByRole('switch').filter({ visible: true }).count(), 4);
  assert.equal(await readonly.locator('[role="switch"]:disabled').count(), 4);
  await readonly.locator('[data-flag-filter="locked"]').click();
  assert.equal(await readonly.locator('[data-feature-flag-row]:visible').count(), 2, 'Viewer permission is not locked state');
  const nojs = await setup(await browser.newContext({ javaScriptEnabled: false }), '', false);
  assert.equal(await nojs.locator('[data-feature-flags-toolbar]').isVisible(), false);
  assert.equal(await nojs.locator('[data-feature-flags-empty]').isVisible(), false);
  assert.equal(await nojs.locator('[data-feature-flag-row]:visible').count(), 4);
  assert.equal(await nojs.locator('form[method="post"][action="/tools/feature-flags/"]:visible').count(), 3);
  console.log('PASS toolbar search/status/counts/empty recovery, keyboard, read-only/no-JS, six themes at 375/1150px and sticky banner');
} finally {
  await browser.close();
}
