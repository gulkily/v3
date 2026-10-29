import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFileSync } from 'node:fs';
const source=readFileSync(new URL('../../public/assets/railspike_workflow.js',import.meta.url),'utf8');
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
try {
 const page=await browser.newPage();let count=0,status=200,delay=0;
 let result={eligible:['one'],workflows:[]};
 await page.route('http://railspike.test/**',async route=>{
  if(route.request().url().includes('/api/auth_status'))return route.fulfill({status:200,body:'status=authenticated'});
  if(route.request().url().includes('/api/railspike_workflow')){
   count++;const snapshot={...result};if(delay)await new Promise(r=>setTimeout(r,delay));
   return route.fulfill({status,json:snapshot});
  }
  return route.fulfill({contentType:'text/html',body:'<details data-railspike-shell open><summary>Development</summary><div data-railspike-post="one" data-railspike-thread="one"></div></details>'});
 });
 await page.goto('http://railspike.test/threads/one');
 await page.evaluate(()=>{localStorage.setItem('forum_pki_fingerprint','operator');localStorage.setItem('forum_pki_private_key','private');});
 await page.clock.install();
 const start=async()=>{await page.addScriptTag({content:source});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));};
 const settled=async()=>{await page.waitForFunction(()=>document.querySelector('[data-railspike-post] button'));await new Promise(r=>setTimeout(r,80));};
 await start();await settled();assert.equal(count,1);
 await page.clock.fastForward(5001);await settled();assert.equal(count,2,'poll without active workflow');
 delay=400;
 await page.evaluate(()=>{ForumRailspike.refresh();ForumRailspike.refresh();ForumRailspike.refresh();});
 await new Promise(r=>setTimeout(r,100));assert.equal(count,3,'concurrent refreshes coalesce');
 await new Promise(r=>setTimeout(r,450));delay=0;
 await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));});
 const hiddenCount=count;await page.clock.fastForward(20000);await settled();assert.equal(count,hiddenCount,'hidden page is silent');
 await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});
 await settled();assert.equal(count,hiddenCount+1);
 result={eligible:['one'],workflows:[{id:'w',post_id:'one',thread_id:'one',version:2,state:'questions',revisions:[{number:1,kind:'questions',questions:['Which phone?']}],history:[]}]};
 await page.evaluate(()=>ForumRailspike.refresh());
 await page.getByLabel('Which phone?').fill('Keep my answer');
 await page.evaluate(()=>ForumRailspike.refresh());assert.equal(await page.getByLabel('Which phone?').inputValue(),'Keep my answer');
 result.workflows[0]={...result.workflows[0],version:3,state:'approved',revisions:[{number:2,kind:'assessment',questions:[],artifact:'Assessment'}]};
 await page.evaluate(()=>ForumRailspike.refresh());
 await page.getByText('Saved draft: Which phone?',{exact:true}).click();
 assert.equal(await page.locator('textarea[readonly]').inputValue(),'Keep my answer','changed revision retains old draft');
 // Storage failure still retains the working draft in memory across a revision.
 await page.evaluate(()=>{window.originalStorageSet=Storage.prototype.setItem;Storage.prototype.setItem=function(){throw Error('quota');};});
 await page.getByLabel('Requested changes',{exact:true}).fill('Unsaved locally');
 result.workflows[0]={...result.workflows[0],version:4,revisions:[{number:3,kind:'assessment',questions:[],artifact:'New assessment'}]};
 await page.evaluate(()=>ForumRailspike.refresh());
 assert.ok((await page.locator('textarea[readonly]').evaluateAll(rows=>rows.map(r=>r.value))).includes('Unsaved locally'));
 assert.match(await page.locator('[data-workflow-draft-warning]').textContent(),/copy your text/);
 await page.evaluate(()=>{Storage.prototype.setItem=window.originalStorageSet;});
 await page.getByText('Saved draft: Requested changes',{exact:true}).click();
 await page.getByRole('button',{name:'Discard saved draft',exact:true}).last().click();await settled();
 const approvedCount=count;await page.clock.fastForward(5001);await settled();assert.equal(count,approvedCount+1,'completed workflow still polls');
 await page.evaluate(()=>window.dispatchEvent(new Event('pagehide')));const navigated=count;
 await page.clock.fastForward(15000);assert.equal(count,navigated,'navigation suspends polling');
 await page.evaluate(()=>window.dispatchEvent(new Event('pageshow')));await settled();assert.equal(count,navigated+1,'back/forward restoration resumes');
 status=403;await page.evaluate(()=>ForumRailspike.refresh());const denied=count;
 await page.clock.fastForward(20000);await page.evaluate(()=>ForumRailspike.refresh());assert.equal(count,denied,'authorization refusal stops every retry');
 assert.match(await page.locator('[data-workflow-notice]').textContent(),/reload/);
 assert.equal(await page.locator('textarea[readonly]').inputValue(),'Keep my answer');
 // A new document is the recovery boundary. Session drafts survive it.
 status=200;await page.reload();await start();await settled();
 assert.equal(await page.locator('textarea[readonly]').inputValue(),'Keep my answer');
 status=503;
 for(let i=0;i<3;i++){await page.clock.fastForward(5001);await settled();}
 const failed=count;await page.clock.fastForward(30000);await settled();assert.equal(count,failed,'transient failures are bounded');
 status=200;await page.getByRole('button',{name:'Refresh status',exact:true}).click();await settled();assert.equal(count,failed+1);
 result={eligible:[],workflows:[]};await page.evaluate(()=>ForumRailspike.refresh());const removed=count;
 await page.clock.fastForward(15000);assert.equal(count,removed);assert.equal(await page.locator('[data-railspike-shell]').isVisible(),false);
 await page.close();console.log('Railspike polling and draft recovery checks passed.');
} finally {await browser.close();}
