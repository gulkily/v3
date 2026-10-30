import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'profile-public-browser-'));
const env = { ...process.env, FORUM_STATIC_PROFILE_PAGES_ENABLED: 'true', FORUM_SITE_ID: 'zenmemes', FORUM_APPROVED_MEMBERS_ONLY: 'false', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/profile_public_details_fixture.php'), root], { env, encoding: 'utf8' });
assert.equal(seed.status, 0, seed.stderr);
const { themes, slug, key } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', data => { logs += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const javaScriptEnabled of [false, true]) {
    const context = await browser.newContext({ javaScriptEnabled, serviceWorkers: 'block', viewport: { width: 390, height: 900 } });
    const page = await context.newPage();
    const initialResponse = await page.goto(base + '/profiles/' + slug);
    assert.ok((await initialResponse.text()).includes('PROFILE STATIC FIRST'), 'anonymous first page uses static artifact');
    assert.equal(await page.locator('main a[href^="/user/"]').count(), 0);
    assert.equal(await page.locator('#account-keys').getByRole('link', { name: 'Approval details', exact: true }).count(), 1);
    const details = page.locator('[data-public-key-details]');
    assert.equal(await details.getAttribute('open'), null);
    await details.locator('summary').focus(); await page.keyboard.press('Enter');
    assert.equal(await details.evaluate(n => n.open), true);
    assert.equal(await details.locator('pre').textContent(), key);
    if (javaScriptEnabled) {
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {writeText: async text => {window.copiedKey = text;}} }));
      await details.locator('button').click();
      assert.equal(await page.evaluate(() => window.copiedKey), key);
      await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: {writeText: async () => {throw new Error('denied');}} }));
      await details.locator('button').click();
      assert.equal(await details.locator('[role=status]').isVisible(), true);
      assert.match(await details.locator('[role=status]').textContent(), /manually/);
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    const [contributionResponse] = await Promise.all([
      page.waitForNavigation(), page.locator('#threads h3 a[href^="/threads/profile-page-"]').first().click()
    ]);
    assert.equal(contributionResponse.status(), 200);
    assert.match(new URL(page.url()).pathname, /^\/threads\/profile-page-/);
    assert.ok(await page.locator(`a[href="/profiles/${slug}"]`).count() > 0);
    await page.locator(`a[href="/profiles/${slug}"]`).first().click();
    await page.waitForURL('**/profiles/' + slug);
    const threads = page.locator('#threads');
    const posts = page.locator('#posts');
    const firstLinks = await threads.locator('h3 a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
    assert.equal(firstLinks.length, 50);
    await threads.getByRole('link', {name: 'Next', exact: true}).click();
    await page.waitForURL(/threads_position=/);
    const secondUrl = page.url();
    assert.doesNotMatch(await page.content(), /PROFILE STATIC FIRST/);
    const secondLinks = await threads.locator('h3 a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
    assert.ok(secondLinks.length > 0 && secondLinks.length < 50);
    assert.equal(secondLinks.some(href => firstLinks.includes(href)), false);
    assert.equal(await posts.locator('article.card').count(), 50);
    await posts.getByRole('link', {name: 'Next', exact: true}).click();
    await page.waitForURL(/posts_position=/);
    assert.ok(page.url().includes('threads_position='));
    await page.reload();
    assert.deepEqual(await threads.locator('h3 a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), secondLinks);
    await page.goto(base + '/profiles/' + slug + '?threads_position=bad');
    assert.equal(await threads.getByRole('alert').isVisible(), true);
    await threads.getByRole('link', {name: 'First page', exact: true}).click();
    assert.equal(await threads.locator('h3 a').count(), 50);
    await page.goto(secondUrl);
    await threads.getByRole('link', {name: 'Previous', exact: true}).click();
    assert.deepEqual(await threads.locator('h3 a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), firstLinks);
    await page.route('**/*threads_position*', route => route.fulfill({status: 503, body: 'Temporarily unavailable'}));
    await page.goto(secondUrl);
    assert.equal(page.url(), secondUrl);
    await page.unroute('**/*threads_position*');
    await page.reload();
    assert.deepEqual(await threads.locator('h3 a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), secondLinks);
    await context.close();
  }
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  for (const width of [390, 1280]) for (const theme of themes) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + '/profiles/' + slug + '?self=1');
    await page.evaluate(theme => localStorage.setItem('forum-zenmemes-theme', theme), theme);
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-resolved-theme'), theme);
    const details = page.locator('[data-public-key-details]');
    assert.equal(await details.evaluate(n => n.open), false);
    assert.equal(await page.locator('#threads h3 a').count(), 50);
    await details.locator('summary').click();
    assert.equal(await details.locator('pre').textContent(), key);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${theme} ${width}: expanded overflow`);
    await page.locator('#threads').getByRole('link', {name: 'Next', exact: true}).click();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${theme} ${width}: loaded history overflow`);
    if (['word97', 'chicago', 'midnight'].includes(theme)) {
      await page.locator('[data-public-key-details] summary').click();
      await page.locator('[data-public-key-details]').scrollIntoViewIfNeeded();
      await page.screenshot({path: `/tmp/profile-public-${theme}-${width}.png`});
    }
    await page.goto(base + '/profiles/openpgp-empty-profile');
    assert.equal(await page.getByText('No visible threads.', {exact: true}).count(), 1);
    assert.equal(await page.getByText('No visible posts.', {exact: true}).count(), 1);
    await page.locator('[data-public-key-details] summary').click();
    assert.equal(await page.getByText('Public key unavailable.', {exact: true}).isVisible(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
  }
  const missing = await page.goto(base + '/profiles/openpgp-missing-profile');
  assert.equal(missing.status(), 404);
  assert.deepEqual(errors, []);
  await context.close();
  console.log(`PASS ${themes.length} themes at phone/desktop sizes: expanded key, loaded history, empty/unavailable and missing profile; no page errors`);
  console.log('PASS profile HTTP static/live paging and key: collapsed default, keyboard, exact copy, denied clipboard recovery, no-JS selection and phone overflow');
} finally {
  await browser.close(); server.kill(); await rm(root, { recursive: true, force: true });
}
