// The header deploy prompt: hidden server HTML, revealed after paint only in a browser
// that has deployed before, and only when the checkout is behind. It sits by the site
// name (under it on wider screens), so revealing it never moves the phone tab bar
// and never wraps the destinations.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const layout = (deploy) => execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, 'board', '1', '1', '0', deploy], { encoding: 'utf8' });
const htmlOn = layout('1');
const htmlOff = layout('0');
const themeKey = htmlOn.match(/"themeStorageKey":"([^"]+)"/)?.[1];
assert.ok(themeKey, 'theme storage key in the layout');
const shots = process.env.DEPLOY_PROMPT_SHOTS || '';
if (shots) await mkdir(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open({ html, remembered, reply, width = 390, theme = null }) {
  const page = await browser.newPage({ viewport: { width, height: 760 } });
  const calls = [];
  await page.addInitScript(([key, value, themeStorageKey, chosen]) => {
    if (value) localStorage.setItem(key, value);
    if (chosen) localStorage.setItem(themeStorageKey, chosen);
  }, ['forum_deploy_prompt', remembered ? '1' : null, themeKey, theme]);
  await page.route('http://app.test/**', async route => {
    const path = new URL(route.request().url()).pathname;
    if (path === '/tools/deploy/') {
      const body = JSON.parse(route.request().postData() || '{}');
      calls.push(body);
      const [status, payload] = reply(body);
      return route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(payload) });
    }
    if (path.startsWith('/assets/')) {
      const file = path.replace(/\.[0-9a-f]{12}\./, '.');
      try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(project + '/public' + file) }); }
      catch { return route.fulfill({ status: 404, body: '' }); }
    }
    if (path.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
    return route.fulfill({ contentType: 'text/html', body: html });
  });
  await page.goto('http://app.test/');
  return { page, calls };
}
const behind = (n) => () => [200, { status: 'ok', action: 'check', result: { outcome: 'checked' }, state: { behind: n } }];
const visible = (page) => page.evaluate(() => { const el = document.querySelector('[data-deploy-prompt]'); return !!el && !el.hidden && el.getBoundingClientRect().width > 0; });
// Phone: every tab. Desktop: the nav may shift right once, but New post and You stay put.
const tabs = (page, width) => page.evaluate((all) => [...document.querySelectorAll(all ? '.site-header-actions a, .site-header-actions summary' : '.new-post-button, .account-menu__trigger')].map(e => { const r = e.getBoundingClientRect(); return [r.x, r.y, r.width, r.height].map(Math.round).join(','); }).join(';'), width < 700);

// Flag off: no markup, no script, no requests, even in a remembered browser.
{
  const { page, calls } = await open({ html: htmlOff, remembered: true, reply: behind(2) });
  assert.equal(/src="[^"]*deploy_prompt/.test(htmlOff), false, 'flag off loads no prompt script');
  await page.waitForTimeout(3600);
  assert.equal(await page.locator('[data-deploy-prompt]').count(), 0);
  assert.equal(calls.length, 0, 'flag off: no deploy request');
  await page.close();
}
// Flag on, browser never deployed: nothing is asked.
{
  const { page, calls } = await open({ html: htmlOn, remembered: false, reply: behind(2) });
  await page.waitForTimeout(3600);
  assert.equal(calls.length, 0, 'never-deployed browser: no deploy request');
  assert.equal(await visible(page), false);
  await page.close();
}
// Remembered and behind: one delayed check that accepts a recent fetch, then the button.
for (const width of [390, 1100]) {
  const { page, calls } = await open({ html: htmlOn, remembered: true, reply: behind(2), width });
  const before = await tabs(page, width);
  await page.waitForTimeout(1500);
  assert.equal(calls.length, 0, `${width}: the check waits after load`);
  await page.waitForFunction(() => !document.querySelector('[data-deploy-prompt]').hidden, null, { timeout: 6000 });
  assert.deepEqual(calls, [{ action: 'check', max_age: 600 }]);
  assert.equal(await visible(page), true, `${width}: Deploy now shows`);
  assert.equal(await tabs(page, width), before, `${width}: revealing the button moves no tab`);
  await page.close();
}
// Up to date: stays hidden.
{
  const { page, calls } = await open({ html: htmlOn, remembered: true, reply: behind(0) });
  await page.waitForTimeout(3800);
  assert.equal(calls.length, 1);
  assert.equal(await visible(page), false, 'up to date: hidden');
  await page.close();
}
// Refused (signed out or no longer root): hidden, and this browser stops asking.
{
  const { page } = await open({ html: htmlOn, remembered: true, reply: () => [403, { status: 'error', error: 'Deploying requires a signed-in root-approved operator.' }] });
  await page.waitForTimeout(3800);
  assert.equal(await visible(page), false);
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_deploy_prompt')), null, 'a refusal forgets the browser');
  await page.close();
}
// Deploy from the header: success reloads; failures keep the page and point to Tools → Deploy.
const deployReplies = {
  deployed: [() => [200, { status: 'ok', action: 'now', result: { outcome: 'deployed', message: 'Updated to abc.' }, state: { behind: 0 } }], null],
  failed: [() => [200, { status: 'ok', action: 'now', result: { outcome: 'failed', message: 'Could not fetch from GitHub: timeout.' }, state: { behind: 2 } }], 'Could not fetch from GitHub: timeout. Open Deploy'],
  busy: [() => [503, { status: 'error', transient: true }], 'The server is busy; the deploy may still have run. Open Deploy'],
  refused: [() => [403, { status: 'error', error: 'Deploying requires a signed-in root-approved operator.' }], 'Deploying requires a signed-in root-approved operator. Open Deploy'],
  dropped: ['abort', 'The connection dropped; the deploy may still have run. Open Deploy'],
};
for (const [name, [nowReply, expected]] of Object.entries(deployReplies)) {
  let loads = 0;
  const { page, calls } = await open({ html: htmlOn, remembered: true, reply: (body) => body.action === 'check' ? behind(2)() : nowReply() });
  if (nowReply === 'abort') await page.route('http://app.test/tools/deploy/', async route => JSON.parse(route.request().postData()).action === 'now' ? route.abort('connectionreset') : route.fallback());
  page.on('framenavigated', frame => { if (frame === page.mainFrame()) loads++; });
  await page.waitForFunction(() => !document.querySelector('[data-deploy-prompt]').hidden, null, { timeout: 6000 });
  const before = loads;
  await page.click('[data-deploy-prompt-button]');
  if (expected === null) {
    await page.waitForFunction(() => document.readyState === 'complete' && performance.getEntriesByType('navigation')[0]?.type === 'reload', null, { timeout: 6000 });
    assert.ok(loads > before, `${name}: the page reloads on the new commit`);
  } else {
    await page.waitForFunction(() => document.querySelector('[data-deploy-prompt-status]').textContent.includes('Open Deploy'), null, { timeout: 6000 });
    assert.equal(await page.textContent('[data-deploy-prompt-status]'), expected, `${name}: message and link`);
    assert.equal(await page.getAttribute('[data-deploy-prompt-status] a', 'href'), '/tools/deploy/');
    assert.equal(await page.isDisabled('[data-deploy-prompt-button]'), false, `${name}: the button can be tried again`);
    if (name === 'refused') assert.equal(await page.evaluate(() => localStorage.getItem('forum_deploy_prompt')), null, 'a refused deploy forgets the browser');
  }
  if (nowReply !== 'abort') assert.deepEqual(calls.map(c => c.action), expected === null ? ['check', 'now', 'check'].slice(0, calls.length) : ['check', 'now'], `${name}: one deploy request`);
  await page.close();
}
// Every theme, phone and desktop: the button fits beside the site name.
for (const theme of ['word97', 'chicago', 'midnight', 'gloss', 'glossdark', 'engagify']) {
  for (const width of [390, 1100]) {
    const { page } = await open({ html: htmlOn, remembered: true, reply: behind(3), width, theme });
    await page.waitForFunction(() => !document.querySelector('[data-deploy-prompt]').hidden, null, { timeout: 6000 });
    const [box, brand] = await page.evaluate(() => [document.querySelector('[data-deploy-prompt-button]'), document.querySelector('.site-brand')].map(e => { const r = e.getBoundingClientRect(); return { x: r.x, y: r.y, w: r.width, h: r.height, right: r.right }; }));
    assert.ok(box.w > 40 && box.h > 14, `${theme} ${width}: button has a usable size`);
    assert.ok(box.right <= width, `${theme} ${width}: button stays on screen`);
    // Phones: on the site name's row. Wider screens: under the name, or beside it in
    // title-bar themes (Word 97, Midnight) whose name row has room.
    if (width < 640) assert.ok(Math.abs((box.y + box.h / 2) - (brand.y + brand.h / 2)) < 24, `${theme} ${width}: on phones the button sits on the site name's row`);
    const header = await page.evaluate(() => document.querySelector('.site-header').getBoundingClientRect().bottom);
    assert.ok(box.y + box.h <= header, `${theme} ${width}: button stays inside the header`);
    // Wider screens: the destinations stay on one row.
    if (width >= 640) assert.equal(await page.evaluate(() => new Set([...document.querySelectorAll('.site-header .nav > .nav-link')].filter(e => e.getBoundingClientRect().width > 0).map(e => Math.round(e.getBoundingClientRect().y))).size), 1, `${theme} ${width}: no wrapped destinations`);
    if (shots) await page.screenshot({ path: `${shots}/${theme}-${width}.png`, clip: { x: 0, y: 0, width, height: 140 } });
    await page.close();
  }
}
await browser.close();
console.log('deploy_prompt: ok');
