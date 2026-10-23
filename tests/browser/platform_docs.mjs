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
      assert.equal(await page.locator('.docs-content ol').count(), 1);
      assert.deepEqual(await page.locator('.docs-content ol > li').evaluateAll(nodes => nodes.map(e => e.value)), [1, 2, 3, 4, 5, 6]);
      assert.ok((await page.locator('.docs-content ol > li > p').first().innerText()).includes('retraction record, signs it'));
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
      let listFixture = null;
      if (process.env.DOCS_LIST_FIXTURE_PATH) {
        await page.goto(base + process.env.DOCS_LIST_FIXTURE_PATH);
        if (theme !== 'default') {
          await page.evaluate(theme => (document.getElementById('theme-stylesheet')?.remove(), document.documentElement.setAttribute('data-theme', theme)), theme);
          await page.evaluate(css => { const style = document.createElement('style'); style.textContent = css; document.head.append(style); }, await readFile('public/assets/theme-' + theme + '.css', 'utf8'));
        }
        const rootList = page.locator('.docs-content > ol').first();
        assert.deepEqual(await rootList.locator(':scope > li').evaluateAll(nodes => nodes.map(e => e.value)), [3, 3, 8]);
        assert.equal(await rootList.locator(':scope > li').first().locator(':scope > ul').count(), 1);
        assert.ok((await rootList.locator(':scope > li').first().innerText()).includes('Parent paragraph after children.'));
        assert.equal(await page.locator('.docs-list-source').count(), 2);
        assert.equal(await page.locator('.docs-list-scroll').count(), 1);
        assert.equal(await page.locator('.docs-content table').count(), 1);
        const deep = page.locator('.docs-list-scroll');
        assert.equal(await deep.locator('a').evaluate(e => { let n = 0; for (let p = e.parentElement; p; p = p.parentElement) { if (p.tagName === 'UL') n++; } return n; }), 8);
        assert.ok(await deep.locator('li p').last().evaluate(e => e.clientWidth > 120));
        await page.locator('[data-docs-return]').focus();
        let reached = false;
        for (let n = 0; n < 100; n++) {
          await page.keyboard.press('Tab');
          if (await page.evaluate(() => document.activeElement?.matches('.docs-list-scroll'))) { reached = true; break; }
        }
        assert.ok(reached, 'Tab reaches the deep-list region');
        assert.equal(await deep.evaluate(e => getComputedStyle(e).outlineStyle), 'solid');
        if (width === 390) {
          await page.keyboard.press('ArrowRight');
          await page.waitForTimeout(200);
          assert.ok(await deep.evaluate(e => e.scrollLeft > 0));
        }
        let reachedSource = false;
        for (let n = 0; n < 100; n++) {
          await page.keyboard.press('Tab');
          if (await page.evaluate(() => document.activeElement?.matches('.docs-list-source'))) { reachedSource = true; break; }
        }
        assert.ok(reachedSource, 'Tab reaches list-source recovery');
        const fallback = page.locator('.docs-list-source').last();
        await fallback.focus();
        assert.equal(await fallback.evaluate(e => getComputedStyle(e).outlineStyle), 'solid');
        assert.ok((await fallback.innerText()).includes('<!--example comment-->'));
        if (width === 390) {
          await page.keyboard.press('ArrowRight');
          await page.waitForTimeout(200);
          assert.ok(await fallback.evaluate(e => e.scrollLeft > 0));
        }
        await deep.evaluate(e => e.scrollLeft = 0);
        await fallback.evaluate(e => e.scrollLeft = 0);
        listFixture = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth, deepWidth: document.querySelector('.docs-list-scroll li p')?.clientWidth}));
        assert.ok(listFixture.scroll <= width + 1);
        await page.screenshot({path: output + '/lists-' + theme + '-' + width + '.png', fullPage: true});
        await rootList.locator('a[href="/docs/reference/v3_cli.md#delete-a-canonical-record"]').click();
        assert.ok(page.url().endsWith('/docs/reference/v3_cli.md#delete-a-canonical-record'));
        assert.equal(await page.locator('#delete-a-canonical-record').count(), 1);
        listFixture.cellLinkNavigation = 'passed';
      }
      let taskFixture = null;
      if (process.env.DOCS_TASK_FIXTURE_PATH) {
        if (width === 1280 && theme === 'default') {
          await page.goto(base + '/tools/');
          await page.locator('a[href="/docs/"]').first().click();
          await page.getByRole('link', {name: 'Browse all docs', exact: true}).click();
          const onboarding = page.locator('[data-docs-results] a[href^="/docs/plans/agent_api_onboarding_checklist.md"]');
          for (let n = 0; n < 20 && !(await onboarding.count()); n++) {
            await page.locator('.docs-pages a', {hasText: 'Next'}).click();
          }
          assert.ok(await onboarding.count(), 'Checklist is discoverable through ordinary Docs browsing');
          await onboarding.first().click();
          assert.ok(await page.locator('.docs-task-label input:disabled').count());
        }
        await page.goto(base + process.env.DOCS_TASK_FIXTURE_PATH);
        if (theme !== 'default') {
          await page.evaluate(theme => (document.getElementById('theme-stylesheet')?.remove(), document.documentElement.setAttribute('data-theme', theme)), theme);
          await page.evaluate(css => { const style = document.createElement('style'); style.textContent = css; document.head.append(style); }, await readFile('public/assets/theme-' + theme + '.css', 'utf8'));
        }
        const boxes = page.locator('.docs-content input[type="checkbox"]');
        assert.equal(await boxes.count(), 5);
        const pending = page.getByRole('checkbox', {name: 'Pending task with CLI wrapped detail.', exact: true});
        assert.equal(await pending.count(), 1);
        assert.equal(await pending.isChecked(), false);
        assert.equal(await pending.isDisabled(), true);
        assert.equal(await page.getByRole('checkbox', {name: 'Child task', exact: true}).isChecked(), true);
        assert.equal(await page.getByRole('checkbox', {name: 'Complete bold task', exact: true}).isChecked(), true);
        assert.equal(await page.getByRole('checkbox', {name: 'Same task', exact: true}).count(), 2);
        assert.ok(await boxes.evaluateAll(nodes => nodes.every(e => e.disabled && !e.name && !e.id && e.labels.length === 1)));
        assert.equal(await page.locator('.docs-task-item').first().evaluate(e => getComputedStyle(e).listStyleType), 'none');
        const before = await boxes.evaluateAll(nodes => nodes.map(e => e.checked));
        for (const box of await boxes.all()) {
          await box.evaluate(e => e.scrollIntoView({block: 'center', behavior: 'instant'}));
          await page.waitForTimeout(100);
          const rect = await box.boundingBox();
          assert.ok(rect && rect.width >= 12 && rect.height >= 12);
          await page.mouse.click(rect.x + rect.width / 2, rect.y + rect.height / 2);
          await page.keyboard.press('Space');
          await page.waitForTimeout(200);
        }
        assert.deepEqual(await boxes.evaluateAll(nodes => nodes.map(e => e.checked)), before);
        await page.locator('[data-docs-return]').focus();
        let reachedTaskLink = false;
        for (let n = 0; n < 100; n++) {
          await page.keyboard.press('Tab');
          assert.equal(await page.evaluate(() => document.activeElement?.matches('.docs-content input[type="checkbox"]')), false);
          if (await page.evaluate(() => document.activeElement?.matches('.docs-task-label a'))) { reachedTaskLink = true; break; }
        }
        assert.ok(reachedTaskLink);
        assert.equal(await page.locator('.docs-list-source').count(), 1);
        assert.ok((await page.locator('.docs-list-source').innerText()).includes('[x] Source only'));
        taskFixture = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth, boxes: [...document.querySelectorAll('.docs-task-label input')].map(e => ({checked:e.checked, disabled:e.disabled, width:e.getBoundingClientRect().width, label:e.labels[0].innerText}))}));
        assert.ok(taskFixture.scroll <= width + 1);
        await page.screenshot({path: output + '/tasks-' + theme + '-' + width + '.png', fullPage: true});
        await page.locator('.docs-task-label a[href="/docs/reference/v3_cli.md#delete-a-canonical-record"]').click();
        assert.ok(page.url().endsWith('/docs/reference/v3_cli.md#delete-a-canonical-record'));
        assert.equal(await page.locator('#delete-a-canonical-record').count(), 1);
        taskFixture.linkNavigation = 'passed';
      }
      evidence.push({ width, theme, javaScript: false, tables: 4, tableSources: 0, procedureItems: 6, keyboard: 'passed', cliFragment: 'passed', dimensions, fixture, listFixture, taskFixture });
      await context.close();
    }
  }
  await writeFile(output + '/evidence.json', JSON.stringify({ base, evidence }, null, 2));
  console.log('PASS Docs desktop/mobile, four themes, no JavaScript, keyboard scroll/focus and CLI fragment (8 scenarios)');
} finally {
  await browser.close();
}
