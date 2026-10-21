import assert from 'node:assert/strict';
import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer as createSocket } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'collapse-browser-'));
let server, browser;
try {
  runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
  const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'fastmod-browser' }], format: 'armored' });
  const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'secrets.php'),
    SIGNED_REACTION_TEST_ROOT: root, FORUM_REPOSITORY_ROOT:join(root,'repository'), FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'tasks.sqlite3'),
    FORUM_PUBLIC_ARTIFACT_ROOT: join(root,'public'), FORUM_STATIC_HTML_ROOT: join(root,'static'), FORUM_DATABASE_PATH: join(root,'read.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  await writeFile(env.FORUM_SECRETS_PATH, '<?php return [];');
  const seed = spawnSync('php', [join(project, 'tests/Support/same_author_collapse_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity } = JSON.parse(seed.stdout);
  const socket = createSocket().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'ignore'] });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block' });
  await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
  await context.addInitScript(({ keys, fingerprint }) => {
    for (const [key, value] of Object.entries({ private_key: keys.privateKey, public_key: keys.publicKey, fingerprint, published_fingerprint: fingerprint, username: 'fastmod-browser' })) localStorage.setItem('forum_pki_' + key, value);
  }, { keys, fingerprint });
  const page = await context.newPage();
  page.setDefaultTimeout(15000);
  const key = 'FORUM_COLLAPSE_SAME_AUTHOR_POSTS_ENABLED';
  const row = () => page.locator('#flag-' + key);
  async function flags() {
    await page.goto(base + '/tools/feature-flags/');
    await page.waitForFunction(() => !document.querySelector('[data-public-auth-resume="true"]'));
    await page.getByRole('searchbox', { name: 'Search flags' }).fill(key);
  }
  async function save(value, reset = false) {
    const response = page.waitForResponse(r => r.url().endsWith('/api/finalize_feature_flag_change'));
    await (reset ? row().locator('.feature-flag-reset-form button') : row().getByRole('switch')).click();
    const result = await (await response).json();
    assert.equal(result.status, 'ok', JSON.stringify(result));
    assert.equal(result.effective_value, String(value));
    assert.match(await readFile(join(root, 'repository', result.record_path + '.asc'), 'utf8'), /PGP SIGNATURE/);
    await page.waitForFunction(key => !document.querySelector('#flag-' + key + ' [role="switch"]').disabled, key);
  }
  await flags();
  assert.equal(await row().getByRole('switch').getAttribute('aria-checked'), 'true');
  const anonymous = await browser.newContext();
  const denied = await anonymous.request.post(base + '/api/prepare_feature_flag_change', { form: { key, value: 'false' } });
  assert.equal(denied.status(), 403);
  const readonly = await anonymous.newPage();
  await readonly.goto(base + '/tools/feature-flags/');
  assert.equal(await readonly.locator('#flag-' + key).getByRole('switch').isDisabled(), true);
  await anonymous.close();
  await page.route('**/api/finalize_feature_flag_change', route => route.fulfill({status:503,json:{status:'error',error:'Temporary save failure. Try again.'}}));
  await row().getByRole('switch').click();
  await row().locator('[data-role="feature-flag-status"]').filter({hasText:'Temporary save failure'}).waitFor();
  assert.equal(await row().getByRole('switch').getAttribute('aria-checked'), 'true');
  await page.unroute('**/api/finalize_feature_flag_change');
  await save(false);
  await flags();
  assert.equal(await row().getByRole('switch').getAttribute('aria-checked'), 'false');
  const themes = ['light','dark','console','word97','chicago','midnight','arena','sticker','whitehot'];
  async function applyTheme(theme) {
    await page.evaluate(async theme => {
      if (!document.querySelector(`[data-test-theme="${theme}"]`)) {
        const link = document.createElement('link'); link.rel='stylesheet'; link.href='/assets/theme-'+theme+'.css';
        link.dataset.testTheme=theme;
        const loaded = new Promise((resolve, reject) => { link.onload=resolve; link.onerror=reject; });
        document.head.append(link); await loaded;
      }
      document.documentElement.dataset.theme=theme;
    }, theme);
  }
  async function layouts(enabled) {
    for (const width of [390, 1280]) {
      await page.setViewportSize({width, height:900});
      await flags();
      for (const theme of themes) {
        await applyTheme(theme);
        assert.equal(await row().getByRole('switch').isVisible(), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `flags ${theme}/${width}`);
      }
      await page.goto(base + '/threads/collapse-root');
      assert.equal(await page.locator('[data-author="reply-agent"]').count(), 1);
      assert.equal(await page.locator('[data-author="other"]').count(), 1);
      assert.equal(await page.locator('.post-card.continuation').count(), enabled ? 2 : 0);
      assert.equal(await page.locator('.post-card.meta-deferred').count(), enabled ? 2 : 0);
      assert.match(await page.locator('section.stack').innerText(), enabled ? /3 replies/ : /5 replies/);
      for (const theme of themes) {
        await applyTheme(theme);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `thread ${theme}/${width}/${enabled}`);
        if (!enabled) {
          for (const card of await page.locator('.post-card').all()) {
            assert.equal(await card.locator(':scope > p.meta').first().isVisible(), true);
            assert.equal(await card.locator('.post-card-actions').isVisible(), true);
          }
        }
        const details = page.locator('.inline-reply-details');
        if (await details.count()) await details.first().evaluate(el => el.open = true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path:`/tmp/collapse-${theme}-${width}-${enabled}.png`,fullPage:true});
      }
      await page.goto(base + '/threads/collapse-empty');
      assert.equal(await page.locator('.continuation').count(), 0);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    }
  }
  await layouts(false);
  await flags(); await save(true, true);
  await flags();
  assert.equal(await row().getByRole('switch').getAttribute('aria-checked'), 'true');
  await layouts(true);
  await flags(); await save(false); await flags(); await save(true);
  const stopped = once(server, 'exit'); server.kill(); await stopped;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env: {...env, [key]: 'false'}, stdio: ['ignore','ignore','ignore'] });
  for (let i=0; i<60; i++) { try { await fetch(base+'/api/version'); break; } catch { await new Promise(resolve=>setTimeout(resolve,100)); } }
  await flags();
  assert.equal(await row().getByRole('switch').isDisabled(), true);
  assert.equal(await row().getByRole('switch').getAttribute('aria-checked'), 'false');
  const lockedWrite = await context.request.post(base+'/api/prepare_feature_flag_change', {form:{key,value:'true'}});
  assert.equal(lockedWrite.status(), 400);
  await page.goto(base+'/threads/collapse-root');
  assert.equal(await page.locator('.continuation').count(), 0);
  console.log('PASS collapse toggle: signed save/reset, failure/retry, authorization, reload, environment locks, thread metadata/counts and 36 theme/viewport/state combinations');
} finally {
  await browser?.close();
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  await rm(root, { recursive: true, force: true });
}
