import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const reader = await readFile(new URL('../../public/assets/paned_activity_reader.js', import.meta.url), 'utf8');
const row = (id, view) => `<div class="paned-list-row" role="option" tabindex="0" data-paned-activity-id="${id}" data-paned-activity-view-${view}="1"><span class="paned-list-from">approval</span><span class="paned-list-subject">Event ${id}</span><span class="paned-list-date">Today</span></div>`;
const detail = (id, security) => `<article data-paned-activity-content-item-id="${id}"><div class="body">${security ? 'Acceptance receipt' : 'Ordinary evidence'} ${id}</div></article>`;
function fixture(view) {
  return `<!doctype html><title>Forte Activity test</title><div class="paned-board-layout">
    <div data-paned-activity-filter-tree>${['all', 'approval', 'security'].map(key => `<div role="option" tabindex="0" data-paned-activity-view="${key}">${key}</div>`).join('')}</div>
    <div data-paned-sort-head><button data-paned-sort-column="date" data-paned-sort-href="/forte/activity/?view=${view}&sort=date&dir=asc">Date</button></div>
    <div data-paned-activity-list-body>${row(1, view)}
      <div data-paned-activity-load-more-group><button data-paned-activity-load-more data-paned-activity-view="${view}" data-paned-activity-has-more="1" data-paned-activity-cursor="next">Load more</button></div>
    </div><div data-paned-activity-content-pane><article data-paned-activity-content-placeholder>Select an event</article></div>
    </div><span data-paned-activity-status-count></span><script>${reader}</script>`;
}
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (const width of [1280, 390]) {
    const context = await browser.newContext({ viewport: { width, height: 844 } });
    const page = await context.newPage();
    let failDetail = true, failPage = true, slowDetail = false, pageRequests = 0;
    let releaseSlow, slowStarted;
    const slowReady = new Promise(resolve => { slowStarted = resolve; });
    await page.route('http://activity.test/**', async route => {
      const url = new URL(route.request().url());
      const view = url.searchParams.get('view') || 'all';
      if (url.pathname === '/api/forte_activity_detail') {
        const id = url.searchParams.get('id');
        if (id === '2' && failDetail) { failDetail = false; await route.fulfill({ status: 500, body: 'failed' }); return; }
        if (id === '3' && slowDetail) {
          slowDetail = false;
          await new Promise(resolve => { releaseSlow = resolve; slowStarted(); });
        }
        await route.fulfill({ json: { status: 'ok', html: detail(id, view === 'security') } });
      } else if (url.pathname === '/api/forte_activity_page') {
        pageRequests++;
        assert.notEqual(view, 'security', 'security rows must not be prefetched into ordinary documents');
        if (view === 'all' && url.searchParams.has('cursor') && failPage) { failPage = false; await route.fulfill({ status: 500, body: 'failed' }); return; }
        const next = url.searchParams.has('cursor');
        await route.fulfill({ json: { status: 'ok', html: next ? row(2, view) + row(3, view) : row(2, view) + row(1, view), detail_html: '', has_more: !next, next_cursor: next ? null : { id: 2, sort_value: 'today' } } });
      } else {
        await route.fulfill({ contentType: 'text/html', body: fixture(view) });
      }
    });
    await page.goto('http://activity.test/forte/activity/');
    await page.getByText('Ordinary evidence 1', { exact: true }).waitFor();
    await page.waitForFunction(() => document.querySelector('[data-paned-activity-id="2"]'));
    assert.equal(pageRequests, 1, 'warm only one page of each other ordinary category');
    await page.locator('[data-paned-activity-view="approval"][role="option"]').click();
    assert.match(page.url(), /view=approval/);
    assert.deepEqual(await page.locator('.paned-list-row:visible').evaluateAll(rows => rows.map(row => row.dataset.panedActivityId)), ['2', '1']);
    const second = page.locator('[data-paned-activity-id="2"]');
    await second.click();
    await page.getByRole('button', { name: 'Retry', exact: true }).click();
    await page.getByText('Ordinary evidence 2', { exact: true }).waitFor();
    await second.focus();
    await page.keyboard.press('ArrowDown');
    assert.equal(await page.locator('[data-paned-activity-id="1"]').getAttribute('aria-selected'), 'true');
    await page.locator('[data-paned-activity-view="all"][role="option"]').click();
    const more = page.locator('[data-paned-activity-load-more][data-paned-activity-view="all"]');
    await more.click();
    await page.waitForFunction(() => !document.querySelector('[data-paned-activity-load-more][data-paned-activity-view="all"]').disabled);
    assert.equal(await more.isVisible(), true, 'failed page remains retryable');
    await more.click();
    await page.locator('[data-paned-activity-id="3"]').waitFor();
    assert.equal(await page.locator('[data-paned-activity-id="2"]').count(), 1, 'overlapping pages must merge rows');
    slowDetail = true;
    await page.locator('[data-paned-activity-id="3"]').click();
    await slowReady;
    const staleResponse = page.waitForResponse(response => response.url().includes('forte_activity_detail?id=3'));
    await page.getByRole('button', { name: 'Date', exact: true }).click();
    await page.waitForURL(/dir=asc/);
    await page.getByText('Ordinary evidence 1', { exact: true }).waitFor();
    releaseSlow(); await staleResponse;
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await page.locator('[data-paned-activity-content-item-id="3"]').count(), 0, 'late detail must not populate a replaced layout');
    await page.locator('[data-paned-activity-view="security"][role="option"]').click();
    await page.waitForURL(/view=security/);
    await page.getByText('Acceptance receipt 1', { exact: true }).waitFor();
    await page.locator('[data-paned-activity-view="all"][role="option"]').click();
    await page.waitForURL(url => !url.searchParams.has('view'));
    await page.getByText('Ordinary evidence 1', { exact: true }).waitFor();
    assert.equal(await page.getByText('Acceptance receipt 1', { exact: true }).count(), 0);
    await context.close();
  }
  console.log('PASS Forte Activity desktop/mobile: category membership, bounded preload, detail/page retry, keyboard selection, duplicate-page merge, stale sort response, security document boundaries');
} finally { await browser.close(); }
