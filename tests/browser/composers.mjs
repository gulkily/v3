// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/composers.mjs
// The shared composer: new posts and replies go through from both pages, with and without JavaScript.
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
const root = await mkdtemp(join(tmpdir(), 'composers-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
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

  // With JavaScript: a new post with a Title, and one without (the first line becomes the title).
  const writer = await open(true);
  await writer.page.goto(base + '/compose/thread');
  assert.equal(await writer.page.getByRole('heading', { name: 'New post' }).isVisible(), true);
  const form = writer.page.locator('[data-compose-kind="thread"]');
  const order = await form.evaluate(f => ['.compose-title', 'textarea[name="body"]', '.compose-tools', '.compose-form-actions']
    .map(q => f.querySelector(q).getBoundingClientRect().top));
  assert.deepEqual([...order].sort((a, b) => a - b), order, 'Title, body, tools, then Post');
  await form.getByRole('textbox', { name: 'Title' }).fill('Composer with a title');
  await form.getByRole('textbox', { name: 'Body' }).fill('Body of the titled post');
  await form.locator('[data-compose-disclosure="tags"] > summary').click();
  await form.getByRole('textbox', { name: 'Tags' }).fill('general composers');
  await form.getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/threads\//, { timeout: 20000 });
  assert.equal(await writer.page.getByRole('heading', { name: 'Composer with a title' }).first().isVisible(), true);
  const threadId = /\/threads\/([^?#]+)/.exec(writer.page.url())[1];
  await writer.page.goto(base + '/compose/thread');
  await settled(writer.page);
  await writer.page.locator('[data-compose-kind="thread"] textarea[name="body"]').fill('Untitled first line\nand more');
  await writer.page.locator('[data-compose-kind="thread"]').getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/threads\//, { timeout: 60000 });
  assert.equal(await writer.page.getByRole('heading', { name: 'Untitled first line' }).first().isVisible(), true, 'the first line becomes the title');

  // The ··· menu, with the mockup's wording.
  await writer.page.goto(base + '/compose/thread');
  await settled(writer.page);
  await writer.page.locator('.compose-action-menu-toggle').click();
  assert.deepEqual(await writer.page.locator('.compose-action-menu-list > button').allTextContents(), ['Post anonymously', 'Save to Outbox', 'Discard draft']);

  // The reply page: parent context, then a reply.
  await writer.page.goto(base + `/compose/reply?thread_id=${threadId}&parent_id=${threadId}`);
  await settled(writer.page);
  const author = (await writer.page.locator('.reply-context .meta a').first().textContent()).trim();
  assert.equal(await writer.page.getByRole('heading', { name: 'Reply to ' + author }).isVisible(), true);
  assert.equal(await writer.page.locator('.reply-context').getByRole('link', { name: 'Composer with a title' }).getAttribute('href'), '/threads/' + threadId);
  await writer.page.locator('[data-compose-kind="reply"] textarea[name="body"]').fill('A reply from the reply page');
  const replied = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await writer.page.locator('[data-compose-kind="reply"]').getByRole('button', { name: 'Post reply' }).click();
  assert.match(await (await replied).text(), /ok/);
  await writer.page.screenshot({ path: '/tmp/composers-reply-posted-390.png' });
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.getByText('A reply from the reply page').first().waitFor();
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });
  await writer.context.close();

  // On a phone, typing hides the bottom tab bar so it can't cover Post (reported on iPhone with the keyboard up).
  const phone = await open(true);
  await phone.page.goto(base + '/threads/' + threadId);
  const replyBody = phone.page.locator('[data-compose-kind="reply"] textarea[name="body"]');
  await replyBody.fill('Typing on a phone');
  await phone.page.waitForFunction(() => document.querySelector('[data-shell="app"]').dataset.tabbar === 'hidden');
  const post = phone.page.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]');
  await post.scrollIntoViewIfNeeded();
  const uncovered = await post.evaluate(button => { const r = button.getBoundingClientRect(); const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return hit === button || button.contains(hit); });
  assert.equal(uncovered, true, 'Post is not covered while typing');
  await phone.page.screenshot({ path: '/tmp/composers-phone-typing-390.png' });
  await phone.context.close();

  // On a phone the ··· menu sits beside Post in every composer: board quick compose, New post, thread reply.
  const narrow = await open(true);
  for (const path of ['/', '/compose/thread', '/threads/' + threadId]) {
    await narrow.page.goto(base + path);
    const actions = narrow.page.locator('form[data-compose-form] .compose-form-actions').first();
    await actions.locator('xpath=ancestor::form[1]').locator('textarea[name="body"]').fill('x');
    const [postBox, menuBox] = await Promise.all([actions.locator(':scope > button[type="submit"]').boundingBox(), actions.locator('.compose-action-menu').boundingBox()]);
    assert.ok(Math.abs(postBox.y - menuBox.y) < postBox.height / 2 && menuBox.x > postBox.x + postBox.width - 1, `${path}: ··· beside Post`);
  }
  await narrow.context.close();

  // A parent that isn't in the thread.
  const missing = await open(true);
  await missing.page.goto(base + `/compose/reply?thread_id=${threadId}&parent_id=no-such-post`);
  await missing.page.getByText('That post was removed or isn’t in this thread.').waitFor();
  assert.equal(await missing.page.getByRole('link', { name: 'Reply to the thread instead.' }).getAttribute('href'), '/threads/' + threadId);
  await missing.context.close();

  // Without JavaScript: both pages post (anonymously, as there is no browser key), with Title and tags still usable.
  const plain = await open(false);
  await plain.page.goto(base + '/compose/thread');
  await plain.page.getByRole('textbox', { name: 'Title' }).fill('Posted without JavaScript');
  await plain.page.getByRole('textbox', { name: 'Body' }).fill('No scripts here');
  await plain.page.locator('[data-compose-disclosure="tags"] > summary').click();
  assert.equal(await plain.page.getByRole('textbox', { name: 'Tags' }).isVisible(), true);
  await plain.page.locator('[data-compose-kind="thread"] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.getByText('Posted without JavaScript').first().waitFor();
  await plain.page.goto(base + `/compose/reply?thread_id=${threadId}&parent_id=${threadId}`);
  await plain.page.locator('.reply-context').waitFor();
  await plain.page.getByRole('textbox', { name: 'Body' }).fill('A reply without JavaScript');
  await plain.page.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.goto(base + '/threads/' + threadId);
  await plain.page.getByText('A reply without JavaScript').first().waitFor();
  await plain.context.close();

  console.log('PASS composers: New post (Title, tags; first line as title), ··· menu wording, reply page context and reply, missing parent, both pages without JavaScript');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
