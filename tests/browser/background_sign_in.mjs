// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/background_sign_in.mjs
// Delay real authentication while interacting with a real thread.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'reaction-browser' }], format: 'armored' });
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const localKeys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'mouse' }], format: 'armored' });
const localFingerprint = (await globalThis.openpgp.readKey({ armoredKey: localKeys.publicKey })).getFingerprint().toUpperCase();
const root = await mkdtemp(join(tmpdir(), 'background-sign-in-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'),
  FORUM_USERNAME_CHANGES_ENABLED: 'true' };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const approved of [false, true]) {
    if (approved) {
      const promote = spawnSync('php', ['-r', `require $argv[1] . '/autoload.php';
        $dir = $argv[2] . '/repository/records/approval-seeds'; if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . '/openpgp-' . strtolower($argv[3]) . '.txt', "Approved-Identity-ID: openpgp:" . strtolower($argv[3]) . "\nSeed-Reason: isolated browser test\n\n");
        $repo = new \\ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2] . '/repository');
        (new \\ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2] . '/repository', $argv[2] . '/read.sqlite3', $repo))->rebuild();`, project, root, fingerprint], { encoding: 'utf8', env });
      assert.equal(promote.status, 0, promote.stderr);
    }
  for (const width of [390, 1280]) {
    for (const mode of ['typing', 'name', 'submit', 'retry']) {
      const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 844 } });
      await context.addInitScript(({ keys, fingerprint }) => {
        if (!sessionStorage.getItem('seeded')) {
          for (const [key, value] of Object.entries({ username: 'reaction-browser', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + key, value);
          sessionStorage.setItem('seeded', '1');
        }
        window.__documentToken = Math.random();
        window.__authEvents = [];
        window.addEventListener('forum:authenticated-in-place', e => window.__authEvents.push(e.detail));
      }, { keys, fingerprint });
      const page = await context.newPage();
      let release, entered;
      const blocked = new Promise(r => { entered = r; });
      const gate = new Promise(r => { release = r; });
      let first = true, authResponse = '', submissions = 0, navigations = 0;
      page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
      page.on('request', request => { if (request.url().endsWith('/api/create_prepared_post')) submissions++; });
      await page.route('**/api/authenticate_identity', async route => {
        if (first) {
          first = false; entered(); await gate;
          if (mode === 'retry') { await route.fulfill({ status: 503, body: 'error=Temporarily unavailable.' }); return; }
        }
        const response = await route.fetch(); authResponse = await response.text();
        await route.fulfill({ response });
      });
      await page.goto(base + '/threads/root-001');
      await blocked;
      const token = await page.evaluate(() => window.__documentToken);
      const body = page.locator('[data-compose-kind="reply"] textarea[name="body"]');
      await body.fill(`Keep this ${mode} ${width}`);
      const row = page.locator('[data-compose-kind="reply"] [data-compose-identity]');
      let input = body;
      if (mode === 'name') {
        await row.getByRole('button', { name: 'Change' }).click();
        input = row.locator('[data-role="compose-identity-input"]');
        await input.fill('Unfinished name');
      }
      await input.evaluate(node => { node.focus(); node.setSelectionRange(2, 5); });
      const before = await input.evaluate(node => ({ value: node.value, start: node.selectionStart, end: node.selectionEnd, scroll: scrollY }));
      let accepted;
      if (mode === 'submit') {
        accepted = page.waitForResponse(r => r.url().endsWith('/api/create_prepared_post'));
        await page.locator('[data-compose-kind="reply"] .compose-form-actions > button[type="submit"]').click();
      }
      release();
      if (mode === 'retry') {
        await page.waitForResponse(r => r.url().endsWith('/api/authenticate_identity') && r.status() === 503);
        // Existing explicit helper recovery stays in place; Account flow is checked separately.
        const recovered = await page.evaluate(() => window.PrivateSiteAuth.authenticate({ force: true, navigate: false }));
        assert.ok(recovered.identityId);
      } else await page.waitForFunction(() => window.__authEvents.length === 1);
      assert.match(authResponse, /status=ok/);
      assert.ok(authResponse.includes(`approved=${Number(approved)}`), authResponse);
      assert.equal(await page.evaluate(() => window.__documentToken), token);
      assert.equal(navigations, 1, 'background authentication does not navigate');
      if (mode === 'submit') {
        const response = await accepted;
        assert.equal(response.ok(), true);
        assert.match(await response.text(), /ok/);
        assert.equal(submissions, 1, 'submission sent once');
      } else {
        const after = await input.evaluate(node => ({ value: node.value, start: node.selectionStart, end: node.selectionEnd, scroll: scrollY, focused: document.activeElement === node }));
        assert.deepEqual(after, { ...before, focused: true });
        assert.equal(await body.inputValue(), `Keep this ${mode} ${width}`);
        if (mode === 'name') assert.equal(await input.isVisible(), true);
      }
      // Account uses the authenticated session even when this identity lacks membership.
      const access = await context.request.get(base + '/api/auth_status');
      assert.equal(access.status(), approved ? 200 : 401, 'membership access remains separate from session');
      const account = await context.request.get(base + '/account/key/');
      assert.ok((await account.text()).includes(`data-authenticated-identity-id="openpgp:${fingerprint.toLowerCase()}"`));
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      await page.screenshot({ path: `/tmp/background-sign-in-${approved}-${mode}-${width}.png` });
      await context.close();
    }
  }
  }
  console.log('PASS approved/unapproved delayed real authentication: typing, name editing, one submission and failure/retry at phone/desktop widths; session verified on Account');
} catch (error) { console.error(serverLog.slice(-2500)); throw error; }
finally { await browser.close(); server.kill(); }
