// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/backlog.mjs [screenshot-dir]
// Tools → Backlog (backlog_view): tick two items, Start, copy the prompt, with JavaScript and
// without it; the list and the start page fit a 375 px phone and a desktop in every permitted theme.
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
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
const php = code => JSON.parse(spawnSync('php', ['-r', `require '${project}/autoload.php'; echo json_encode(${code});`], { encoding: 'utf8' }).stdout);
const themes = php(`ForumRewrite\\SiteProfileRegistry::all()['zenmemes']['permittedThemes']`).filter(theme => theme !== 'auto');
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'backlog-'));
// The backlog reads the checkout's own items; the test site gets a small set of its own.
const item = (id, title, kind, workflow, extra = '') =>
  `ID: ${id}\nTitle: ${title}\nKind: ${kind}\nWorkflow: ${workflow}\nCreated: 2026-11-0${id.slice(-1)}\n${extra}\nRequest:\nPlease.\n\nSources:\n- Session: browser check\n`;
const items = {
  'PF-20261101-F01': item('PF-20261101-F01', 'A feature with a long title that has to wrap on a small phone without pushing the page sideways', 'feature', 'ready', 'Estimate: 0.15\nImpact: 0.6\n'),
  'PF-20261101-B02': item('PF-20261101-B02', 'A bug', 'bug', 'untriaged'),
  'PF-20261101-F03': item('PF-20261101-F03', 'Averyveryverylongunbrokenwordthatcannotwrapunlessthepageletsitbreakanywhere', 'feature', 'untriaged'),
  'PF-20261101-F04': item('PF-20261101-F04', 'Waiting on review', 'feature', 'blocked'),
  'PF-20261101-F05': item('PF-20261101-F05', 'Finished', 'feature', 'done'),
};
await mkdir(join(root, 'docs/feedback/items'), { recursive: true });
for (const [id, text] of Object.entries(items)) await writeFile(join(root, 'docs/feedback/items', id + '.txt'), text);
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root, FORUM_BACKLOG_ENABLED: 'true',
  // Permitted themes that sit behind a flag, so the theme matrix can apply them.
  FORUM_ZALGO_THEME_ENABLED: '1', FORUM_VAPORGEIST_THEME_ENABLED: '1',
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 375, theme = null) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height: 720 }, permissions: ['clipboard-read', 'clipboard-write'] });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  if (theme) await page.addInitScript(theme => { localStorage.setItem('zenmemes-theme', theme); }, theme);
  return { context, page, errors };
};
const fitsWidth = page => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
const tidy = '/backlog/start?items=PF-20261101-F01,PF-20261101-B02';
// The Start bar is pinned to the bottom of the view, so bring a row to the middle before ticking it.
const tick = async (page, label, options = {}) => {
  const box = page.getByLabel(label, options);
  await box.evaluate(node => node.scrollIntoView({ block: 'center' }));
  await box.check();
};
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // With JavaScript: Tools → Backlog, tick two, Start, Copy.
  const writer = await open(true);
  await writer.page.goto(base + '/tools/');
  await writer.page.locator('.tool-launcher-button[href="/backlog"]').click();
  await writer.page.waitForURL(base + '/backlog');
  assert.equal(await writer.page.locator('.backlog-row').count(), 3, 'ready and untriaged items; blocked and done stay out');
  assert.equal(await writer.page.locator('.backlog-row').first().getAttribute('data-workflow'), 'ready', 'the item with the best impact for its effort leads');
  assert.equal(await writer.page.locator('[data-backlog-count]').isVisible(), false, 'no count until something is ticked');
  await tick(writer.page, 'A feature with a long title');
  await tick(writer.page, 'A bug', { exact: true });
  assert.equal(await writer.page.locator('[data-backlog-count]').textContent(), '2 selected');
  await writer.page.getByRole('button', { name: 'Start' }).click();
  await writer.page.waitForURL(base + tidy);
  const prompt = await writer.page.locator('[data-copy-prompt-source]').textContent();
  assert.match(prompt, /^Please work on these 2 v3 backlog items together as one Railspike cycle, one-shot or staged as AGENTS\.md says\./);
  assert.equal(await writer.page.locator('[aria-current="true"]', { hasText: 'Agent decides' }).count(), 1, 'the agent decides by default');
  assert.match(prompt, /- docs\/agents\/backlog_to_pr\.md\n- docs\/feedback\/items\/PF-20261101-F01\.txt\n- docs\/feedback\/items\/PF-20261101-B02\.txt\n/);
  assert.match(prompt, /\n\nWork in a clone of the repository and open the pull request yourself\. Please don't give me patch files or pasted code\. If you can't clone or push, stop before writing code and tell me what to connect\.\n\nRead first:\n/);
  assert.match(prompt, /\nBegin now\. If the items don't belong in one pull request, say so before you build\. /);
  await writer.page.getByRole('button', { name: 'Copy prompt' }).click();
  await writer.page.getByRole('button', { name: 'Copied' }).waitFor();
  assert.equal(await writer.page.evaluate(() => navigator.clipboard.readText()), prompt, 'the clipboard holds the prompt');
  // One-shot and Staged are links to the same items in the other modes.
  await writer.page.getByRole('link', { name: 'One-shot' }).click();
  await writer.page.waitForURL(base + tidy + '&mode=one-shot');
  assert.match(await writer.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on these 2 v3 backlog items together as one Railspike one-shot cycle\./);
  await writer.page.getByRole('link', { name: 'Staged cycle' }).click();
  await writer.page.waitForURL(base + tidy + '&mode=staged');
  assert.match(await writer.page.locator('[data-copy-prompt-source]').textContent(), /as one staged Railspike cycle\./);
  // Sorting is a link with readable words; the active sort flips its order.
  await writer.page.goto(base + '/backlog');
  await writer.page.getByRole('link', { name: 'Entered' }).click();
  await writer.page.waitForURL(base + '/backlog?sort=entered');
  assert.equal(await writer.page.locator('.backlog-row__id').first().textContent(), 'PF-20261101-F03');
  await writer.page.getByRole('link', { name: /^Entered/ }).click();
  await writer.page.waitForURL(base + '/backlog?sort=entered&order=oldest');
  assert.equal(await writer.page.locator('.backlog-row__id').first().textContent(), 'PF-20261101-F01');
  await writer.page.getByRole('link', { name: 'Waiting on you' }).click();
  await writer.page.waitForURL(base + '/backlog?sort=entered&order=oldest&show=waiting');
  assert.deepEqual(await writer.page.locator('.backlog-row__id').allTextContents(), ['PF-20261101-F04']);
  assert.deepEqual(writer.errors, []);
  await writer.context.close();

  // Without JavaScript: the plain form lands on the same tidy address, and the prompt is selectable text.
  const plain = await open(false);
  await plain.page.goto(base + '/backlog');
  await tick(plain.page, 'A feature with a long title');
  await tick(plain.page, 'A bug', { exact: true });
  await plain.page.getByRole('button', { name: 'Start' }).click();
  await plain.page.waitForURL(base + tidy);
  assert.match(await plain.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on these 2 v3 backlog items together/);
  assert.equal(await plain.page.locator('[data-copy-prompt-button]').isVisible(), false, 'no script: no Copy button; the text is there to select');
  // Start with nothing ticked returns to the list.
  await plain.page.goto(base + '/backlog');
  await plain.page.getByRole('button', { name: 'Start' }).click();
  await plain.page.waitForURL(base + '/backlog');
  // A closed item and an unknown one are named and left out.
  await plain.page.goto(base + '/backlog/start?items=PF-20261101-B02,PF-20261101-F05,PF-20261101-F99');
  assert.match(await plain.page.locator('main').textContent(), /Left out, no such item: PF-20261101-F99/);
  assert.match(await plain.page.locator('main').textContent(), /Left out, already closed: PF-20261101-F05/);
  assert.match(await plain.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on this v3 backlog item as one/);
  await plain.context.close();

  // Agents: the same list and prompt as plain text.
  const list = await fetch(base + '/backlog.txt?sort=entered&order=oldest');
  assert.equal(list.status, 200);
  assert.match(list.headers.get('content-type'), /^text\/plain/);
  const listText = await list.text();
  assert.match(listText, /^Sort: entered\nOrder: oldest\nShow: can-start\nKind: all\nCount: 3\n/);
  assert.deepEqual([...listText.matchAll(/^ID: (\S+)$/gm)].map(m => m[1]), ['PF-20261101-F01', 'PF-20261101-B02', 'PF-20261101-F03']);
  assert.match(listText, /ID: PF-20261101-F01\n[^]*?Estimate: 0\.15\nImpact: 0\.6\nFile: docs\/feedback\/items\/PF-20261101-F01\.txt\n/);
  const started = await fetch(base + '/backlog/start.txt?items=PF-20261101-F01,PF-20261101-F05,PF-20261101-F99');
  assert.equal(started.status, 200);
  assert.equal(started.headers.get('x-left-out'), 'PF-20261101-F99,PF-20261101-F05');
  assert.match(await started.text(), /^Please work on this v3 backlog item as one Railspike cycle, one-shot or staged as AGENTS\.md says\.\n[^]*- docs\/feedback\/items\/PF-20261101-F01\.txt\n/);
  assert.equal((await fetch(base + '/backlog/start.txt?items=PF-20261101-F05')).status, 404);
  assert.equal((await fetch(base + '/backlog/start.txt')).status, 400);

  // Every permitted theme, phone and desktop, scripts on and off: nothing pushes the page sideways.
  let cases = 0;
  for (const theme of themes) for (const width of [375, 1280]) for (const scripts of [true, false]) {
    const viewer = await open(scripts, width, scripts ? theme : null);
    for (const path of ['/backlog?show=open', tidy]) {
      await viewer.page.goto(base + path);
      if (scripts) assert.equal(await viewer.page.locator('html').getAttribute('data-theme'), theme, `${theme}: theme applied`);
      assert.equal(await fitsWidth(viewer.page), true, `${theme} ${width}px scripts ${scripts} ${path}: fits`);
      if (path !== tidy) {
        // The pinned Start bar covers rows, so it needs a solid background even where --panel is see-through.
        const color = await viewer.page.locator('.backlog-start-bar').evaluate(node => getComputedStyle(node).backgroundColor);
        assert.match(color, /^rgb\(/, `${theme} ${width}px scripts ${scripts}: Start bar is opaque (${color})`);
      }
      if (shots && scripts && ['gloss', 'word97', 'bubblegum', 'midnight', 'arena', 'vapor'].includes(theme)) {
        await viewer.page.screenshot({ path: join(shots, `backlog-${theme}-${width}-${path.includes('start') ? 'start' : 'list'}.png`) });
      }
      cases++;
    }
    assert.deepEqual(viewer.errors, [], `${theme} ${width}px: no page errors`);
    await viewer.context.close();
  }
  console.log(`PASS backlog: tick two, Start, Copy, One-shot and Staged with JavaScript (Agent decides by default); plain form to the tidy address without; sort, order and filter links; closed and unknown items left out; /backlog.txt and /backlog/start.txt; ${cases} theme/width/script cases of the list and the start page without sideways overflow`);
} catch (error) {
  console.error(serverLog.split('\n').filter(line => /error|warning|fatal/i.test(line)).slice(-10).join('\n'));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
