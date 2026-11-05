// Run with: CHROMIUM_PATH=/usr/bin/google-chrome [PLAYWRIGHT_MODULE=/path/to/playwright-core/index.mjs] node tests/browser/board_search.mjs [screenshot-dir] [all|theme,theme,...]
// Board search (board_thread_search one-shot) through the real FrontController: the app account menu links Search,
// the native form submits without JavaScript, results page with shared Previous/Next links, and the empty and
// invalid-position states. Every permitted zenmemes theme at phone and desktop width: no overflow, no page errors,
// and the Search icon in Word97, Chicago and Midnight.
import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const php = code => JSON.parse(spawnSync('php', ['-r', `require '${project}/autoload.php'; echo json_encode(${code});`], { encoding: 'utf8' }).stdout);
const permitted = php(`ForumRewrite\\SiteProfileRegistry::all()['zenmemes']['permittedThemes']`);
const themes = !process.argv[3] || process.argv[3] === 'all' ? permitted : process.argv[3].split(',');
const themeKey = php(`ForumRewrite\\BrowserRuntimeProfile::fromProfile(ForumRewrite\\SiteProfileRegistry::all()['zenmemes'])['themeStorageKey']`);
const iconThemes = ['word97', 'chicago', 'midnight'];
if (shots) await mkdir(shots, { recursive: true });

const root = await mkdtemp(join(tmpdir(), 'board-search-'));
const repository = join(root, 'repository');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
for (let i = 0; i < 30; i++) {
  const id = `search-${String(i).padStart(3, '0')}`;
  const title = i === 0 ? `Needle ${'with a very long unbroken title '.repeat(3)}${'x'.repeat(120)}` : `Search fixture ${i}`;
  await writeFile(join(repository, 'records/posts', `${id}.txt`),
    `Post-ID: ${id}\nCreated-At: 2026-09-${String(1 + (i % 28)).padStart(2, '0')}T12:${String(i).padStart(2, '0')}:00Z\nBoard-Tags: general\nSubject: ${title}\n\nA body that mentions the needle.\n`);
}
for (const command of [['init'], ['add', '.'], ['-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-qm', 'fixture']]) {
  assert.equal(spawnSync('git', command, { cwd: repository }).status, 0, command.join(' '));
}
await mkdir(join(root, 'state/private'), { recursive: true });
await mkdir(join(root, 'sessions'), { recursive: true });
await symlink(join(project, 'templates'), join(root, 'templates'));
await symlink(join(project, 'public'), join(root, 'public'));
const built = spawnSync('php', ['-r', `require '${project}/autoload.php'; (new ForumRewrite\\ReadModel\\ReadModelBuilder($argv[1], $argv[2], new ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[1])))->rebuild();`,
  repository, join(root, 'read.sqlite3')], { encoding: 'utf8' });
assert.equal(built.status, 0, built.stderr + built.stdout);

const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'unused-secrets.php'), PRIVATE_MESSAGE_TEST_ROOT: root,
  PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')],
  { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = '';
server.stderr.on('data', data => { serverLog += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open(theme, width, javaScriptEnabled = true) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce', javaScriptEnabled });
  await context.addInitScript(({ theme, themeKey }) => { localStorage.setItem(themeKey, theme); }, { theme, themeKey });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.setDefaultTimeout(15000);
  return { context, page, errors };
}
const noOverflow = page => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1);
const rows = page => page.locator('.board-search .thread-row').count();

try {
  for (let attempt = 0; attempt < 80; attempt++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(done => setTimeout(done, 100)); }
  }
  let checks = 0;
  for (const theme of themes) {
    for (const width of [390, 1280]) {
      const label = `${theme} ${width}px`;
      const { context, page, errors } = await open(theme, width);
      await page.goto(base + '/');
      // Desktop shows Search in the bar; phones list it in More.
      if (width < 640) await page.locator('.account-menu__trigger').click();
      const link = page.locator(width < 640 ? '.account-menu__more a[href="/search"]' : 'nav.nav a[href="/search"]');
      assert.equal(await link.isVisible(), true, `${label}: Search in the ${width < 640 ? 'More panel' : 'bar'}`);
      if (shots) await page.screenshot({ path: join(shots, `${theme}-${width}-menu.png`) });
      if (iconThemes.includes(theme)) {
        const icon = await link.evaluate(node => getComputedStyle(node, '::before').backgroundImage);
        assert.match(icon, /^url\(/, `${label}: Search icon`);
      }
      await link.click();
      await page.waitForURL(base + '/search');
      assert.equal(await page.locator('.board-search input[name="q"]').isVisible(), true, `${label}: field`);
      assert.equal(await page.locator('.board-search button[type="submit"]').isVisible(), true, `${label}: button`);
      assert.equal(await link.evaluate(node => node.classList.contains('is-active')), true, `${label}: active section`);
      await page.locator('.board-search input[name="q"]').fill('NEEDLE');
      await page.locator('.board-search input[name="q"]').press('Enter');
      await page.waitForURL(/\/search\?q=NEEDLE$/);
      assert.equal(await rows(page), 25, `${label}: first page`);
      assert.equal(await noOverflow(page), true, `${label}: results overflow`);
      if (shots) await page.screenshot({ path: join(shots, `${theme}-${width}-results.png`), fullPage: true });
      await page.locator('.board-search a[rel="next"]').click();
      await page.waitForURL(/position=/);
      assert.equal(await rows(page), 5, `${label}: second page`);
      assert.equal(await page.locator('.board-search a[rel="next"]').count(), 0, `${label}: last page`);
      await page.locator('.board-search a[rel="prev"]').click();
      await page.waitForURL(/position=/);
      assert.equal(await rows(page), 25, `${label}: back to newer`);
      await page.goto(base + '/search?q=' + encodeURIComponent('absent '.repeat(40)));
      assert.equal(await page.getByRole('heading', { name: 'No matching threads' }).isVisible(), true, `${label}: empty state`);
      assert.equal(await noOverflow(page), true, `${label}: long query overflow`);
      await page.goto(base + '/search?q=needle&position=bad');
      assert.equal(await page.getByRole('alert').isVisible(), true, `${label}: invalid position`);
      await page.getByRole('link', { name: 'First page' }).click();
      await page.waitForURL(/\/search\?q=needle$/);
      assert.equal(await rows(page), 25, `${label}: restart`);
      if (shots) await page.screenshot({ path: join(shots, `${theme}-${width}-restart.png`) });
      assert.deepEqual(errors, [], `${label}: page errors`);
      await context.close();
      checks++;
    }
  }
  const { context, page } = await open('auto', 390, false);
  await page.goto(base + '/search');
  assert.equal(await page.locator('.board-search input[name="q"]').isVisible(), true, 'no-script field');
  await page.locator('.board-search input[name="q"]').fill('needle');
  await page.locator('.board-search button[type="submit"]').click();
  await page.waitForURL(/\/search\?q=needle$/);
  assert.equal(await rows(page), 25, 'no-script results');
  await page.goto(base + '/users/');
  assert.equal(await page.locator('[data-users-search]').count() === 0 || !(await page.locator('[data-users-search]').isVisible()), true, 'no-script Users filter stays hidden');
  await context.close();
  console.log(`board search: ${checks} theme/width checks and the no-script flow passed`);
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
