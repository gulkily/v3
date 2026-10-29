// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/viewer_nav_prepaint.mjs
// Member-only navigation (Messages, Invite) is in every viewer's HTML and must
// have its final visibility from first paint; only real status changes update it.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync, writeFileSync } from 'node:fs';
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
const root = await mkdtemp(join(tmpdir(), 'viewer-nav-prepaint-'));
const env = { ...process.env, FORUM_SITE_ID: process.env.NAV_SITE || 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'),
  FORUM_PRIVATE_MESSAGES_ENABLED: 'true' };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const promote = spawnSync('php', ['-r', `require $argv[1] . '/autoload.php';
  $dir = $argv[2] . '/repository/records/approval-seeds'; if (!is_dir($dir)) mkdir($dir, 0777, true);
  file_put_contents($dir . '/openpgp-' . strtolower($argv[3]) . '.txt', "Approved-Identity-ID: openpgp:" . strtolower($argv[3]) . "\\nSeed-Reason: isolated browser test\\n\\n");
  $repo = new \\ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2] . '/repository');
  (new \\ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2] . '/repository', $argv[2] . '/read.sqlite3', $repo))->rebuild();`, project, root, fingerprint], { encoding: 'utf8', env });
assert.equal(promote.status, 0, promote.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

const themes = (process.env.NAV_THEMES || 'light,dark,word97,chicago,midnight').split(',');
const routes = (process.env.NAV_ROUTES || '/,/threads/root-001,/about/,/users/,/tools/').split(',');
const iconThemes = new Set(['word97', 'chicago', 'midnight']);
const shots = process.env.NAV_SHOTS || '';
let checked = 0;

// Records member-link visibility and boxes from the moment each link is parsed,
// then every frame until told to stop. Installed before any page script runs.
function observe() {
  const links = () => Array.from(document.querySelectorAll('[data-member-only]'));
  // While parsing, only visibility is meaningful: positions depend on header
  // markup not parsed yet. Painted frames also compare positions.
  const snapshot = withBoxes => JSON.stringify(links().map(link => {
    const box = link.getBoundingClientRect();
    return withBoxes ? [link.getAttribute('href'), getComputedStyle(link).display !== 'none', Math.round(box.x), Math.round(box.width)]
      : [link.getAttribute('href'), getComputedStyle(link).display !== 'none'];
  }));
  window.__navStates = []; window.__navParsed = [];
  const record = () => { const value = snapshot(true); if (window.__navStates.at(-1) !== value) window.__navStates.push(value); };
  new MutationObserver(() => {
    if (!links().length) return;
    const value = snapshot(false); if (window.__navParsed.at(-1) !== value) window.__navParsed.push(value);
  }).observe(document, { childList: true, subtree: true });
  // Before <main> is parsed or while stylesheets are still arriving (site.css
  // loads asynchronously for everyone), only visibility is compared.
  const stylesReady = () => Array.from(document.querySelectorAll('link[rel="stylesheet"]')).every(link => link.sheet && link.media !== 'print');
  const frame = () => {
    if (links().length) {
      if (document.querySelector('main') && stylesReady()) record();
      else { const value = snapshot(false); if (window.__navParsed.at(-1) !== value) window.__navParsed.push(value); }
    }
    if (!window.__navStop) requestAnimationFrame(frame);
  };
  requestAnimationFrame(frame);
}

async function visit(context, path, label) {
  const page = await context.newPage();
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  page.messagingRequests = 0;
  page.on('request', request => { if (/\/assets\/private_message_/.test(request.url())) page.messagingRequests++; });
  await page.goto(base + path, { waitUntil: 'load' });
  // Long enough for deferred scripts, background sign-in and the approval check to settle.
  await page.waitForTimeout(Number(process.env.NAV_SETTLE_MS || 1500));
  const { states, parsed } = await page.evaluate(() => {
    window.__navStop = true;
    return { states: window.__navStates.map(value => JSON.parse(value)), parsed: window.__navParsed.map(value => JSON.parse(value)) };
  });
  assert.deepEqual(errors, [], `${label}: page errors`);
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: horizontal overflow`);
  return { page, states, parsed };
}

function visible(state) { return state.filter(([, shown]) => shown).map(([href]) => href); }

async function tabBar(page, label) {
  // Phone tab bar: visible tabs share the bar evenly, with no empty slot.
  const tabs = await page.evaluate(() => {
    const bar = document.querySelector('.site-header--app .site-header-actions');
    if (!bar || getComputedStyle(bar).position !== 'fixed') return null;
    return Array.from(bar.querySelectorAll(':scope > .nav > .nav-link, :scope > .new-post-button, :scope > .account-menu'))
      .filter(node => getComputedStyle(node).display !== 'none')
      .map(node => Math.round(node.getBoundingClientRect().width));
  });
  if (tabs === null) return;
  assert.ok(tabs.length >= 3, `${label}: tab bar has tabs`);
  assert.ok(Math.max(...tabs) - Math.min(...tabs) <= 2, `${label}: tabs share the bar evenly (${tabs})`);
}

async function messagesIcon(page, label, theme) {
  if (!iconThemes.has(theme)) return;
  const image = await page.locator('.nav [data-member-only][href="/messages"]').evaluate(node => getComputedStyle(node, '::before').backgroundImage);
  assert.ok(image && image !== 'none', `${label}: Messages icon in ${theme}`);
}

function seedScript({ keys, fingerprint, theme, remembered }) {
  localStorage.setItem('zenmemes-theme', theme);
  if (keys && !sessionStorage.getItem('seeded')) {
    for (const [key, value] of Object.entries({ username: 'reaction-browser', public_key: keys.publicKey, private_key: keys.privateKey, fingerprint, published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + key, value);
    if (remembered) localStorage.setItem('forum_pki_approved_fingerprint', fingerprint.toLowerCase());
    sessionStorage.setItem('seeded', '1');
  }
}

try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const theme of themes) {
    for (const width of [390, 1280]) {
      const viewport = { width, height: 844 };
      // Anonymous: links are in the HTML but never shown, and leave no gap.
      {
        const context = await browser.newContext({ serviceWorkers: 'block', viewport });
        await context.addInitScript(seedScript, { theme });
        await context.addInitScript(observe);
        for (const path of routes) {
          const label = `anonymous ${theme} ${width} ${path}`;
          const { page, states, parsed } = await visit(context, path, label);
          assert.ok(states.length >= 1, `${label}: links rendered`);
          assert.deepEqual([...states, ...parsed].flatMap(visible), [], `${label}: member links stay hidden`);
          if (width === 390) await tabBar(page, label);
          assert.equal(page.messagingRequests, 0, `${label}: no messaging downloads`);
          if (shots && path === '/') await page.screenshot({ path: `${shots}/anonymous-${theme}-${width}.png` });
          await page.close(); checked++;
        }
        await context.close();
      }
      // Returning approved member: visible from first paint, nothing moves after.
      {
        const context = await browser.newContext({ serviceWorkers: 'block', viewport });
        await context.addInitScript(seedScript, { keys, fingerprint, theme, remembered: true });
        await context.addInitScript(observe);
        for (const path of routes) {
          const label = `member ${theme} ${width} ${path}`;
          const { page, states, parsed } = await visit(context, path, label);
          assert.equal(states.length, 1, `${label}: no change after first paint (${JSON.stringify(states)})`);
          assert.ok(parsed.every(state => visible(state).length === state.length), `${label}: shown while parsing (${JSON.stringify(parsed)})`);
          assert.deepEqual(visible(states[0]).sort(), ['/invites/', '/messages'], `${label}: Messages and Invite shown`);
          if (width === 390) await tabBar(page, label);
          await messagesIcon(page, label, theme);
          // Unread works on a page with no server viewer, without moving or re-captioning Messages.
          const messages = page.locator('.nav [href="/messages"]');
          const box = JSON.stringify(states[0].find(([href]) => href === '/messages').slice(2));
          await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready', null, { timeout: 10000 });
          const after = await messages.evaluate(node => { const r = node.getBoundingClientRect(); return JSON.stringify([Math.round(r.x), Math.round(r.width)]); });
          assert.equal(after, box, `${label}: unread did not move Messages`);
          assert.equal(await messages.innerText(), 'Messages', `${label}: caption unchanged`);
          if (shots && path === '/') await page.screenshot({ path: `${shots}/member-${theme}-${width}.png` });
          await page.close(); checked++;
        }
        await context.close();
      }
    }
  }
  // Real status changes update in place: first sign-in, then sign-out.
  {
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
    await context.addInitScript(seedScript, { keys, fingerprint, theme: 'light', remembered: false });
    await context.addInitScript(observe);
    const first = await visit(context, '/threads/root-001', 'first sign-in');
    await first.page.waitForFunction(() => document.documentElement.getAttribute('data-member') === 'approved');
    assert.deepEqual(visible(first.states[0]), [], 'not shown before the client knows');
    assert.equal(await first.page.locator('.nav [href="/messages"]').isVisible(), true, 'shown in place after sign-in');
    await first.page.evaluate(() => {
      ['forum_pki_public_key', 'forum_pki_private_key', 'forum_pki_fingerprint'].forEach(key => localStorage.removeItem(key));
      window.dispatchEvent(new Event('forum:identity-changed'));
    });
    assert.equal(await first.page.locator('.nav [href="/messages"]').isVisible(), false, 'hidden in place after sign-out');
    assert.equal(await first.page.evaluate(() => localStorage.getItem('forum_pki_approved_fingerprint')), null);
    await first.page.close();
    await context.close();
    // The next visit after sign-in is final from first paint.
    const again = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
    await again.addInitScript(seedScript, { keys, fingerprint, theme: 'light', remembered: false });
    await again.addInitScript(observe);
    const learned = await visit(again, '/threads/root-001', 'learn');
    await learned.page.waitForFunction(() => document.documentElement.getAttribute('data-member') === 'approved');
    await learned.page.close();
    const next = await visit(again, '/about/', 'after sign-in');
    assert.equal(next.states.length, 1, 'after sign-in: no change after first paint');
    assert.deepEqual(visible(next.states[0]).sort(), ['/invites/', '/messages']);
    await next.page.close();
    await again.close();
  }
  // Without JavaScript nobody sees member links; the routes still work by URL.
  {
    const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(base + '/');
    assert.equal(await page.locator('[data-member-only]').count() > 0, true);
    assert.equal(await page.locator('[data-member-only]:visible').count(), 0, 'no-JS: hidden');
    await context.close();
  }
  console.log(`PASS viewer nav pre-paint: ${checked} page states across themes=${themes.join('/')}, widths=390/1280, routes=${routes.length}; sign-in/sign-out in place, no-JS hidden`);
} catch (error) { console.error(serverLog.slice(-2500)); throw error; }
finally { await browser.close(); server.kill(); }
