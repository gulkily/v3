import assert from 'node:assert/strict';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {once} from 'node:events';
const {chromium}=await import('playwright-core');
const project=resolve(new URL('../..',import.meta.url).pathname);
const root=await mkdtemp(join(tmpdir(),'profile-delivery-'));
const env={...process.env,FORUM_SITE_ID:'zenmemes',FORUM_STATIC_PROFILE_PAGES_ENABLED:'false',SIGNED_REACTION_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'absent.php'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite')};
const seed=spawnSync('php',[join(project,'tests/Support/profile_delivery_fixture.php'),root],{env,encoding:'utf8'});
assert.equal(seed.status,0,seed.stderr); const {slug,themes}=JSON.parse(seed.stdout);
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/tag_pagination_browser_router.php')],{env,stdio:'ignore'});
const browser=await chromium.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try {
  for(let i=0;i<60;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  for(const javaScriptEnabled of [false,true]){
    const context=await browser.newContext({javaScriptEnabled,serviceWorkers:'block'});const page=await context.newPage();
    for(const width of [390,1280]){
      await page.setViewportSize({width,height:900});
      await page.goto(base+'/users/');
      await page.locator('a.account-row').first().click();await page.waitForLoadState('load');
      const link=page.locator(`a[href="/profiles/${slug}"]`).first();await link.click();await page.waitForLoadState('load');
      assert.equal(new URL(page.url()).pathname,`/profiles/${slug}`);
      assert.equal(await page.locator('main').count(),1);
      assert.ok(!(await page.content()).includes('route-source: static-html'));
      for(const theme of themes){
        await page.evaluate(theme=>document.documentElement.setAttribute('data-theme',theme),theme);
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Profile overflow ${theme}/${width}`);
      }
      await page.goto(base+'/tools/feature-flags');
      const row=page.locator('[data-flag-key="FORUM_STATIC_PROFILE_PAGES_ENABLED"]');
      await row.locator('.feature-flag-name').waitFor();
      assert.ok((await row.locator('.feature-flag-name').innerText()).includes('Static profile pages'));
      assert.ok((await row.innerText()).includes('runtime'));
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Flag overflow ${width}`);
    }
    await context.close();
  }
  console.log(`PASS profile links, runtime delivery and feature control; JS on/off, phone/desktop, ${themes.length} profile themes`);
} finally {await browser.close();server.kill('SIGTERM');await once(server,'exit');await rm(root,{recursive:true,force:true});}
