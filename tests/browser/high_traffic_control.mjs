import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, copyFile, utimes, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright-core';
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(readFileSync(join(project,'public/assets/openpgp.min.js'),'utf8'));
const keys=await globalThis.openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'traffic-operator'}],format:'armored'});
const fingerprint=(await globalThis.openpgp.readKey({armoredKey:keys.publicKey})).getFingerprint().toUpperCase();
const root=await mkdtemp(join(tmpdir(),'high-traffic-browser-'));
const cache=join(project,'state/high_traffic',createHash('sha256').update(join(root,'repository')).digest('hex').slice(0,16));
const env={...process.env,FORUM_SITE_ID:'zenmemes',FORUM_APPROVED_MEMBERS_ONLY:'false',FORUM_SECRETS_PATH:join(root,'absent.php'),HIGH_TRAFFIC_TEST_ROOT:root,FORUM_REPOSITORY_ROOT:join(root,'repository'),FORUM_DATABASE_PATH:join(root,'read.sqlite3'),FORUM_TASK_QUEUE_DATABASE_PATH:join(root,'tasks.sqlite3'),FORUM_PUBLIC_ARTIFACT_ROOT:join(root,'public'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')};
const seed=spawnSync('php',[join(project,'tests/Support/fastmod_browser_fixture.php'),root],{input:JSON.stringify(keys),encoding:'utf8',env});
assert.equal(seed.status,0,seed.stderr);const {identity}=JSON.parse(seed.stdout);
await writeFile(join(root,'repository/records/posts/traffic-visible.txt'),'Post-ID: traffic-visible\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nSubject: Traffic browser fixture\n\nA visible public thread.\n');
for(const args of [['add','records/posts/traffic-visible.txt'],['commit','-m','Seed a visible browser fixture thread']]) {
 const seeded=spawnSync('git',['-C',join(root,'repository'),...args],{encoding:'utf8'});assert.equal(seeded.status,0,seeded.stderr);
}

const normal=await readFile(join(project,'public/.htaccess'),'utf8');await writeFile(join(root,'public/.htaccess'),normal);
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/high_traffic_browser_router.php')],{env,stdio:['ignore','ignore','pipe']});
let logs='';server.stderr.on('data',chunk=>logs+=chunk);
const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
try {
 for(let i=0;i<100;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,50));}}
 const context=await browser.newContext({serviceWorkers:'block'});
 await context.addCookies([{name:'identity_hint',value:identity,url:base}]);
 await context.addInitScript(({keys,fingerprint})=>{
  for(const [key,value] of Object.entries({forum_pki_private_key:keys.privateKey,forum_pki_public_key:keys.publicKey,forum_pki_fingerprint:fingerprint,forum_pki_published_fingerprint:fingerprint,forum_pki_username:'traffic-operator'}))localStorage.setItem(key,value);
 },{keys,fingerprint});
 const page=await context.newPage();page.on('pageerror',e=>console.error(e));await page.goto(base+'/tools/feature-flags/');await page.waitForFunction(()=>!document.querySelector('[data-public-auth-resume="true"]'));
 const toggle=page.locator('[data-high-traffic-control] button');const status=page.locator('[data-high-traffic-status]');
 assert.equal(await toggle.getAttribute('aria-checked'),'false');
 await verifyLayout('disabled');
 await page.route('**/api/finalize_high_traffic',async route=>{await new Promise(r=>setTimeout(r,250));await route.continue();});
 await toggle.click();await toggle.waitFor({state:'visible'});

 await page.waitForFunction(()=>document.querySelector('[data-high-traffic-control] button').disabled);
 await page.waitForFunction(()=>document.querySelector('[data-high-traffic-control] button').getAttribute('aria-checked')==='true' || document.querySelector('[data-high-traffic-status]').textContent.includes('CLI recovery'));
 assert.equal(await toggle.getAttribute('aria-checked'),'true',await status.textContent());
 assert((await readFile(join(root,'public/.htaccess'),'utf8')).includes('BEGIN V3 HIGH TRAFFIC'));
 const shared=await context.newPage();const sharedAuthRequests=[];shared.on('request',r=>{if(/\/api\/(auth_challenge|authenticate_identity)$/.test(new URL(r.url()).pathname))sharedAuthRequests.push(r.url());});await shared.goto(base+'/');await shared.waitForFunction(()=>!document.querySelector('[data-public-auth-resume="true"]'));
 assert.equal(await shared.locator('meta[name="v3-generated-at"]').count(),1);
 const posted=await context.request.post(base+'/api/create_thread',{form:{board_tags:'general',subject:'Live action while cached',body:'Authenticated actions retain live routing.'}});
 assert.equal(posted.status(),200,await posted.text());
 await shared.goto(base+'/threads/traffic-visible/');await shared.waitForFunction(()=>!document.querySelector('[data-public-auth-resume="true"]'));
 assert.equal(await shared.locator('meta[name="v3-generated-at"]').count(),1);
 const like=shared.locator('[data-action="apply-thread-tag"][data-tag="like"]').first();
 await like.locator('xpath=ancestor::article[1]').hover();
 const voted=shared.waitForResponse(r=>r.url().endsWith('/api/apply_thread_tag')&&r.request().method()==='POST');
 await like.click();const voteResult=await voted;assert.equal(voteResult.status(),200,await voteResult.text());
 assert.equal(JSON.parse(await readFile(join(cache,'state.json'),'utf8')).enabled,true,'ordinary likes must preserve high-traffic mode');
 assert.equal(sharedAuthRequests.length,0,'shared public navigation must not force a new sign-in');

 await shared.close();
 const hintPage=await context.newPage();await hintPage.clock.install();await hintPage.goto(base+'/');await hintPage.waitForFunction(()=>!document.querySelector('[data-public-auth-resume="true"]'));
 await utimes(join(cache,'pages/index.html'),1,1);
 const hinted=hintPage.waitForResponse(r=>r.url().endsWith('/internal/high-traffic/refresh'));
 await hintPage.clock.fastForward(65000);assert.equal((await hinted).status(),204);
 assert.equal(JSON.parse(await readFile(join(cache,'hints',createHash('sha256').update('/').digest('hex')+'.json'),'utf8')).route,'/');
 await hintPage.close();

 async function verifyLayout(state) { for(const width of [375,1150]){
  await page.setViewportSize({width,height:850});
  for(const theme of ['light','dark','console','lcd','chicago','vapor','forge','sticker','arena','thermal','whitehot','word97','midnight']){
   await page.evaluate(theme=>{document.documentElement.dataset.theme=theme;},theme);
   await toggle.scrollIntoViewIfNeeded();
   const bounds=await toggle.boundingBox();assert(bounds.x>=0&&bounds.x+bounds.width<=width,`${theme}/${width} clipped toggle`);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${theme}/${width} overflow`);
  }
 }
 await page.screenshot({path:join(root,state+'-desktop.png'),fullPage:false});
 await page.setViewportSize({width:375,height:850});await toggle.scrollIntoViewIfNeeded();
 await page.screenshot({path:join(root,state+'-phone.png'),fullPage:false});
 }
 await verifyLayout('enabled');
 await writeFile(join(root,'public/.htaccess'),'BrokenRouting\n');
 await toggle.click();await page.waitForFunction(()=>document.querySelector('[data-high-traffic-status]').textContent.includes('CLI recovery'));
 assert.equal(await toggle.isEnabled(),true);assert.equal(await status.isVisible(),true);await verifyLayout('failure');
 await writeFile(join(root,'public/.htaccess'),normal);
 await toggle.focus();await page.keyboard.press('Space');await page.waitForFunction(()=>document.querySelector('[data-high-traffic-control] button').getAttribute('aria-checked')==='false');
 assert.equal(await status.isVisible(),false);
 const denied=await browser.newContext({serviceWorkers:'block'});const no=await denied.newPage();await no.goto(base+'/tools/feature-flags/');
 assert.equal(await no.locator('[data-high-traffic-control]').count(),0);
 const forbidden=await denied.request.post(base+'/api/prepare_high_traffic',{form:{enabled:'true'}});assert.equal(forbidden.status(),403);
 await context.close();await denied.close();
 console.log(JSON.stringify({status:'passed',fixture:root,checks:['real operator signing','live action and signed like from shared page','timestamp hint via browser clock','enable/disable','pending','failure and retry','unauthorized rejection','phone/desktop all shared themes']}));
} catch(error){console.error(logs.slice(-1200));throw error;}
finally{await browser.close();server.kill();await rm(cache,{recursive:true,force:true});}
