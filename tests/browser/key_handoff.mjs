// Key handoff (/account/key/share): the key goes only to the loopback page that opened the window,
// only to the exact origin named in ?to=, and only after a click. Run: node tests/browser/key_handoff.mjs
import assert from 'node:assert/strict';
import { mkdtemp, writeFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createServer as createNetServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const root = await mkdtemp(join(tmpdir(), 'key-handoff-'));
async function freePort() {
  const socket = createNetServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(done => socket.close(done)); return port;
}
const php = code => {
  const result = spawnSync('php', ['-r', 'require ' + JSON.stringify(join(project, 'autoload.php')) + ';' + code], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr + result.stdout); return result.stdout;
};

// The "source" instance serves the handoff page.
php(`ForumRewrite\\Support\\LocalRepositoryBootstrap::initializeLocalRepository(${JSON.stringify(project)}, ${JSON.stringify(join(root, 'repository'))});`);
await writeFile(join(root, 'secrets.php'), '<?php return [];');
const sourcePort = await freePort();
const source = `http://127.0.0.1:${sourcePort}`;
const server = spawn('php', ['-S', `127.0.0.1:${sourcePort}`, join(project, 'public/router.php')], {
  cwd: join(project, 'public'), stdio: ['ignore', 'ignore', 'pipe'],
  env: { ...process.env, V3_DEV_LOG: '0', FORUM_REPOSITORY_ROOT: join(root, 'repository'), FORUM_DATABASE_PATH: join(root, 'cache/index.sqlite3'),
    FORUM_STATIC_HTML_ROOT: join(root, 'static'), FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'private/tasks.sqlite3'),
    FORUM_SECRETS_PATH: join(root, 'secrets.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false', FAST_SCORING_ENABLED: 'false',
    PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'),
    PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH: join(root, 'private/message_history_sync.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') } });
let log = ''; server.stderr.on('data', data => { log += data; });

// Two "local copies": plain pages on other loopback ports that open the handoff and record what arrives.
const openerHtml = `<!doctype html><title>local</title><button id="go">Connect</button><script>
  window.__got = [];
  addEventListener('message', event => window.__got.push({ origin: event.origin, data: event.data }));
  document.getElementById('go').onclick = () => { window.__popup = open(document.body.dataset.url, 'handoff', 'popup,width=480,height=600'); };
</script>`;
const openers = [];
for (let n = 0; n < 2; n++) {
  const http = createServer((request, response) => { response.setHeader('Content-Type', 'text/html'); response.end(openerHtml); });
  http.listen(0, '127.0.0.1'); await once(http, 'listening');
  openers.push({ http, origin: `http://127.0.0.1:${http.address().port}` });
}

const pair = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'ilyag' }], format: 'armored' });
const fingerprint = (await pgp.readKey({ armoredKey: pair.publicKey })).getFingerprint().toUpperCase();
let browser;
const share = to => source + '/account/key/share?to=' + encodeURIComponent(to);

async function context(withKey) {
  const ctx = await browser.newContext();
  if (withKey) await ctx.addInitScript(({ origin, publicKey, privateKey, fingerprint }) => {
    if (location.origin !== origin) return;
    for (const [k, v] of Object.entries({ username: 'ilyag', public_key: publicKey, private_key: privateKey, fingerprint, published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + k, v);
  }, { origin: source, publicKey: pair.publicKey, privateKey: pair.privateKey, fingerprint });
  return ctx;
}
async function openFrom(ctx, opener, to) {
  const page = await ctx.newPage();
  await page.goto(opener.origin + '/');
  await page.evaluate(url => { document.body.dataset.url = url; }, share(to));
  const [popup] = await Promise.all([ctx.waitForEvent('page'), page.click('#go')]);
  await popup.waitForLoadState('load');
  return { page, popup };
}
const got = page => page.evaluate(() => window.__got);

try {
  for (let n = 0; n < 100; n++) { try { await fetch(source + '/api/version'); break; } catch { await new Promise(done => setTimeout(done, 100)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const ctx = await context(true);

  // 1. Accepted: the opener gets the key only after the click, and the popup closes.
  const ok = await openFrom(ctx, openers[0], openers[0].origin);
  await ok.popup.getByText('Key: ilyag · …' + fingerprint.slice(-16).replace(/(.{4})(?=.)/g, '$1 ')).waitFor();
  await new Promise(done => setTimeout(done, 500));
  assert.deepEqual(await got(ok.page), [], 'Nothing is sent before the click');
  await ok.popup.screenshot({ path: join(root, 'handoff.png') });
  await Promise.all([ok.popup.waitForEvent('close'), ok.popup.getByRole('button', { name: 'Use it there' }).click()]);
  const received = await got(ok.page);
  assert.equal(received.length, 1);
  assert.equal(received[0].origin, source);
  assert.equal(received[0].data.type, 'forum-key-handoff');
  assert.equal(received[0].data.fingerprint, fingerprint);
  assert.equal(received[0].data.privateKey, pair.privateKey);

  // 2. ?to= names a different local page than the opener: the browser drops the message.
  const elsewhere = await openFrom(ctx, openers[0], openers[1].origin);
  await elsewhere.popup.getByRole('button', { name: 'Use it there' }).click();
  await new Promise(done => setTimeout(done, 800));
  assert.deepEqual(await got(elsewhere.page), [], 'A different origin never receives the key');

  // 3. Non-loopback or https targets: no button at all.
  for (const bad of ['https://127.0.0.1:' + sourcePort, 'http://evil.example:80', 'http://127.0.0.1.evil.example:80', 'javascript:alert(1)']) {
    const attempt = await openFrom(ctx, openers[0], bad);
    await attempt.popup.getByText('This link has no valid local address.').waitFor();
    assert.equal(await attempt.popup.getByRole('button', { name: 'Use it there' }).count(), 0, bad);
    await attempt.popup.close(); await attempt.page.close();
  }

  // 4. Opened on its own, without an opener: refused, with Details.
  const alone = await ctx.newPage();
  await alone.goto(share(openers[0].origin));
  await alone.getByText('Open this from your local copy').waitFor();
  assert.equal(await alone.getByRole('button', { name: 'Use it there' }).isVisible(), false);
  assert.match(await alone.locator('details pre').textContent(), /step: key handoff: opener/);

  // 5. No key in this browser: link to Account, nothing to send.
  const empty = await context(false);
  const none = await openFrom(empty, openers[0], openers[0].origin);
  await none.popup.getByText('This browser has no key.').waitFor();
  assert.equal(await none.popup.getByRole('button', { name: 'Use it there' }).isVisible(), false);
  await new Promise(done => setTimeout(done, 300));
  assert.deepEqual(await got(none.page), []);
  console.log('PASS key handoff: click-only, exact origin, loopback-only, opener-only, no key. Artifacts: ' + root);
} catch (error) {
  console.error(log.split('\n').filter(line => !/Accepted|Closing|\[200\]/.test(line)).slice(-30).join('\n'));
  throw error;
} finally {
  if (browser) await browser.close();
  server.kill();
  for (const opener of openers) opener.http.close();
}
