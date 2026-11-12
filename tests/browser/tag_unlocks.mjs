// Resolved/Closed/Reopen on replies in a #meta thread (resources/tagsets.txt).
// Run: node tests/browser/tag_unlocks.mjs  (set TAG_UNLOCKS_SHOTS=<dir> to save screenshots)
import assert from 'node:assert/strict';
import { mkdtemp, realpath, rm, mkdir } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const playwright = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const chromium = playwright.chromium ?? playwright.default.chromium;
const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'unlocks-viewer' }], format: 'armored' });
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const themes = (process.env.TAG_UNLOCKS_THEMES || 'light,console,gloss').split(',');
const viewports = [{ width: 375, height: 740, label: 'phone' }, { width: 1280, height: 800, label: 'desktop' }];
const shots = process.env.TAG_UNLOCKS_SHOTS || '';
if (shots) await mkdir(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, headless: true, args: ['--no-sandbox'] });
const root = await mkdtemp(join(await realpath(tmpdir()), 'tag-unlocks-browser-'));
let server;
let serverLog = '';
try {
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const seed = spawnSync('php', [join(project, 'tests/Support/tag_unlocks_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity, thread, first, second } = JSON.parse(seed.stdout);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { serverLog += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const card = (page, id) => page.locator(`.post-card[data-post-id="${id}"]`);
  const visible = async (locator) => (await locator.count()) > 0 && await locator.first().isVisible();

  // 0. No JavaScript: the closed reply is folded to its summary line and expands natively.
  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: viewports[0] });
  try {
    const page = await noScript.newPage();
    await page.goto(`${base}/threads/${thread}`);
    const closed = card(page, second);
    const summary = closed.locator('[data-role="post-resolution-summary"]');
    assert.equal(await summary.isVisible(), true);
    assert.match(await summary.innerText(), /closed/i);
    assert.match(await summary.innerText(), /Thread pages have no text version/);
    assert.equal(await closed.locator('.body').isVisible(), false);
    await summary.click();
    assert.equal(await closed.locator('.body').isVisible(), true);
    assert.equal(await card(page, first).locator('[data-role="post-resolution-summary"]').isVisible(), false);
    assert.equal(await card(page, first).locator('.body').isVisible(), true);
  } finally { await noScript.close(); }

  // 1. Signed-in approved viewer: Resolved folds state in place, survives a reload, Reopen clears it.
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: viewports[1] });
  try {
    await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
    await context.addInitScript(({ keys, fingerprint }) => {
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_username', 'unlocks-viewer');
      localStorage.setItem('forum-zenmemes-theme', 'light');
    }, { keys, fingerprint });
    const page = await context.newPage();
    page.on('dialog', dialog => dialog.dismiss());
    await page.goto(`${base}/threads/${thread}`);
    const reply = card(page, first);
    assert.equal(await reply.getAttribute('data-resolution'), null);
    assert.equal(await card(page, second).getAttribute('data-resolution'), 'closed');
    await reply.hover();
    const responsePromise = page.waitForResponse(response => /\/api\/apply_post_tag$/.test(response.url()));
    await reply.locator('[data-tag="resolved"]').click();
    assert.match(await (await responsePromise).text(), /status=ok/);
    await page.waitForFunction(id => document.querySelector(`.post-card[data-post-id="${id}"]`)?.getAttribute('data-resolution') === 'resolved', first);
    assert.equal(await reply.locator('.body').isVisible(), false, 'resolved reply folds');
    assert.match(await reply.locator('[data-role="post-resolution-summary"]').innerText(), /resolved/i);
    await reply.locator('[data-role="post-resolution-summary"]').click();
    assert.equal(await reply.locator('.body').isVisible(), true, 'folded reply expands');
    assert.equal(await reply.locator('[data-tag="resolved"]').isHidden(), true);
    assert.equal(await reply.locator('[data-tag="open"]').isHidden(), false);
    await page.reload({ waitUntil: 'load' });
    assert.equal(await card(page, first).getAttribute('data-resolution'), 'resolved');
    await card(page, first).hover();
    const reopen = page.waitForResponse(response => /\/api\/apply_post_tag$/.test(response.url()));
    const summary = card(page, first).locator('[data-role="post-resolution-summary"]');
    if (await summary.count()) await summary.click();
    await card(page, first).locator('[data-tag="open"]').click();
    assert.match(await (await reopen).text(), /status=ok/);
    await page.waitForFunction(id => !document.querySelector(`.post-card[data-post-id="${id}"]`)?.hasAttribute('data-resolution'), first);
    assert.equal(await card(page, first).locator('[data-role="post-resolution-summary"]').isVisible(), false);
    assert.equal(await card(page, first).locator('.body').isVisible(), true);
    await page.reload({ waitUntil: 'load' });
    assert.equal(await card(page, first).getAttribute('data-resolution'), null);

    // The opening post takes the same tags; its label sits after the byline and it does not fold.
    const rootCard = card(page, thread);
    const rootLabel = rootCard.locator('[data-role="post-resolution-label"]');
    assert.equal(await rootLabel.isVisible(), false);
    await rootCard.hover();
    const closeRoot = page.waitForResponse(response => /\/api\/apply_post_tag$/.test(response.url()));
    await rootCard.locator('[data-tag="closed"]').click();
    assert.match(await (await closeRoot).text(), /status=ok/);
    await page.waitForFunction(id => document.querySelector(`.post-card[data-post-id="${id}"]`)?.getAttribute('data-resolution') === 'closed', thread);
    assert.equal(await rootLabel.isVisible(), true);
    assert.match(await rootLabel.innerText(), /closed/i);
    assert.equal(await rootCard.locator('.body').isVisible(), true, 'opening post stays unfolded');
    await page.reload({ waitUntil: 'load' });
    assert.equal(await card(page, thread).locator('[data-role="post-resolution-label"]').isVisible(), true);
    assert.equal(await card(page, thread).locator('[data-tag="open"]').isHidden(), false);
  } finally { await context.close(); }

  // 2. Themes at phone and desktop widths: buttons inside the viewport.
  const failures = [];
  for (const theme of themes) {
    for (const viewport of viewports) {
      const ctx = await browser.newContext({ serviceWorkers: 'block', viewport: { width: viewport.width, height: viewport.height } });
      try {
        await ctx.addInitScript(({ theme }) => { localStorage.setItem('forum-zenmemes-theme', theme); }, { theme });
        const page = await ctx.newPage();
        await page.goto(`${base}/threads/${thread}`);
        const folded = card(page, second).locator('[data-role="post-resolution-summary"]');
        const foldBox = await folded.boundingBox();
        if (!foldBox || foldBox.x < 0 || foldBox.x + foldBox.width > viewport.width + 1 || foldBox.height > 60) failures.push(`${theme} ${viewport.label}: folded line not one line inside the viewport (${JSON.stringify(foldBox)})`);
        if (await card(page, second).locator('.body').isVisible()) failures.push(`${theme} ${viewport.label}: closed reply not folded`);
        const button = card(page, first).locator('[data-tag="closed"]');
        await card(page, first).hover();
        const box = await button.boundingBox();
        if (!box || box.x < 0 || box.x + box.width > viewport.width + 1) failures.push(`${theme} ${viewport.label}: Closed button outside viewport (${JSON.stringify(box)})`);
        if (shots) await page.screenshot({ path: join(shots, `${theme}-${viewport.label}.png`), fullPage: true });
      } catch (error) {
        failures.push(`${theme} ${viewport.label}: ${error.message}`);
      } finally { await ctx.close(); }
    }
  }
  assert.deepEqual(failures, []);
  console.log('tag_unlocks: ok');
} catch (error) {
  console.error(serverLog.slice(-5000));
  throw error;
} finally {
  if (server) server.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
