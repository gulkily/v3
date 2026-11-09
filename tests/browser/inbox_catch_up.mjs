// Caught up on Unread saves without a page reload: only the submitted section
// changes, the other sections stay, and the Unread link's count refreshes.
// A refused or failed save says so in place and offers Retry. Without
// JavaScript the plain form still posts and follows the redirect.
// Runs against php -S with tests/Support/unread_page_router.php, so the 303
// back to /unread is a real redirect that fetch follows.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, writeFile, mkdtemp, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const dir = await mkdtemp(join(tmpdir(), 'unread-catch-up-'));
const stateFile = join(dir, 'state.txt');
const port = await new Promise(done => { const s = createServer().listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => done(p)); }); });
const origin = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, `${project}/tests/Support/unread_page_router.php`], { env: { ...process.env, UNREAD_ROUTER_STATE: stateFile }, stdio: 'ignore' });

async function reset(next = 'ok') { await writeFile(stateFile, `Replies: r1,r2\nNew: n1\nNext: ${next}\nPosts: \nCount calls: 0\n`); }
async function steer(next) { await writeFile(stateFile, (await readFile(stateFile, 'utf8')).replace(/^Next: .*$/m, `Next: ${next}`)); }
async function state() { return Object.fromEntries((await readFile(stateFile, 'utf8')).trim().split('\n').map(line => line.split(/: ?(.*)/s).slice(0, 2))); }
for (let i = 0; i < 50; i++) { try { await fetch(origin + '/unread'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (const [width, height] of [[1100, 700], [390, 500]]) {
    await reset();
    const page = await browser.newPage({ viewport: { width, height } });
    await page.addInitScript(() => {
      const fingerprint = 'a'.repeat(40);
      for (const [key, value] of [['forum_pki_fingerprint', fingerprint], ['forum_pki_public_key', 'k'], ['forum_pki_private_key', 'k'], ['forum_pki_approved_fingerprint', fingerprint]]) localStorage.setItem(key, value);
    });
    let dropPosts = false;
    await page.route(`${origin}/unread`, route => (dropPosts && route.request().method() === 'POST' ? route.abort('connectionfailed') : route.continue()));

    await page.goto(origin + '/unread');
    await page.evaluate(() => { window.__sameDocument = true; });
    await page.waitForFunction(() => document.querySelector('[data-inbox-unread]')?.dataset.unreadCount === '2');
    const callsBefore = Number((await state())['Count calls']);

    // A failed request leaves everything listed and offers Retry.
    dropPosts = true;
    await page.click('#inbox-replies button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('#inbox-replies button[type="submit"]')?.textContent === 'Retry');
    assert.match(await page.textContent('#inbox-replies'), /Couldn’t save\. Nothing was marked read\./, `${width}: a network failure says so`);
    assert.equal(await page.locator('#inbox-replies .recent-entry').count(), 2, `${width}: nothing disappears on failure`);
    dropPosts = false;

    // A refused save swaps in the server's own alert and Retry.
    await steer('refuse');
    await page.click('#inbox-replies button[type="submit"]');
    await page.waitForFunction(() => { const form = document.querySelector('#inbox-replies form'); return form && !form.hasAttribute('aria-busy') && !form.querySelector('button').disabled; });
    assert.equal((await state()).Posts, 'replies=r1,r2', `${width}: the refused save reached the server once`);
    assert.equal(await page.locator('#inbox-replies .message-list-alert').count(), 1, `${width}: one alert, from the server`);
    assert.equal(await page.textContent('#inbox-replies button[type="submit"]'), 'Retry');
    assert.equal(await page.evaluate(() => document.activeElement?.textContent), 'Retry', `${width}: focus lands on Retry`);

    // A good save replaces only Replies, without leaving the page.
    await steer('ok');
    await page.click('#inbox-replies button[type="submit"]');
    await page.waitForFunction(() => /No new replies/.test(document.getElementById('inbox-replies').textContent));
    assert.equal(await page.evaluate(() => window.__sameDocument), true, `${width}: no page reload`);
    assert.equal((await state()).Posts, 'replies=r1,r2 replies=r1,r2', `${width}: posts exactly the listed replies`);
    assert.equal(await page.locator('#inbox-replies form').count(), 0, `${width}: Caught up is gone once caught up`);
    assert.equal(await page.locator('#inbox-new .recent-entry').count(), 1, `${width}: New posts untouched`);
    assert.equal(await page.evaluate(() => document.activeElement?.id), 'inbox-replies-heading', `${width}: focus stays in the section`);
    await page.waitForFunction(() => document.querySelector('[data-inbox-unread]')?.dataset.unreadCount === '0');
    assert.ok(Number((await state())['Count calls']) > callsBefore, `${width}: the Unread count refreshes`);

    // The other section works the same way afterwards.
    await page.click('#inbox-new button[type="submit"]');
    await page.waitForFunction(() => /No new posts/.test(document.getElementById('inbox-new').textContent));
    assert.equal(await page.evaluate(() => window.__sameDocument), true, `${width}: still no reload`);
    await page.close();
  }

  // Without JavaScript the form still posts and the server redirects as before.
  await reset();
  const plain = await browser.newPage({ javaScriptEnabled: false });
  await plain.goto(origin + '/unread');
  await Promise.all([plain.waitForNavigation(), plain.click('#inbox-replies button[type="submit"]')]);
  assert.match(await plain.textContent('#inbox-replies'), /No new replies/, 'no-JS: the plain form still works');
  console.log('inbox_catch_up: ok');
} finally {
  await browser.close();
  server.kill();
  await rm(dir, { recursive: true, force: true });
}
