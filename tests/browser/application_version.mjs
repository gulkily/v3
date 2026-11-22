// Run with: node tests/browser/application_version.mjs
import assert from 'node:assert/strict';
import { cpSync, mkdtempSync, mkdirSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = mkdtempSync(join(tmpdir(), 'application-version-'));
const code = join(root, 'code'), repository = join(root, 'repository');
mkdirSync(code);
symlinkSync(join(project, 'templates'), join(code, 'templates'));
symlinkSync(join(project, 'public'), join(code, 'public'));
cpSync(join(project, 'tests/fixtures/parity_minimal_v1'), repository, { recursive: true });
const git = (path, ...args) => {
  const result = spawnSync('git', ['-C', path, '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', '-c', 'commit.gpgsign=false', ...args], { encoding:'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return result.stdout.trim();
};
for (const path of [code, repository]) {
  git(path, 'init'); git(path, 'add', '.'); git(path, 'commit', '--allow-empty', '-m', 'Initial');
}
const router = join(root, 'router.php');
writeFileSync(router, `<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/') || $path === '/favicon.ico' || $path === '/service_worker.js') return require getenv('VERSION_TEST_PROJECT') . '/public/router.php';
require getenv('VERSION_TEST_PROJECT') . '/autoload.php';
(new ForumRewrite\\Application(getenv('VERSION_TEST_CODE'), getenv('FORUM_REPOSITORY_ROOT'), getenv('FORUM_DATABASE_PATH')))->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
`);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(done=>socket.close(done));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(project, 'public'), router], {
  env:{...process.env, VERSION_TEST_PROJECT:project, VERSION_TEST_CODE:code, FORUM_REPOSITORY_ROOT:repository,
    FORUM_DATABASE_PATH:join(root, 'read.sqlite3'), FORUM_STATIC_HTML_ROOT:join(root, 'static'),
    FORUM_TASK_QUEUE_DATABASE_PATH:join(root, 'tasks.sqlite3'), FORUM_SECRETS_PATH:join(root, 'absent.php'),
    FORUM_APP_VERSION_NOTIFICATION:'true', FORUM_NAV_CACHED_COPY_ENABLED:'false', FORUM_FAST_NAVIGATION_ENABLED:'false', FAST_SCORING_ENABLED:'false',
    FORUM_APPROVED_MEMBERS_ONLY:'false', FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED:'false'}, stdio:'ignore'
});
let browser;
try {
  for (let n=0; n<100; n++) { try { if ((await fetch(base+'/api/version')).ok) break; } catch {} await new Promise(done=>setTimeout(done,50)); }
  browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless:true, args:['--no-sandbox']});
  for (const viewport of [{width:1280,height:800},{width:390,height:844}]) {
    const context = await browser.newContext({viewport, colorScheme:viewport.width < 600 ? 'dark' : 'light'});
    const page = await context.newPage(), errors = [];
    page.on('pageerror', error=>errors.push(error.message));
    await page.goto(base+'/');
    const banner = page.locator('[data-role="app-version-banner"]');
    const first = await page.getAttribute('meta[name="app-version"]','content');
    assert.equal(first, git(code, 'rev-parse', 'HEAD'));
    await assert.rejects(banner.waitFor({state:'visible',timeout:250}));
    git(repository, 'commit', '--allow-empty', '-m', 'New content');
    const poll = async () => {
      await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
      await page.waitForTimeout(300);
    };
    await poll(); assert.equal(await banner.isVisible(), false, 'content does not announce a deploy');
    await page.route('**/api/version?*', route=>route.abort());
    await poll(); assert.equal(await banner.isVisible(), false, 'failed poll keeps current page');
    git(code, 'commit', '--allow-empty', '-m', 'Deploy code');
    const latest = git(code, 'rev-parse', 'HEAD');
    await page.unroute('**/api/version?*');
    await poll(); await banner.waitFor({state:'visible'});
    assert.equal(await page.getAttribute('meta[name="app-version"]','content'), first, 'notice does not replace current page');
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth), 'no page overflow');
    if (process.env.VERSION_TEST_SCREENSHOT_DIR) await page.screenshot({path:join(process.env.VERSION_TEST_SCREENSHOT_DIR, `application-version-${viewport.width}.png`)});
    await page.locator('[data-action="reload-for-new-version"]').click();
    await page.waitForURL(url=>url.searchParams.get('__v')===latest);
    await page.waitForLoadState('load');
    assert.equal(await page.getAttribute('meta[name="app-version"]','content'), latest);
    assert.equal(await banner.isVisible(), false, 'Reload clears notice');
    assert.equal(await page.evaluate(()=>sessionStorage.getItem('forum_pending_app_version')), null);
    await page.reload();
    assert.equal(await banner.isVisible(), false, 'no repeated reload notice');
    assert.deepEqual(errors, []);
    await context.close();
  }
  console.log('application_version: desktop and phone code/content, failure/retry and Reload ok');
} finally {
  if (browser) await browser.close();
  server.kill('SIGKILL'); await once(server, 'exit');
  rmSync(root, {recursive:true,force:true});
}
