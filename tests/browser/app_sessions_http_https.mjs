// Run with: node tests/browser/app_sessions_http_https.mjs
// Replays the sign-in failures reported on 2026-11-02 in a real browser, with the site served
// over http and https on the same host (different ports share cookies, as on a phone that
// visits both addresses):
//   1. sign-in over http after an https visit (a Secure cookie from https used to block it);
//   2. a browser still holding a Secure PHPSESSID signs in over http, and https retires it;
//   3. an operator's Deploy request after the session has sat idle past PHP's cleanup;
//   4. after sign-out, a copy of the old cookie no longer signs anyone in.
// The host is a made-up name mapped to 127.0.0.1: Chrome treats localhost and 127.0.0.1 as
// secure and would send Secure cookies over http there, hiding failure 1.
import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import https from 'node:https';
import http from 'node:http';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const operator = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'browser-inviter' }], format: 'armored', date: new Date(Date.now() - 60000) });
const fingerprint = (await pgp.readKey({ armoredKey: operator.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'app-sessions-http-https-'));
const host = 'app.test';
const freePort = async () => { const s = createServer().listen(0, '127.0.0.1'); await once(s, 'listening'); const p = s.address().port; await new Promise(r => s.close(r)); return p; };

let server, proxy, browser, logs = '';
const results = [];
async function check(name, fn) {
  try { await fn(); results.push(['PASS', name]); }
  catch (error) { results.push(['FAIL', name + ': ' + String(error.message || error).split('\n')[0]]); }
}

try {
  const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
  assert.equal(fixture.status, 0, fixture.stderr);
  const cert = spawnSync('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj', '/CN=' + host,
    '-addext', 'subjectAltName=DNS:' + host, '-keyout', join(root, 'tls.key'), '-out', join(root, 'tls.crt')], { encoding: 'utf8' });
  assert.equal(cert.status, 0, cert.stderr);

  const phpPort = await freePort(), httpsPort = await freePort();
  const httpBase = `http://${host}:${phpPort}`, httpsBase = `https://${host}:${httpsPort}`;
  // PHP's file-session cleanup runs on every request and drops anything idle for 2 seconds,
  // so "idle past cleanup" takes seconds instead of 24 minutes. App-owned sessions ignore it.
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-d', 'session.gc_maxlifetime=2', '-d', 'session.gc_probability=1', '-d', 'session.gc_divisor=1',
    '-S', `127.0.0.1:${phpPort}`, join(project, 'tests/Support/app_sessions_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false',
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
    stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  await import('node:fs').then(fs => fs.mkdirSync(join(root, 'sessions'), { recursive: true }));
  proxy = https.createServer({ key: await readFile(join(root, 'tls.key')), cert: await readFile(join(root, 'tls.crt')) }, (req, res) => {
    const upstream = http.request({ host: '127.0.0.1', port: phpPort, method: req.method, path: req.url,
      headers: { ...req.headers, 'x-test-forwarded-proto': 'https' } }, reply => { res.writeHead(reply.statusCode, reply.headers); reply.pipe(res); });
    upstream.on('error', error => { res.writeHead(502); res.end(String(error)); });
    req.pipe(upstream);
  }).listen(httpsPort, '127.0.0.1');
  await once(proxy, 'listening');
  for (let i = 0; i < 60; i++) { try { await fetch(`http://127.0.0.1:${phpPort}/api/version`); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',
    args: ['--no-sandbox', '--no-proxy-server', `--host-resolver-rules=MAP ${host} 127.0.0.1`] });
  const newBrowserContext = async () => {
    const context = await browser.newContext({ ignoreHTTPSErrors: true, serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
    await context.addInitScript(({ operator, fingerprint }) => {
      for (const [key, value] of Object.entries({ username: 'browser-inviter', public_key: operator.publicKey, private_key: operator.privateKey, fingerprint, published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
    }, { operator, fingerprint });
    return context;
  };
  const signInOnAccount = async (page, base) => {
    const answers = [];
    const record = async response => {
      if (response.url().includes('/api/authenticate_identity')) answers.push((await response.text().catch(() => '')).trim());
    };
    page.on('response', record);
    await page.goto(base + '/account/key/');
    try {
      await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]')?.dataset.authenticatedIdentityId !== '', null, { timeout: 15000 });
    } catch (error) {
      throw new Error('not signed in' + (answers.length ? ` (server said: ${answers.join(' | ')})` : ''));
    } finally {
      page.off('response', record);
    }
  };
  // Requests go through a page so they use the browser's cookies and host mapping.
  const inPage = async (context, base, path, init = {}) => {
    const scratch = await context.newPage();
    try {
      await scratch.goto(base + '/api/version');
      return await scratch.evaluate(async ([path, init]) => {
        const response = await fetch(path, { credentials: 'same-origin', ...init });
        return { status: response.status, text: await response.text() };
      }, [path, init]);
    } finally { await scratch.close(); }
  };
  const status = async (context, base) => (await inPage(context, base, '/api/auth_status')).status;

  // 1. https first, then http, in the same browser.
  const phone = await newBrowserContext();
  const page = await phone.newPage();
  await check('sign-in over https', async () => { await signInOnAccount(page, httpsBase); assert.equal(await status(phone, httpsBase), 200); });
  await check('sign-in over http after an https visit', async () => { await signInOnAccount(page, httpBase); assert.equal(await status(phone, httpBase), 200); });
  await check('http sign-in stays signed in on reload', async () => { await page.reload(); assert.equal(await status(phone, httpBase), 200); });

  // 2. A browser still holding PHP's Secure session cookie from before the switch.
  const legacy = await newBrowserContext();
  await legacy.addCookies([{ name: 'PHPSESSID', value: 'from-php-sessions', url: httpsBase, secure: true, httpOnly: true, sameSite: 'Lax' }]);
  const legacyPage = await legacy.newPage();
  await check('browser with an old Secure PHPSESSID signs in over http', async () => { await signInOnAccount(legacyPage, httpBase); assert.equal(await status(legacy, httpBase), 200); });
  await check('an https visit retires the old PHPSESSID', async () => {
    await legacyPage.goto(httpsBase + '/');
    assert.equal((await legacy.cookies(httpsBase)).some(cookie => cookie.name === 'PHPSESSID'), false);
  });

  // 3. Deploy after the session sat idle past PHP's cleanup (another visitor's request runs it).
  // Over https, where this browser signed in on every version, so a refusal can only mean
  // the session itself was lost.
  await check('operator Deploy request after idle', async () => {
    await new Promise(r => setTimeout(r, 4000));
    await fetch(`http://127.0.0.1:${phpPort}/api/auth_challenge`, { headers: { Host: `${host}:${phpPort}` } });
    const response = await inPage(phone, httpsBase, '/tools/deploy/', { method: 'POST', body: new URLSearchParams({ action: 'status' }).toString(),
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'ForumDeploy', Accept: 'application/json' } });
    assert.equal(/requires a signed-in root-approved operator/.test(response.text), false, `refused (${response.status}): ${response.text.slice(0, 120)}`);
  });

  // 4. Sign-out ends the session on the server: a copied cookie stops working.
  await check('copied cookie is dead after sign-out', async () => {
    const before = (await phone.cookies(httpBase)).filter(cookie => !cookie.secure && cookie.name !== 'identity_hint');
    assert.ok(before.length > 0, 'no http session cookie to copy');
    const signOut = await inPage(phone, httpBase, '/api/clear_identity', { method: 'POST' });
    assert.equal(signOut.status, 200);
    const copy = await browser.newContext();
    await copy.addCookies(before.map(({ name, value }) => ({ name, value, url: httpBase })));
    assert.equal(await status(copy, httpBase), 401);
    await copy.close();
  });

  for (const [outcome, name] of results) console.log(`${outcome} ${name}`);
  const failed = results.filter(([outcome]) => outcome === 'FAIL').length;
  console.log(failed === 0 ? 'PASS app sessions over http and https' : `FAIL ${failed} of ${results.length} replayed checks`);
  process.exitCode = failed === 0 ? 0 : 1;
} catch (error) {
  console.error(error);
  console.error(logs.slice(-2000));
  process.exitCode = 1;
} finally {
  await browser?.close();
  proxy?.close();
  server?.kill();
  await rm(root, { recursive: true, force: true });
}
