// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/nojs_basic_access.mjs
// Basic reading and posting with JavaScript disabled from the first request
// (docs/plans/nojs_basic_access/): entry, board page 2, a thread, inline and
// standalone threads and replies, a failed submission keeping the draft,
// reload persistence, no dead script-only controls, and honest members-only denial.
import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'nojs-basic-'));
const env = { ...process.env, IMAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'),
  FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
  FAST_SCORING_ENABLED: 'false', FORUM_APP_VERSION_NOTIFICATION: 'false',
  VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/nojs_browser_fixture.php'), root, '55'], { cwd: project, env, encoding: 'utf8' });
assert.equal(seeded.status, 0, seeded.stderr + seeded.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/image_browser_router.php')], { cwd: project, env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const failures = [];
const check = (ok, message) => { if (!ok) failures.push(message); };
let browser;
try {
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ javaScriptEnabled: false, serviceWorkers: 'block', viewport: { width: 1024, height: 900 } });
  const page = await context.newPage(); page.setDefaultTimeout(15000);
  const submit = kind => page.locator(`[data-compose-kind="${kind}"] .compose-form-actions > button[type="submit"]`).first();
  const deadControls = async where => {
    const dead = [];
    for (const button of await page.locator('button[type="button"]').all()) {
      if (await button.isVisible()) dead.push((await button.evaluate(e => e.outerHTML)).slice(0, 80));
    }
    check(dead.length === 0, `${where}: script-only controls visible without JavaScript: ${dead.join(' | ')}`);
  };

  // Entry and board pagination through ordinary links.
  await page.goto(base + '/');
  await page.getByRole('link', { name: 'All', exact: true }).click();
  const seededTitles = async () => new Set((await page.locator('body').innerText()).match(/Seeded thread \d\d/g) || []);
  const first = await seededTitles();
  await deadControls('board');
  await page.locator('a[rel="next"]').first().click(); await page.waitForLoadState('load');
  const second = await seededTitles();
  assert.ok(second.size > 0, 'page 2 shows no threads');
  assert.equal([...second].filter(t => first.has(t)).length, 0, 'page 2 repeats page 1');
  assert.equal(new Set([...first, ...second]).size, 55, 'pages 1 and 2 together miss seeded threads');
  await page.locator('a[rel="prev"]').first().click(); await page.waitForLoadState('load');
  assert.deepEqual([...await seededTitles()].sort(), [...first].sort(), 'going back does not restore page 1');
  console.log('PASS entry, board page 2 and back');

  // A thread, then the inline board composer.
  await page.getByRole('link', { name: 'Seeded thread 30' }).first().click();
  await page.getByText('Seeded body 30').first().waitFor();
  await deadControls('thread');
  await page.goto(base + '/');
  await page.locator('[data-inline-thread-compose] textarea[name="body"]').fill('Inline thread without JavaScript');
  await submit('thread').click(); await page.waitForURL(/\/threads\/[^/?]+/);
  await page.reload(); await page.getByText('Inline thread without JavaScript').first().waitFor();
  const inlineThreadUrl = page.url();
  console.log('PASS inline thread posted and kept after reload');

  // Standalone New post: a failed submission keeps the draft, then succeeds.
  await page.goto(base + '/');
  await page.getByRole('link', { name: /New post/ }).click();
  await deadControls('compose');
  await page.locator('input[name="subject"]').fill('Standalone draft title');
  await submit('thread').click(); await page.waitForLoadState('load');
  await page.getByText(/body is required/i).first().waitFor();
  assert.equal(await page.locator('input[name="subject"]').inputValue(), 'Standalone draft title');
  await page.locator('textarea[name="body"]').fill('Standalone thread without JavaScript');
  await submit('thread').click(); await page.waitForURL(/\/threads\/[^/?]+/);
  await page.reload(); await page.getByText('Standalone thread without JavaScript').first().waitFor();
  console.log('PASS standalone thread with retained draft after a failed submission');

  // Replies: inline on the thread page, then the standalone reply page with a failed submission first.
  await page.goto(inlineThreadUrl);
  await page.locator('[data-inline-reply-compose] textarea[name="body"]').first().fill('Inline reply without JavaScript');
  await submit('reply').click(); await page.waitForLoadState('load');
  await page.goto(inlineThreadUrl); await page.getByText('Inline reply without JavaScript').first().waitFor();
  // In a merged same-author run only the last post's actions show (earlier ones reveal on hover/focus).
  await page.locator('a[href*="/compose/reply?"]').last().click();
  await submit('reply').click(); await page.waitForLoadState('load');
  await page.getByText(/body is required/i).first().waitFor();
  check(await page.locator('.reply-context').count() > 0, 'reply error page lost the thread context');
  await page.locator('textarea[name="body"]').fill('Standalone reply without JavaScript');
  await submit('reply').click(); await page.waitForLoadState('load');
  await page.goto(inlineThreadUrl); await page.getByText('Standalone reply without JavaScript').first().waitFor();
  console.log('PASS inline and standalone replies kept after reload');
  await context.close();

  // Members-only: protected pages are denied honestly, with working ways forward.
  await writeFile(join(root, 'flags.json'), JSON.stringify({ members: true }));
  const fresh = await browser.newContext({ javaScriptEnabled: false, serviceWorkers: 'block' });
  const denied = await fresh.newPage();
  const response = await denied.goto(base + '/threads/');
  check(response.status() === 401, `members-only board answered ${response.status()}, expected 401`);
  const heading = (await denied.locator('h1:visible').first().innerText()).trim();
  check(!/reconnecting|verifying/i.test(heading + await denied.locator('main').innerText()),
    `members-only denial claims progress without JavaScript: "${heading}"`);
  for (const href of ['/account/key/', '/lobby/']) {
    check(await denied.locator(`main a[href^="${href}"]`).first().isVisible(), `members-only denial lacks a visible ${href} link`);
  }
  await fresh.close();

  assert.deepEqual(failures, [], failures.join('\n'));
  console.log('PASS no dead script-only controls; honest members-only denial');
} finally {
  if (browser) await browser.close();
  server.kill(); await once(server, 'exit');
  if (process.env.KEEP_NOJS_ROOT) console.error('kept', root); else await rm(root, { recursive: true, force: true });
  if (process.exitCode && serverLog) console.error(serverLog.slice(-2000));
}
