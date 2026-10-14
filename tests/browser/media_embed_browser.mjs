import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'media-embed-browser-'));
const env = { ...process.env, MEDIA_EMBED_BROWSER_ROOT: root, FORUM_INSTANCE_CONFIG_FILE: join(root, 'unused.php') };
const fixture = spawnSync('php', [join(project, 'tests/Support/media_embed_browser_fixture.php'), root], { cwd: project, env, encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const { themes } = JSON.parse(fixture.stdout);
let flags = { cards: true, players: true, site: 'mitrapclub' };
const setFlags = async changes => { flags = { ...flags, ...changes }; await writeFile(join(root, 'flags.json'), JSON.stringify(flags)); };
await setFlags({});
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/media_embed_browser_router.php')], { cwd: project, env });
let serverLog = '';
server.stderr.on('data', b => { serverLog += b; });
let browser;
const urls = [
  'https://www.youtube.com/watch?v=M7lc1UVf-VE',
  'https://youtube.com/shorts/qwUM4Sk1_nE',
  'https://open.spotify.com/track/11dFghVXANMlKmJXsNCbNl',
  'https://open.spotify.com/album/1A2GTWGtFfWp7KSQTwWOyo',
  'https://open.spotify.com/playlist/37i9dQZF1DX4JAvHpjipBk',
  'https://www.tiktok.com/@scout2015/video/6718335390845095173',
  'https://instagram.com/p/CachedPreview/',
  'https://instagram.com/reel/FailedPreview/',
];
try {
  for (let i = 0; i < 100; i++) {
    try { if ((await fetch(base + '/api/version')).ok) break; } catch {}
    await new Promise(r => setTimeout(r, 50));
  }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 360, height: 800 } });
  const playerRequests = [];
  await context.route('**/*', async route => {
    const url = route.request().url();
    if (url.startsWith(base)) return route.continue();
    if (route.request().isNavigationRequest()) playerRequests.push(url);
    return route.fulfill({ contentType: 'text/html', body: '<p>Provider content unavailable</p>' });
  });
  async function post(path, data) {
    const response = await context.request.post(base + path, { form: data });
    const body = await response.text();
    assert.equal(response.status(), 200, body);
    return Object.fromEntries(body.trim().split('\n').map(line => line.split('=', 2)));
  }
  const mixed = await post('/api/create_thread', { subject: 'Media browser acceptance', body: urls.join('\n') });
  await post('/api/create_reply', { thread_id: mixed.thread_id, parent_id: mixed.post_id, body: urls.join('\n') });
  const spotify = await post('/api/create_thread', { body: urls[2] });
  const tiktok = await post('/api/create_thread', { body: urls[5] });
  for (const [index, url] of urls.entries()) await post('/api/create_thread', { subject: 'Single media ' + index, body: url });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/threads/' + mixed.thread_id);
  assert.equal(await page.locator('details[data-media-embed-card]').count(), 12);
  assert.equal(await page.locator('.media-embed-card--preview').count(), 2);
  assert.equal(playerRequests.length, 0, 'collapsed players must not navigate');
  assert(await page.locator('[data-provider="instagram"]:not(.media-embed-card--preview)').first().isVisible());
  for (const source of await page.locator('.media-embed-card__source a').all()) assert(await source.isVisible());
  for (const details of await page.locator('details[data-media-embed-card]').all()) {
    await details.locator('summary').focus();
    await page.keyboard.press('Enter');
    await details.locator('iframe').waitFor({ state: 'visible' });
  }
  await page.waitForTimeout(200);
  assert.equal(playerRequests.length, 12);
  for (const iframe of await page.locator('iframe[data-media-embed-iframe]').all()) {
    const box = await iframe.boundingBox();
    assert(box.width <= 360 && box.width > 100, JSON.stringify(box));
  }
  const image = await page.locator('.media-embed-card__thumbnail').first().boundingBox();
  assert(image.width <= 360, `Instagram thumbnail overflow: ${image.width}`);
  for (const id of [spotify.thread_id, tiktok.thread_id]) {
    await page.goto(base + '/threads/' + id);
    assert.equal(await page.locator('details[data-media-embed-card]').count(), 1, 'bare URL body must remain visible');
    assert.equal(await page.locator('[data-media-embed-warm-beacon]').count(), 0);
  }
  await page.goto(base + '/threads/?view=all');
  assert(await page.locator('[data-provider="spotify"]').count() >= 4);
  await page.goto(base + '/forte?view=all&selected=' + mixed.thread_id);
  const forte = page.locator(`[data-paned-board-content-post-id="${mixed.thread_id}"]`);
  assert.equal(await forte.locator('details[data-media-embed-card]').count(), 12);
  await forte.locator('summary').first().click();
  await page.waitForFunction(id => document.querySelector(`[data-paned-board-content-post-id="${id}"] iframe`)?.getAttribute('src'), mixed.thread_id);
  assert(await forte.locator('iframe').first().getAttribute('src'));
  await page.goto(base + '/compose/reply?thread_id=' + mixed.thread_id + '&parent_id=' + mixed.post_id);
  assert(await page.locator('[data-media-embed-card]').count() >= 8, 'reply context should use shared embeds');
  await setFlags({ site: 'qdb' });
  const quote = await post('/api/create_quote', { body: urls.join('\n') });
  await post('/api/create_thread', { subject: 'Media news', board_tags: 'news', body: urls.join('\n') });
  await page.goto(base + '/latest');
  assert.equal(await page.locator(`[data-post-id="${quote.post_id}"] [data-media-embed-card]`).count(), 8);
  await page.goto(base + '/');
  assert.equal(await page.locator('.qdb-news-item [data-media-embed-card]').count(), 8);
  for (const theme of themes) {
    await setFlags({ site: ['chouse', 'qdb', 'mitrapclub'].includes(theme) ? theme : 'mitrapclub' });
    await context.addCookies([{ name: 'theme-hint', value: theme, url: base }]);
    await page.evaluate(theme => {
      for (const site of ['mitrapclub', 'chouse', 'qdb']) localStorage.setItem('forum-' + site + '-theme', theme);
    }, theme);
    for (const width of [360, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(base + '/threads/' + mixed.thread_id);
      await page.waitForFunction(expected => document.documentElement.dataset.resolvedTheme === expected
        && [...document.querySelectorAll('link[data-theme-name]')].some(link => link.dataset.themeName === expected && link.sheet), theme === 'auto' ? 'light' : theme);
      assert.equal(await page.locator('html').getAttribute('data-resolved-theme'), theme === 'auto' ? 'light' : theme);
      await page.locator('details[data-media-embed-card]').evaluateAll(ds => ds.forEach(d => { d.open = true; }));
      for (const iframe of await page.locator('iframe[data-media-embed-iframe]').all()) {
        const box = await iframe.boundingBox();
        assert(box.x >= -1 && box.x + box.width <= width + 1, `${theme} ${width}: ${JSON.stringify(box)}`);
      }
      if (['dark', 'mitrapclub'].includes(theme)) await page.screenshot({ path: join(tmpdir(), `media-embed-${theme}-${width}.png`) });
    }
  }
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({ result: 'passed', themes: themes.length, widths: [360, 1280], sharedViews: ['board', 'thread/root/reply', 'Forte', 'reply context', 'QDB quote/news'], providers: ['youtube', 'instagram', 'spotify', 'tiktok'] }));
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  if (browser) await browser.close();
  server.kill();
  await once(server, 'exit');
  await rm(root, { recursive: true, force: true });
}
