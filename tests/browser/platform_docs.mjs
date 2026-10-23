import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { mkdir, writeFile, readFile } from 'node:fs/promises';

const base = process.env.DOCS_BASE_URL || 'http://127.0.0.1:8007';
const output = process.env.DOCS_EVIDENCE_ROOT || '/tmp/markdown-renderer-browser';
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const evidence = [];
try {
  for (const width of [1280, 390]) {
    for (const theme of ['default', 'word97', 'chicago', 'midnight']) {
      const context = await browser.newContext({ viewport: { width, height: 844 }, javaScriptEnabled: false, serviceWorkers: 'block' });
      const page = await context.newPage();
      page.setDefaultTimeout(12000);
      console.log("Checking", base, width, theme);
      if (width === 1280 && theme === 'default') {
        await page.goto(base + '/tools/');
        await page.locator('a[href="/docs/"]').first().click();
        await page.locator('a[href="/docs/reference/author_post_retraction.md"]').first().click();
      } else {
        await page.goto(base + '/docs/reference/author_post_retraction.md');
      }
      if (theme !== 'default') {
        // Exercise theme CSS independently of the disabled preference script.
        await page.evaluate(theme => (document.getElementById('theme-stylesheet')?.remove(), document.documentElement.setAttribute('data-theme', theme)), theme);
        await page.evaluate(css => { const style = document.createElement('style'); style.textContent = css; document.head.append(style); }, await readFile('public/assets/theme-' + theme + '.css', 'utf8'));
      }
      assert.equal(await page.locator('.docs-content table').count(), 4);
      assert.equal(await page.locator('.docs-table-source').count(), 0);
      assert.equal(await page.locator('.docs-content ol').count(), 6);
      assert.deepEqual(await page.locator('.docs-content ol').evaluateAll(nodes => nodes.map(e => e.start)), [1, 2, 3, 4, 5, 6]);
      assert.equal(await page.locator('.docs-content ol > li').count(), 6);
      assert.ok(await page.locator('.docs-content strong').count() > 0);
      const dimensions = await page.evaluate(() => ({
        width: innerWidth,
        scroll: document.documentElement.scrollWidth,
        tables: [...document.querySelectorAll('.docs-table-scroll')].map(e => ({ client: e.clientWidth, scroll: e.scrollWidth })),
      }));
      assert.ok(dimensions.scroll <= width + 1, JSON.stringify({ width, theme, dimensions }));
      const region = page.locator('.docs-table-scroll').first();
      await region.focus();
      assert.equal(await region.evaluate(e => document.activeElement === e), true);
      assert.equal(await region.evaluate(e => getComputedStyle(e).outlineStyle), 'solid');
      if (width === 390) {
        await page.keyboard.press('ArrowRight');
        await page.waitForTimeout(200);
        assert.ok(await region.evaluate(e => e.scrollLeft) > 0);
      }
      const code = page.locator('.docs-content pre[aria-label="Code"]').first();
      await code.focus();
      assert.equal(await code.evaluate(e => document.activeElement === e), true);
      assert.equal(await code.evaluate(e => getComputedStyle(e).whiteSpace), 'pre');
      assert.ok((await page.locator('.docs-content').innerText()).includes('(source unavailable)'));
      if (width === 390) {
        assert.ok(await code.evaluate(e => e.scrollWidth > e.clientWidth));
        await page.keyboard.press('ArrowRight');
        await page.waitForTimeout(200);
        assert.ok(await code.evaluate(e => e.scrollLeft > 0));
      }
      // Real Tab traversal must reach a scroll region, not just programmatic focus.
      await page.locator('[data-docs-return]').focus();
      let tabReachedRegion = false;
      for (let n = 0; n < 90; n++) {
        await page.keyboard.press('Tab');
        if (await page.evaluate(() => document.activeElement?.matches('.docs-table-scroll, .docs-content pre[aria-label="Code"]'))) {
          tabReachedRegion = true;
          break;
        }
      }
      assert.ok(tabReachedRegion);
      console.log('Capturing', width, theme);
      await page.screenshot({ path: output + '/' + theme + '-' + width + '.png', timeout: 12000 });
      console.log('Following CLI', width, theme);
      const cli = page.locator('.docs-content a[href="/docs/reference/v3_cli.md#delete-a-canonical-record"]').first();
      assert.ok(await cli.count());
      await cli.click({ timeout: 12000 });
      assert.ok(page.url().endsWith('/docs/reference/v3_cli.md#delete-a-canonical-record'));
      assert.equal(await page.locator('#delete-a-canonical-record').count(), 1);
      assert.ok(await page.locator('#delete-a-canonical-record').evaluate(e => Math.abs(e.getBoundingClientRect().top) < 180));
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'CLI overflow');
      let fixture = null;
      if (process.env.DOCS_TABLE_FIXTURE_PATH) {
        await page.goto(base + process.env.DOCS_TABLE_FIXTURE_PATH);
        if (theme !== 'default') {
          await page.evaluate(theme => (document.getElementById('theme-stylesheet')?.remove(), document.documentElement.setAttribute('data-theme', theme)), theme);
          await page.evaluate(css => { const style = document.createElement('style'); style.textContent = css; document.head.append(style); }, await readFile('public/assets/theme-' + theme + '.css', 'utf8'));
        }
        assert.deepEqual(await page.locator('table th').evaluateAll(nodes => nodes.slice(0, 3).map(e => getComputedStyle(e).textAlign)), ['left', 'center', 'right']);
        assert.ok(await page.locator('table th').first().evaluate(e => e.clientWidth >= 100 && e.clientHeight < 80), 'Columns remain readable beside long code');
        assert.equal(await page.locator('.docs-table-source').count(), 1);
        assert.ok((await page.locator('table').innerText()).includes('a|b'));
        assert.ok((await page.locator('table code').first().innerText()).includes('c|d'));
        assert.equal(await page.locator('table img, table script').count(), 0);
        assert.ok(await page.locator('table a[href="/docs/reference/v3_cli.md#delete-a-canonical-record"]').count());
        fixture = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth, alignment: [...document.querySelectorAll('table th')].map(e => getComputedStyle(e).textAlign)}));
        assert.ok(fixture.scroll <= width + 1);
        await page.screenshot({path: output + '/fixture-' + theme + '-' + width + '.png', fullPage: true});
        await page.locator('table a[href="/docs/reference/v3_cli.md#delete-a-canonical-record"]').click();
        assert.ok(page.url().endsWith('/docs/reference/v3_cli.md#delete-a-canonical-record'));
        assert.equal(await page.locator('#delete-a-canonical-record').count(), 1);
        fixture.cellLinkNavigation = 'passed';
      }
      evidence.push({ width, theme, javaScript: false, tables: 4, tableSources: 0, procedureItems: 6, keyboard: 'passed', cliFragment: 'passed', dimensions, fixture });
      await context.close();
    }
  }
  await writeFile(output + '/evidence.json', JSON.stringify({ base, evidence }, null, 2));
  console.log('PASS Docs desktop/mobile, four themes, no JavaScript, keyboard scroll/focus and CLI fragment (8 scenarios)');
} finally {
  await browser.close();
}
