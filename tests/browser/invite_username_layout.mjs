import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'browser-inviter' }], format: 'armored', date: new Date(Date.now() - 60000) });
const fingerprint = (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'invite-browser-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
let server, logs = '', lastPage;
const contexts = [];
async function start(privateSite, usernameChanges = true) {
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: String(privateSite), FORUM_USERNAME_CHANGES_ENABLED: String(usernameChanges),
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  const base = `http://127.0.0.1:${port}`;
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); return base; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  throw Error('Server did not start');
}
async function contextWithIdentity(base, identity) {
  const context = await browser.newContext({ serviceWorkers: 'block' }); contexts.push(context);
  if (identity) await context.addInitScript(({ keys, fingerprint }) => {
    if (!localStorage.getItem('forum_pki_private_key')) {
      localStorage.setItem('forum_pki_username', 'browser-inviter');
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint.toUpperCase());
    }
  }, identity);
  const page = await context.newPage(); lastPage = page; page.setDefaultTimeout(20000);
  return { context, page };
}
try {
    const base = await start(false, false);
    const inviter = await contextWithIdentity(base, { keys, fingerprint });
    await inviter.page.goto(base + '/account/key/');
    await inviter.page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
    async function issue(targeted = true) {
      return inviter.page.evaluate(async targeted => {
        const token = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));
        const hash = 'sha256:' + Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2, '0')).join('');
        const post = async (url, values) => (await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(values) })).json();
        const prepared = await post('/api/prepare_invitation', { verification_hash: hash, max_redemptions: '2',
          expires_at: new Date(Date.now() + 3600000).toISOString().replace(/\.\d{3}Z$/, 'Z'), destination: targeted ? '/threads/root-001' : '' });
        if (prepared.status !== 'ok') throw Error(prepared.error);
        if (!prepared.canonical_record.includes('Max-Redemptions: 2\n')) throw Error('Missing signed limit');
        const issued = await post('/api/create_prepared_invitation', { ...prepared,
          author_identity_id: window.__forumBrowserIdentity.currentAuthorIdentityId(),
          detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record) });
        if (issued.status !== 'ok') throw Error(issued.error);
        return { token, issued };
      }, targeted);
    }
    const invitation = await issue(false);
    const lobby = await contextWithIdentity(base);
    const account = await contextWithIdentity(base);
    await account.page.goto(base + '/account/key/');
    await account.page.evaluate(() => window.__forumBrowserIdentity.ensureActionIdentity(null, null, { temporaryName:'guest' }));
    await account.page.evaluate(() => window.PrivateSiteAuth.authenticate({ navigate:false, force:true }));
    const allowedThemes = await inviter.page.locator('[data-theme-option]').evaluateAll(items => items.map(item => item.getAttribute('data-theme-option')).filter(theme => theme !== 'auto'));
    const themes = process.env.LAYOUT_THEMES ? process.env.LAYOUT_THEMES.split(',') : allowedThemes;
    for (const theme of themes) assert.ok(allowedThemes.includes(theme), 'Theme is permitted by this site: ' + theme);
    const shots = '/tmp/v3-invitation-username-layout'; await mkdir(shots, { recursive:true });
    await lobby.page.goto(base + '/lobby/#invite=' + invitation.token);
    for (const view of [lobby, account]) {
      view.page.on('pageerror', error => console.error('Layout page error: ' + error.message));
      await view.page.addInitScript(() => {
        const capture = () => {
          const button = document.querySelector('[data-action=redeem-invitation], [data-action=rename-profile]');
          if (!button) { requestAnimationFrame(capture); return; }
          window.__firstNamingFrame = { hidden: button.getClientRects().length === 0 || getComputedStyle(button).visibility === 'hidden', y: button.getBoundingClientRect().y };
        };
        requestAnimationFrame(capture);
      });
    }
    for (const theme of themes) {
      for (const width of [390, 1280]) {
        for (const [kind, view, url] of [
          ['lobby', lobby, '/lobby/#invite=' + invitation.token], ['account', account, '/account/key/']
        ]) {
          await view.page.bringToFront();
          await view.page.setViewportSize({ width, height: 900 });
          await view.page.evaluate(theme => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme), theme);
          const taggedUrl = url.includes('#') ? url.replace('#', `?layout=${theme}-${width}#`) : url + `?layout=${theme}-${width}`;
          await view.page.goto(base + taggedUrl);
          await view.page.evaluate(() => document.fonts.ready);
          await view.page.waitForFunction(() => window.__firstNamingFrame);
          assert.equal(await view.page.getAttribute('html','data-theme'), theme);
          const action = view.page.locator(kind === 'lobby' ? '[data-action=redeem-invitation]' : '[data-action=rename-profile]');
          assert.equal(await action.isVisible(), true, `${theme}/${width}/${kind} action`);
          assert.equal(await view.page.evaluate(() => window.__firstNamingFrame.hidden), false);
          if (kind === 'lobby') {
            const y = await action.evaluate(el => el.getBoundingClientRect().y);
            assert.ok(Math.abs(y - await view.page.evaluate(() => window.__firstNamingFrame.y)) < 2, `${theme}/${width}: initial action shift`);
            await view.page.locator('[data-invitation-username]').fill('!');
            await action.click();
            await view.page.locator('[data-role=invitation-redemption-feedback][data-status=error]').waitFor();
            await view.page.locator('[data-invitation-username]').fill('a'.repeat(80));
          } else {
            await action.click();
            await view.page.locator('[data-role=name-editor-input]').fill('a'.repeat(80));
            await view.page.locator('details').last().evaluate(el => { el.open = true; });
          }
          assert.equal(await view.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${theme}/${width}/${kind} overflow`);
          if ((theme === 'light' && width === 390) || (theme === 'word97' && width === 1280)) {
            await view.page.screenshot({ path: join(shots, `${kind}-${theme}-${width}.png`), fullPage:true });
          }
        }
      }
      console.log('PASS initial/loaded/long/error/expanded layout: ' + theme + ', phone and desktop');
    }
    console.log('Screenshots: ' + shots);
} catch (error) {
  console.error('Root:', root, 'Page:', lastPage?.url());
  if (lastPage) console.error((await lastPage.locator('body').innerText()).slice(0, 2400));
  await writeFile(join(root, 'server.log'), logs);
  throw error;
} finally {
  await browser.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  if (process.env.KEEP_INVITE_FIXTURE !== '1') await rm(root, { recursive: true, force: true });
}
