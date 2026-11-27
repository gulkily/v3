// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/support_wall.mjs [--screenshots=/tmp/support]
// The support wall: a goal-50 campaign drawn as squares; pledge 3 squares with a preset, with and
// without JavaScript, and land back on the wall; a failed post keeps its amount; no theme overflows.
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
const shots = (process.argv.find(a => a.startsWith('--screenshots=')) || '').slice('--screenshots='.length);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const people = [];
for (const [name, approved] of [['ilya', true], ['mara', true], ['rin', true], ['sam', true], ['newcomer', false]]) {
  const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored' });
  people.push({ name, approved, publicKey: keys.publicKey });
}
// mara 3 delivered, rin 1, sam 2.5 (2 squares and "Also: sam"), newcomer 1 waiting for approval.
const pledges = [['mara', '3', 'filled'], ['rin', '1', ''], ['sam', '2.5', ''], ['newcomer', '1', '']];
const root = await mkdtemp(join(tmpdir(), 'support-wall-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root, FORUM_PLEDGES_ENABLED: '1',
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/support_wall_browser_fixture.php'), root], { input: JSON.stringify({ people, pledges }), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr + seeded.stdout);
const { thread } = JSON.parse(seeded.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/support_wall_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 375, height = 667) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height } });
  const page = await context.newPage();
  const seen = { errors: [] };
  page.on('dialog', d => { void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
const blocks = page => page.locator('.support-wall__square--first').evaluateAll(els => els.map(el => ({
  name: el.querySelector('.support-wall__name').textContent.trim(),
  squares: document.querySelectorAll(`.support-wall__square[data-block="${el.dataset.block}"]`).length,
  state: el.dataset.state,
})));
const sum = async page => (await page.locator('.pledge-totals__sum').textContent()).replace(/\s+/g, ' ').trim();
function approveAuthorOf(start) {
  const done = spawnSync('php', ['-r', `$db = new PDO('sqlite:' . $argv[1]);
    $find = $db->prepare('SELECT author_identity_id FROM posts WHERE body LIKE ? ORDER BY created_at DESC LIMIT 1');
    $find->execute([$argv[2] . '%']);
    $id = (string) $find->fetchColumn();
    file_put_contents($argv[3] . '/records/approval-seeds/' . str_replace(':', '-', $id) . '.txt',
        "Approved-Identity-ID: {$id}\\nSeed-Reason: support wall browser check\\n\\nApproved so its pledges count.\\n");
    $db->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?')->execute([$id]);
    echo $id;`, join(root, 'read.sqlite3'), start, join(root, 'repository')], { encoding: 'utf8' });
  assert.equal(done.status, 0, done.stderr);
  assert.match(done.stdout, /^openpgp:/);
}

try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // The Step 2 fixture at the reported size, 375 by 667.
  const phone = await open(true);
  await phone.page.goto(base + '/support/');
  assert.equal(await sum(phone.page), '6.5 of 50 squares pledged');
  const before = await blocks(phone.page);
  assert.deepEqual(before.map(b => b.squares).sort(), [1, 1, 2, 3], 'blocks of 3, 1, 2 and a pending 1');
  assert.deepEqual(before.filter(b => b.state === 'pending').map(b => b.name), ['newcomer'], 'the pending block');
  assert.equal(before.at(-1).state, 'pending', 'pending comes after the counted blocks');
  assert.equal(await phone.page.locator('.support-wall__square--delivered').count(), 3, 'mara\'s 3 squares are delivered');
  assert.equal(await phone.page.locator('.support-wall__square').count(), 50);
  assert.equal(await phone.page.locator('.support-wall__also').textContent(), 'Also: sam');
  assert.equal(await phone.page.locator('.pending-chip, .chip--pending').first().isVisible(), true);
  assert.ok(await phone.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'no sideways scroll at 375');
  // A block's name links to its pledge in the thread.
  const href = await phone.page.locator('.support-wall__square--first a').first().getAttribute('href');
  assert.match(href, new RegExp(`^/threads/${thread}#post-`));

  // A failed post keeps the amount, and the same press works once the server answers again.
  const form = phone.page.locator('[data-pledge-form]');
  await form.getByRole('button', { name: /^Pledge 3/ }).click();
  assert.equal(await form.locator('[data-role="pledge-amount"]').inputValue(), '3');
  assert.equal(await form.getByRole('button', { name: /^Pledge 3/ }).getAttribute('aria-pressed'), 'true');
  assert.match(await form.locator('textarea[name="body"]').inputValue(), /^pledge: 3$/m);
  await form.locator('textarea[name="body"]').fill('pledge: 3\n\nFrom the wall.');
  await phone.page.route('**/api/**', route => route.request().method() === 'POST' && /create_reply|reply/.test(route.request().url())
    ? route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"Server busy"}' }) : route.continue());
  await form.getByRole('button', { name: 'Post pledge' }).click();
  await phone.page.waitForTimeout(2500);
  assert.match(phone.page.url(), /\/support\/$/, 'a failed post stays on the wall');
  assert.equal(await form.locator('[data-role="pledge-amount"]').inputValue(), '3', 'the amount is kept');
  await phone.page.unroute('**/api/**');
  await form.getByRole('button', { name: 'Post pledge' }).click();
  await phone.page.waitForURL(/\/support\/\?created_post_id=/, { timeout: 20000 });
  // A new key is not approved yet: its 3 squares wait, dashed, after the counted blocks.
  const waiting = await blocks(phone.page);
  assert.equal(waiting.filter(b => b.state === 'pending' && b.squares === 3).length, 1, 'a pending 3-square block');
  assert.equal(await sum(phone.page), '6.5 of 50 squares pledged', 'pending stays out of the total');
  approveAuthorOf('pledge: 3\n\nFrom the wall.');
  await phone.page.goto(base + '/support/');
  assert.equal(await sum(phone.page), '9.5 of 50 squares pledged', 'approved, the pledge counts');
  assert.equal((await blocks(phone.page)).filter(b => b.state === 'counted' && b.squares === 3).length, 2, 'a second counted 3-square block');
  assert.deepEqual(phone.seen.errors, []);

  // Without JavaScript: no presets, the field posts, and the reply lands back on the wall.
  const plain = await open(false, 1280, 900);
  await plain.page.goto(base + '/support/');
  assert.equal(await plain.page.locator('[data-role="pledge-presets"]').count(), 0);
  await plain.page.locator('[data-pledge-form] [data-role="pledge-amount"]').fill('5');
  await plain.page.locator('[data-pledge-form] textarea[name="body"]').fill('No script.');
  await plain.page.locator('[data-pledge-form]').getByRole('button', { name: 'Post pledge' }).click();
  await plain.page.waitForURL(/\/support\/\?created_post_id=/, { timeout: 20000 });
  // A post with no name never counts, so the wall is unchanged; the thread shows why.
  assert.equal(await sum(plain.page), '9.5 of 50 squares pledged');
  await plain.page.goto(base + '/threads/' + thread);
  assert.equal(await plain.page.getByText('Not counted: no name').first().isVisible(), true);
  assert.equal(await plain.page.getByRole('link', { name: 'Open the wall' }).isVisible(), true);

  // Every theme, phone and wide: nothing overflows, squares stay square.
  for (const theme of ['light', 'dark', 'gloss', 'glossdark', 'underground', 'undergrounddark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'bubblegum', 'spearmint', 'engagify', 'ochre']) {
    for (const [width, height] of [[375, 667], [1280, 900]]) {
      const view = await open(true, width, height);
      await view.page.addInitScript(theme => {
        localStorage.setItem('zenmemes-theme', theme);
        for (const key of Object.keys(localStorage)) if (key.endsWith('-theme')) localStorage.setItem(key, theme);
      }, theme);
      await view.page.goto(base + '/support/');
      const applied = await view.page.locator('html').getAttribute('data-theme');
      if (applied !== theme) { console.log(`SKIP ${theme}: not offered on this site (page shows ${applied})`); await view.context.close(); break; }
      assert.ok(await view.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme} ${width}px: no page overflow`);
      const box = await view.page.locator('.support-wall__square').first().boundingBox();
      assert.ok(Math.abs(box.width - box.height) <= 1, `${theme} ${width}px: squares are square`);
      if (shots) await view.page.screenshot({ path: join(shots, `support-${theme}-${width}.png`), fullPage: true });
      await view.context.close();
    }
  }
  console.log('support wall: ok');
} catch (error) {
  console.error(serverLog.slice(-3000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
