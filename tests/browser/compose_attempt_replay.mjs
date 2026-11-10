// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/compose_attempt_replay.mjs
// A lost response or Back must not publish an anonymous post twice, on a real server.
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = mkdtempSync(join(tmpdir(), 'compose-attempt-'));
const repository = join(root, 'repository');
mkdirSync(join(root, 'static'));
cpSync(join(project, 'public/assets'), join(root, 'artifacts/assets'), { recursive: true });
cpSync(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
const git = (...args) => {
  const result = spawnSync('git', ['-C', repository, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', ...args], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return result.stdout.trim();
};
git('init'); git('add', '.'); git('commit', '-m', 'Initialize');

const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise((done) => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), join(project, 'public/router.php')], {
  cwd: project,
  env: { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_REPOSITORY_ROOT: repository, FORUM_DATABASE_PATH: join(root, 'read.sqlite3'),
    FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
    FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED: 'false', FAST_SCORING_ENABLED: 'false',
    FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
  stdio: 'ignore',
});
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

const commits = () => Number(git('rev-list', '--count', 'HEAD'));
const committedWith = (text) => { const found = spawnSync('git', ['-C', repository, 'grep', '-l', text, 'HEAD', '--', 'records/posts'], { encoding: 'utf8' }); return found.stdout.trim().split('\n').filter(Boolean); };
const submitAnonymously = (page) => page.locator('form[data-compose-form] [data-action="submit-anonymous-compose"]').first()
  .evaluate((button) => button.closest('form').requestSubmit(button));
const body = (page) => page.locator('form[data-compose-form] textarea[name="body"]').first();

// The server handles the POST, but neither its response nor its cookies reach the browser.
async function submitLosingResponse(page) {
  let lost;
  const done = new Promise((resolve) => { lost = resolve; });
  const handler = async (route) => {
    const request = route.request();
    if (request.method() !== 'POST') return route.fallback();
    // Read the body: an unread response can crash Node's fetch when the server closes the socket.
    await (await fetch(request.url(), { method: 'POST', headers: { 'content-type': request.headers()['content-type'] }, body: request.postData(), redirect: 'manual' })).arrayBuffer();
    await route.abort();
    lost();
  };
  await page.route('**/compose/**', handler);
  await submitAnonymously(page);
  await Promise.race([done, new Promise((_, reject) => setTimeout(() => reject(new Error('compose POST never arrived')), 15000))]);
  await page.unroute('**/compose/**', handler);
}

async function lostResponse(context, composeUrl, text, prefix) {
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto(base + composeUrl);
  await body(page).fill(text);
  const committed = commits();
  await submitLosingResponse(page);
  assert.equal(commits(), committed + 1, 'first submission published');

  // Lookup down: the draft survives and an identical retry reuses the post.
  await page.route('**/api/get_post*', (route) => route.abort());
  await page.goto(base + composeUrl);
  assert.equal(await body(page).inputValue(), text, 'draft kept while the lookup is down');
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/compose')), submitAnonymously(page)]);
  assert.equal(commits(), committed + 1, 'identical retry published nothing new');
  const published = committedWith(text);
  assert.equal(published.length, 1, 'one post holds the text');
  assert.match(published[0], new RegExp(`^HEAD:records/posts/${prefix}-[a-f0-9]{32}\\.txt$`));
  await page.close();
}

async function reconciledAfterLoss(context, composeUrl, text) {
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto(base + composeUrl);
  await body(page).fill(text);
  const committed = commits();
  await submitLosingResponse(page);
  await page.goto(base + composeUrl);
  await page.waitForFunction(() => document.querySelector('form[data-compose-form] textarea[name="body"]').value === '');
  assert.equal(commits(), committed + 1);
  await page.close();
}

async function backAndPrefill(context, text) {
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto(`${base}/compose/thread`);
  await body(page).fill(text);
  const committed = commits();
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/compose')), submitAnonymously(page)]);
  assert.equal(commits(), committed + 1);
  await page.goto(`${base}/compose/thread?subject=Clipped&body=Clipped%20text`);
  assert.equal(await body(page).inputValue(), 'Clipped text', 'a link opened after publishing keeps its text');
  await body(page).fill(`${text} again`);
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/compose')), submitAnonymously(page)]);
  await page.goBack();
  await page.waitForLoadState('load');
  assert.equal(await body(page).inputValue(), '', 'Back to a prefilled link leaves no published text');
  await page.goto(`${base}/compose/thread`);
  await body(page).fill(`${text} plain`);
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/compose')), submitAnonymously(page)]);
  await page.goBack();
  await page.waitForLoadState('load');
  assert.equal(await body(page).inputValue(), '', 'Back leaves no published text');
  assert.equal(await page.evaluate(() => localStorage.getItem('forum_compose_draft:thread')), null, 'Back saves no draft of the published text');
  assert.equal(commits(), committed + 3, 'Back published nothing');
  await page.close();
}

// The stored post no longer matches this draft (say, a deploy changed the record format).
// The refusal must keep the text and the next send must publish, never refuse forever.
async function conflictThenFreshSend(context, composeUrl, text) {
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto(base + composeUrl);
  await body(page).fill(text);
  const committed = commits();
  await submitLosingResponse(page);
  const [stored] = committedWith(text);
  const storedPath = join(repository, stored.replace(/^HEAD:/, ''));
  writeFileSync(storedPath, readFileSync(storedPath, 'utf8').replace(text, `${text} as stored`));
  git('commit', '-am', 'Diverge stored post');

  await page.route('**/api/get_post*', (route) => route.abort());
  await page.goto(base + composeUrl);
  await submitAnonymously(page);
  await page.waitForSelector('[data-compose-attempt-retired]');
  assert.equal(await body(page).inputValue(), text, 'refusal keeps the text');
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/compose')), submitAnonymously(page)]);
  assert.equal(commits(), committed + 3, 'the next send published under a fresh ID');
  await page.close();
}

let failed = false;
try {
  for (const [name, options] of [['desktop', {}], ['phone', { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true }]]) {
    // page.route cannot see requests a service worker handles.
    const context = await browser.newContext({ ...options, serviceWorkers: 'block' });
    for (let i = 0; i < 100; i += 1) { try { await (await fetch(base + '/compose/thread')).text(); break; } catch { await new Promise((done) => setTimeout(done, 100)); } }
    await lostResponse(context, '/compose/thread', `Retry thread ${name}`, 'thread');
    await reconciledAfterLoss(context, '/compose/thread', `Reconciled thread ${name}`);
    await lostResponse(context, '/compose/reply?thread_id=root-001&parent_id=root-001', `Retry reply ${name}`, 'reply');
    await reconciledAfterLoss(context, '/compose/reply?thread_id=root-001&parent_id=root-001', `Reconciled reply ${name}`);
    await backAndPrefill(context, `Prefill check ${name}`);
    await conflictThenFreshSend(context, '/compose/thread', `Conflict thread ${name}`);
    await conflictThenFreshSend(context, '/compose/reply?thread_id=root-001&parent_id=root-001', `Conflict reply ${name}`);
    await context.close();
    console.log(`PASS compose attempt replay (${name})`);
  }
} catch (error) {
  failed = true;
  console.error(error);
} finally {
  await browser.close();
  server.kill();
  rmSync(root, { recursive: true, force: true });
}
process.exit(failed ? 1 : 0);
