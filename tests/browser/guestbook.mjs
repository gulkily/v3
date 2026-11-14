// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/guestbook.mjs
// The guestbook: sign the book from its last page, with and without JavaScript, and land back on the book.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'guestbook-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/guestbook_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 390) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height: 844 } });
  const page = await context.newPage();
  const seen = { errors: [] };
  page.on('dialog', d => { void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
const shots = process.env.GUESTBOOK_SCREENSHOTS || '';
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // With JavaScript, on a phone: sign on the last page and land back on the book.
  const phone = await open(true);
  await phone.page.goto(base + '/guestbook/');
  const form = phone.page.locator('#sign form');
  await form.locator('textarea[name="body"]').fill('Signed with a script.');
  await form.getByRole('button', { name: 'Sign the book' }).click();
  await phone.page.waitForURL(/\/guestbook\/\?created_post_id=/, { timeout: 20000 });
  assert.equal(await phone.page.locator('.guestbook__entry', { hasText: 'Signed with a script.' }).isVisible(), true);
  assert.deepEqual(phone.seen.errors, []);

  // Without JavaScript, on a wide screen: the same form posts and returns to the book.
  const wide = await open(false, 1280);
  await wide.page.goto(base + '/guestbook/');
  await wide.page.locator('#sign textarea[name="body"]').fill('Signed without a script.');
  await wide.page.locator('#sign').getByRole('button', { name: 'Sign the book' }).click();
  await wide.page.waitForURL(/\/guestbook\/\?created_post_id=/, { timeout: 20000 });
  await wide.page.goto(base + '/guestbook/');
  assert.equal(await wide.page.locator('.guestbook__entry', { hasText: 'Signed without a script.' }).isVisible(), true);

  // Both entries are ordinary replies in the thread (the fixture's #meta thread shows a hidden summary line too).
  await wide.page.goto(base + '/threads/root-001');
  assert.equal(await wide.page.locator('.body', { hasText: 'Signed with a script.' }).isVisible(), true);
  assert.equal(await wide.page.locator('.body', { hasText: 'Signed without a script.' }).isVisible(), true);

  // More entries: a long one and a Markdown table, enough for a second spread.
  const filler = await open(false, 1280);
  const longNote = 'A long note that keeps going. '.repeat(70).trim();
  const notes = [longNote, '| Day | Weather |\n| --- | --- |\n| Wednesday | Rain |', 'Short one.', 'Another visitor.', 'Hello from the night shift.'];
  for (const note of notes) {
    await filler.page.goto(base + '/guestbook/');
    await filler.page.locator('#sign textarea[name="body"]').fill(note);
    await filler.page.locator('#sign').getByRole('button', { name: 'Sign the book' }).click();
    await filler.page.waitForURL(/\/guestbook\/\?created_post_id=/, { timeout: 20000 });
  }
  await filler.context.close();

  // Every theme, at phone and wide sizes, with and without JavaScript: the paper stays paper, nothing overflows.
  const themes = ['light', 'dark', 'gloss', 'glossdark', 'word97', 'chicago', 'midnight', 'console', 'lcd', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'engagify'];
  for (const javaScriptEnabled of [true, false]) {
    for (const width of [390, 1280]) {
      const viewer = await open(javaScriptEnabled, width);
      for (const theme of javaScriptEnabled ? themes : ['light']) {
        await viewer.page.addInitScript(theme => {
          localStorage.setItem('zenmemes-theme', theme);
          for (const key of Object.keys(localStorage)) if (key.endsWith('-theme')) localStorage.setItem(key, theme);
        }, theme);
        for (const path of ['/guestbook/', '/guestbook/?page=1']) {
          await viewer.page.goto(base + path);
          const check = await viewer.page.evaluate(() => {
            const page = document.querySelector('.guestbook__page');
            const pages = [...document.querySelectorAll('.guestbook__page')].map(p => p.getBoundingClientRect());
            return {
              paper: getComputedStyle(page).backgroundColor,
              ink: getComputedStyle(document.querySelector('.guestbook__entry') || page).color,
              overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
              sideBySide: pages.length === 2 && Math.abs(pages[0].top - pages[1].top) < 2,
              widest: Math.max(...[...document.querySelectorAll('.guestbook__entry')].map(e => e.getBoundingClientRect().right)),
              bookRight: document.querySelector('.guestbook__book').getBoundingClientRect().right,
            };
          });
          const label = `${theme} ${width}px js=${javaScriptEnabled} ${path}`;
          assert.equal(check.paper, 'rgb(246, 239, 223)', `paper colour: ${label}`);
          assert.equal(check.ink, 'rgb(43, 38, 32)', `ink colour: ${label}`);
          assert.ok(check.overflow <= 0, `no sideways scroll: ${label} (${check.overflow}px)`);
          assert.equal(check.sideBySide, width === 1280, `facing pages only when wide: ${label}`);
          assert.ok(check.widest <= check.bookRight, `entries stay inside the book: ${label}`);
        }
      }
      await viewer.context.close();
    }
  }
  assert.equal((await (await fetch(base + '/guestbook/?page=1')).text()).includes('Later pages'), true, 'The first spread turns forward.');

  if (shots) {
    for (const [name, ctx, width] of [['phone', phone, 390], ['wide', wide, 1280]]) {
      await ctx.page.setViewportSize({ width, height: 900 });
      for (const [suffix, path] of [['latest', '/guestbook/'], ['first', '/guestbook/?page=1']]) {
        await ctx.page.goto(base + path);
        await ctx.page.screenshot({ path: join(shots, `guestbook-${name}-${suffix}.png`), fullPage: true });
      }
    }
  }
  console.log('guestbook: ok');
} catch (error) {
  console.error(serverLog.slice(-3000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
