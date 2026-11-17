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
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'pledges-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root, FORUM_PLEDGES_ENABLED: '1',
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
  await openRows(page);
}
// Each pledge is a closed row, and a campaign card's buttons sit in a closed "More"; a reader opens
// one by pressing its summary, as this does for every row and every "More".
async function openRows(page) {
  const closed = page.locator('details.pledge-row-fold:not([open]) > summary, details.post-more:not([open]) > summary');
  for (let n = await closed.count(); n > 0; n--) {
    // Centre it first: on a phone the fixed bottom bar covers a row scrolled only to the edge.
    await closed.first().evaluate(e => e.scrollIntoView({ block: 'center' }));
    await closed.first().click();
  }
}
// Presses a control after centring it: on a phone the fixed bottom bar covers one scrolled only to the edge.
async function press(locator) {
  await locator.evaluate(e => e.scrollIntoView({ block: 'center' }));
  await locator.click();
}
// A pledge row's amount and status, as "300 · Open".
const rowText = async (row) => `${(await row.locator('.pledge-amount').textContent()).trim()} · ${(await row.locator('[data-role="pledge-card-status"]').textContent()).trim()}`;
// Approves the author of the newest post whose body starts with `start`, as an approval would: pledges
// count only from approved keys, and the automatic guest keys these visitors get are not approved.
function approveAuthorOf(start) {
  const done = spawnSync('php', ['-r', `$db = new PDO('sqlite:' . $argv[1]);
    $find = $db->prepare('SELECT author_identity_id FROM posts WHERE body LIKE ? ORDER BY created_at DESC LIMIT 1');
    $find->execute([$argv[2] . '%']);
    $id = (string) $find->fetchColumn();
    // An approval seed is canonical, so later writes that recompute approval keep it.
    file_put_contents($argv[3] . '/records/approval-seeds/' . str_replace(':', '-', $id) . '.txt',
        "Approved-Identity-ID: {$id}\nSeed-Reason: browser check approves this visitor\n\nApproved so its pledges count.\n");
    $db->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?')->execute([$id]);
    echo $id;`, join(root, 'read.sqlite3'), start, join(root, 'repository')], { encoding: 'utf8' });
  assert.equal(done.status, 0, done.stderr);
  assert.match(done.stdout, /^openpgp:/);
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
  approveAuthorOf('Three days, about a hundred people.');
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '0 of 1000 coffees pledged');

  // The pledge form in the totals block keeps a first line "pledge: N" in the note, both ways.
  // The reply box at the bottom has no amount field.
  const reply = writer.page.locator('[data-pledge-form]');
  const bottom = writer.page.locator('[data-inline-reply-compose] [data-compose-kind="reply"]');
  assert.equal(await bottom.locator('[data-role="pledge-amount"]').count(), 0);
  assert.equal(await writer.page.locator('[data-pledge-totals] [data-pledge-form]').count(), 0);
  assert.equal(await writer.page.locator('.pledge-form [data-pledge-form]').count(), 1);
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
  // The two composers keep separate drafts: text in the reply box does not reach the pledge form.
  await bottom.locator('textarea[name="body"]').fill('Just a question.');
  assert.equal(await body.inputValue(), 'pledge: 300\n\nI can order these myself.');
  await bottom.locator('textarea[name="body"]').fill('');
  // The opening post's Reply link goes to the reply box, not the pledge form.
  await writer.page.locator('[data-inline-reply-link]').first().click();
  assert.equal(await bottom.locator('textarea[name="body"]').evaluate(el => el === document.activeElement), true);
  // A failed first press keeps the amount and note, and a second press posts the pledge.
  await writer.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await reply.getByRole('button', { name: 'Post pledge' }).click();
  await writer.page.locator('.pledge-form [data-role="compose-identity-status"][data-kind="error"]').waitFor({ state: 'visible', timeout: 30000 });
  assert.equal(await amount.inputValue(), '300');
  assert.equal(await body.inputValue(), 'pledge: 300\n\nI can order these myself.');
  assert.equal(await writer.page.locator('.pledge-amount[data-pledge-state="counted"]').count(), 0);
  const replied = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post pledge' }).click();
  assert.equal((await replied).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.locator('.pledge-amount[data-pledge-state="counted"]').waitFor();
  // The pledge arrives as a closed row: name, amount and status, with the note hidden until opened.
  assert.equal(await writer.page.locator('details.pledge-row-fold').evaluate(d => d.open), false);
  assert.equal(await writer.page.getByText('I can order these myself.').isVisible(), false);
  await openRows(writer.page);
  assert.equal(await writer.page.getByText('I can order these myself.').isVisible(), true);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '300 of 1000 coffees pledged');
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '1 pledge');
  assert.equal(await rowText(writer.page.locator('.pledge-row-summary').first()), '300 · Open');
  assert.equal(await writer.page.locator('.pledge-bar__fill').evaluate(e => e.style.width), '30%');
  // A reply saved on this device is listed by the reply box at the bottom, not by the pledge form.
  const unsentBesideReplyBox = await writer.page.evaluate(async (thread) => {
    const item = window.forumOutboxCompose.replyItem({ threadId: thread, parentId: thread, body: 'Saved for later' });
    await window.forumOutboxStorage.save(item);
    await window.forumOutboxCompose.showUnsentReplies();
    const list = document.querySelector('[data-role="outbox-unsent-replies"]');
    const beside = !!list && !list.closest('.pledge-form') && list.nextElementSibling === document.querySelector('[data-inline-reply-compose]').closest('[data-compose-root]');
    await window.forumOutboxStorage.remove(item.id);
    await window.forumOutboxCompose.showUnsentReplies();
    return beside;
  }, threadId);
  assert.equal(unsentBesideReplyBox, true);

  // Past the goal the bar shows a goal mark.
  await settled(writer.page);
  await amount.fill('1,000');
  const second = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post pledge' }).click();
  assert.equal((await second).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.locator('.pledge-bar__goal').waitFor({ state: 'attached' });
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged Goal reached');

  // Withdraw: the pledger posts a third pledge with a note and takes it back with one press.
  await settled(writer.page);
  await body.fill('Half a tray more.');
  await amount.fill('5');
  const third = writer.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await reply.getByRole('button', { name: 'Post pledge' }).click();
  assert.equal((await third).status(), 200);
  await settled(writer.page);
  await writer.page.goto(base + '/threads/' + threadId);
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1305 of 1000 coffees pledged Goal reached');
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
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1305 of 1000 coffees pledged Goal reached');
  const withdrew = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await withdrawForm.getByRole('button', { name: 'Withdraw pledge' }).click();
  assert.equal((await withdrew).status(), 200);
  await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"]').waitFor({ state: 'attached', timeout: 30000 });
  await settled(writer.page);
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged Goal reached');
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '2 pledges');
  assert.equal(await rowText(writer.page.locator('.pledge-row-summary', { has: writer.page.locator('[data-pledge-state="withdrawn"]') })), '5 · Withdrawn');
  assert.match((await writer.page.locator('[data-pledge-state="withdrawn-by"]').textContent()).trim(), /^Withdrawn [A-Z][a-z]{2} \d{1,2}$/);
  assert.equal(await writer.page.locator('.pledge-amount[data-pledge-state="withdrawn"] strong').evaluate(e => getComputedStyle(e).textDecorationLine), 'line-through');
  assert.equal(await writer.page.getByText('Half a tray more.').first().isVisible(), true, 'the withdrawn pledge keeps its text');
  assert.equal(await writer.page.locator('article.post-card').count(), cardsBefore, 'the bare withdrawal adds no card');
  assert.equal(await writer.page.locator('[data-role="pledge-withdraw"]').count(), 2, 'a withdrawn pledge has no button');
  // The label leads to the signed withdraw reply on its own page.
  const withdrawHref = await writer.page.locator('[data-pledge-state="withdrawn-by"] a').getAttribute('href');
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
  await writer.page.locator('.pledge-amount[data-pledge-state="fill"]').waitFor({ state: 'attached', timeout: 30000 });
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-amount[data-pledge-state="fill"]').textContent()).trim(), /^Delivered by \S+$/);
  assert.equal((await writer.page.locator('.pledge-totals__count').textContent()).trim(), '2 pledges · 300 delivered');
  assert.equal((await writer.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged Goal reached');
  assert.equal(await writer.page.locator('article.post-card').count(), fillCards, 'the bare fill adds no card');
  assert.equal(await writer.page.locator('[data-role="pledge-line"]').count(), 1, 'a filled pledge has no fill button');
  assert.equal(await writer.page.locator('[data-role="pledge-withdraw"]').count(), 1, 'a filled pledge cannot be withdrawn');
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });
  // Inside "More", Retract is a plain button, not a second "…" menu; it still asks first.
  await openRows(writer.page);
  assert.equal(await writer.page.locator('.post-more .post-more-actions').count(), 0, 'no "…" menu inside More');
  const cardsBeforeRetract = await writer.page.locator('article.post-card').count();
  await press(writer.page.locator('.post-more [data-action="retract-post"]:not([data-role])').first());
  assert.equal(writer.seen.dialogs.length, 1, 'Retract asks to confirm');
  assert.match(writer.seen.dialogs[0], /^Retract this post\?/);
  writer.seen.dialogs.length = 0;
  assert.equal(await writer.page.locator('article.post-card').count(), cardsBeforeRetract, 'a dismissed confirm retracts nothing');

  // Without JavaScript the server writes the line. The post has no name, so it never counts.
  const plain = await open(false);
  await plain.page.goto(base + '/threads/' + threadId);
  await plain.page.locator('[data-pledge-form] [data-role="pledge-amount"]').fill('12.5');
  await plain.page.locator('[data-pledge-form] textarea[name="body"]').fill('Decaf, then.');
  await plain.page.locator('[data-pledge-form] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.goto(base + '/threads/' + threadId);
  assert.equal(await rowText(plain.page.locator('.pledge-row-summary', { has: plain.page.locator('[data-pledge-state="unnamed"]') })), '12.5 · Not counted: no name');
  assert.equal((await plain.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged Goal reached');
  // The card shows the amount, so the thread page leaves the written line out; the note stays.
  assert.equal(await plain.page.getByText('pledge: 12.5').count(), 0);
  // Without JavaScript a row still opens: it is a plain disclosure.
  await openRows(plain.page);
  assert.equal(await plain.page.getByText('Decaf, then.').first().isVisible(), true);
  // A line that is not a number stays a reply and says why.
  await plain.page.locator('[data-inline-reply-compose] textarea[name="body"]').fill('pledge: a few');
  await press(plain.page.locator('[data-inline-reply-compose] .compose-form-actions > button[type="submit"]'));
  await plain.page.waitForLoadState('load');
  await plain.page.goto(base + '/threads/' + threadId);
  assert.equal((await plain.page.locator('.pledge-amount[data-pledge-state="unreadable"]').textContent()).trim(), 'Not counted: “a few” is not a number');
  await plain.context.close();

  // Approval: a second visitor's automatic guest key is not approved, so its first pledge waits and
  // stays out of the sum. Approving the key (here, in the read model) makes it count on the next view.
  const pledger = await open(true);
  await pledger.page.goto(base + '/threads/' + threadId);
  await settled(pledger.page);
  await pledger.page.locator('[data-pledge-form] [data-role="pledge-amount"]').fill('20');
  const first = pledger.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
  await pledger.page.locator('[data-pledge-form]').getByRole('button', { name: 'Post pledge' }).click();
  assert.equal((await first).status(), 200);
  await settled(pledger.page);
  await pledger.page.goto(base + '/threads/' + threadId);
  await settled(pledger.page);
  assert.equal(await rowText(pledger.page.locator('.pledge-row-summary', { has: pledger.page.locator('[data-pledge-state="waiting"]') })), '20 · Waiting for approval');
  assert.equal((await pledger.page.locator('.pledge-totals__sum').textContent()).trim(), '1300 of 1000 coffees pledged Goal reached');
  approveAuthorOf('pledge: 20');
  await pledger.page.goto(base + '/threads/' + threadId);
  await settled(pledger.page);
  assert.equal(await pledger.page.locator('.pledge-amount[data-pledge-state="waiting"]').count(), 0);
  assert.equal((await pledger.page.locator('.pledge-totals__sum').textContent()).trim(), '1320 of 1000 coffees pledged Goal reached');

  // Review: the now approved visitor pledges again and marks both delivered; the author confirms one,
  // after a failed first press, and disputes the other. The dispute stays in the conversation.
  for (const n of ['30']) {
    await pledger.page.locator('[data-pledge-form] [data-role="pledge-amount"]').fill(n);
    const posted = pledger.page.waitForResponse(r => /\/api\/(create_prepared_post|create_reply)$/.test(r.url()), { timeout: 60000 });
    await pledger.page.locator('[data-pledge-form]').getByRole('button', { name: 'Post pledge' }).click();
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
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 350 delivered$/);
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
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 350 delivered \(20 confirmed\)$/);
  assert.match((await writer.page.locator('.pledge-amount[data-pledge-state="fill"]', { hasText: 'confirmed by' }).textContent()).trim(), /^Delivered by \S+ · confirmed by \S+$/);
  const reviewCards = await writer.page.locator('article.post-card').count();

  const disputeForm = writer.page.locator('[data-role="pledge-line"]').filter({ has: writer.page.locator('input[name="body"][value="dispute"]') });
  assert.equal(await disputeForm.count(), 1, 'a confirmed delivery has no review buttons');
  const disputed = writer.page.waitForResponse(r => /\/api\/create_prepared_post$/.test(r.url()), { timeout: 60000 });
  await disputeForm.getByRole('button', { name: 'Dispute' }).click();
  assert.equal((await disputed).status(), 200);
  await writer.page.waitForLoadState('load');
  await settled(writer.page);
  assert.match((await writer.page.locator('.pledge-totals__count').textContent()).trim(), / · 320 delivered \(20 confirmed\)$/);
  const struck = writer.page.locator('.pledge-amount[data-pledge-state="fill-disputed"]');
  assert.match((await struck.textContent()).trim(), /^Delivered by \S+ · disputed by \S+$/);
  assert.equal(await struck.locator('s').evaluate(e => getComputedStyle(e).textDecorationLine), 'line-through');
  // The dispute and the fill it answers both show as cards.
  assert.equal(await writer.page.locator('article.post-card').count(), reviewCards + 2, 'the dispute and its fill stay in the conversation');
  assert.equal(await writer.page.locator('[data-role="pledge-line"] input[name="body"][value="confirm"]').count(), 0);
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });

  // "More": a card on a campaign thread shows only its short byline until "More" is opened; Reply
  // and Like work from inside it.
  await writer.page.goto(base + '/threads/' + threadId);
  await writer.page.waitForLoadState('load');
  const firstMore = writer.page.locator('article.pledge-row details.post-more').first();
  await press(writer.page.locator('article.pledge-row details.pledge-row-fold:not([open]) > summary').first());
  assert.equal(await firstMore.evaluate(d => d.open), false);
  assert.equal(await firstMore.locator('.post-card-actions').isVisible(), false);
  await press(firstMore.locator(':scope > summary'));
  const like = firstMore.locator('button[data-tag="like"]');
  assert.equal(await like.isVisible(), true);
  const liked = writer.page.waitForResponse(r => /\/api\//.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 });
  await press(like);
  assert.equal((await liked).ok(), true);
  await writer.page.waitForFunction(() => document.querySelector('article.pledge-row details.post-more button[data-tag="like"]')?.getAttribute('aria-pressed') === 'true', null, { timeout: 30000 });
  assert.match(await firstMore.getByRole('link', { name: 'Reply' }).getAttribute('href'), /^\/compose\/reply\?thread_id=.+&parent_id=/);
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });

  // A second campaign with a long unit, so its board row has to wrap rather than squeeze the title.
  await writer.page.goto(base + '/compose/thread');
  const longForm = writer.page.locator('[data-compose-kind="thread"]');
  await longForm.getByRole('textbox', { name: 'Title' }).fill('Roasting day');
  await longForm.getByRole('textbox', { name: 'Body' }).fill('goal: 40 hand-roasted single-origin coffees from the shop on Mass Ave');
  await longForm.getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/threads\//, { timeout: 30000 });
  const longId = /\/threads\/([^?#]+)/.exec(writer.page.url())[1];
  // The board row's line, checked against the thread page's own numbers. Without JavaScript too.
  const boardLine = async (page, id) => {
    const row = page.locator(`article#post-${id}`);
    const line = row.locator('[data-pledge-row]');
    const title = row.locator('.thread-row__title');
    const titleHeight = (await title.boundingBox()).height;
    const geometry = await row.evaluate(r => {
      const box = s => r.querySelector(s)?.getBoundingClientRect();
      const [t, l, c] = [box('.thread-row__title'), box('[data-pledge-row]'), box('.thread-row__count')];
      return { below: l.top >= t.bottom - 1, clear: !c || c.width === 0 || l.right <= c.left + 1, fits: r.scrollWidth <= r.clientWidth + 1 };
    });
    await line.evaluate(e => { e.style.display = 'none'; });
    const titleAlone = (await title.boundingBox()).height;
    await line.evaluate(e => { e.style.display = ''; });
    return { text: (await line.innerText()).replace(/\s+/g, ' ').trim(), sameTitle: Math.abs(titleHeight - titleAlone) < 1, ...geometry };
  };
  const plainView = await open(false);
  await plainView.page.goto(base + '/threads/?view=all&sort=newest');
  assert.match((await boardLine(plainView.page, threadId)).text, /^\d[\d.,]* \/ 1000 coffees/, 'board line without JavaScript');
  await plainView.context.close();

  // Every theme: nothing overflows, and the sum, bar and field are on the page.
  for (const theme of ['light', 'dark', 'gloss', 'glossdark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'bubblegum', 'spearmint']) {
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
      // The form sits below the opening post's text, inside a visible frame.
      const framed = await view.page.locator('.pledge-form').evaluate(form => {
        const body = form.closest('.card, article, main').querySelector('.body');
        const style = getComputedStyle(form);
        const edge = ['Top', 'Right', 'Bottom', 'Left'].every(side => parseFloat(style[`border${side}Width`]) > 0 && style[`border${side}Style`] !== 'none');
        return { below: !!body && form.getBoundingClientRect().top >= body.getBoundingClientRect().bottom - 1, edge, colour: style.borderTopColor, page: getComputedStyle(form.parentElement).backgroundColor };
      });
      assert.ok(framed.below, `${theme} ${width}px: the pledge form follows the opening post`);
      assert.ok(framed.edge, `${theme} ${width}px: the pledge form has a border`);
      if (shots) {
        await mkdir(shots, { recursive: true });
        await view.page.screenshot({ path: join(shots, `${theme}-${width}-rows.png`), fullPage: true });
      }
      // Closed rows are one line each, and rows that follow each other touch.
      const rows = await view.page.locator('article.pledge-row').evaluateAll(cards => cards.map(c => c.getBoundingClientRect()).map(r => ({ top: r.top, bottom: r.bottom, height: r.height })));
      assert.ok(rows.length >= 4, `${theme} ${width}px: pledge rows are drawn`);
      const lines = await view.page.locator('.pledge-row-summary').evaluateAll(sums => sums.map(e => e.getBoundingClientRect().height / parseFloat(getComputedStyle(e).fontSize)));
      assert.ok(lines.every(h => h < 2.6), `${theme} ${width}px: each closed row is one line (${lines.map(h => h.toFixed(1)).join(', ')} em)`);
      assert.ok(rows.some((r, i) => i > 0 && Math.abs(r.top - rows[i - 1].bottom) < 2), `${theme} ${width}px: adjacent rows join into a list`);
      await openRows(view.page);
      const mark = await view.page.locator('[data-pledge-state="withdrawn-by"] a').boundingBox();
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
      // The board row shows the thread page's sum and status word, under the title, without crowding it.
      const pledged = (await view.page.locator('.pledge-totals__sum strong').textContent()).trim();
      const status = (await view.page.locator('.pledge-totals__sum .pledge-status').allTextContents()).map(t => t.trim()).join('');
      await view.page.goto(base + '/threads/?view=all&sort=newest');
      for (const [id, expected] of [[threadId, `${pledged} / 1000 coffees${status ? ' ' + status : ''}`], [longId, '0 / 40 hand-roasted single-origin coffees from the shop on Mass Ave']]) {
        const seen = await boardLine(view.page, id);
        assert.equal(seen.text, expected, `${theme} ${width}px: board line`);
        assert.equal(await view.page.locator(`article#post-${id} .thread-row__excerpt`).filter({ hasText: 'goal:' }).count(), 0, `${theme} ${width}px: the excerpt leaves out the goal line`);
        assert.ok(seen.below && seen.sameTitle && seen.clear && seen.fits, `${theme} ${width}px: board line under the title, title unchanged, clear of the count (${JSON.stringify(seen)})`);
      }
      assert.ok(await view.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme} ${width}px: no board overflow`);
      if (shots) {
        await view.page.locator(`article#post-${threadId}`).scrollIntoViewIfNeeded();
        await view.page.screenshot({ path: join(shots, `${theme}-${width}-board.png`) });
      }
      console.log(`PASS ${theme} ${width}px`);
      await view.context.close();
    }
  }
  await writer.context.close();
  console.log('PASS pledge campaign: goal line, totals, amount field both ways, signed pledge, goal mark, withdraw with a failed first press, fill with a failed first press, confirm with a failed first press, dispute, no-JavaScript pledge, uncounted reason, board rows, themes');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
