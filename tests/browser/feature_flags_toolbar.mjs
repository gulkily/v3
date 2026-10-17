import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const html = mode => execFileSync('php', ['tests/Support/feature_flags_toolbar_fixture.php', mode], { encoding: 'utf8' });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
async function setup(context, mode = '') {
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
    for (const theme of ['light', 'dark', 'console']) {
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
  const readonly = await setup(await browser.newContext(), 'readonly');
  assert.equal(await readonly.getByRole('switch').filter({ visible: true }).count(), 4);
  assert.equal(await readonly.locator('[role="switch"]:disabled').count(), 4);
  await readonly.locator('[data-flag-filter="locked"]').click();
  assert.equal(await readonly.locator('[data-feature-flag-row]:visible').count(), 2, 'Viewer permission is not locked state');
  const nojs = await setup(await browser.newContext({ javaScriptEnabled: false }));
  assert.equal(await nojs.locator('[data-feature-flags-toolbar]').isVisible(), false);
  assert.equal(await nojs.locator('[data-feature-flags-empty]').isVisible(), false);
  assert.equal(await nojs.locator('[data-feature-flag-row]:visible').count(), 4);
  assert.equal(await nojs.locator('form[method="post"][action="/tools/feature-flags/"]').count(), 3);
  console.log('PASS toolbar search/status/counts/empty recovery, keyboard, read-only/no-JS, light/dark/console at 375/1150px and sticky banner');
} finally {
  await browser.close();
}
