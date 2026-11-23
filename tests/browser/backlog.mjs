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
// A second server over the same site whose profile names a claims thread (the fixture's root-001).
// The first server names none, so everything above the claims section runs as it did before claims.
const claimsSocket = createServer().listen(0, '127.0.0.1'); await once(claimsSocket, 'listening'); const claimsPort = claimsSocket.address().port; await new Promise(r => claimsSocket.close(r));
const claimsBase = `http://127.0.0.1:${claimsPort}`;
const claimsServer = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${claimsPort}`, join(project, 'tests/Support/backlog_claims_browser_router.php')], { env });
claimsServer.stderr.on('data', d => { serverLog += d; });
const seedIdentity = JSON.parse(seeded.stdout).identity;
// Runs PHP against the test site: write a post, approve a key.
const site = (code, ...args) => {
  const done = spawnSync('php', ['-r', `require '${project}/autoload.php'; $root = '${root}';
    $repository = new \\ForumRewrite\\Canonical\\CanonicalRecordRepository($root . '/repository');
    $writer = new \\ForumRewrite\\Write\\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
    $db = new PDO('sqlite:' . $root . '/read.sqlite3'); ${code}`, ...args], { encoding: 'utf8', env });
  assert.equal(done.status, 0, done.stderr + done.stdout);
  return done.stdout;
};
const approve = identity => site(`file_put_contents($root . '/repository/records/approval-seeds/' . str_replace(':', '-', $argv[1]) . '.txt',
  "Approved-Identity-ID: {$argv[1]}\nSeed-Reason: browser check approves this key\n\nApproved so its claims count.\n");
  $db->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?')->execute([$argv[1]]);`, identity);
const post = (body, author = '', parent = 'root-001') => site(`$input = ['thread_id' => 'root-001', 'parent_id' => $argv[2], 'body' => $argv[1]];
  if ($argv[3] !== '') $input['author_identity_id'] = $argv[3];
  echo $writer->createReply($input)['post_id'];`, body, parent, author);
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

  // Claims. The first server names no claims thread: nothing about claims anywhere.
  assert.doesNotMatch(listText, /^Claim/m);
  const before = await open(false);
  await before.page.goto(base + '/backlog');
  assert.equal(await before.page.getByRole('link', { name: 'Hide claimed' }).count(), 0);
  assert.equal(await before.page.locator('[data-role="backlog-claim"]').count(), 0);
  await before.context.close();
  // The second server reads claims from root-001: one counted claim on two items, a guest claim on a third.
  for (let i = 0; i < 60; i++) { try { await fetch(claimsBase + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  approve(seedIdentity);
  const seedClaim = post('claim: PF-20261101-F01, PF-20261101-F03\n\nBranch fix/a-long-branch-name-for-the-wrapping-check.', seedIdentity);
  post('claim: PF-20261101-B02');
  const holders = await open(false);
  await holders.page.goto(claimsBase + '/backlog');
  const claimOf = id => holders.page.locator('.backlog-row', { has: holders.page.locator(`input[value="${id}"]`) }).locator('[data-role="backlog-claim"]');
  assert.match(await claimOf('PF-20261101-F01').textContent(), /^Claimed by \S+$/);
  assert.equal(await claimOf('PF-20261101-B02').textContent(), 'Claimed by a guest · not counted');
  await holders.page.getByRole('link', { name: 'Hide claimed' }).click();
  await holders.page.waitForURL(claimsBase + '/backlog?claimed=hide');
  assert.deepEqual(await holders.page.locator('.backlog-row__id').allTextContents(), ['PF-20261101-B02'], 'counted claims hide their rows; a guest claim does not');
  await holders.page.getByRole('link', { name: 'Hide claimed' }).click();
  await holders.page.waitForURL(claimsBase + '/backlog');
  assert.equal(await holders.page.locator('.backlog-row').count(), 3);
  await holders.context.close();
  const claimedText = await (await fetch(claimsBase + '/backlog.txt')).text();
  assert.match(claimedText, /ID: PF-20261101-F01\n[^]*?File: docs\/feedback\/items\/PF-20261101-F01\.txt\nClaim-State: held\nClaimed-By: \S+\nClaimed-Since: \S+\nClaim-Post: \S+\n/);
  assert.match(claimedText, /ID: PF-20261101-B02\n[^]*?Claim-State: none\nGuest-Claims: 1\n/);

  // Start claims. A member first: posting once gives this browser a key the server recognises; then it is approved.
  const claimPosts = () => Number(site(`echo $db->query("SELECT COUNT(*) FROM posts WHERE body LIKE 'claim:%' AND retracted_at IS NULL")->fetchColumn();`));
  const member = await open(true);
  await member.page.goto(claimsBase + '/compose/thread');
  const compose = member.page.locator('[data-compose-kind="thread"]');
  await compose.getByRole('textbox', { name: 'Title' }).fill('Hello from the member');
  await compose.getByRole('textbox', { name: 'Body' }).fill('A first post, so this browser has a key.');
  await compose.getByRole('button', { name: 'Post', exact: true }).click();
  await member.page.waitForURL(/\/threads\//, { timeout: 30000 });
  approve(site(`$find = $db->prepare('SELECT author_identity_id FROM posts WHERE body LIKE ? ORDER BY created_at DESC LIMIT 1');
    $find->execute(['A first post, so this browser%']); echo $find->fetchColumn();`).trim());
  // Tick the free item and one the seed key holds, press Start: the prompt is there at once, and the claim follows.
  let posts = claimPosts();
  await member.page.goto(claimsBase + '/backlog');
  await tick(member.page, 'A bug', { exact: true });
  await tick(member.page, 'A feature with a long title');
  await member.page.getByRole('button', { name: 'Start' }).click();
  await member.page.waitForURL(claimsBase + '/backlog/start?items=PF-20261101-F01,PF-20261101-B02');
  assert.match(await member.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on these 2 v3 backlog items together/, 'the prompt does not wait for the claim');
  const startClaim = id => member.page.locator(`[data-role="backlog-claim"][data-claim-id="${id}"]`);
  await member.page.locator('[data-claim-id="PF-20261101-B02"]', { hasText: /^Claimed by \S+ since \w+ \d+ · / }).waitFor({ timeout: 60000 });
  assert.match(await startClaim('PF-20261101-B02').textContent(), /^Claimed by \S+ since \w+ \d+ · Claimed by a guest · not counted$/, 'the member holds it; the guest claim is still listed');
  assert.match(await startClaim('PF-20261101-F01').textContent(), /^Claimed by seed since \w+ \d+ · Also claimed by \S+$/, 'held by someone else: Start says who and still claims');
  assert.equal(await member.page.locator('[data-role="backlog-claim-form"]').count(), 0, 'nothing left to claim');
  assert.equal(claimPosts(), posts + 1, 'one claim post for both items');
  assert.match(await member.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on these 2 v3 backlog items together/, 'the prompt is still on the page');
  // A reload claims nothing more, and neither does opening a start address without pressing Start.
  await member.page.reload();
  await member.page.goto(claimsBase + '/backlog/start?items=PF-20261101-F03');
  await member.page.waitForTimeout(1500);
  assert.equal(claimPosts(), posts + 1, 'a reload or a pasted address posts no claim');
  assert.match(await startClaim('PF-20261101-F03').textContent(), /^Claimed by seed since/);
  // There the member has no claim yet, so Claim is offered. A failed post says so and keeps the button; a second press claims.
  const claim = member.page.locator('[data-role="backlog-claim-form"]');
  assert.equal(await claim.getByRole('button', { name: 'Claim' }).isVisible(), true);
  await member.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await claim.getByRole('button', { name: 'Claim' }).click();
  await claim.locator('[data-role="backlog-claim-feedback"]', { hasText: /^Not claimed\./ }).waitFor({ timeout: 30000 });
  assert.equal(await claim.getByRole('button', { name: 'Claim' }).isEnabled(), true);
  assert.equal(claimPosts(), posts + 1);
  await claim.getByRole('button', { name: 'Claim' }).click();
  await member.page.locator('[data-claim-id="PF-20261101-F03"]', { hasText: /Also claimed by/ }).waitFor({ timeout: 60000 });
  assert.equal(claimPosts(), posts + 2);
  assert.deepEqual(member.errors, []);
  // Start pressed while the post cannot be sent: the prompt is there, the page says Not claimed, and Claim is left to press.
  site(`$db->exec("UPDATE posts SET retracted_at = '2026-11-23T00:00:00Z' WHERE body LIKE 'claim: PF-20261101-F03%' AND author_label != 'seed'");`);
  posts = claimPosts();
  await member.page.goto(claimsBase + '/backlog');
  await tick(member.page, 'Averyveryverylong');
  await member.page.route('**/api/prepare_reply', route => route.abort(), { times: 1 });
  await member.page.getByRole('button', { name: 'Start' }).click();
  await member.page.waitForURL(claimsBase + '/backlog/start?items=PF-20261101-F03');
  assert.match(await member.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on this v3 backlog item/);
  await member.page.locator('[data-role="backlog-claim-feedback"]', { hasText: /^Not claimed\./ }).waitFor({ timeout: 30000 });
  assert.equal(claimPosts(), posts);
  const memberCookies = await member.context.cookies();
  await member.context.close();
  // No key: a visitor who never posted gets the prompt, "Not claimed", and no button; nothing is posted.
  const visitor = await open(true);
  await visitor.page.goto(claimsBase + '/backlog?show=open');
  await tick(visitor.page, 'A bug', { exact: true });
  await visitor.page.getByRole('button', { name: 'Start' }).click();
  await visitor.page.waitForURL(claimsBase + '/backlog/start?items=PF-20261101-B02');
  assert.match(await visitor.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on this v3 backlog item/);
  assert.equal(await visitor.page.locator('[data-role="backlog-claim-form"]').count(), 0);
  await visitor.page.waitForTimeout(1500);
  assert.equal(claimPosts(), posts, 'a visitor leaves no claim by pressing Start');
  assert.deepEqual(visitor.errors, []);
  await visitor.context.close();
  // Scripts off, even for the recognised member: the prompt, who holds what, and no Claim button to press.
  // This needs a browser started with scripting off: the test browser's per-page switch stops scripts
  // but still reports scripting as on to CSS, and the form is hidden by a (scripting: none) rule.
  const noScriptBrowser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--blink-settings=scriptEnabled=false'] });
  const scriptless = { context: await noScriptBrowser.newContext({ serviceWorkers: 'block', viewport: { width: 375, height: 720 } }) };
  scriptless.page = await scriptless.context.newPage();
  await scriptless.context.addCookies(memberCookies);
  await scriptless.page.goto(claimsBase + '/backlog/start?items=PF-20261101-F03');
  assert.match(await scriptless.page.locator('[data-copy-prompt-source]').textContent(), /^Please work on this v3 backlog item/);
  assert.match(await scriptless.page.locator('[data-claim-id="PF-20261101-F03"]').textContent(), /^Claimed by seed since/);
  assert.equal(await scriptless.page.locator('[data-role="backlog-claim-form"]').count(), 1, 'the server drew the form for the member');
  assert.equal(await scriptless.page.locator('[data-role="backlog-claim-form"]').isVisible(), false, 'without scripts it is not shown, so no unsigned claim is posted');
  await noScriptBrowser.close();

  // Every permitted theme, phone and desktop, scripts on and off: nothing pushes the page sideways.
  // The matrix runs on the claims server, so every list has holder lines.
  let cases = 0;
  for (const theme of themes) for (const width of [375, 1280]) for (const scripts of [true, false]) {
    const viewer = await open(scripts, width, scripts ? theme : null);
    for (const path of ['/backlog?show=open', tidy]) {
      await viewer.page.goto(claimsBase + path);
      if (path !== tidy) assert.equal(await viewer.page.locator('[data-role="backlog-claim"]').count(), 3, 'holder lines are on the list');
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
  console.log(`PASS backlog: tick two, Start, Copy, One-shot and Staged with JavaScript (Agent decides by default); plain form to the tidy address without; sort, order and filter links; closed and unknown items left out; /backlog.txt and /backlog/start.txt; holder lines, Hide claimed and claim lines in the text list on a site with a claims thread, none on a site without; Start claims in the background with the prompt already shown, once, never on a reload or a pasted address; a failed claim says so and Claim retries; no claim without a key or without scripts; ${cases} theme/width/script cases of the list and the start page without sideways overflow`);
} catch (error) {
  console.error(serverLog.split('\n').filter(line => /error|warning|fatal/i.test(line)).slice(-10).join('\n'));
  throw error;
} finally {
  await browser.close();
  server.kill();
  claimsServer.kill();
}
