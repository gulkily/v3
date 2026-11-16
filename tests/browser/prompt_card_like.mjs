// Like on a /agent/ prompt card: the thread page's Like, pressed from the card.
// Run: node tests/browser/prompt_card_like.mjs [screenshot-dir]
import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || '';
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'prompt-liker' }], format: 'armored' });
keys.priorPublicKey = (await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'earlier-like' }], format: 'armored' })).publicKey;
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const root = await mkdtemp(join(tmpdir(), 'prompt-card-like-browser-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'),
  SIGNED_REACTION_TEST_ROOT: root, VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
let server;
try {
  const seed = spawnSync('php', [join(project, 'tests/Support/prompt_card_like_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity } = JSON.parse(seed.stdout);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(done => socket.close(done));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: 'ignore' });
  const context = await browser.newContext({ serviceWorkers: 'block' });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
  await context.addInitScript(({ keys, fingerprint }) => {
    localStorage.setItem('forum_pki_private_key', keys.privateKey);
    localStorage.setItem('forum_pki_public_key', keys.publicKey);
    localStorage.setItem('forum_pki_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
    localStorage.setItem('forum_pki_username', 'prompt-liker');
  }, { keys, fingerprint });
  const page = await context.newPage();
  const responses = [];
  page.on('response', response => { if (/\/api\/apply_thread_tag$/.test(response.url())) responses.push(response); });
  await page.goto(base + '/agent/');
  const card = page.locator('#prompt-root-001');
  const like = card.locator('[data-action="apply-thread-tag"][data-tag="like"]');
  const count = card.locator('[data-role="thread-score"]');
  assert.equal(await like.textContent(), 'Like');
  assert.equal(await count.textContent(), '', "nothing shows at zero (the fixture's earlier like is a newcomer's, which the score leaves out)");

  // A failed save puts the button and the count back and says why.
  await page.route('**/api/apply_thread_tag', route => route.fulfill({ status: 200, contentType: 'text/plain', body: 'status=error\nerror=Injected failure\n' }));
  await like.click();
  await page.waitForFunction(() => document.querySelector('#prompt-root-001 [data-role="thread-reaction-feedback"]').textContent.includes('Injected failure'));
  assert.equal(await like.textContent(), 'Like');
  assert.equal(await like.isDisabled(), false);
  assert.equal(await count.textContent(), '');
  await page.unroute('**/api/apply_thread_tag');
  responses.length = 0;

  await like.click();
  await page.waitForFunction(() => /Like added|Liked/.test(document.querySelector('#prompt-root-001 [data-role="thread-reaction-feedback"]').textContent));
  assert.equal(responses.length, 1);
  assert.match(await responses[0].text(), /status=ok/);
  const fields = new URLSearchParams(responses[0].request().postData());
  const record = fields.get('canonical_record');
  assert.match(record, /\nTarget-Kind: thread\nTarget-ID: root-001\nOperation: add\nTags: like\n/);
  const id = /^Record-ID: (.+)$/m.exec(record)[1];
  assert.equal(await readFile(join(root, 'repository/records/thread-labels', id + '.txt'), 'utf8'), record);
  assert.equal(await like.textContent(), 'Liked');
  assert.equal(await like.getAttribute('aria-pressed'), 'true');
  assert.equal(await count.textContent(), '1 like');

  // Reload: the server's count and this device's remembered like.
  await page.reload();
  await page.waitForFunction(() => document.querySelector('#prompt-root-001 [data-action="apply-thread-tag"]').textContent === 'Liked');
  assert.equal(await count.textContent(), '1 like');
  assert.equal(await like.isDisabled(), true);

  // Copy still works beside the new scripts.
  await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: base });
  await card.locator('[data-copy-prompt-button]').click();
  await page.waitForFunction(() => document.querySelector('#prompt-root-001 [data-copy-prompt-button]').hasAttribute('data-copied'));
  assert.equal(await card.locator('[data-copy-prompt-button]').getAttribute('aria-label'), 'Copied');
  assert.equal(await page.evaluate(() => navigator.clipboard.readText()), await card.locator('textarea').inputValue());
  if (shots) await card.screenshot({ path: join(shots, 'copied.png') });
  await page.waitForFunction(() => !document.querySelector('#prompt-root-001 [data-copy-prompt-button]').hasAttribute('data-copied'));
  const field = await card.locator('textarea').boundingBox();
  const copy = await card.locator('[data-copy-prompt-button]').boundingBox();
  assert.ok(copy.y - field.y < 12 && field.x + field.width - (copy.x + copy.width) < 12, 'Copy sits in the top right corner of the field');
  assert.ok(copy.width >= 44 && copy.height >= 44, 'Copy keeps a 44px tap target');

  // Fields are as tall as their text plus one empty line, with and without
  // CSS field sizing (the script sizes them where it is missing).
  const fieldFit = () => page.evaluate(() => Array.from(document.querySelectorAll('[data-copy-prompt] textarea')).map(field => {
    const style = getComputedStyle(field);
    const line = parseFloat(style.lineHeight);
    const lines = Math.round((field.scrollHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)) / line);
    const text = field.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
    return { id: field.id, capped: field.scrollHeight > field.clientHeight + 1, slack: text - lines * line, spare: parseFloat(style.paddingBottom) / line };
  }));
  const assertFit = async (label) => {
    const fields = await fieldFit();
    assert.ok(fields.length >= 4, label + ': library card and the three site prompts');
    for (const field of fields) {
      assert.ok(field.capped || field.slack < 2, `${label}: ${field.id} has ${field.slack}px of empty room`);
      assert.ok(field.spare >= 1 && field.spare < 2, `${label}: ${field.id} leaves about one empty line`);
    }
  };
  assert.equal(await card.locator('textarea').inputValue(), 'First line preview.\nSecond line body.', "the body's closing newline is not shown");
  await assertFit('field-sizing');
  const fallback = await context.newPage();
  await fallback.addInitScript(() => { CSS.supports = () => false; });
  await fallback.goto(base + '/agent/');
  await fallback.addStyleTag({ content: '[data-copy-prompt] textarea { field-sizing: fixed !important; }' });
  await fallback.setViewportSize({ width: 500, height: 900 });
  await fallback.waitForTimeout(200);
  for (const field of await fallback.evaluate(() => Array.from(document.querySelectorAll('[data-copy-prompt] textarea')).map(field => ({ id: field.id, inline: field.style.height, gap: field.scrollHeight - field.clientHeight })))) {
    assert.match(field.inline, /px$/, 'script fallback sized ' + field.id);
    assert.ok(field.gap <= 1 || field.id.includes('shared-'), 'script fallback shows all of ' + field.id);
  }
  await fallback.close();

  if (shots) {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.screenshot({ path: join(shots, 'page-1280.png'), fullPage: true });
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      for (const theme of (process.env.THEMES || 'gloss,glossdark,light,dark,word97,chicago,midnight,bubblegum,spearmint,sticker,engagify').split(',')) {
        await page.evaluate(theme => localStorage.setItem('forum-zenmemes-theme', theme), theme);
        await page.reload();
        assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-theme')), theme, 'theme id is real');
        await card.screenshot({ path: join(shots, `${theme}-${width}.png`) });
        if (width === 390) {
          await card.locator('[data-copy-prompt-button]').hover();
          await card.screenshot({ path: join(shots, `${theme}-hover.png`) });
          await card.locator('[data-copy-prompt-button]').click();
          await card.screenshot({ path: join(shots, `${theme}-copied.png`) });
          await page.mouse.move(0, 0);
        }
      }
    }
  }
  console.log('prompt_card_like: ok');
} finally {
  if (server) server.kill();
  await browser.close();
  await rm(root, { recursive: true, force: true });
}
