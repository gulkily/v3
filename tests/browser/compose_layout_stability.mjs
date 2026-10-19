import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'compose-layout-browser-'));
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const openpgp = globalThis.openpgp;
const keys = await openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'image-author'}],format:'armored'});
const key = await openpgp.readKey({armoredKey:keys.publicKey});
const fingerprint = key.getFingerprint();
const env = {...process.env, IMAGE_TEST_ROOT:root, FORUM_SECRETS_PATH:join(root,'unused.php'), FORUM_STATIC_HTML_ROOT:join(root,'static'),FORUM_PUBLIC_ARTIFACT_ROOT:join(root,'artifacts'),FAST_SCORING_ENABLED:'false', FORUM_APP_VERSION_NOTIFICATION:'false'};
function command(executable,args,options={}) {
 const r=spawnSync(executable,args,{cwd:project,env,encoding:'utf8',...options});
 assert.equal(r.status,0,r.stderr + '\n' + r.stdout);return r.stdout;
}
command('php',[join(project,'tests/Support/image_browser_fixture.php'),root],{input:JSON.stringify({publicKey:keys.publicKey})});
let flags={images:true,members:false};
const setFlags=async next=>{flags={...flags,...next};await writeFile(join(root,'flags.json'),JSON.stringify(flags));};
await setFlags({});
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-d','upload_max_filesize=6M','-d','post_max_size=8M','-S',`127.0.0.1:${port}`,join(project,'tests/Support/image_browser_router.php')],{cwd:project,env});
let log='';server.stderr.on('data',b=>{log+=b;});
let browser;
try {
 for(let i=0;i<100;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,50));}}
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({serviceWorkers:'block',hasTouch:true,viewport:{width:390,height:844}});
 await context.addInitScript(({keys,fingerprint})=>{
  localStorage.setItem('forum_pki_public_key',keys.publicKey);localStorage.setItem('forum_pki_private_key',keys.privateKey);
  localStorage.setItem('forum_pki_fingerprint',fingerprint);localStorage.setItem('forum_pki_published_fingerprint',fingerprint);
 },{keys,fingerprint});
 // Establish the saved identity session before composing: public authentication
 // intentionally reloads the page, which otherwise races automated form input.
 const initialChallenge = (await (await context.request.get(base+'/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
 const initialSignature = await openpgp.sign({message:await openpgp.createMessage({text:initialChallenge}),signingKeys:await openpgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true});
 const initialAuth = await context.request.post(base+'/api/authenticate_identity',{data:{challenge:initialChallenge,detached_signature:initialSignature,identity_id:'openpgp:'+fingerprint}});
 assert.equal(initialAuth.status(),200,await initialAuth.text());
 const page=await context.newPage();const errors=[],expectedOfflineErrors=[];page.on('pageerror',e=>{if(process.env.IMAGE_BROWSER==='webkit' && e.message.includes('due to access control checks.')) expectedOfflineErrors.push(e.message);else errors.push(e.message);});


 const geometry = () => page.locator('.compose-form-actions').evaluate(actions =>
   [actions, ...actions.querySelectorAll(':scope > button, :scope > details > summary')]
     .map(node => { const {x,y,width,height}=node.getBoundingClientRect(); return {x,y,width,height}; }));
 let cases=0;
 for (const width of [390, 1280]) {
   await page.setViewportSize({width,height:1000});
   for (const [theme, images, approved] of [
     ['light',true,true], ['word97',true,true], ['chicago',true,true], ['midnight',true,true],
     ['light',true,false], ['light',false,false]
   ]) {
     await setFlags({images});
     // The layout bootstrap migrates this legacy preference on first load.
     await page.addInitScript(theme => {
       localStorage.setItem('zenmemes-theme',theme);
       for(const key of Object.keys(localStorage)) if(key.endsWith('-theme')) localStorage.setItem(key,theme);
     }, theme);
     await page.route('**/api/get_profile?*', route => route.fulfill({status:200,body:
       'Identity-ID: openpgp:'+fingerprint+'\nApproved: '+(approved?'yes':'no')+'\nImage-Upload-Allowed: '+(approved?'yes':'no')+'\n'}));
     for (const path of ['/compose/thread','/compose/reply']) {
       let release; const gate = new Promise(resolve => {release=resolve;});
       await page.route('**/assets/*.js*', async route => {await gate; await route.continue();});
       try {
         await page.goto(base+path,{waitUntil:'commit'});
         await page.locator('.compose-form-actions').waitFor();
         await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
         assert.equal(await page.locator('html').getAttribute('data-theme'),theme);
         const before=await geometry();
         if(path === '/compose/thread') {
           const [, submit, menu] = before;
           assert.equal(menu.y, submit.y, `${theme} ${width}px: menu and submit top edges align`);
           assert.equal(menu.height, submit.height, `${theme} ${width}px: menu and submit heights match`);
           assert.ok(menu.x >= submit.x + submit.width, 'Menu stays beside the submit button');
         }
         assert.equal(await page.locator('[data-action="save-compose-outbox"]').isDisabled(),true);
         release();
         await page.waitForLoadState('networkidle');
         if (images) {
           await page.waitForFunction(() => typeof document.querySelector('[data-compose-form]').__imageEligible === 'boolean');
           assert.equal(await page.locator('.image-upload-choose').evaluate(button => !button.hidden),approved);
           await page.locator('.compose-action-menu-toggle').click();
           assert.equal(await page.locator('.image-upload-choose').isVisible(),approved);
           await page.locator('.compose-action-menu-toggle').click();
         } else assert.equal(await page.locator('.image-compose-fields').count(),0);
         assert.deepEqual(await geometry(),before,`${path}: ${theme}, ${width}px, images=${images}, approved=${approved}`);
         assert.equal(await page.locator('[data-action="save-compose-outbox"]').isEnabled(),true);
         // Keep the status visible, but outside the region that moves the actions.
         const status=page.locator('[data-role="compose-identity-status"]');
         assert.equal(await status.isVisible(),true);
         assert.ok((await status.boundingBox()).y >= before[0].y+before[0].height);
         cases++;
         console.log(`PASS ${path} ${theme} ${width}px images=${images} approved=${approved}`);
       } finally {
         release();
         await page.unroute('**/assets/*.js*');
       }
     }
     await page.unroute('**/api/get_profile?*');
   }
 }
 // Exercise the server-rendered buttons with their actual Outbox bindings.
 for (const path of ['/compose/thread','/compose/reply']) {
   await page.goto(base+path);
   await page.locator('[name="body"]').fill('Layout regression draft');
   if(path.endsWith('reply')) await page.locator('[name="thread_id"]').evaluate(input => {input.value='layout-test-thread';});
   const menu=page.locator('.compose-action-menu > summary');
   if(await menu.count()) await menu.click();
   await page.locator('[data-action="save-compose-outbox"]').click();
   await page.waitForFunction(() => document.querySelector('[data-role="outbox-compose-status"]').textContent.includes('draft saved'));
 }
 assert.deepEqual(errors,[]);
 console.log(`PASS compose first-paint geometry: ${cases} route/theme/viewport/permission cases; both Outbox saves`);
} finally {
 if(browser) await browser.close();
 server.kill();
 await once(server,'exit');
 await rm(root,{recursive:true,force:true});
}
