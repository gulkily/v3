import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'tag-pagination-browser-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_SECRETS_PATH: join(root, 'absent.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seed = spawnSync('php', [join(project, 'tests/Support/tag_pagination_fixture.php'), root], {env, encoding:'utf8'});
assert.equal(seed.status,0,seed.stderr);
const { themes } = JSON.parse(seed.stdout);
const socket = createServer().listen(0,'127.0.0.1'); await once(socket,'listening');
const port=socket.address().port; await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/signed_reaction_browser_router.php')],{env,stdio:['ignore','ignore','pipe']});
let logs=''; server.stderr.on('data',v=>logs+=v);
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const cards='main article.thread-card';
try {
  for(let i=0;i<60;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  for(const javaScriptEnabled of [false,true]) {
    const context=await browser.newContext({javaScriptEnabled,serviceWorkers:'block',viewport:{width:390,height:900}});
    const page=await context.newPage();
    await page.goto(base+'/tags/paging');
    assert.equal(await page.locator(cards).count(),25);
    const keys=()=>page.locator(cards+' h2 a').evaluateAll(n=>n.map(v=>v.getAttribute('href')));
    const first=await keys();
    await page.getByRole('link',{name:'Next',exact:true}).focus(); await page.keyboard.press('Enter'); await page.waitForURL(/position=/);
    const secondUrl=page.url(); const second=await keys();
    assert.equal(second.length,25); assert.equal(first.some(v=>second.includes(v)),false);
    await page.getByRole('link',{name:'Next',exact:true}).click(); await page.waitForLoadState('load');
    assert.equal(await page.locator(cards).count(),15);
    await page.getByRole('link',{name:'Previous',exact:true}).click(); await page.waitForLoadState('load');
    assert.deepEqual(await keys(),second);
    await page.reload(); assert.deepEqual(await keys(),second);
    await page.goBack(); await page.goForward(); assert.deepEqual(await keys(),second);
    await page.goto(base+'/tags/paging?position=invalid');
    assert.equal(await page.locator(cards).count(),0);
    await page.getByRole('link',{name:'First page',exact:true}).click(); await page.waitForLoadState('load');
    assert.deepEqual(await keys(),first);
    await page.route('**/tags/paging?position=*',r=>r.fulfill({status:503,contentType:'text/html',body:'<p>Temporarily unavailable</p>'}));
    await page.getByRole('link',{name:'Next',exact:true}).click(); await page.waitForLoadState('load');
    await page.getByText('Temporarily unavailable').waitFor(); await page.unroute('**/tags/paging?position=*'); await page.reload();
    assert.deepEqual(await keys(),second);
    if(javaScriptEnabled) {
      for(const width of [390,1280]) for(const theme of themes) {
        await page.setViewportSize({width,height:900});
        await page.goto(secondUrl);
        // Test the shared pagination classes under every installed theme, including site-specific styles.
        await page.evaluate(theme=>{
          document.documentElement.setAttribute('data-theme',theme);
          const link=document.createElement('link');link.rel='stylesheet';link.href='/assets/theme-'+theme+'.css';document.head.appendChild(link);
        },theme);
        await page.waitForTimeout(100);
        assert.equal(await page.locator('.pagination-nav a').count(),2);
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${theme}/${width}: overflow`);
        const geometry=await page.locator('.pagination-nav').boundingBox();
        await page.waitForTimeout(100);
        const later=await page.locator('.pagination-nav').boundingBox();
        assert.equal(Math.round(geometry.y),Math.round(later.y),`${theme}/${width}: unstable pagination`);
      }
    }
    console.log(`PASS online JS=${javaScriptEnabled}: all boundaries, keyboard, history, retry${javaScriptEnabled ? ', '+themes.length+' themes at phone/desktop' : ''}`);
    await context.close();
  }
  // Real installed worker and cached snapshot, then loss of network.
  const context=await browser.newContext({viewport:{width:390,height:900}});
  const page=await context.newPage(); await page.goto(base+'/tags/paging');
  const onlineNext=await page.getByRole('link',{name:'Next',exact:true}).getAttribute('href');
  await page.evaluate(async()=>{
    const registration=await navigator.serviceWorker.ready;
    await new Promise((resolve,reject)=>{
      const channel=new MessageChannel(); channel.port1.onmessage=e=>e.data.status==='error'?reject(new Error(e.data.error)):resolve();
      registration.active.postMessage({type:'refresh-offline-reader',force:true},[channel.port2]);
    });
  });
  await page.reload(); await context.setOffline(true);
  await page.goto(base+onlineNext);
  await page.getByText('This position is not available in the saved snapshot.',{exact:false}).waitFor();
  assert.equal(await page.locator('[data-role="offline-reader-content"] article.thread-card').count(),0);
  await page.getByRole('link',{name:'First page',exact:true}).click(); await page.waitForLoadState('load');
  const savedCards='[data-role="offline-reader-content"] article';
  await page.getByRole('link',{name:'Next',exact:true}).waitFor();
  const savedKeys=()=>page.locator('[data-role="offline-reader-content"] a[href^="/threads/paging-"]').evaluateAll(n=>n.map(v=>v.getAttribute('href')));
  assert.equal((await savedKeys()).length,25);
  const savedFirst=await savedKeys();
  await page.getByRole('link',{name:'Next',exact:true}).click(); await page.getByRole('link',{name:'Previous',exact:true}).waitFor();
  const savedSecond=await savedKeys(); assert.equal(savedSecond.length,25);assert.equal(savedFirst.some(v=>savedSecond.includes(v)),false);
  await page.reload(); await page.getByRole('link',{name:'Previous',exact:true}).waitFor();assert.deepEqual(await savedKeys(),savedSecond);
  await page.getByRole('link',{name:'Next',exact:true}).click();await page.getByRole('link',{name:'Previous',exact:true}).waitFor();assert.equal((await savedKeys()).length,15);
  await page.goBack();await page.getByRole('link',{name:'Next',exact:true}).waitFor();assert.deepEqual(await savedKeys(),savedSecond);
  await context.setOffline(false);await page.reload();
  await page.getByRole('link',{name:'First page',exact:true}).waitFor();
  await page.getByRole('link',{name:'First page',exact:true}).click();await page.waitForLoadState('load');assert.equal(await page.locator(cards).count(),25);
  console.log('PASS real offline worker: online cursor recovery, saved traversal/reload/history, reconnect recovery');
  await context.close();
} finally {
  await browser.close();server.kill();await once(server,'exit');
  if(process.exitCode) console.error(logs.slice(-6000));
  await rm(root,{recursive:true,force:true});
}
