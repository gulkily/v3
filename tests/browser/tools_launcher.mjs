// Run with: CHROMIUM_PATH=/usr/bin/google-chrome [FORUM_SITE_ID=chouse] node tests/browser/tools_launcher.mjs [screenshot-dir] [all|theme,theme,...]
// The grouped Tools launcher (ui_tools_launcher): /tools/ lists every tool once under four group headings with theme hooks,
// Account is not a tool, and each tool page's Tools bar lists the same tools in the same order with the current one marked.
// In every permitted theme at phone and desktop width: no overflow, no page errors, icons in Word97, Chicago and Midnight.
import assert from 'node:assert/strict';
import { mkdtemp, mkdir } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const site = process.env.FORUM_SITE_ID || 'zenmemes';
const php = code => JSON.parse(spawnSync('php', ['-r', `require '${project}/autoload.php'; echo json_encode(${code});`], { encoding: 'utf8' }).stdout);
const permitted = php(`ForumRewrite\\SiteProfileRegistry::all()['${site}']['permittedThemes']`);
const themes = !process.argv[3] || process.argv[3] === 'all' ? permitted : process.argv[3].split(',');
const registry = php('ForumRewrite\\Tools\\ToolsPageSupport::registry()');
const groups = php('ForumRewrite\\Tools\\ToolsPageSupport::groups()');
const themeKey = php(`ForumRewrite\\BrowserRuntimeProfile::fromProfile(ForumRewrite\\SiteProfileRegistry::all()['${site}'])['themeStorageKey']`);
const iconThemes = ['word97', 'chicago', 'midnight', 'engagify'];
// Tool pages that render their Tools bar for a visitor who isn't signed in.
const barPages = registry.filter(tool => !tool.standalone && !['user-access', 'visitor-statistics', 'llm-exchanges'].includes(tool.key));
if (shots) await mkdir(shots, { recursive: true });

runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'alice' }], format: 'armored' });
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'tools-launcher-'));
const env = { ...process.env, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php') };
const seeded = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], {
  input: JSON.stringify({ profiles: [{ name: 'alice', fingerprint, publicKey: keys.publicKey }], incoming: 'unused', outgoing: 'unused' }), encoding: 'utf8', env,
});
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1');
await once(socket, 'listening');
const port = socket.address().port;
await new Promise(done => socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')], {
  env: { ...env, PRIVATE_MESSAGE_TEST_ROOT: root, PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') },
  stdio: ['ignore', 'ignore', 'pipe'],
});
let serverLog = '';
server.stderr.on('data', data => { serverLog += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open(theme, width) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
  await context.addInitScript(({ theme, themeKey }) => { localStorage.setItem(themeKey, theme); }, { theme, themeKey });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.setDefaultTimeout(15000);
  return { context, page, errors };
}
const noOverflow = page => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1);
const iconOf = locator => locator.evaluate(node => {
  const style = getComputedStyle(node, '::before');
  return { image: style.backgroundImage, width: parseFloat(style.width) || 0 };
});

try {
  for (let attempt = 0; attempt < 80; attempt++) {
    try { await fetch(base + '/api/version'); break; } catch { await new Promise(done => setTimeout(done, 100)); }
  }

  // Order and current marking on every tool page that shows the bar.
  const expected = registry.map(tool => tool.href);
  {
    const { context, page, errors } = await open(themes[0], 1280);
    for (const tool of barPages) {
      await page.goto(base + tool.href);
      const bar = page.locator('.tools-nav');
      assert.equal(await bar.count(), 1, `${tool.href}: one Tools bar`);
      const hrefs = await bar.locator('a').evaluateAll(links => links.map(link => link.getAttribute('href')));
      assert.deepEqual(hrefs, expected, `${tool.href}: bar order`);
      assert.equal(await bar.locator('.tools-nav-divider').count(), Object.keys(groups).length - 1, `${tool.href}: a divider between groups`);
      assert.deepEqual(await bar.locator('a.is-active').evaluateAll(links => links.map(link => link.getAttribute('href'))), [tool.href], `${tool.href}: current tool marked`);
      assert.equal(await page.locator('a[href="/account/key/"][data-tone]').count(), 0, `${tool.href}: Account is not a tool`);
    }
    assert.deepEqual(errors, []);
    await context.close();
  }

  let cases = 0;
  for (const width of [390, 1280]) for (const theme of themes) {
    const label = `${site} ${theme} ${width}px`;
    const { context, page, errors } = await open(theme, width);

    await page.goto(base + '/tools/');
    await page.evaluate(() => document.fonts.ready);
    if (theme !== 'auto') assert.equal(await page.evaluate(() => document.documentElement.dataset.theme), theme, `${label}: theme applied`);
    assert.equal(await page.locator('.tools-nav').count(), 0, `${label}: no Tools bar on /tools/`);
    const headings = await page.locator('.tool-launcher-group-heading').evaluateAll(nodes => nodes.map(node => [node.dataset.shape, node.textContent.trim()]));
    assert.deepEqual(headings, Object.entries(groups), `${label}: group headings and hooks in order`);
    if (iconThemes.includes(theme)) {
      for (const [shape] of Object.entries(groups)) {
        const icon = await iconOf(page.locator(`.tool-launcher-group-heading[data-shape="${shape}"]`));
        assert.ok(icon.image.includes('data:image/svg+xml') && icon.width > 0, `${label}: ${shape} group heading icon ${JSON.stringify(icon)}`);
      }
    }
    const listed = await page.locator('.tool-launcher-button').evaluateAll(links => links.map(link => link.getAttribute('href')));
    assert.deepEqual(listed, expected, `${label}: every tool once, in group order`);
    assert.equal(await page.locator('.tool-launcher-button[href="/account/key/"]').count(), 0, `${label}: no Account tile`);
    for (const tool of registry) {
      const item = page.locator(`.tool-launcher-item[data-tone="${tool.tone}"]`);
      assert.equal(await item.count(), 1, `${label}: ${tool.key} tone`);
      assert.equal(await item.locator('.tool-launcher-description').count(), 0, `${label}: ${tool.key} has no description text`);
      if (iconThemes.includes(theme)) {
        const icon = await iconOf(item.locator('.tool-launcher-button'));
        assert.ok(icon.image.includes('data:image/svg+xml') && icon.width > 0, `${label}: ${tool.key} launcher icon ${JSON.stringify(icon)}`);
      }
    }
    // Labels stay whole: no button is narrower than its own text.
    const clipped = await page.locator('.tool-launcher-button').evaluateAll(links => links.filter(link => link.scrollWidth > link.clientWidth + 1).map(link => link.textContent.trim()));
    assert.deepEqual(clipped, [], `${label}: clipped labels`);
    assert.equal(await noOverflow(page), true, `${label}: /tools/ overflow`);
    if (shots) await page.screenshot({ path: join(shots, `tools-${site}-${theme}-${width}.png`), fullPage: true });

    await page.goto(base + '/tools/bookmarklets/');
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await noOverflow(page), true, `${label}: Tools bar overflow`);
    if (iconThemes.includes(theme)) {
      for (const tool of registry) {
        const icon = await iconOf(page.locator(`.tools-nav a[href="${tool.href}"]`));
        assert.ok(icon.image.includes('data:image/svg+xml') && icon.width > 0, `${label}: ${tool.key} bar icon ${JSON.stringify(icon)}`);
      }
    }
    if (shots) await page.screenshot({ path: join(shots, `bar-${site}-${theme}-${width}.png`) });

    assert.deepEqual(errors, [], `${label}: page errors`);
    await context.close();
    cases++;
  }
  console.log(`PASS tools launcher (${site}): ${barPages.length} tool bars in order with the current tool marked; ${cases} theme/width cases of /tools/ and the Tools bar (groups and hooks, each tool once, no Account, no description text, whole labels, no overflow, icons on tools and group headings in ${iconThemes.join('/')})`);
} catch (error) {
  console.error(serverLog.slice(-3000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
