import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { runInThisContext } from 'node:vm';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'backup-flags-browser-'));
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'backup-operator' }], format: 'armored' });
const fingerprint = (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
const env = { ...process.env, PRIVATE_MESSAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'),
  FORUM_IMAGE_ROOT: join(root, 'images'), FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_IMAGE_CONTENT_ENABLED: 'false',
  FORUM_PRIVATE_MESSAGES_ENABLED: 'false', FORUM_APP_VERSION_NOTIFICATION: 'false', FAST_SCORING_ENABLED: 'false',
  FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'artifacts'),
  VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
delete env.FORUM_TEXT_BACKUP_ENABLED; delete env.FORUM_IMAGE_BACKUP_ENABLED;
const fixture = spawnSync('php', [join(project, 'tests/Support/image_browser_fixture.php'), root], {
  input: JSON.stringify({ publicKey: keys.publicKey }), encoding: 'utf8', env,
});
assert.equal(fixture.status, 0, fixture.stderr + fixture.stdout);
await mkdir(join(root, 'images')); // Retained-store availability with image authoring disabled.
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`,
  join(project, 'tests/Support/private_message_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let log = '', browser; server.stderr.on('data', data => { log += data; });
try {
  for (let n = 0; n < 100; n++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block', acceptDownloads: true });
  const anonymous = await browser.newContext({ serviceWorkers: 'block' });
  const sign = async text => pgp.sign({ message: await pgp.createMessage({ text }), signingKeys: await pgp.readPrivateKey({ armoredKey: keys.privateKey }), detached: true });
  const challenge = (await (await context.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
  const auth = await context.request.post(base + '/api/authenticate_identity', { data: { challenge,
    identity_id: 'openpgp:' + fingerprint, detached_signature: await sign(challenge) } });
  assert.equal(auth.status(), 200, await auth.text());
  await context.request.get(base + '/api/set_identity_hint?identity_hint=' + encodeURIComponent('openpgp:' + fingerprint));
  await context.addInitScript(({ keys, fingerprint }) => {
    for (const [name, value] of Object.entries({ public_key: keys.publicKey, private_key: keys.privateKey,
      fingerprint, published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + name, value);
  }, { keys, fingerprint });
  const page = await context.newPage(); const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/tools/');
  await page.locator('a[href="/tools/feature-flags/"]').first().click();
  const flags = { text: 'FORUM_TEXT_BACKUP_ENABLED', image: 'FORUM_IMAGE_BACKUP_ENABLED' };
  async function toggle(kind, value) {
    await page.goto(base + '/tools/feature-flags/');
    await page.getByRole('searchbox', { name: 'Search flags' }).fill(flags[kind]);
    const row = page.locator(`[data-flag-key="${flags[kind]}"]`);
    if (await row.getByRole('switch').getAttribute('aria-checked') === String(value)) return;
    const responsePromise = page.waitForResponse(r => r.url().endsWith('/api/finalize_feature_flag_change'));
    await row.getByRole('switch').click();
    const response = await responsePromise; const result = await response.json();
    assert.equal(response.status(), 200, JSON.stringify(result)); assert.equal(result.effective_value, String(value));
    assert.match(await readFile(join(root, 'repository', result.record_path + '.asc'), 'utf8'), /PGP SIGNATURE/);
    await page.waitForFunction(key => !document.querySelector(`[data-flag-key="${key}"] [role="switch"]`).disabled, flags[kind]);
  }
  for (const key of Object.values(flags)) {
    const denied = await anonymous.request.post(base + '/api/prepare_feature_flag_change', { data: { key, value: 'false' } });
    assert.equal(denied.status(), 403);
  }
  let checks = 0;
  const paths = ['/downloads/repository.tar.gz', '/downloads/repository.zip', '/downloads/read_model.sqlite3', '/downloads/images.zip', '/downloads/images.tar'];
  for (const [text, images] of [[true, true], [false, true], [false, false], [true, false], [true, true]]) {
    await toggle('text', text); await toggle('image', images);
    await page.waitForFunction(visible => Boolean(document.querySelector('.tools-nav a[href="/tools/backup/"]')) === visible, text || images);
    for (const path of paths) {
      const allowed = path.includes('/images.') ? images : text;
      const response = await anonymous.request.get(base + path);
      assert.equal(response.status(), allowed ? 200 : 403, path);
      if (!allowed) {
        assert.equal(await response.text(), 'Backup unavailable.\n');
        assert.equal(response.headers()['content-disposition'], undefined);
        assert.match(response.headers()['cache-control'], /no-store/);
      } else assert.match(response.headers()['content-disposition'], /attachment/);
    }
    if (!text) {
      await page.goto(base + '/tools/codebase/');
      let databaseRequests = 0;
      const count = request => { if (new URL(request.url()).pathname === '/downloads/read_model.sqlite3') databaseRequests++; };
      page.on('request', count);
      await page.locator('[data-view-query]').first().click();
      await page.locator('[data-query-dialog]').waitFor({ state: 'visible' });
      await page.waitForTimeout(100);
      assert.match(await page.locator('[data-query-dialog]').innerText(), /text backups are disabled/);
      assert.equal(databaseRequests, 0);
      await page.locator('[data-close-query]').click();
      page.off('request', count);
    }
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      for (const theme of ['light', 'dark', 'console', 'word97', 'chicago', 'midnight']) {
        await page.evaluate(theme => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme), theme);
        for (const path of ['/tools/', '/tools/backup/', '/tools/sqlite/', '/tools/feature-flags/']) {
          let sqliteRequests = 0;
          const count = request => { if (new URL(request.url()).pathname === '/downloads/read_model.sqlite3') sqliteRequests++; };
          page.on('request', count);
          await page.goto(base + path); await page.evaluate(() => document.fonts.ready);
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${path}/${theme}/${width} overflow`);
          assert.equal(await page.locator('.tools-nav a[href="/tools/backup/"]').count(), text || images ? 1 : 0);
          if (path === '/tools/') {
            const link = page.locator('.tool-launcher-button[href="/tools/backup/"]');
            assert.equal(await link.count(), text || images ? 1 : 0);
            if ((text || images) && ['word97', 'chicago', 'midnight'].includes(theme)) {
              for (const selector of ['.tool-launcher-button[href="/tools/backup/"]', '.tools-nav a[href="/tools/backup/"]']) {
                const icon = await page.locator(selector).evaluate(node => {
                  const style = getComputedStyle(node, '::before'); return { content: style.content, background: style.backgroundImage, width: style.width };
                });
                assert.ok(icon.background !== 'none' && parseFloat(icon.width) > 0, JSON.stringify({ theme, selector, icon }));
              }
            }
          }
          if (path === '/tools/backup/') {
            assert.equal(await page.locator('a[href^="/downloads/repository.zip"]').count(), text ? 1 : 0);
            assert.equal(await page.locator('a[href^="/downloads/images.zip"]').count(), images ? 1 : 0);
            if (!text && images) assert.match(await page.locator('main').innerText(), /matching content backup/);
            if (!text && !images) assert.match(await page.locator('main').innerText(), /Backups are unavailable/);
          }
          if (path === '/tools/sqlite/') {
            assert.equal(await page.locator('[data-sqlite-viewer]').count(), text ? 1 : 0);
            if (!text) { assert.equal(sqliteRequests, 0); assert.match(await page.locator('main').innerText(), /text backups are disabled/); }
          }
          page.off('request', count); checks++;
          if (theme === 'midnight' && width === 390 && path === '/tools/backup/') await page.screenshot({ path: join(root, `backup-${Number(text)}${Number(images)}.png`), fullPage: true });
        }
      }
    }
  }
  // Re-enable recovery through the actual database loader and ordinary archive link.
  await page.goto(base + '/tools/sqlite/'); await page.getByRole('button', { name: 'Load Database', exact: true }).click();
  await page.locator('[data-role="sqlite-query-panel"]').waitFor({ state: 'visible' });
  await page.goto(base + '/tools/backup/');
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('a[href^="/downloads/repository.zip"]').click()]);
  await download.saveAs(join(root, 'recovered.zip'));
  assert.equal(spawnSync('unzip', ['-t', join(root, 'recovered.zip')]).status, 0);
  assert.deepEqual(errors, []);
  console.log(`PASS: ${checks} theme/viewport/page states, signed toggles, authorization, HTTP matrix, live navigation, icons, SQLite and archive recovery. Artifacts: ${root}`);
} finally {
  if (browser) await browser.close(); server.kill(); await once(server, 'exit');
  await writeFile(join(root, 'server.log'), log);
}
