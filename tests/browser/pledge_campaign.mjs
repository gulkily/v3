// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/pledge_campaign.mjs [--screenshots=/tmp/pledges]
// Pledge campaigns: a thread with a goal line shows its totals; the reply form's amount field
// writes the pledge line into the body with JavaScript, and the server writes it without.
// --screenshots also saves the campaign thread in every theme at phone and desktop width.
import assert from 'node:assert/strict';
import { mkdtemp, mkdir } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = (process.argv.find(a => a.startsWith('--screenshots=')) || '').slice('--screenshots='.length);
// --count-unnamed runs with "Count pledges with no name" on: the no-JavaScript pledge then counts.
const countUnnamed = process.argv.includes('--count-unnamed');
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'pledges-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root, FORUM_PLEDGES_ENABLED: '1', ...(countUnnamed ? { FORUM_PLEDGES_COUNT_UNNAMED_ENABLED: '1' } : {}),
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 375, height = 667) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height } });
  const page = await context.newPage();
  const seen = { dialogs: [], errors: [] };
  page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
async function settled(page) {
  for (let quiet = 0, i = 0; quiet < 3 && i < 40; i++) {
    const url = page.url(); await page.waitForTimeout(400);
    quiet = page.url() === url && await page.evaluate(() => document.readyState === 'complete').catch(() => false) ? quiet + 1 : 0;
  }
}
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // The author posts a campaign: the goal line is typed in the opening post.
  const writer = await open(true);
  await writer.page.goto(base + '/compose/thread');
  const form = writer.page.locator('[data-compose-kind="thread"]');
  await form.getByRole('textbox', { name: 'Title' }).fill('Coffee for the hack weekend');
  await form.getByRole('textbox', { name: 'Body' }).fill('Three days, about a hundred people.\n\ngoal: 1000 coffees\n\nPledge any number.');
  await form.getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/threads\//, { timeout: 30000 });
  const threadId = /\/threads\/([^?#]+)/.exec(writer.page.url())[1];
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '0 of 1000 coffees pledged');

  // The amount field keeps a first line "pledge: N" in the body, both ways.
  const reply = writer.page.locator('[data-compose-kind="reply"]');
  const amount = reply.locator('[data-role="pledge-amount"]');
  const body = reply.locator('textarea[name="body"]');
  assert.equal((await reply.locator('.pledge-field').textContent()).trim(), 'Pledgecoffees');
  await body.fill('I can order these myself.');
  await amount.fill('300');
  assert.equal(await body.inputValue(), 'pledge: 300\n\nI can order these myself.');
  await amount.fill('350');
  assert.equal(await body.inputValue(), 'pledge: 350\n\nI can order these myself.');
  await amount.fill('');
  assert.equal(await body.inputValue(), 'I can order these myself.');
  await body.fill('pledge: 300\n\nI can order these myself.');
  assert.equal(await amount.inputValue(), '300', 'a typed line shows in the field');
  const replied = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post reply' }).click();
  assert.equal((await replied).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.locator('.pledge-amount[data-pledge-state="counted"]').waitFor();
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '300 of 1000 coffees pledged');
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '1 pledge');
  assert.equal((await writer.page.locator('.pledge-amount[data-pledge-state="counted"]').textContent()).trim(), '300 coffees');
  assert.equal(await writer.page.locator('.pledge-bar__fill').evaluate(e => e.style.width), '30%');

  // Past the goal the bar shows a goal mark.
  await settled(writer.page);
  await amount.fill('1,000');
  const second = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post reply' }).click();
  assert.equal((await second).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.locator('.pledge-bar__goal').waitFor({ state: 'attached' });
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged');

  // Withdraw: the pledger posts a third pledge with a note and takes it back with one press.
  await settled(writer.page);
  await body.fill('Half a tray more.');
  await amount.fill('5');
  const third = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post reply' }).click();
  assert.equal((await third).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1305 of 1000 coffees pledged');
  const withdrawForms = writer.page.locator('[data-role="pledge-withdraw"]');
  assert.equal(await withdrawForms.count(), 3, 'each of the pledger\'s counted pledges has the button');
  const cardsBefore = await writer.page.locator('article.post-card').count();
  const withdrawForm = withdrawForms.last();
  if (shots) { await mkdir(shots, { recursive: true }); await withdrawForm.scrollIntoViewIfNeeded(); await writer.page.screenshot({ path: join(shots, 'pledger-withdraw-button-375.png'), fullPage: true }); }
  // A failed send says so beside the button, and a second press works.
  await writer.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await withdrawForm.getByRole('button', { name: 'Withdraw pledge' }).click();
  const failure = withdrawForm.locator('[data-role="pledge-withdraw-feedback"]');
  await failure.waitFor({ state: 'visible' });
  assert.match((await failure.textContent()).trim(), /^Withdrawal failed\. Try again\./);
  assert.equal(await withdrawForm.getByRole('button', { name: 'Withdraw pledge' }).isEnabled(), true);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1305 of 1000 coffees pledged');
  const withdrew = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await withdrawForm.getByRole('button', { name: 'Withdraw pledge' }).click();
  assert.equal((await withdrew).status(), 200);
  await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"]').waitFor({ timeout: 30000 });
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged');
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '2 pledges');
  assert.match((await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"]').textContent()).trim(), /^5 coffees · Withdrawn [A-Z][a-z]{2} \d{1,2}$/);
  assert.equal(await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"] strong').evaluate(e => getComputedStyle(e).textDecorationLine), 'line-through');
  assert.equal(await writer.page.getByText('Half a tray more.').first().isVisible(), true, 'the withdrawn pledge keeps its text');
  assert.equal(await writer.page.locator('article.post-card').count(), cardsBefore, 'the bare withdrawal adds no card');
  assert.equal(await writer.page.locator('[data-role="pledge-withdraw"]').count(), 2, 'a withdrawn pledge has no button');
  // The label leads to the signed withdraw reply on its own page.
  const withdrawHref = await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"] a').getAttribute('href');
  assert.match(withdrawHref, /^\/posts\//);
  const replyPage = await open(true);
  await replyPage.page.goto(base + withdrawHref);
  assert.equal(await replyPage.page.getByText('withdraw', { exact: true }).first().isVisible(), true);
  await replyPage.context.close();
  // Another reader sees the same mark and no button.
  const reader = await open(true);
  await reader.page.goto(base + '/threads/' + threadId);
  assert.equal(await reader.page.locator('.pledge-amount[data-pledge-state="withdrawn"]').count(), 1);
  assert.equal(await reader.page.locator('[data-role="pledge-withdraw"]').count(), 0);
  await reader.context.close();
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });

  // Fill: the pledger marks the 300 pledge delivered with one press; a failed first press says so.
  const fillForms = writer.page.locator('[data-role="pledge-line"]');
  assert.equal(await fillForms.count(), 2, 'each open pledge has Mark delivered for its pledger');
  const fillForm = fillForms.first();
  const fillCards = await writer.page.locator('article.post-card').count();
  await writer.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await fillForm.getByRole('button', { name: 'Mark delivered' }).click();
  const fillFailure = fillForm.locator('[data-role="pledge-withdraw-feedback"]');
  await fillFailure.waitFor({ state: 'visible' });
  assert.match((await fillFailure.textContent()).trim(), /^Marking failed\. Try again\./);
  assert.equal(await fillForm.getByRole('button', { name: 'Mark delivered' }).isEnabled(), true);
  const filledReply = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await fillForm.getByRole('button', { name: 'Mark delivered' }).click();
  assert.equal((await filledReply).status(), 200);
  await writer.page.locator('.pledge-amount[data-pledge-state="fill"]').waitFor({ timeout: 30000 });
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-amount[data-pledge-state="fill"]').textContent()).trim(), /^Delivered by \S+$/);
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '2 pledges · 300 filled · 0 confirmed');
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged');
  assert.equal(await writer.page.locator('article.post-card').count(), fillCards, 'the bare fill adds no card');
  assert.equal(await writer.page.locator('[data-role="pledge-line"]').count(), 1, 'a filled pledge has no fill button');
  assert.equal(await writer.page.locator('[data-role="pledge-withdraw"]').count(), 1, 'a filled pledge cannot be withdrawn');
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });

  // Without JavaScript the server writes the line. The post has no name, so it counts only with --count-unnamed.
  const plain = await open(false);
  await plain.page.goto(base + '/threads/' + threadId);
  await plain.page.locator('[data-compose-kind="reply"] [data-role="pledge-amount"]').fill('12.5');
  await plain.page.locator('[data-compose-kind="reply"] textarea[name="body"]').fill('Decaf, then.');
  await plain.page.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.goto(base + '/threads/' + threadId);
  if (countUnnamed) {
    assert.equal(await plain.page.locator('.pledge-amount[data-pledge-state="unnamed"]').count(), 0);
    assert.equal((await plain.page.locator('.pledge-amount[data-pledge-state="counted"]').last().textContent()).trim(), '12.5 coffees');
    assert.equal((await plain.page.locator('.pledge-totals__sum').textContent()).trim(), '1312.5 of 1000 coffees pledged');
    assert.equal((await plain.page.locator('.pledge-totals__count').textContent()).trim(), '3 pledges · 300 filled · 0 confirmed');
  } else {
    assert.equal((await plain.page.locator('.pledge-amount[data-pledge-state="unnamed"]').textContent()).trim(), '12.5 coffees · Not counted: no name');
    assert.equal((await plain.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged');
  }
  assert.equal(await plain.page.getByText('pledge: 12.5').first().isVisible(), true);
  // A line that is not a number stays a reply and says why.
  await plain.page.locator('[data-compose-kind="reply"] textarea[name="body"]').fill('pledge: a few');
  await plain.page.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.goto(base + '/threads/' + threadId);
  assert.equal((await plain.page.locator('.pledge-amount[data-pledge-state="unreadable"]').textContent()).trim(), 'Not counted: “a few” is not a number');
  await plain.context.close();

  // Review: a second visitor pledges twice and marks both delivered; the author confirms one, after a
  // failed first press, and disputes the other. The dispute stays in the conversation.
  const pledger = await open(true);
  await pledger.page.goto(base + '/threads/' + threadId);
  await settled(pledger.page);
  for (const n of ['20', '30']) {
    await pledger.page.locator('[data-compose-kind="reply"] [data-role="pledge-amount"]').fill(n);
    const posted = pledger.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
    await pledger.page.locator('[data-compose-kind="reply"]').getByRole('button', { name: 'Post reply' }).click();
    assert.equal((await posted).status(), 200);
    await settled(pledger.page);
    await pledger.page.goto(base + '/threads/' + threadId);
    await settled(pledger.page);
  }
  for (let i = 0; i < 2; i++) {
    const mark = pledger.page.locator('[data-role="pledge-line"]').filter({ has: pledger.page.getByRole('button', { name: 'Mark delivered' }) }).first();
    const marked = pledger.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
    await mark.getByRole('button', { name: 'Mark delivered' }).click();
    assert.equal((await marked).status(), 200);
    await pledger.page.waitForLoadState('load');
    await settled(pledger.page);
  }
  assert.equal(await pledger.page.locator('[data-role="pledge-line"] input[name="body"][value="confirm"]').count(), 0, 'the pledger cannot review their own fill');
  assert.deepEqual(pledger.seen, { dialogs: [], errors: [] });
  await pledger.context.close();

  await writer.page.goto(base + '/threads/' + threadId);
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 350 filled · 0 confirmed$/);
  const confirmForms = writer.page.locator('[data-role="pledge-line"]').filter({ has: writer.page.locator('input[name="body"][value="confirm"]') });
  assert.equal(await confirmForms.count(), 2, 'each unreviewed delivery has Confirm for the author');
  const confirmForm = confirmForms.first();
  await writer.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await confirmForm.getByRole('button', { name: 'Confirm' }).click();
  const reviewFailure = confirmForm.locator('[data-role="pledge-withdraw-feedback"]');
  await reviewFailure.waitFor({ state: 'visible' });
  assert.match((await reviewFailure.textContent()).trim(), /^Review failed\. Try again\./);
  assert.equal(await confirmForm.getByRole('button', { name: 'Confirm' }).isEnabled(), true);
  const confirmed = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await confirmForm.getByRole('button', { name: 'Confirm' }).click();
  assert.equal((await confirmed).status(), 200);
  await writer.page.waitForLoadState('load');
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 350 filled · 20 confirmed$/);
  assert.match((await writer.page.locator('.pledge-amount[data-pledge-state="fill"]', { hasText: 'confirmed by' }).textContent()).trim(), /^Delivered by \S+ · confirmed by \S+$/);
  const reviewCards = await writer.page.locator('article.post-card').count();

  const disputeForm = writer.page.locator('[data-role="pledge-line"]').filter({ has: writer.page.locator('input[name="body"][value="dispute"]') });
  assert.equal(await disputeForm.count(), 1, 'a confirmed delivery has no review buttons');
  const disputed = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await disputeForm.getByRole('button', { name: 'Dispute' }).click();
  assert.equal((await disputed).status(), 200);
  await writer.page.waitForLoadState('load');
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 320 filled · 20 confirmed$/);
  const struck = writer.page.locator('.pledge-amount[data-pledge-state="fill-disputed"]');
  assert.match((await struck.textContent()).trim(), /^Delivered by \S+ · disputed by \S+$/);
  assert.equal(await struck.locator('s').evaluate(e => getComputedStyle(e).textDecorationLine), 'line-through');
  // The dispute and the fill it answers both show as cards.
  assert.equal(await writer.page.locator('article.post-card').count(), reviewCards + 2, 'the dispute and its fill stay in the conversation');
  assert.equal(await writer.page.locator('[data-role="pledge-line"] input[name="body"][value="confirm"]').count(), 0);
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });

  // Every theme: nothing overflows, and the sum, bar and field are on the page.
  for (const theme of countUnnamed ? [] : ['light', 'dark', 'gloss', 'glossdark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'bubblegum', 'spearmint']) {
    for (const [width, height] of [[375, 667], [1280, 900]]) {
      const view = await open(true, width, height);
      await view.page.addInitScript(theme => {
        localStorage.setItem('zenmemes-theme', theme);
        for (const key of Object.keys(localStorage)) if (key.endsWith('-theme')) localStorage.setItem(key, theme);
      }, theme);
      await view.page.goto(base + '/threads/' + threadId);
      const applied = await view.page.locator('html').getAttribute('data-theme');
      if (applied !== theme) { console.log(`SKIP ${theme}: not offered on this site (page shows ${applied})`); await view.context.close(); break; }
      assert.ok(await view.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme} ${width}px: no page overflow`);
      const bar = await view.page.locator('.pledge-bar').boundingBox();
      const fill = await view.page.locator('.pledge-bar__fill').boundingBox();
      assert.ok(bar.width > 100 && bar.height >= 8 && fill.width > bar.width * 0.9, `${theme} ${width}px: bar is drawn and full past the goal`);
      const colours = await view.page.locator('.pledge-bar__fill').evaluate(e => [getComputedStyle(e).backgroundColor, getComputedStyle(e.closest('.card') || document.body).backgroundColor]);
      assert.notEqual(colours[0], colours[1], `${theme}: bar fill differs from the card`);
      assert.equal(await view.page.locator('[data-role="pledge-amount"]').count(), 1);
      const mark = await view.page.locator('.pledge-amount[data-pledge-state="withdrawn"] a').boundingBox();
      assert.ok(mark && mark.width > 20 && mark.height > 8, `${theme} ${width}px: the Withdrawn label is drawn`);
      const fillMark = await view.page.locator('.pledge-amount[data-pledge-state="fill"] a').first().boundingBox();
      assert.ok(fillMark && fillMark.width > 40 && fillMark.height > 8, `${theme} ${width}px: the Delivered label is drawn`);
      const disputedMark = await view.page.locator('.pledge-amount[data-pledge-state="fill-disputed"] s').boundingBox();
      assert.ok(disputedMark && disputedMark.width > 40 && disputedMark.height > 8, `${theme} ${width}px: the disputed delivery is drawn`);
      if (shots) {
        await mkdir(shots, { recursive: true });
        await view.page.locator('[data-pledge-totals]').scrollIntoViewIfNeeded();
        await view.page.screenshot({ path: join(shots, `${theme}-${width}.png`), fullPage: true });
      }
      console.log(`PASS ${theme} ${width}px`);
      await view.context.close();
    }
  }
  await writer.context.close();
  console.log('PASS pledge campaign: goal line, totals, amount field both ways, signed pledge, goal mark, withdraw with a failed first press, fill with a failed first press, confirm with a failed first press, dispute, no-JavaScript pledge, uncounted reason, themes');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
