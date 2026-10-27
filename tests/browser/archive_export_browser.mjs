import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, stat, writeFile, unlink } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'archive-export-browser-'));
function command(cmd, args) {
  const result = spawnSync(cmd, args, {cwd:project, encoding:'utf8'});
  assert.equal(result.status, 0, result.stdout + result.stderr); return result.stdout;
}
command('php', ['-r', `require $argv[1].'/autoload.php'; mkdir($argv[2].'/repository',0700,true);`, project, root]);
command('cp', ['-R', join(project,'tests/fixtures/parity_minimal_v1/records'), join(root,'repository/records')]);
for (const args of [['init','-q'], ['config','user.name','Export Test'], ['config','user.email','export@example.invalid'], ['add','.'], ['commit','-qm','Fixture']]) command('git',['-C',join(root,'repository'),...args]);
const socket=createServer().listen(0,'127.0.0.1'); await once(socket,'listening');
const port=socket.address().port; await new Promise(r=>socket.close(r));
const env={...process.env, FORUM_REPOSITORY_ROOT:join(root,'repository'), FORUM_DATABASE_PATH:join(root,'cache/index.sqlite3'),
  FORUM_STATIC_HTML_ROOT:join(root,'static'), FORUM_TASK_QUEUE_DATABASE_PATH:join(root,'private/tasks.sqlite3'),
  FORUM_IMAGE_ROOT:join(root,'images'), FORUM_APPROVED_MEMBERS_ONLY:'false', FORUM_SITE_ID:'qdb', FORUM_SECRETS_PATH:join(root,'none.php')};
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t',join(project,'public'),join(project,'public/router.php')],{cwd:project,env});
let log='';server.stderr.on('data',b=>{log+=b;}); const base=`http://127.0.0.1:${port}`;
let browser, holder;
try {
  for(let i=0;i<100;i++){try {await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,50));}}
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
  const context=await browser.newContext({serviceWorkers:'block',acceptDownloads:true});
  const page=await context.newPage();
  const overflow=async()=>assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  for(const width of [390,1280]) {
    await page.setViewportSize({width,height:900}); await page.goto(base+'/tools/backup');
    for(const theme of ['light','word97','chicago','midnight']) {
      await page.evaluate(t=>localStorage.setItem(window.forumBrowserRuntime.themeStorageKey,t),theme);
      await page.reload();
      const link=page.locator('a[href^="/downloads/repository.tar.gz"]').first(); assert.ok(await link.isVisible()); await overflow();
    }
    await page.screenshot({path:join(root,`backup-${width}.png`),fullPage:true});
  }
  // Ordinary link activation, then the same download again: the cached file survives both requests.
  const cold=await Promise.all([page.waitForEvent('download'),page.locator('a[href^="/downloads/repository.tar.gz"]').first().click()]);
  await cold[0].saveAs(join(root,'cold.tar.gz'));
  const cache=join(root,'cache/archive-exports');
  const entries=await readdir(cache);assert.equal(entries.length,1);
  const artifact=join(cache,entries[0],'archive.tar.gz'); const before=await stat(artifact);
  const warm=await context.request.get(base+'/downloads/repository.tar.gz');assert.equal(warm.status(),200);
  assert.deepEqual(await warm.body(),await readFile(join(root,'cold.tar.gz')));
  assert.equal((await stat(artifact)).ino,before.ino);assert.equal((await stat(artifact)).mtimeMs,before.mtimeMs);
  assert.match(command('tar',['-tzf',join(root,'cold.tar.gz')]),/repository\/records\/posts/);
  holder=spawn('php',['-r','$h=fopen($argv[1],"c+");flock($h,LOCK_EX);echo "ready\\n";fflush(STDOUT);fgets(STDIN);flock($h,LOCK_UN);',join(root,'cache/image-export.lock')],{stdio:['pipe','pipe','pipe']});
  await once(holder.stdout,'data');
  for(const width of [390,1280]) {
    await page.setViewportSize({width,height:900});
    const response=await page.goto(base+'/downloads/repository.zip');
    assert.equal(response.status(),503);assert.equal(response.headers()['retry-after'],'5');assert.match(response.headers()['cache-control'],/no-store/);
    const text=await page.locator('body').innerText();assert.match(text,/Retry the download/);assert.ok(!text.includes(root));await overflow();
    await page.screenshot({path:join(root,`busy-${width}.png`),fullPage:true});
    const conflict=await page.goto(base+'/downloads/repository.zip?revision='+'0'.repeat(40));
    assert.equal(conflict.status(),409);assert.match(await page.locator('body').innerText(),/Refresh Backup/);await overflow();
  }
  holder.stdin.end('release\n');await once(holder,'exit');holder=null;
  await page.goto(base+'/tools/backup');
  const retry=await Promise.all([page.waitForEvent('download'),page.locator('a[href^="/downloads/repository.zip"]').first().click()]);
  await retry[0].saveAs(join(root,'retry.zip'));assert.match(command('unzip',['-Z1',join(root,'retry.zip')]),/repository\/records\/posts/);
  await writeFile(join(root,'repository/records/posts/broken.txt'),'uncommitted');
  const failed=await page.goto(base+'/downloads/repository.tar.gz');assert.equal(failed.status(),500);
  assert.match(await page.locator('body').innerText(),/site operator/);await overflow();
  await unlink(join(root,'repository/records/posts/broken.txt'));
  const repaired=await context.request.get(base+'/downloads/repository.tar.gz');assert.equal(repaired.status(),200);
  const sqlite=await context.request.get(base+'/downloads/read_model.sqlite3');assert.equal(sqlite.status(),200);
  assert.equal((await sqlite.body()).subarray(0,15).toString(),'SQLite format 3');
  await page.goto(base+'/tools/codebase');assert.ok(await page.locator('a[href="/downloads/repository.tar.gz"]').isVisible());
  console.log(`PASS: archive links, four themes/two viewports, cold/warm reuse, busy headers, conflict, retry extraction, failure recovery text. Artifacts: ${root}`);
} catch(error) {console.error(log);throw error;}
finally {if(holder){holder.stdin.end('release\n');}await browser?.close();server.kill();}
