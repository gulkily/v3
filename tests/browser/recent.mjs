import assert from 'node:assert/strict';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {once} from 'node:events';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE||'playwright-core');
const project=resolve(new URL('../..',import.meta.url).pathname);
const parent=await mkdtemp(join(tmpdir(),'recent-browser-')),root=join(parent,'fixture');
const env={...process.env,FORUM_SITE_ID:'zenmemes',SIGNED_REACTION_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'absent.php'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')};
const seed=spawnSync('php',[join(project,'tests/Support/recent_browser_fixture.php'),root],{env,encoding:'utf8'});assert.equal(seed.status,0,seed.stderr);
const {themes}=JSON.parse(seed.stdout);
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/signed_reaction_browser_router.php')],{env,stdio:['ignore','ignore','pipe']});let logs='';server.stderr.on('data',x=>logs+=x);
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
try {
 for(let i=0;i<60;i++){try{await fetch(base+'/api/version');break}catch{await new Promise(r=>setTimeout(r,100))}}
 for(const width of [390,1280])for(const theme of themes){
  const context=await browser.newContext({viewport:{width,height:900},serviceWorkers:'block'});
  await context.addInitScript(theme=>{localStorage.setItem('forum-zenmemes-theme',theme)},theme);
  const page=await context.newPage();await page.goto(base+'/recent/');
  assert.equal(await page.locator('html').getAttribute('data-resolved-theme'),theme);
  assert.equal(await page.locator('.recent-entry').count(),50);
  if(width===390 && theme===themes[0]) {
   await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   console.log('Local Recent timing: '+JSON.stringify(await page.evaluate(()=>({responseMs:performance.getEntriesByType('navigation')[0].responseEnd,paintMs:performance.getEntriesByName('first-contentful-paint')[0]?.startTime}))))
  }
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,`${theme} ${width} overflow`);
  const nav=page.locator('.site-header a[href="/recent/"]');await nav.scrollIntoViewIfNeeded();assert.equal(await nav.isVisible(),true);
  if(['word97','chicago','midnight'].includes(theme)) {
   assert.match(await nav.evaluate(el=>getComputedStyle(el,'::before').backgroundImage),/data:image\/svg/);
   await page.screenshot({path:`/tmp/recent-${theme}-${width}.png`});
  }
  await page.getByRole('navigation',{name:'Pagination',exact:true}).scrollIntoViewIfNeeded();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
  await context.close();
 }
 const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:900},serviceWorkers:'block'});
 const page=await context.newPage();await page.goto(base+'/');await page.locator('.site-header a[href="/recent/"]').click();
 assert.equal(await page.locator('.recent-entry').count(),50);
 await page.getByRole('link',{name:'Next',exact:true}).focus();await page.keyboard.press('Enter');await page.waitForURL(/position=/);
 assert.ok(await page.locator('.recent-entry').count()>0);assert.ok(await page.locator('.recent-entry').count()<50);
 const cursorUrl=page.url();const ids=await page.locator('.recent-entry').evaluateAll(rows=>rows.map(row=>row.dataset.postId));
 await page.reload();assert.deepEqual(await page.locator('.recent-entry').evaluateAll(rows=>rows.map(row=>row.dataset.postId)),ids);
 const anchored=page.locator('.recent-entry a[href*="#post-"]').first();const anchorId=(await anchored.getAttribute('href')).split('#post-')[1];
 await anchored.click();await page.waitForURL(/#post-/);assert.equal(await page.locator('.post-card:target').getAttribute('data-post-id'),anchorId);
 await page.goBack();assert.equal(page.url(),cursorUrl);
 await page.locator('.recent-entry a').first().click();await page.waitForURL(/\/threads\//);assert.equal(await page.locator('h1').count()>0,true);
 await page.goBack();assert.equal(page.url(),cursorUrl);
 assert.deepEqual(await page.locator('.recent-entry').evaluateAll(rows=>rows.map(row=>row.dataset.postId)),ids);
 await context.setOffline(true);let failed=false;try{await page.reload()}catch{failed=true}assert.equal(failed,true);
 await context.setOffline(false);await page.goto(cursorUrl);
 assert.deepEqual(await page.locator('.recent-entry').evaluateAll(rows=>rows.map(row=>row.dataset.postId)),ids);
 await page.getByRole('link',{name:'Previous',exact:true}).click();assert.equal(await page.locator('.recent-entry').count(),50);
 console.log(`PASS Recent navigation, ${themes.length} themes x 2 widths, icons, no-JavaScript keyboard paging, anchored reply, thread open/Back/reload and offline reconnect`);
 await context.close();
} finally {await browser.close();server.kill('SIGTERM');await rm(parent,{recursive:true,force:true});if(process.env.RECENT_DEBUG)console.log(logs)}
