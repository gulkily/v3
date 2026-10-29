import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';
const source=readFileSync(new URL('../../public/assets/railspike_workflow.js',import.meta.url),'utf8');
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
try {
 for(const status of ['pending','approved']) {
  const page=await browser.newPage(),requests=[];
  await page.route('http://railspike.test/**',route=>{
   const path=new URL(route.request().url()).pathname;
   if(path.startsWith('/api/')){requests.push(path);return route.fulfill({status:path==='/api/auth_status'?401:403,json:{}});}
   return route.fulfill({contentType:'text/html',body:'<details data-railspike-shell><summary>Development</summary><div data-railspike-post="one" data-railspike-thread="one"></div></details>'});
  });
  await page.goto('http://railspike.test/');
  await page.evaluate(status=>{
   localStorage.setItem('forum_pki_private_key','saved-private-key');
   window.authenticationCalls=0;window.reloads=0;
   window.PrivateSiteAuth={async authenticate(options){window.authenticationCalls++;if(options?.navigate!==false)window.reloads++;return {status};}};
  },status);
  await page.addScriptTag({content:source});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  await page.waitForFunction(()=>document.querySelector('[data-workflow-notice]'));
  assert.equal(await page.evaluate(()=>authenticationCalls),1);
  assert.equal(await page.evaluate(()=>reloads),0,`${status} background authentication must not reload`);
  assert.deepEqual(requests,['/api/auth_status','/api/railspike_workflow']);
  await page.evaluate(()=>ForumRailspike.refresh());assert.equal(requests.length,2,'403 is terminal until reload');
  await page.close();
 }
 console.log('Railspike background authentication navigation checks passed.');
} finally {await browser.close();}
