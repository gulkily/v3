// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tools_import.mjs [screenshot-dir]
// Tools → Import (import_instance_gui): idle, running, result and pending states at phone and desktop
// width in several themes. No horizontal overflow, Advanced starts closed and opens without overflow,
// the page script follows a queued import and reloads into the result, and no page errors.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const html = Object.fromEntries(['idle', 'running', 'result', 'pending', 'signed-out'].map((state) => [state,
  execFileSync('php', [`${project}/tests/Support/import_page_fixture.php`, state], { encoding: 'utf8' })]));
const themeKey = html.idle.match(/"themeStorageKey":"([^"]+)"/)?.[1];
const shots = process.argv[2] || '';
if (shots) await mkdir(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open(state, { width, theme, onPost = null }) {
  const context = await browser.newContext({ viewport: { width, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.addInitScript(([key, chosen]) => { if (chosen && key) localStorage.setItem(key, chosen); }, [themeKey, theme]);
  let current = state;
  await page.route('http://app.test/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    if (path === '/tools/import/' && route.request().method() === 'POST') {
      const reply = onPost ? onPost(JSON.parse(route.request().postData() || '{}')) : { status: 'ok', message: '', state: { busy: false } };
      if (reply.next) current = reply.next;
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(reply) });
    }
    if (path.startsWith('/assets/')) {
      const file = path.replace(/\.[0-9a-f]{12}\./, '.');
      try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : file.endsWith('.svg') ? 'image/svg+xml' : 'application/javascript', body: await readFile(project + '/public' + file) }); }
      catch { return route.fulfill({ status: 404, body: '' }); }
    }
    if (path.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
    return route.fulfill({ contentType: 'text/html', body: html[current] });
  });
  await page.goto('http://app.test/tools/import/');
  await page.waitForTimeout(200);
  return { page, context, errors };
}

const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
let cases = 0;
for (const theme of ['gloss', 'glossdark', 'word97', 'midnight', 'bubblegum', 'spearmint', 'light', 'dark']) {
  for (const width of [390, 1280]) {
    for (const state of ['idle', 'running', 'result', 'pending', 'signed-out']) {
      const { page, context, errors } = await open(state, { width, theme, onPost: () => ({ status: 'ok', message: '', state: { busy: true, task: { status: 'running' }, progress: [] } }) });
      assert.ok(await overflow(page) <= 0, `${theme}/${width}/${state}: page overflows`);
      if (state === 'result') {
        assert.equal(await page.locator('details.import-advanced').evaluate((d) => d.open), false, 'Advanced starts closed');
        await page.click('details.import-advanced summary');
        assert.ok(await overflow(page) <= 0, `${theme}/${width}: open Advanced overflows`);
        await page.click('details.import-advanced summary');
      }
      if (state === 'idle' || state === 'result') assert.ok(await page.locator('button[value="import"]').isVisible(), `${state}: Import visible`);
      if (state === 'running') assert.ok(await page.locator('button[value="import"]').isDisabled(), 'running: Import disabled');
      if (state === 'pending') {
        assert.ok(await page.locator('button[value="resume"]').isVisible() && await page.locator('button[value="cancel"]').isVisible(), 'pending: Resume and Cancel');
        assert.equal(await page.locator('input[name="source"]').count(), 0, 'pending: no source field');
      }
      if (state === 'signed-out') assert.equal(await page.locator('[data-import-form]').count(), 0, 'signed out: no form');
      if (shots && ['gloss', 'word97', 'bubblegum', 'midnight'].includes(theme) && state !== 'signed-out') await page.screenshot({ path: `${shots}/import-${state}-${theme}-${width}.png`, fullPage: true });
      assert.deepEqual(errors, [], `${theme}/${width}/${state}: page errors`);
      await context.close();
      cases++;
    }
  }
}

// The script queues, shows progress while the worker runs, then reloads into the result.
{
  let polls = 0;
  const { page, context, errors } = await open('idle', { width: 390, theme: 'gloss', onPost: (body) => {
    if (body.action === 'import') {
      assert.equal(body.source, 'forum.example');
      return { status: 'ok', message: 'Queued for the task worker.', state: { busy: true, task: { status: 'queued' }, progress: [] } };
    }
    polls++;
    if (polls < 2) return { status: 'ok', message: '', state: { busy: true, task: { status: 'running' }, progress: ['Downloading repository archive...'] } };
    return { status: 'ok', message: '', state: { busy: false, task: { status: 'completed' }, progress: ['Import result: complete'] }, next: 'result' };
  } });
  await page.fill('input[name="source"]', 'forum.example');
  await page.click('button[value="import"]');
  await page.waitForSelector('[data-import-progress] li', { timeout: 8000 });
  assert.equal(await page.locator('[data-import-status]').textContent(), 'Importing…');
  assert.ok(await page.locator('button[value="import"]').isDisabled(), 'busy while following');
  await page.waitForSelector('details.import-advanced', { timeout: 10000 });
  assert.deepEqual(errors, []);
  await context.close();
}

// A refusal shows the server's reason and re-enables the form.
{
  const { page, context, errors } = await open('idle', { width: 390, theme: 'gloss', onPost: () => ({ status: 'error', error: 'An import is already queued or running.' }) });
  await page.fill('input[name="source"]', 'forum.example');
  await page.click('button[value="import"]');
  await page.waitForFunction(() => document.querySelector('[data-import-status]')?.textContent === 'An import is already queued or running.');
  assert.equal(await page.locator('button[value="import"]').isDisabled(), false);
  assert.deepEqual(errors, []);
  await context.close();
}

await browser.close();
console.log(`PASS tools import: ${cases} theme/width/state cases without overflow or errors; Advanced folds; queued import followed to its result; refusal shown`);
