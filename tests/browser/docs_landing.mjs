import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

// Docs landing/results acceptance. Stage 5: browser rendering matches the server's.
const base = process.env.DOCS_DYNAMIC_BASE_URL || 'http://127.0.0.1:8191';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
const queries = ['?group=Plans+and+history&page=2', '?category=Architecture&q=trust', '?folder=docs%2Fplans%2Farchive', '?q=no-such-doc-7aa32', '?page=1', '?q=retraction'];
// Compare what a reader sees and what assistive technology gets, ignoring whitespace.
const snapshot = page => page.evaluate(() => {
  const root = document.querySelector('[data-docs-library]');
  const clean = node => node.outerHTML.replace(/\s+/g, ' ').replace(/> </g, '><').trim();
  return {
    chips: root.querySelector('[data-docs-chips]').hidden ? '' : clean(root.querySelector('[data-docs-chips]')),
    count: root.querySelector('[data-docs-count]').textContent.trim(),
    rows: clean(root.querySelector('[data-docs-results]')),
    pages: root.querySelector('[data-docs-pages]').hidden ? '' : root.querySelector('[data-docs-pages]').innerText.replace(/\s+/g, ' ').trim(),
    empty: root.querySelector('[data-docs-empty]').hidden,
    current: [...root.querySelectorAll('[aria-current]')].map(node => node.getAttribute('href') || node.textContent.trim()),
    open: [...root.querySelectorAll('[data-docs-folder-branch]')].filter(node => node.open).map(node => node.dataset.docsFolderBranch),
    hiddenInputs: [...document.querySelectorAll('[data-docs-filter-input]')].map(input => input.name + '=' + input.value),
    search: document.getElementById('docs-search-input').value,
  };
});
try {
  const server = await (await browser.newContext({ javaScriptEnabled: false })).newPage();
  const client = await (await browser.newContext()).newPage();
  await client.goto(base + '/docs/');
  for (const query of queries) {
    await server.goto(base + '/docs/' + query);
    // Navigate in place from the landing view, as the static host would.
    await client.evaluate(url => { history.pushState(null, '', url); dispatchEvent(new PopStateEvent('popstate')); }, '/docs/' + query);
    await client.waitForFunction(q => document.querySelector('[data-docs-library]').dataset.docsQuery === q, query);
    assert.deepEqual(await snapshot(client), await snapshot(server), query);
    assert.equal(await client.locator('[data-docs-landing]').first().isHidden(), true, query);
    console.log('parity', query);
  }
  // Landing quick link → results in place, with focus on the results heading and a live count.
  await client.goto(base + '/docs/');
  await client.click('.docs-quick-links a >> nth=2');
  await client.waitForFunction(() => document.querySelector('[data-docs-library]').dataset.docsReady === 'true');
  assert.equal(await client.evaluate(() => document.activeElement.id), 'docs-library-heading');
  assert.equal(await client.getAttribute('[data-docs-count]', 'role'), 'status');
  assert.match(new URL(client.url()).search, /^\?group=Workflow$/);
  await client.goBack();
  assert.equal(await client.locator('.docs-topics').isVisible(), true);
  console.log('landing navigation ok');
  // An unavailable index shows Retry; retrying after recovery renders results.
  const failing = await (await browser.newContext()).newPage();
  let fail = true;
  await failing.route('**/docs/_search.json', route => fail ? route.abort() : route.continue());
  await failing.goto(base + '/docs/');
  await failing.evaluate(() => { history.pushState(null, '', '/docs/?q=retraction'); dispatchEvent(new PopStateEvent('popstate')); });
  await failing.waitForSelector('[data-docs-error]:not([hidden])');
  fail = false;
  await failing.click('[data-docs-retry]');
  await failing.waitForFunction(() => document.querySelector('[data-docs-library]').dataset.docsReady === 'true');
  assert.equal(await failing.locator('[data-docs-error]').isHidden(), true);
  console.log('index failure and retry ok');
} finally {
  await browser.close();
}
