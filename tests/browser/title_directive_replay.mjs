// Known-user replay for the title directive: the thread author retitles through the ordinary
// inline reply composer; an approved non-operator member is ignored until the operator turns the
// second flag on through the Feature Flags page; turning the directive flag off leaves the line as text.
import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
async function identity(name) {
  const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  const fingerprint = (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
  return { name, keys, fingerprint, id: 'openpgp:' + fingerprint.toLowerCase() };
}
const author = await identity('replay-author');
const member = await identity('replay-member');
const operator = await identity('replay-operator');
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const root = await mkdtemp(join(tmpdir(), 'title-directive-replay-'));
let server;
try {
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const seed = spawnSync('php', [join(project, 'tests/Support/title_directive_browser_fixture.php'), root], {
    input: JSON.stringify({ authorPublicKey: author.keys.publicKey, memberPublicKey: member.keys.publicKey, operatorPublicKey: operator.keys.publicKey }), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr + seed.stdout);
  const fixture = JSON.parse(seed.stdout);
  assert.equal(fixture.author, author.id);
  const threadId = fixture.thread_id;
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
  let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 50)); } }

  async function contextFor(who) {
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 800 } });
    const sign = async text => pgp.sign({ message: await pgp.createMessage({ text }), signingKeys: await pgp.readPrivateKey({ armoredKey: who.keys.privateKey }), detached: true });
    const challenge = (await (await context.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
    const auth = await context.request.post(base + '/api/authenticate_identity', { data: { challenge, identity_id: who.id, detached_signature: await sign(challenge) } });
    assert.equal(auth.status(), 200, await auth.text());
    await context.request.get(base + '/api/set_identity_hint?identity_hint=' + encodeURIComponent(who.id));
    await context.addInitScript(({ keys, fingerprint, name }) => {
      for (const [key, value] of Object.entries({ public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint, username: name })) localStorage.setItem('forum_pki_' + key, value);
      localStorage.setItem('forum-zenmemes-theme', 'light');
    }, { keys: who.keys, fingerprint: who.fingerprint, name: who.name });
    return context;
  }

  /** Posts a reply through the thread page's ordinary inline composer and returns the finalize response text. */
  async function replyThroughComposer(context, body) {
    const page = await context.newPage();
    page.on('dialog', dialog => dialog.dismiss());
    await page.goto(base + '/threads/' + threadId);
    // Any Reply link opens the shared inline composer. Dispatch the click through the DOM so a
    // collapsed same-author card overlapping an earlier link does not matter here (link geometry
    // is covered by inline_reply_link.mjs); this test is about the write path and its outcome.
    await page.locator('[data-inline-reply-link]').first().waitFor();
    await page.evaluate(() => document.querySelector('[data-inline-reply-link]').click());
    await page.waitForSelector('[data-inline-reply-compose][data-inline-reply-expanded="1"]', { timeout: 15000 });
    await page.locator('textarea[name="body"]').fill(body);
    const finalize = page.waitForResponse(response => /\/api\/create_prepared_post$/.test(response.url()), { timeout: 30000 });
    await page.locator('[data-inline-reply-compose] button:has-text("Post reply")').first().click();
    const response = await finalize;
    const text = await response.text();
    assert.equal(response.status(), 200, text);
    await page.close();
    return text;
  }

  async function threadState(context) {
    const page = await context.newPage();
    await page.goto(base + '/threads/' + threadId);
    const title = await page.locator('h1').first().innerText();
    const signals = await page.locator('[data-role="post-signals"][data-signal-scope="thread"]').innerText().catch(() => '');
    const bodyText = await page.locator('body').innerText();
    await page.close();
    return { title, signals, bodyText };
  }

  async function titleEverywhere(context, title) {
    const page = await context.newPage();
    for (const path of ['/?view=all', '/recent/', '/forte', '/forte?selected=' + threadId]) {
      await page.goto(base + path);
      assert.match(await page.locator('body').innerText(), new RegExp(title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), path);
    }
    await page.close();
  }

  // 1. The author retitles their own thread with the flags at their defaults.
  const authorContext = await contextFor(author);
  assert.equal((await threadState(authorContext)).title, 'Replay original title');
  await replyThroughComposer(authorContext, 'title: Renamed by the author\nThanks for the discussion.');
  let state = await threadState(authorContext);
  assert.equal(state.title, 'Renamed by the author');
  assert.match(state.signals, /Title set by replay-author/);
  assert.match(state.bodyText, /title: Renamed by the author/, 'the reply keeps its directive line as text');
  await titleEverywhere(authorContext, 'Renamed by the author');

  // 2. An approved member who is neither the author nor an operator is ignored by default.
  const memberContext = await contextFor(member);
  await replyThroughComposer(memberContext, 'title: Member attempt one');
  state = await threadState(memberContext);
  assert.equal(state.title, 'Renamed by the author');
  assert.match(state.bodyText, /title: Member attempt one/);

  // 3. The operator widens authority through the Feature Flags page.
  const operatorContext = await contextFor(operator);
  async function toggle(context, key, value) {
    const page = await context.newPage();
    await page.goto(base + '/tools/feature-flags/');
    const row = page.locator(`[data-flag-key="${key}"]`);
    if (await row.getByRole('switch').getAttribute('aria-checked') !== String(value)) {
      const responsePromise = page.waitForResponse(r => r.url().endsWith('/api/finalize_feature_flag_change'), { timeout: 30000 });
      await row.getByRole('switch').click();
      const response = await responsePromise;
      const result = await response.json();
      assert.equal(response.status(), 200, JSON.stringify(result));
      assert.equal(result.effective_value, String(value));
      await page.waitForFunction(k => !document.querySelector(`[data-flag-key="${k}"] [role="switch"]`).disabled, key);
    }
    await page.close();
  }
  await toggle(operatorContext, 'FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS', true);
  await replyThroughComposer(memberContext, 'title: Member attempt two');
  state = await threadState(memberContext);
  assert.equal(state.title, 'Member attempt two');
  assert.match(state.signals, /Title set by replay-member/);

  // 4. Directive flag off: the line is plain text again, even for the author.
  await toggle(operatorContext, 'FORUM_TITLE_DIRECTIVE_ENABLED', false);
  await replyThroughComposer(authorContext, 'title: Should stay as text');
  state = await threadState(authorContext);
  assert.equal(state.title, 'Member attempt two');
  assert.match(state.bodyText, /title: Should stay as text/);

  await authorContext.close(); await memberContext.close(); await operatorContext.close();
  console.log('title_directive_replay: ok');
} finally {
  if (server) server.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
