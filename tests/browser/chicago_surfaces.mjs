// Real account/list routes, including framed disclosure and restore recovery.
// CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/chicago_surfaces.mjs
import assert from 'node:assert/strict';
import { mkdtemp, rm, readFile, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { runInThisContext } from 'node:vm';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'chicago-surfaces-'));
const shots = process.env.CHICAGO_SCREENSHOT_DIR || '/tmp/chicago-surfaces';
await mkdir(shots, { recursive: true });
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'alice' }], format: 'armored' });
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
const profile = { name: 'alice', fingerprint, publicKey: keys.publicKey };
let server, browser, logs = '';
try {
  const seed = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], {
    input: JSON.stringify({ profiles: [profile], canonical_identity: profile, incoming: 'fixture', outgoing: 'fixture' }), encoding: 'utf8',
  });
  assert.equal(seed.status, 0, seed.stderr);
  const threads = spawnSync('php', ['-r', `
    require $argv[1] . '/autoload.php';
    for ($i = 0; $i < 30; $i++) {
      $id = 'chicago-' . $i;
      file_put_contents($argv[2] . '/repository/records/posts/' . $id . '.txt',
        "Post-ID: $id\nCreated-At: 2026-11-01T12:00:00Z\nBoard-Tags: general\nSubject: Chicago " . str_repeat('LongTitle', 20) . " $i\n\nA readable thread.\n");
    }
  `, project, root], { encoding: 'utf8' });
  assert.equal(threads.status, 0, threads.stderr);
  for (const args of [['add', 'records'], ['-c', 'commit.gpgsign=false', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-qm', 'Chicago fixture']]) {
    const result = spawnSync('git', args, { cwd: join(root, 'repository'), encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
  }
  const rebuild = spawnSync('php', ['-r', `require $argv[1] . '/autoload.php';
    (new ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2] . '/repository', $argv[2] . '/read.sqlite3',
      new ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2] . '/repository')))->rebuild();`, project, root], { encoding: 'utf8' });
  assert.equal(rebuild.status, 0, rebuild.stderr);
  const socket = createServer().listen(0, '127.0.0.1');
  await once(socket, 'listening');
  const port = socket.address().port;
  await new Promise(done => socket.close(done));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')], {
    env: { ...process.env, FORUM_SITE_ID: 'zenmemes', PRIVATE_MESSAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'absent.php'),
      FORUM_APPROVED_MEMBERS_ONLY: 'false', PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'),
      VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'],
  });
  server.stderr.on('data', data => { logs += data; });
  for (let i = 0; i < 80; i++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(done => setTimeout(done, 100)); }
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const noOverflow = async page => assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'page overflow');
  async function frame(locator) {
    await locator.waitFor({ state: 'visible' });
    const style = await locator.evaluate(el => { const s = getComputedStyle(el); return { radius: s.borderRadius, background: s.backgroundColor, shadow: s.boxShadow }; });
    assert.equal(style.radius, '0px');
    assert.equal(style.background, 'rgb(192, 192, 192)');
    assert.match(style.shadow, /inset/);
  }
  for (const width of [390, 1280]) for (const member of [false, true]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
    await context.addInitScript(({ member, keys, fingerprint }) => {
      localStorage.setItem('forum-zenmemes-theme', 'chicago');
      if (member) for (const [name, value] of Object.entries({ username: 'alice', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + name, value);
    }, { member, keys, fingerprint });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    await page.goto(base + '/account/key/');
    assert.equal(await page.locator('html').getAttribute('data-theme'), 'chicago');
    await frame(page.locator(`.account-card[data-account-card="${member ? 'member' : 'guest'}"]`));
    assert.equal(await page.locator('.account-avatar:visible').evaluate(el => getComputedStyle(el).borderRadius), '0px');
    for (const group of await page.locator('.account-group:visible').all()) await frame(group);
    const advanced = page.locator('.account-page > .account-key-advanced');
    await frame(advanced);
    const summary = advanced.locator(':scope > summary');
    assert.ok((await summary.boundingBox()).height >= 44, 'framed summary has a full row hit target');
    await summary.focus();
    await page.keyboard.press('Enter');
    assert.equal(await advanced.getAttribute('open'), '');
    const padding = await advanced.locator(':scope > div').evaluate(el => parseFloat(getComputedStyle(el).paddingLeft));
    assert.ok(padding >= 12, 'expanded content has an inset from the frame');
    await page.locator('#key-keys > summary').click();
    await noOverflow(page);
    await page.screenshot({ path: join(shots, `account-${member ? 'member' : 'guest'}-${width}.png`), fullPage: true });
    if (member) {
      const row = page.locator('button.account-row[data-action="remove-key"]');
      await row.waitFor({ state: 'visible' });
      assert.equal(await row.evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)', 'row shares the group background');
      await row.scrollIntoViewIfNeeded();
      const box = await row.boundingBox();
      await page.mouse.move(box.x + 10, box.y + 10);
      await page.mouse.down();
      assert.equal(await row.evaluate(el => getComputedStyle(el).boxShadow), 'none', 'pressed grouped row stays flat');
      // Release away from the row so this style check does not remove the key.
      await page.mouse.move(0, 0);
      await page.mouse.up();
      await row.click();
      await frame(page.locator('#key-remove'));
      await noOverflow(page);
      await page.locator('#key-remove button[value="cancel"]').click();
      assert.equal(await page.evaluate(() => localStorage.getItem('forum_pki_fingerprint')), fingerprint, 'cancel preserves the key');
    }
    await page.locator('[data-backup-open="restore"]').click();
    const sheet = page.locator('#key-backup');
    await frame(sheet);
    await sheet.locator('[data-backup-restore]').click();
    await page.waitForFunction(() => Boolean(document.querySelector('[data-backup-status]').textContent.trim()));
    assert.equal(await sheet.locator('[data-backup-file]').isVisible(), true, 'failed restore keeps file chooser');
    assert.equal(await sheet.locator('[data-backup-restore]').isEnabled(), true, 'failed restore remains retryable');
    await noOverflow(page);
    await page.screenshot({ path: join(shots, `restore-${member ? 'member' : 'guest'}-${width}.png`) });
    await sheet.locator('[data-action="close-sheet"]').click();
    assert.equal(await sheet.isVisible(), false);
    await page.goto(base + '/threads/?view=all');
    await frame(page.locator('.board-toolbar'));
    await frame(page.locator('.thread-rows').first());
    await noOverflow(page);
    await page.locator('.board-sort > summary').click();
    await frame(page.locator('.board-sort__menu'));
    await noOverflow(page);
    await page.screenshot({ path: join(shots, `board-${member ? 'member' : 'guest'}-${width}.png`) });
    await page.locator('.board-sort__menu a', { hasText: 'Top', exact: true }).click();
    await page.waitForURL(/sort=top/);
    await noOverflow(page);
    await page.locator('.account-menu__trigger').click();
    await frame(page.locator('.account-menu__panel'));
    await noOverflow(page);
    await context.close();
  }
  console.log('PASS Chicago actual routes: guest/member, 390/1280px, disclosure keyboard/expansion, pressed row, restore failure/retry, board/sort/account menus and long-title overflow');
} catch (error) {
  console.error(logs.slice(-2000));
  throw error;
} finally {
  if (browser) await browser.close();
  if (server) { server.kill(); await once(server, 'exit'); }
  await rm(root, { recursive: true, force: true });
}
