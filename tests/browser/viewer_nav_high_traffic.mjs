// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/viewer_nav_high_traffic.mjs
// With high-traffic mode on, everyone gets the same cached pages; member-only
// navigation must still be final from first paint, and unread must work there.
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const openpgp = globalThis.openpgp;
const keys = await openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'traffic-member' }], format: 'armored' });
const fingerprint = (await openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const root = await mkdtemp(join(tmpdir(), 'viewer-nav-high-traffic-'));
const cache = join(project, 'state/high_traffic', createHash('sha256').update(join(root, 'repository')).digest('hex').slice(0, 16));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_APPROVED_MEMBERS_ONLY: 'false', FORUM_PRIVATE_MESSAGES_ENABLED: 'true',
  FORUM_SECRETS_PATH: join(root, 'absent.php'), HIGH_TRAFFIC_TEST_ROOT: root, FORUM_REPOSITORY_ROOT: join(root, 'repository'),
  FORUM_DATABASE_PATH: join(root, 'read.sqlite3'), FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'tasks.sqlite3'),
  FORUM_PUBLIC_ARTIFACT_ROOT: join(root, 'public'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/fastmod_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seed.status, 0, seed.stderr);
const { identity } = JSON.parse(seed.stdout);
await mkdir(join(root, 'repository/records/posts'), { recursive: true });
await writeFile(join(root, 'repository/records/posts/traffic-visible.txt'), 'Post-ID: traffic-visible\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nSubject: Traffic browser fixture\n\nA visible public thread.\n');
for (const args of [['add', 'records'], ['commit', '-m', 'Seed visible thread']]) {
  const result = spawnSync('git', ['-C', join(root, 'repository'), ...args], { encoding: 'utf8' }); assert.equal(result.status, 0, result.stderr);
}
await writeFile(join(root, 'public/.htaccess'), await readFile(join(project, 'public/.htaccess'), 'utf8'));
const cli = command => { const result = spawnSync('php', [join(project, 'scripts/high_traffic.php'), command], { encoding: 'utf8', env }); assert.equal(result.status, 0, command + ': ' + result.stderr + result.stdout); };
cli('prepare');
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/high_traffic_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', chunk => { logs += chunk; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const routes = ['/', '/about/', '/threads/traffic-visible/'];

function observe() {
  const links = () => Array.from(document.querySelectorAll('[data-member-only]'));
  const snapshot = boxes => JSON.stringify(links().map(link => {
    const box = link.getBoundingClientRect();
    return boxes ? [link.getAttribute('href'), getComputedStyle(link).display !== 'none', Math.round(box.x), Math.round(box.width)] : [link.getAttribute('href'), getComputedStyle(link).display !== 'none'];
  }));
  const push = (list, value) => { if (list.at(-1) !== value) list.push(value); };
  window.__painted = []; window.__parsed = [];
  const stylesReady = () => Array.from(document.querySelectorAll('link[rel="stylesheet"]')).every(link => link.sheet && link.media !== 'print');
  new MutationObserver(() => { if (links().length) push(window.__parsed, snapshot(false)); }).observe(document, { childList: true, subtree: true });
  const frame = () => {
    if (links().length) push(document.querySelector('main') && stylesReady() ? window.__painted : window.__parsed, snapshot(document.querySelector('main') && stylesReady()));
    if (!window.__stop) requestAnimationFrame(frame);
  };
  requestAnimationFrame(frame);
}
async function signIn(context) {
  const challenge = (await (await context.request.get(base + '/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
  const privateKey = await openpgp.readPrivateKey({ armoredKey: keys.privateKey });
  const signature = await openpgp.sign({ message: await openpgp.createMessage({ text: challenge }), signingKeys: privateKey, detached: true, format: 'armored' });
  const response = await context.request.post(base + '/api/authenticate_identity', { form: { identity_id: identity, challenge, detached_signature: signature } });
  assert.match(await response.text(), /approved=1/);
}
async function visit(context, path, label) {
  const page = await context.newPage();
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  const response = await page.goto(base + path);
  await page.waitForTimeout(1000);
  const result = await page.evaluate(() => { window.__stop = true; return { painted: window.__painted.map(JSON.parse), parsed: window.__parsed.map(JSON.parse) }; });
  assert.deepEqual(errors, [], `${label}: page errors`);
  return { page, response, ...result };
}
const shown = state => state.filter(([, visible]) => visible).map(([href]) => href).sort();

try {
  for (let i = 0; i < 100; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  let checked = 0;
  for (const mode of ['enabled', 'disabled']) {
    cli(mode === 'enabled' ? 'enable' : 'disable');
    // Cached and live HTML is the same for an anonymous visitor and a signed-in member.
    const anonymousHtml = await (await fetch(base + '/about/')).text();
    for (const width of [390, 1280]) {
      const anonymous = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 844 } });
      await anonymous.addInitScript(observe);
      const member = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 844 } });
      await member.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
      await signIn(member);
      await member.addInitScript(({ keys, fingerprint }) => {
        for (const [key, value] of Object.entries({ username: 'traffic-member', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint, approved_fingerprint: fingerprint.toLowerCase() })) localStorage.setItem('forum_pki_' + key, value);
      }, { keys, fingerprint });
      await member.addInitScript(observe);
      const memberHtml = await (await member.request.get(base + '/about/')).text();
      const header = html => (html.match(/<header class="site-header[\s\S]*?<\/header>/) || [''])[0];
      assert.ok(header(anonymousHtml).includes('data-member-only'), `${mode}: header has member-only links`);
      assert.equal(header(memberHtml), header(anonymousHtml), `${mode}: header identical for member and anonymous`);
      if (mode === 'enabled') assert.equal(memberHtml, anonymousHtml, 'enabled: the member gets the cached page');
      for (const path of routes) {
        const label = `${mode} ${width} ${path}`;
        const guest = await visit(anonymous, path, 'anonymous ' + label);
        assert.deepEqual([...guest.painted, ...guest.parsed].flatMap(shown), [], `anonymous ${label}: hidden`);
        await guest.page.close();
        const seen = await visit(member, path, 'member ' + label);
        if (mode === 'enabled') assert.equal(seen.response.headers()['cache-control'], 'public, no-cache, must-revalidate', `member ${label}: cached page`);
        assert.equal(seen.painted.length, 1, `member ${label}: no change after first paint (${JSON.stringify(seen.painted)})`);
        assert.deepEqual(shown(seen.painted[0]), ['/invites/', '/messages'], `member ${label}: shown`);
        assert.ok(seen.parsed.every(state => shown(state).length === state.length), `member ${label}: shown while parsing`);
        await seen.page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready', null, { timeout: 10000 });
        assert.equal(await seen.page.locator('.nav [href="/messages"]').innerText(), 'Messages', `member ${label}: caption`);
        await seen.page.close();
        checked += 2;
      }
      await anonymous.close(); await member.close();
    }
  }
  console.log(`PASS viewer nav high traffic: ${checked} page states, enabled and disabled, 390/1280, routes=${routes.join(' ')}; cached pages identical for member and anonymous, unread ready on cached pages`);
} catch (error) { console.error(logs.slice(-2000)); throw error; }
finally { try { cli('disable'); } catch {} await browser.close(); server.kill(); await rm(cache, { recursive: true, force: true }); }
