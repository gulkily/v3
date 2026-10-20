import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

// Docs landing/results acceptance. Stage 5: browser rendering matches the server's.
const base = process.env.DOCS_DYNAMIC_BASE_URL || 'http://127.0.0.1:8191';
// The browser side may run on the static release host; the JS-off reference is always the dynamic host.
const clientBase = process.env.DOCS_CLIENT_BASE_URL || base;
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
// Known-user acceptance: hosts × JavaScript × width/zoom × the icon themes. Each view must
// reflow without page-wide scrolling, keep readable diagram text and show a visible focus ring.
async function acceptance() {
  const output = process.env.DOCS_EVIDENCE_ROOT || '/tmp/docs-landing-browser';
  await mkdir(output, { recursive: true });
  const hosts = [['dynamic', base], ...(clientBase !== base ? [['static', clientBase]] : [])];
  const evidence = [];
  for (const [host, url] of hosts) for (const js of [true, false]) for (const [width, zoom] of [[1280, 1], [1280, 2], [320, 1]]) for (const theme of ['default', 'word97', 'chicago', 'midnight']) {
    const context = await browser.newContext({ viewport: { width: width / zoom, height: 900 / zoom }, deviceScaleFactor: zoom, javaScriptEnabled: js });
    const page = await context.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const views = { landing: '/docs/', browse: '/docs/?group=Workflow', search: '/docs/?q=recovery' };
    const result = { host, js, width, zoom, theme };
    for (const [view, path] of Object.entries(views)) {
      await page.goto(url + path, { waitUntil: 'networkidle' });
      // Swap the theme in its own <link>; wait on the response, since pages without
      // JavaScript never deliver the stylesheet's load event to an injected listener.
      if (theme !== 'default') {
        await Promise.all([
          page.waitForResponse(response => response.url().endsWith('/assets/theme-' + theme + '.css')),
          page.evaluate(name => {
            document.documentElement.dataset.theme = name;
            document.getElementById('theme-stylesheet').href = '/assets/theme-' + name + '.css';
          }, theme),
        ]);
        await page.waitForTimeout(200);
      }
      if (js && view !== 'landing') await page.waitForFunction(() => document.querySelector('[data-docs-library]').dataset.docsReady === 'true');
      const state = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        topics: Boolean(document.querySelector('.docs-topics')?.getClientRects().length),
        rows: [...document.querySelectorAll('.docs-result')].filter(row => row.getClientRects().length).length,
        notice: document.body.innerText.includes('needs JavaScript'),
        smallestDiagramText: Math.min(...[...document.querySelectorAll('.docs-flow *')].filter(node => node.getClientRects().length && [...node.childNodes].some(child => child.nodeType === 3 && child.textContent.trim())).map(node => parseFloat(getComputedStyle(node).fontSize))),
      }));
      assert.ok(state.overflow <= 1, `${view} overflow ${JSON.stringify(result)}`);
      if (view === 'landing') {
        assert.ok(state.topics, 'landing topics');
        assert.ok(state.smallestDiagramText >= 13.6, 'diagram text ' + state.smallestDiagramText);
      } else if (view === 'browse') {
        assert.equal(state.rows, 16, `browse rows ${JSON.stringify(result)}`);
      } else {
        // Static search without JavaScript is the one recorded gap: it must say so.
        const shell = host === 'static' && !js;
        assert.equal(state.notice, shell, `search notice ${JSON.stringify(result)}`);
        assert.ok(shell ? state.rows === 0 : state.rows > 0, `search rows ${JSON.stringify(result)}`);
      }
      // Tab through the first controls; each must show a ring of at least 2px.
      const rings = [];
      for (let index = 0; index < 14; index++) {
        await page.keyboard.press('Tab');
        rings.push(await page.evaluate(() => {
          const node = document.activeElement;
          if (!node.closest('.docs-page')) return null;
          const style = getComputedStyle(node);
          return { text: (node.textContent || node.name || '').trim().slice(0, 24), outline: style.outlineStyle !== 'none' ? parseFloat(style.outlineWidth) : 0 };
        }));
      }
      const docsRings = rings.filter(Boolean);
      assert.ok(docsRings.length > 3, `${view} focus reached docs controls ${JSON.stringify(result)}`);
      for (const ring of docsRings) assert.ok(ring.outline >= 2, `${view} focus ring ${JSON.stringify(ring)} ${JSON.stringify(result)}`);
      await page.screenshot({ path: `${output}/${host}-${js ? 'js' : 'nojs'}-${width}-${zoom}x-${theme}-${view}.png`, fullPage: view !== 'browse' || width === 320 });
      result[view] = 'passed';
    }
    assert.deepEqual(errors, []);
    evidence.push(result);
    console.log('accept', JSON.stringify(result));
    await context.close();
  }
  await writeFile(output + '/evidence.json', JSON.stringify(evidence, null, 2));
}
try {
  const server = await (await browser.newContext({ javaScriptEnabled: false })).newPage();
  const client = await (await browser.newContext()).newPage();
  await client.goto(clientBase + '/docs/');
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
  await client.goto(clientBase + '/docs/');
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
  await failing.goto(clientBase + '/docs/');
  await failing.evaluate(() => { history.pushState(null, '', '/docs/?q=retraction'); dispatchEvent(new PopStateEvent('popstate')); });
  await failing.waitForSelector('[data-docs-error]:not([hidden])');
  fail = false;
  await failing.click('[data-docs-retry]');
  await failing.waitForFunction(() => document.querySelector('[data-docs-library]').dataset.docsReady === 'true');
  assert.equal(await failing.locator('[data-docs-error]').isHidden(), true);
  console.log('index failure and retry ok');
  await acceptance();
} finally {
  await browser.close();
}
