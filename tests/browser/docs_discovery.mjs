import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const output = process.env.DOCS_EVIDENCE_ROOT || '/tmp/docs-discovery-browser';
const targets = [
  ['dynamic', process.env.DOCS_DYNAMIC_BASE_URL || 'http://127.0.0.1:8184'],
  ['static', process.env.DOCS_STATIC_BASE_URL || 'http://127.0.0.1:8185'],
];
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
const evidence = [];
async function libraryReady(page) {
  await page.waitForFunction(() => { const root = document.querySelector('[data-docs-library]'); return root?.dataset.docsReady === 'true' && root.dataset.docsQuery === location.search; });
}
// Filters are link lists inside a closed <details>; folder links may sit in collapsed branches.
async function chooseFilter(page, key, value) {
  await page.locator('.docs-filters').evaluate(details => { details.open = true; });
  const link = page.locator(`.docs-filters a[href*="${key}=${encodeURIComponent(value).replace(/%20/g, '+')}"]`).first();
  await link.evaluate(node => { for (let parent = node.closest('details'); parent; parent = parent.parentElement.closest('details')) parent.open = true; });
  await link.click(); await libraryReady(page);
}
async function clearAll(page) {
  await page.locator('[data-docs-chips] a', { hasText: 'Clear all' }).click(); await libraryReady(page);
}
async function applyTheme(page, theme) {
  if (theme === 'default') return;
  await page.evaluate(theme => { document.getElementById('theme-stylesheet')?.remove(); document.documentElement.dataset.theme = theme; }, theme);
  await page.addStyleTag({ content: await readFile('public/assets/theme-' + theme + '.css', 'utf8') });
}
async function icon(page, selector) {
  const node = page.locator(selector).first();
  assert.ok(await node.isVisible());
  const result = await node.evaluate(e => {
    const style = getComputedStyle(e, '::before');
    return { image: style.backgroundImage, width: style.width, height: style.height, content: style.content };
  });
  assert.ok(result.image.includes('data:image/svg+xml'), JSON.stringify(result));
  assert.ok(parseFloat(result.width) > 0 && parseFloat(result.height) > 0);
  return result;
}
try {
  for (const [mode, base] of targets.filter(([mode]) => !process.env.DOCS_ONLY_MODE || process.env.DOCS_ONLY_MODE === mode)) for (const width of [1280, 390]) for (const theme of ['default', 'word97', 'chicago', 'midnight']) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
    // Keep preference cookies from switching these anonymous static scenarios
    // to the existing personalized dynamic path. Theme CSS is applied below.
    if (mode === 'static') await context.addInitScript(() => {
      Object.defineProperty(document, 'cookie', { get: () => '', set: () => {} });
    });
    const page = await context.newPage(); page.setDefaultTimeout(15000);
    const failures = []; page.on('pageerror', error => failures.push(error.message));
    await page.goto(base + '/tools/'); await applyTheme(page, theme);
    const launcher = theme === 'default' ? null : await icon(page, '.tool-launcher-button[href="/docs/"]');
    const subnav = theme === 'default' ? null : await icon(page, '.tools-nav .nav-link[href="/docs/"]');
    if (theme !== 'default') await page.screenshot({ path: `${output}/${mode}-${theme}-${width}-tools.png` });
    const retryIndex = mode === 'dynamic' && width === 1280 && theme === 'default';
    let failedIndex = false;
    if (retryIndex) await context.route(base + '/docs/_search.json', route => {
      if (!failedIndex) { failedIndex = true; return route.fulfill({ status: 503, body: 'Temporarily unavailable' }); }
      return route.continue();
    });
    await page.locator('.tool-launcher-button[href="/docs/"]').click();
    // Tools opens the curated landing view; the search index loads only for results.
    await page.locator('.docs-topics').waitFor();
    if (mode === 'static') assert.ok((await page.content()).includes('route-source: static-html'), page.url());
    await applyTheme(page, theme);
    const search = page.locator('[name=q]');
    await search.focus(); await page.keyboard.type('SCHEMA_VERSION');
    await search.press('Enter');
    if (retryIndex) { await page.locator('[data-docs-error]').waitFor(); await page.locator('[data-docs-retry]').click(); }
    await libraryReady(page);
    await chooseFilter(page, 'folder', 'Project');
    const contributing = page.locator('[data-docs-results] a[href*="CONTRIBUTING.md"]');
    await contributing.waitFor(); await contributing.focus();
    await Promise.all([page.waitForURL('**/docs/_project/CONTRIBUTING.md?**'), page.keyboard.press('Enter')]);
    await page.locator('.docs-content').waitFor();
    assert.ok(page.url().includes('q=SCHEMA_VERSION'));
    if (mode === 'static') assert.ok((await page.content()).includes('route-source: static-html'));
    await page.locator('[data-docs-return]').click(); await libraryReady(page);
    assert.equal(await page.locator('[name=q]').inputValue(), 'SCHEMA_VERSION');
    await clearAll(page);
    await chooseFilter(page, 'group', 'Plans and history');
    await page.locator('[name=q]').fill('docs_discovery'); await page.locator('[name=q]').press('Enter'); await libraryReady(page);
    await page.locator('[data-docs-results] a').first().waitFor();
    assert.ok((await page.locator('[data-docs-results]').innerText()).includes('docs/plans/'));
    await clearAll(page);
    await page.locator('[name=q]').fill('no-such-doc-7aa32'); await page.locator('[name=q]').press('Enter'); await libraryReady(page);
    await page.locator('[data-docs-empty]').waitFor();
    assert.equal(await page.locator('[data-docs-count]').innerText(), '0 documents');
    await page.locator('[data-docs-empty] a', { hasText: 'Clear filters' }).click(); await libraryReady(page);
    await applyTheme(page, theme);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Page-wide overflow');
    await page.screenshot({ path: `${output}/${mode}-${theme}-${width}-library.png` });
    const asset = await page.goto(base + '/docs/plans/backlog/feature-flags-redesign.html');
    assert.equal(asset.status(), 200); assert.equal(await page.locator('.docs-content script').count(), 0);
    const download = await context.request.get(base + '/docs/_download/docs/examples/secrets.php.example');
    assert.equal(download.headers()['content-type'], 'application/octet-stream');
    assert.ok(download.headers()['content-disposition'].includes('attachment'));
    assert.equal(download.headers()['x-content-type-options'], 'nosniff');
    const missing = await page.goto(base + '/docs/no-such-document.md?q=recovery');
    assert.equal(missing.status(), 404);
    const retried = page.waitForResponse(r => r.url().includes('/docs/no-such-document.md'));
    await page.locator('[data-docs-document-retry]').click(); assert.equal((await retried).status(), 404);
    await page.locator('[data-docs-return]').click(); assert.equal(await page.locator('[name=q]').inputValue(), 'recovery');
    assert.deepEqual(failures, []);
    evidence.push({ mode, width, theme, launcher, subnav, keyboardSearchReturn: 'passed', historyEmptyReset: 'passed', inertAssets: 'passed', headers: 'passed', missingRetry: 'passed', searchIndexRetry: retryIndex ? 'passed' : 'shared path', overflow: 'passed' });
    console.log('PASS', mode, width, theme); await context.close();
  }
  await writeFile(output + '/evidence.json', JSON.stringify({ targets, evidence }, null, 2));
} finally { await browser.close(); }
