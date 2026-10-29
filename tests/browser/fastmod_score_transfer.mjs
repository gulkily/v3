import assert from 'node:assert/strict';
import { mkdtemp, writeFile, readFile, rm, access } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createServer as socketServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'score-transfer-browser-'));
const servers = []; let browser; let providerCalls = 0;
const provider = createServer((req, res) => { providerCalls++; res.writeHead(500); res.end('No scoring expected'); });
await new Promise(resolve => provider.listen(0, '127.0.0.1', resolve));
const common = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_APPROVED_MEMBERS_ONLY: 'false',
  FORUM_SECRETS_PATH: join(root, 'secrets.php'), FAST_SCORING_ENABLED: 'true', FASTMOD_AUTOMATIC_TAGGING_ENABLED: 'true',
  FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED: 'true', LLM_PROVIDER: 'openai', LLM_API_KEY: 'test-only', LLM_MODEL: 'test',
  LLM_API_BASE_URL: `http://127.0.0.1:${provider.address().port}` };
function environment(name) { return { ...common, FORUM_REPOSITORY_ROOT: join(root, name), FORUM_DATABASE_PATH: join(root, name + '-read.sqlite3'),
  FORUM_STATIC_HTML_ROOT: join(root, name + '-static'), FORUM_PUBLIC_ARTIFACT_ROOT: join(root, name + '-public'),
  FORUM_TASK_QUEUE_DATABASE_PATH: join(root, name + '-tasks.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, name + '-visits.sqlite3'),
  FAST_SCORING_DATABASE_PATH: join(root, name + '-scores.sqlite3') }; }
function php(args, env = common) {
  const result = spawnSync('php', args, { encoding: 'utf8', env, timeout: 120000 });
  assert.equal(result.status, 0, result.stdout + result.stderr); return result.stdout;
}
async function serve(name) {
  const socket = socketServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const server = spawn('php', ['-S', `127.0.0.1:${port}`, join(project, 'public/router.php')], { env: environment(name), stdio: ['ignore', 'ignore', 'pipe'] });
  let errors = ''; server.stderr.on('data', chunk => { errors += chunk; }); servers.push(server);
  const url = `http://127.0.0.1:${port}`;
  for (let i = 0; i < 100; i++) { try { const response = await fetch(url + '/api/version'); if (response.ok) return url; } catch {} await new Promise(resolve => setTimeout(resolve, 50)); }
  throw new Error(errors);
}
try {
  await writeFile(common.FORUM_SECRETS_PATH, '<?php return [];');
  const original = JSON.parse(php([join(project, 'tests/Support/fastmod_score_transfer_fixture.php'), root]));
  const source = await serve('source');
  const cli = (...args) => php([join(project, 'scripts/import_instance.php'), ...args], environment('target'));
  assert.match(cli(source, '--dry-run'), /scores_eligible: 1/);
  await assert.rejects(access(join(root, 'target-scores.sqlite3')));
  assert.match(cli(source), /scores_imported: 1/);
  const inspect = () => JSON.parse(php([join(project, 'tests/Support/fastmod_score_transfer_fixture.php'), root, 'inspect']));
  const initial = inspect(); assert.equal(initial.scores.length, 1); assert.equal(initial.jobs, 0); assert.match(initial.labels, /meta/);
  assert.equal(initial.scores[0].updated_at, original.updated_at);
  assert.match(cli(source), /scores_existing: 1/); assert.deepEqual(inspect(), initial);
  const target = await serve('target');
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  for (const javaScriptEnabled of [true, false]) {
    const context = await browser.newContext({ javaScriptEnabled, serviceWorkers: 'block' }); const page = await context.newPage();
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(target + '/tags/meta');
      assert.equal(await page.getByRole('link', { name: 'Portable score example', exact: true }).isVisible(), true);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      await page.goto(target + '/forte/?tag=meta&selected=post-1');
      assert.equal(await page.locator('[data-paned-thread-id="post-1"]').isVisible(), true);
      assert.match(await page.locator('[data-paned-thread-id="post-1"]').getAttribute('data-paned-thread-tags'), /meta/);
      await page.goto(target + '/posts/post-1'); assert.match(await page.locator('body').innerText(), /Meta: 0.99/);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      await page.screenshot({ path: `/tmp/score-transfer-${width}-${javaScriptEnabled}.png`, fullPage: true });
    }
    const plain = await context.request.get(target + '/api/get_thread?thread_id=post-1'); assert.match(await plain.text(), /^Labels: meta$/m);
    await context.close();
  }
  php([join(project, 'scripts/build_static_artifacts.php'), join(root, 'target'), join(root, 'target-read.sqlite3'), join(root, 'target-static')], environment('target'));
  assert.match(await readFile(join(root, 'target-static/current/tags/meta.html'), 'utf8'), /Portable score example/);
  assert.equal(providerCalls, 0);
  console.log('PASS normal HTTP export → CLI preview/import/repeat → live/Forte/text/offline/static tags; phone/desktop, JS on/off; zero provider calls');
} finally {
  if (browser) await browser.close();
  for (const server of servers) { server.kill('SIGTERM'); await once(server, 'exit'); }
  await new Promise(resolve => provider.close(resolve));
  await rm(root, { recursive: true, force: true });
}
