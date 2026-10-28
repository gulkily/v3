import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'standard-activity-browser-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/standard_activity_pagination_fixture.php'), root], { env, encoding: 'utf8' });
assert.equal(seed.status, 0, seed.stderr);
const { themes } = JSON.parse(seed.stdout);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let logs = ''; server.stderr.on('data', data => { logs += data; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const rowLinks = 'article.card a[href^="/posts/page-"]';
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  for (const javaScriptEnabled of [false, true]) {
    const context = await browser.newContext({ javaScriptEnabled, serviceWorkers: 'block', viewport: { width: 390, height: 900 } });
    try {
      const page = await context.newPage();
      await page.goto(base + '/activity/?view=all');
      assert.equal(await page.locator(rowLinks).count(), 100);
      const firstKeys = await page.locator(rowLinks).evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
      assert.equal(await page.locator('[data-railspike-activity]').count(),0);
      const rss=await context.request.get(base+'/activity/?view=all&format=rss');
      assert.equal(rss.status(),200);assert.match(await rss.text(),/<rss/);
      await page.locator(rowLinks).first().click();await page.waitForLoadState('load');
      assert.match(new URL(page.url()).pathname,/^\/posts\/page-/);
      await page.goto(base+'/activity/?view=all');
      const next = page.getByRole('link', { name: 'Next', exact: true });
      await next.focus(); await page.keyboard.press('Enter'); await page.waitForURL(/position=/);
      await page.waitForLoadState('load');
      const secondUrl = page.url();
      const secondKeys = await page.locator(rowLinks).evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
      assert.equal(secondKeys.length, 100);
      assert.equal(firstKeys.some(key => secondKeys.includes(key)), false);
      const source = page.locator('a[href^="/source/"][href*="/records/posts/page-"]').first();
      assert.equal((await context.request.get(base + await source.getAttribute('href'))).status(), 200);
      assert.equal(await page.locator('.activity-commit-manifest li').count(), 100);
      assert.equal(await page.getByText('records/notes/unrelated-2299.txt', { exact: true }).count(), 0);
      const commitLink = page.locator('a[href^="/source/commits/"]').first();
      const commitPage = await context.request.get(base + await commitLink.getAttribute('href'));
      assert.equal(commitPage.status(), 200);
      assert.ok((await commitPage.text()).includes('records/notes/unrelated-2299.txt'));
      await page.getByRole('link', { name: 'Next', exact: true }).click(); await page.waitForLoadState('load');
      assert.equal(await page.locator(rowLinks).count(), 5);
      assert.equal(await page.getByRole('link', { name: 'Next', exact: true }).count(), 0);
      await page.getByRole('link', { name: 'Previous', exact: true }).click(); await page.waitForLoadState('load');
      assert.deepEqual(await page.locator(rowLinks).evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), secondKeys);
      await page.reload();
      await page.goBack(); await page.goForward(); await page.waitForLoadState('load');
      assert.equal(await page.locator(rowLinks).count(), 100);
      await page.goto(secondUrl);
      await page.getByRole('link', { name: 'Approvals', exact: true }).click(); await page.waitForLoadState('load');
      assert.equal(new URL(page.url()).searchParams.has('position'), false);
      await page.getByText('No activity.', { exact: true }).waitFor();
      await page.goto(base + '/activity/?position=invalid');
      await page.getByRole('alert').waitFor();
      await page.getByRole('link', { name: 'First page', exact: true }).click(); await page.waitForLoadState('load');
      assert.equal(await page.locator(rowLinks).count(), 100);
      // Native HTTP failure retains its requested URL; browser Reload retries it.
      await page.route('**/activity/?**position=**', route => route.fulfill({ status: 503, contentType: 'text/html', body: '<p>Temporarily unavailable</p>' }));
      await page.getByRole('link', { name: 'Next', exact: true }).click(); await page.waitForLoadState('load');
      await page.getByText('Temporarily unavailable').waitFor();
      const failedUrl = page.url();
      await page.unroute('**/activity/?**position=**');
      await page.reload();
      assert.equal(page.url(), failedUrl);
      assert.deepEqual(await page.locator(rowLinks).evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), secondKeys);
      console.log(`PASS standard Activity JavaScript=${javaScriptEnabled}: keyboard links, all boundaries, evidence, history, reset, invalid position and HTTP retry`);
      if (javaScriptEnabled) {
        for (const width of [390, 1280]) for (const theme of themes) {
          await page.evaluate(theme => localStorage.setItem('forum-zenmemes-theme', theme), theme);
          const themed = await context.newPage();
          try {
            await themed.setViewportSize({ width, height: 900 });
            await themed.goto(secondUrl);
            assert.equal(await themed.locator('html').getAttribute('data-resolved-theme'), theme);
            assert.equal(await themed.locator(rowLinks).count(), 100);
            await themed.getByRole('navigation', { name: 'Pagination', exact: true }).scrollIntoViewIfNeeded();
            assert.equal(await themed.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${theme} ${width}: page overflow`);
            if (['word97', 'chicago', 'midnight'].includes(theme)) await themed.screenshot({ path: `/tmp/activity-pagination-${theme}-${width}.png` });
          } finally { await themed.close(); }
        }
        console.log(`PASS standard Activity ${themes.length} themes at 390/1280: long subjects/source paths and native controls without page overflow`);
      }
    } finally { await context.close(); }
  }
  // Actual unchanged Forte production flow: append rather than replace.
  const context = await browser.newContext({ serviceWorkers: 'block' });
  try {
    const page = await context.newPage();
    await page.goto(base + '/forte/activity/');
    const rows = page.locator('[data-paned-activity-id]:visible');
    const before = await rows.count();
    await page.locator('[data-paned-activity-load-more][data-paned-activity-view="all"]').click();
    await page.waitForFunction(before => [...document.querySelectorAll('[data-paned-activity-id]')].filter(n => !n.hidden).length > before, before);
    const selected = rows.nth(before);
    const id = await selected.getAttribute('data-paned-activity-id');
    await selected.click();
    await page.locator(`[data-paned-activity-content-item-id="${id}"]:visible`).waitFor();
    await page.goto(base + '/forte/activity/?view=commits');
    const commits = page.locator('[data-paned-activity-commit-sha]:visible');
    assert.equal(await commits.count(), 100);
    await page.locator('[data-paned-activity-load-more][data-paned-activity-view="commits"]').click();
    await page.waitForFunction(() => [...document.querySelectorAll('[data-paned-activity-commit-sha]')].filter(n => !n.hidden).length > 100);
    await commits.last().click();
    await page.locator('[data-paned-activity-commit-detail]:visible').waitFor();
    console.log('PASS unchanged Forte Activity/Commits: real append, appended-row selection and details');
    await page.goto(base + '/forte/activity/');
    const firstId = await page.locator('[data-paned-activity-id]:visible').first().getAttribute('data-paned-activity-id');
    for (const route of ['/activity/?view=all', '/forte/activity/', '/api/forte_activity_detail?id=' + firstId]) {
      const times = [];
      for (let i = 0; i < 20; i++) {
        const start = performance.now();
        const response = await context.request.get(base + route);
        assert.equal(response.status(), 200);
        await response.body();
        times.push(performance.now() - start);
      }
      times.sort((a,b) => a-b);
      assert.ok(times[18] <= 1000, `${route} warm p95 exceeded 1s: ${times[18]}`);
      console.log(`PASS HTTP ${route}: 20 requests, warm p95 ${times[18].toFixed(1)}ms`);
    }
    const mutate = sql => {
      const result = spawnSync('php', ['-r', '$pdo=new PDO("sqlite:".$argv[1]); $pdo->exec($argv[2]);', join(root, 'read.sqlite3'), sql], { env, encoding:'utf8' });
      assert.equal(result.status, 0, result.stderr);
    };
    const recover = () => {
      const result = spawnSync('php', ['-r', 'require $argv[1]."/autoload.php"; (new \\ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2],$argv[3],new \\ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2])))->rebuild();', project, join(root,'repository'), join(root,'read.sqlite3')], { env, encoding:'utf8' });
      assert.equal(result.status, 0, result.stderr);
    };
    for (const width of [390,1280]) {
      await page.setViewportSize({width,height:900});
      mutate('UPDATE activity SET evidence_json=NULL WHERE source_path IS NOT NULL');
      for (const route of ['/activity/?view=all','/forte/activity/']) {
        await page.goto(base + route);
        await page.getByRole('link', {name:'Reload',exact:true}).first().waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1), true);
        await page.screenshot({path:`/tmp/activity-evidence-unavailable-${route.startsWith('/forte')?'forte':'standard'}-${width}.png`});
      }
      recover();
      await page.getByRole('link', {name:'Reload',exact:true}).first().click();
      await page.waitForLoadState('load');
      assert.equal(await page.getByRole('link', {name:'Reload',exact:true}).count(), 0);
      for (const route of ['/activity/?view=all','/forte/activity/']) {
        mutate('DELETE FROM activity');
        await page.goto(base+route);
        assert.equal(await page.locator('[data-paned-activity-id]:visible').count(),0);
        assert.equal(await page.getByRole('link', {name:'Reload',exact:true}).count(),0);
        recover();
        await page.reload();
      }
    }
    console.log('PASS indexed Activity phone/desktop: unavailable evidence, Reload after rebuild, empty state and recovery');

  } finally { await context.close(); }
} catch (error) { console.error(logs.slice(-4000)); throw error; }
finally { await browser.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true }); }
