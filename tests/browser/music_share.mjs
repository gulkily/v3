// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/music_share.mjs
// The Music page's share box (mitrapclub): a pasted link becomes a #music track at the top of the page,
// with and without JavaScript, and a draft there never shows up in the Board's composer.
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
const root = await mkdtemp(join(tmpdir(), 'music-share-'));
const env = { ...process.env, FORUM_SITE_ID: 'mitrapclub', FORUM_MEDIA_EMBEDS_ENABLED: 'true', FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED: 'true', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 390) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height: 844 } });
  const page = await context.newPage();
  const seen = { dialogs: [], errors: [] };
  page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
// Once a key is registered, a page load may sign in and reload once (a known behavior, see
// docs/plans/backlog/unexpected_page_reload_handoff.md); wait for the page to stay put before typing.
async function settled(page) {
  for (let quiet = 0, i = 0; quiet < 3 && i < 40; i++) {
    const url = page.url(); await page.waitForTimeout(400);
    quiet = page.url() === url && await page.evaluate(() => document.readyState === 'complete').catch(() => false) ? quiet + 1 : 0;
  }
}
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const link = 'https://www.youtube.com/watch?v=GQLciY_ityU';

  // With JavaScript at phone width: paste, add a title, Post; back on /music/ with the track first.
  const writer = await open(true);
  await writer.page.goto(base + '/music/');
  await settled(writer.page);
  const composer = writer.page.locator('.music-share');
  const form = composer.locator('[data-compose-kind="thread"]');
  assert.equal(await composer.locator('[data-inline-thread-compose]').getAttribute('data-inline-thread-expanded'), '0', 'starts as one line, like the Board');
  await form.getByRole('textbox', { name: 'Body' }).fill(link);
  assert.equal(await composer.locator('[data-inline-thread-compose]').getAttribute('data-inline-thread-expanded'), '1', 'grows while in use');
  await form.getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/music\/\?created_post_id=/, { timeout: 60000 });
  const created = new URL(writer.page.url()).searchParams.get('created_post_id');
  const first = writer.page.locator('.music-tracks .thread-row').first();
  assert.equal(await first.getAttribute('id'), 'post-' + created, 'the new track is first');
  assert.equal(await first.locator('.thread-row__player').count(), 1);
  const threadId = (await first.getAttribute('id')).replace('post-', '');
  const record = await (await fetch(base + '/api/get_post?post_id=' + threadId)).text();
  assert.match(record, /^Board-Tags: music$/m);
  assert.equal(await writer.page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true, 'nothing scrolls sideways at 390px');

  // A draft typed on /music/ stays out of the Board's composer.
  await writer.page.goto(base + '/music/');
  await settled(writer.page);
  await writer.page.locator('.music-share textarea[name="body"]').fill('half a link');
  await writer.page.waitForTimeout(1500);
  await writer.page.goto(base + '/threads/');
  await settled(writer.page);
  const boardBody = writer.page.locator('form[data-compose-form] textarea[name="body"]').first();
  assert.equal(await boardBody.inputValue(), '', 'the Board composer has no Music draft');
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });
  await writer.context.close();

  // Without JavaScript: the same form posts and returns to /music/.
  const plain = await open(false);
  await plain.page.goto(base + '/music/');
  await plain.page.locator('.music-share textarea[name="body"]').fill('https://open.spotify.com/album/3LVR5at2HIWpbqFfV7Zzdj');
  await plain.page.locator('.music-share button[type="submit"]').first().click();
  await plain.page.waitForURL(/\/music\/\?created_post_id=/, { timeout: 20000 });
  assert.equal(await plain.page.locator('.music-tracks .thread-row').first().locator('[data-provider="spotify"]').count(), 1);
  await plain.context.close();

  console.log('PASS music share: the Board-style one-line composer grows, paste and Post from /music/ at 390px with and without JavaScript, track first with its player and #music, no Music draft on the Board');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
