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
 for (const width of (process.argv.includes('--shared-only') ? [] : [390, 1280])) {
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
         // Routine readiness leaves no visible status or reserved layout space.
         const status=page.locator('[data-role="compose-identity-status"]');
         assert.equal(await status.isVisible(),false);
         assert.equal(await status.textContent(),'');
         assert.equal(await status.boundingBox(),null);
         await page.locator('[name="body"]').focus();
         assert.equal(await status.isVisible(),false);
         // Exercise shared pending/failure/recovery rendering in the real DOM.
         await page.evaluate(() => {
           const root=document.querySelector('[data-compose-root]');
           window.__forumBrowserIdentity.renderIdentityPreparationState(root,'publishing');
         });
         assert.equal(await status.isVisible(),true);
         assert.match(await status.textContent(),/Publishing/);
         await page.evaluate(() => window.__forumBrowserIdentity.renderIdentityPreparationState(
           document.querySelector('[data-compose-root]'),'failed',
           {message:'Retry identity preparation.',technicalDetails:'Test publication failure'}));
         assert.equal(await status.isVisible(),true);
         await status.locator('[data-role="browser-key-status-technical-toggle"]').click();
         assert.equal(await status.locator('[data-role="browser-key-status-technical-details"]').isVisible(),true);
         await page.evaluate(() => window.__forumBrowserIdentity.renderIdentityPreparationState(
           document.querySelector('[data-compose-root]'),'ready'));
         assert.equal(await status.isVisible(),false);
         assert.doesNotMatch(await status.textContent(),/Test publication failure/);
         assert.deepEqual(await geometry(),before);
         assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),`${theme} ${width}px: no page overflow`);
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
 for (const path of (process.argv.includes('--shared-only') ? [] : ['/compose/thread','/compose/reply'])) {
   await page.goto(base+path);
   await page.locator('[name="body"]').fill('Layout regression draft');
   if(path.endsWith('reply')) await page.locator('[name="thread_id"]').evaluate(input => {input.value='layout-test-thread';});
   const menu=page.locator('.compose-action-menu > summary');
   if(await menu.count()) await menu.click();
   await page.locator('[data-action="save-compose-outbox"]').click();
   await page.waitForFunction(() => document.querySelector('[data-role="outbox-compose-status"]').textContent.includes('draft saved'));
 }
 // A real signed post supplies the shared thread/inline-reply fixture.
 await page.goto(base+'/compose/thread');
 await page.locator('[name="body"]').fill('Identity readiness verification thread');
 await page.locator('.compose-form-actions > button').click();
 await page.waitForURL(/\/threads\//);
 const threadPath=new URL(page.url()).pathname;
 for (const width of [390,1280]) {
   await page.setViewportSize({width,height:1000});
   for (const theme of ['light','word97','chicago','midnight']) {
     await page.addInitScript(theme => {
       localStorage.setItem('zenmemes-theme',theme);
       for(const key of Object.keys(localStorage)) if(key.endsWith('-theme')) localStorage.setItem(key,theme);
     },theme);
     for (const [site,path] of [['zenmemes','/'],['zenmemes',threadPath],['zenmemes','/forte'],['qdb','/add']]) {
       await setFlags({site});
       await page.goto(base+path);
       if(path===threadPath && await page.locator('[data-inline-reply-trigger]').isVisible())
         await page.locator('[data-inline-reply-trigger]').click();
       if(path==='/forte') await page.locator('[data-paned-board-new]').click();
       const editor=page.locator('[data-compose-root] textarea[name="body"]:visible').first();
       await editor.fill('Draft remains intact');
       await page.waitForFunction(() => window.__forumBrowserIdentity &&
         [...document.querySelectorAll('[data-role="compose-identity-status"]')].length > 0 &&
         [...document.querySelectorAll('[data-role="compose-identity-status"]')].every(node=>node.hidden && node.textContent===''));
       assert.equal(await editor.inputValue(),'Draft remains intact');
       assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),`${site}${path} ${theme} ${width}px: no overflow`);
       if(process.env.IDENTITY_SCREENSHOT_DIR && width===390 && theme==='midnight' && path===threadPath)
         await page.screenshot({path:join(process.env.IDENTITY_SCREENSHOT_DIR,'identity-ready-thread-phone.png'),fullPage:true});
       console.log(`PASS shared readiness ${site}${path} ${theme} ${width}px`);
     }
   }
 }
 await setFlags({site:'zenmemes'});
 await page.goto(base+'/account/key/');
 await page.locator('[data-action="load-browser-key"]').evaluate(button=>button.click());
 await page.waitForFunction(() => document.querySelector('[data-role="browser-key-status-message"]').textContent.includes('Loaded the saved browser public key'));
 assert.equal(await page.locator('#simple-status').isVisible(),true,'Explicit Account result stays visible');
 await page.goto(base+threadPath);
 if(await page.locator('[data-inline-reply-trigger]').isVisible()) await page.locator('[data-inline-reply-trigger]').click();
 await page.locator('[data-compose-root] textarea[name="body"]').fill('Signed reply after readiness cleanup');
 await page.locator('[data-compose-root] button[type="submit"]').first().click();
 await page.getByText('Signed reply after readiness cleanup',{exact:true}).first().waitFor();
 assert.deepEqual(errors,[]);
 console.log(`PASS ${cases} standalone layout cases; 32 shared readiness cases; explicit Account outcome; signed thread and reply`);
} finally {
 if(browser) await browser.close();
 server.kill();
 await once(server,'exit');
 await rm(root,{recursive:true,force:true});
}
