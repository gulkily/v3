// An operator edits the site tagsets on /tools/tag-unlocks/: preview, save, new buttons, reset.
// Run: node tests/browser/editable_tagsets.mjs  (set EDITABLE_TAGSETS_SHOTS=<dir> to save screenshots)
import assert from 'node:assert/strict';
import { mkdtemp, realpath, rm, mkdir } from 'node:fs/promises';
import { readFileSync, existsSync } from 'node:fs';
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
const makeKeys = async (name) => {
  const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  return { keys, fingerprint: (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase() };
};
const operator = await makeKeys('tagsets-operator');
const stranger = await makeKeys('tagsets-stranger');
const themes = (process.env.EDITABLE_TAGSETS_THEMES || 'light,dark,gloss,word97,midnight,bubblegum').split(',');
const viewports = [{ width: 375, height: 740, label: 'phone' }, { width: 1280, height: 800, label: 'desktop' }];
const shots = process.env.EDITABLE_TAGSETS_SHOTS || '';
if (shots) await mkdir(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, headless: true, args: ['--no-sandbox'] });
const root = await mkdtemp(join(await realpath(tmpdir()), 'editable-tagsets-browser-'));
const eventBlock = '\n\nParent: event\nUnlocks: attend, interested\n';
let server;
let serverLog = '';
try {
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const seed = spawnSync('php', [join(project, 'tests/Support/editable_tagsets_browser_fixture.php'), root], { input: JSON.stringify(operator.keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity, issue, event, reply } = JSON.parse(seed.stdout);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { serverLog += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const signedIn = async (who, viewport, theme = 'light', hint = null) => {
    const ctx = await browser.newContext({ serviceWorkers: 'block', viewport });
    if (hint) await ctx.addCookies([{ name: 'identity_hint', value: hint, url: base }]);
    await ctx.addInitScript(({ keys, fingerprint, theme }) => {
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
      localStorage.setItem('forum-zenmemes-theme', theme);
    }, { keys: who.keys, fingerprint: who.fingerprint, theme });
    return ctx;
  };
  const editor = page => page.locator('[data-tagsets-editor]');

  // 1. Others never see Edit: signed out, or a key that is not an operator.
  for (const ctx of [await browser.newContext({ viewport: viewports[1] }), await signedIn(stranger, viewports[1])]) {
    try {
      const page = await ctx.newPage();
      await page.goto(`${base}/tools/tag-unlocks/`);
      assert.equal(await editor(page).isVisible(), false);
      assert.match(await page.locator('.tag-unlocks-page').innerText(), /shipped rules/);
    } finally { await ctx.close(); }
  }

  // 2. Operator: an error blocks Save; dropping #issue lists the marked thread; adding #event saves and unlocks Attend.
  const ctx = await signedIn(operator, viewports[1], 'light', identity);
  try {
    const page = await ctx.newPage();
    page.on('dialog', dialog => dialog.dismiss());
    await page.goto(`${base}/tools/tag-unlocks/`);
    assert.equal(await editor(page).isVisible(), true, 'operator sees Edit before any script round trip');
    await editor(page).locator('summary').click();
    const text = page.locator('[data-tagsets-text]');
    const shipped = await text.inputValue();
    assert.match(shipped, /Parent: issue/);
    const save = page.locator('[data-tagsets-save]');
    assert.equal(await save.isDisabled(), true);

    await text.fill(shipped + '\n\nParent: event\nUnlocks: like\n');
    await page.locator('[data-tagsets-preview]').click();
    await page.locator('[data-tagsets-result] .tag-unlocks-error').first().waitFor();
    assert.match(await page.locator('[data-tagsets-result]').innerText(), /like is a built-in tag/);
    assert.equal(await save.isDisabled(), true, 'an error keeps Save off');

    await text.fill('Parent: event\nUnlocks: attend\n');
    await page.locator('[data-tagsets-preview]').click();
    await page.getByText('These threads have marks that would lose their buttons:').waitFor();
    assert.match(await page.locator('[data-tagsets-result]').innerText(), /Agent accessibility[\s\S]*closed · 1 mark/);

    await text.fill(shipped.trimEnd() + eventBlock);
    await page.locator('[data-tagsets-preview]').click();
    await page.waitForFunction(() => !document.querySelector('[data-tagsets-save]').disabled);
    assert.equal(await page.getByText('would lose their buttons').count(), 0);
    await Promise.all([page.waitForEvent('load', { timeout: 30000 }), save.click()]);
    assert.match(await page.locator('.tag-unlocks-page').innerText(), /set on this site/);
    assert.match(await page.locator('.tag-unlocks-sets').innerText(), /#event[\s\S]*attend[\s\S]*interested/);
    assert.ok(existsSync(join(root, 'repository/records/instance/tagsets.txt')));

    await page.goto(`${base}/threads/${event}`);
    const card = page.locator(`.post-card[data-post-id="${reply}"]`);
    await card.hover();
    assert.equal(await card.locator('[data-tag="attend"]').innerText(), 'Attend');
    assert.equal(await card.locator('[data-tag="interested"]').innerText(), 'Interested');
    const tap = page.waitForResponse(response => /\/api\/apply_post_tag$/.test(response.url()));
    await card.locator('[data-tag="attend"]').click();
    assert.match(await (await tap).text(), /status=ok/);

    // Reset to default: preview of the shipped text, then save removes the site record.
    await page.goto(`${base}/tools/tag-unlocks/`);
    await editor(page).locator('summary').click();
    await page.locator('[data-tagsets-reset]').click();
    await page.waitForFunction(() => !document.querySelector('[data-tagsets-save]').disabled);
    await Promise.all([page.waitForEvent('load', { timeout: 30000 }), save.click()]);
    assert.match(await page.locator('.tag-unlocks-page').innerText(), /shipped rules/);
    assert.equal(existsSync(join(root, 'repository/records/instance/tagsets.txt')), false);
    await page.goto(`${base}/threads/${event}`);
    assert.equal(await page.locator(`.post-card[data-post-id="${reply}"] [data-tag="attend"]`).count(), 0);
    await page.goto(`${base}/threads/${issue}`);
    assert.match(await page.locator('.post-card [data-role="post-resolution-summary"]').first().innerText(), /closed/i, 'the closed reply is still closed');
  } finally { await ctx.close(); }

  // 3. Themes at phone and desktop widths: the open editor fits, with a preview.
  const failures = [];
  for (const theme of themes) {
    for (const viewport of viewports) {
      const themed = await signedIn(operator, { width: viewport.width, height: viewport.height }, theme, identity);
      try {
        const page = await themed.newPage();
        await page.goto(`${base}/tools/tag-unlocks/`);
        await editor(page).locator('summary').click();
        await page.locator('[data-tagsets-preview]').click();
        await page.waitForFunction(() => !document.querySelector('[data-tagsets-save]').disabled);
        for (const selector of ['[data-tagsets-text]', '[data-tagsets-save]', '[data-tagsets-preview]']) {
          const box = await page.locator(selector).boundingBox();
          if (!box || box.x < 0 || box.x + box.width > viewport.width + 1) failures.push(`${theme} ${viewport.label}: ${selector} outside viewport (${JSON.stringify(box)})`);
        }
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        if (scrollWidth > viewport.width + 1) failures.push(`${theme} ${viewport.label}: page scrolls sideways (${scrollWidth})`);
        if (shots) await page.screenshot({ path: join(shots, `${theme}-${viewport.label}.png`), fullPage: true });
      } catch (error) {
        failures.push(`${theme} ${viewport.label}: ${error.message}`);
      } finally { await themed.close(); }
    }
  }
  assert.deepEqual(failures, []);
  console.log('editable_tagsets: ok');
} catch (error) {
  console.error(serverLog.slice(-5000));
  throw error;
} finally {
  if (server) server.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
