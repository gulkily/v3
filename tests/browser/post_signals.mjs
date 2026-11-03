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
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'signals-viewer' }], format: 'armored' });
keys.priorPublicKey = (await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'signals-prior' }], format: 'armored' })).publicKey;
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const themes = ['light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'chouse', 'qdb', 'mitrapclub'];
const viewports = [{ width: 375, height: 667, label: 'phone' }, { width: 1280, height: 800, label: 'desktop' }];
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const root = await mkdtemp(join(tmpdir(), 'post-signals-browser-'));
let server;
try {
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const seed = spawnSync('php', [join(project, 'tests/Support/post_signals_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity } = JSON.parse(seed.stdout);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
  let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // 1. Anonymous reader, no JavaScript: the signals line and the retitle are in the server HTML.
  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: viewports[0] });
  try {
    const page = await noScript.newPage();
    await page.goto(base + '/threads/root-001');
    assert.equal(await page.locator('h1').first().innerText(), 'Signals browser title');
    const rootSignals = page.locator('[data-role="post-signals"][data-signal-scope="thread"]');
    assert.match(await rootSignals.innerText(), /Liked by signals-prior/);
    assert.match(await rootSignals.innerText(), /Title set by signals-prior/);
    const replySignals = page.locator('.post-card[data-post-id="reply-001"] [data-role="post-signals"]');
    assert.match(await replySignals.innerText(), /Flagged by signals-prior/);
    assert.equal(await page.locator('[data-role="post-signals"][hidden]').count() > 0, true, 'reply without signals keeps a hidden line');
  } finally { await noScript.close(); }

  // 2. Every theme at phone and desktop widths: visible, inside the viewport, no viewer-specific markup.
  const failures = [];
  for (const theme of themes) {
    for (const viewport of viewports) {
      const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: viewport.width, height: viewport.height } });
      try {
        await context.addInitScript(({ theme }) => { localStorage.setItem('forum-zenmemes-theme', theme); }, { theme });
        const page = await context.newPage();
        await page.goto(base + '/threads/root-001');
        const line = page.locator('[data-role="post-signals"][data-signal-scope="thread"]');
        await line.waitFor({ state: 'visible' });
        const box = await line.boundingBox();
        if (!box || box.x < 0 || box.x + box.width > viewport.width + 1) failures.push(`${theme} ${viewport.label}: signals line outside viewport (${JSON.stringify(box)})`);
        const like = page.locator('.thread-root-card [data-action="apply-thread-tag"][data-tag="like"]').first();
        const likeBox = await like.boundingBox();
        if (!likeBox) failures.push(`${theme} ${viewport.label}: Like button not visible`);
        const forte = await context.newPage();
        await forte.goto(base + '/forte?selected=root-001');
        const paned = forte.locator('.paned-post-signals[data-signal-scope="thread"]');
        await paned.waitFor({ state: 'visible', timeout: 15000 });
        if (!/Liked by signals-prior/.test(await paned.innerText())) failures.push(`${theme} ${viewport.label}: Forte signals missing`);
      } catch (error) {
        failures.push(`${theme} ${viewport.label}: ${error.message}`);
      } finally { await context.close(); }
    }
  }
  assert.deepEqual(failures, []);

  // 3. Signed-in approved viewer likes a reply: their name is appended in place, and a reload shows the same.
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: viewports[1] });
  try {
    await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
    await context.addInitScript(({ keys, fingerprint }) => {
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_username', 'signals-viewer');
      localStorage.setItem('forum-zenmemes-theme', 'light');
    }, { keys, fingerprint });
    const page = await context.newPage();
    page.on('dialog', dialog => dialog.dismiss());
    await page.goto(base + '/threads/root-001');
    const card = page.locator('.post-card[data-post-id="reply-001"]');
    const before = await card.locator('[data-role="post-signals"]').innerText();
    assert.doesNotMatch(before, /signals-viewer/);
    const responsePromise = page.waitForResponse(response => /\/api\/apply_post_tag$/.test(response.url()));
    await card.hover();
    await card.locator('[data-action="apply-post-tag"][data-tag="like"]').click();
    const response = await responsePromise;
    assert.match(await response.text(), /status=ok/);
    await page.waitForFunction(() => /Liked by signals-viewer/.test(document.querySelector('.post-card[data-post-id="reply-001"] [data-role="post-signals"]')?.textContent || ''));
    const after = await card.locator('[data-role="post-signals"]').innerText();
    assert.match(after, /Liked by signals-viewer/);
    assert.match(after, /Flagged by signals-prior/);
    await page.reload();
    assert.match(await page.locator('.post-card[data-post-id="reply-001"] [data-role="post-signals"]').innerText(), /Liked by signals-viewer/);
  } finally { await context.close(); }
  console.log('post_signals: ok');
} finally {
  if (server) server.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
