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
    assert.equal((await plain.page.locator('.pledge-totals__count').textContent()).trim(), '3 pledges');
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
  console.log('PASS pledge campaign: goal line, totals, amount field both ways, signed pledge, goal mark, no-JavaScript pledge, uncounted reason, themes');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { await browser.close(); server.kill(); }
