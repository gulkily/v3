import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm, mkdir } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium, webkit, firefox } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'reply-viewport-flow-'));
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
 browser=process.env.REPLY_BROWSER==='webkit' ? await webkit.launch({headless:true}) : process.env.REPLY_BROWSER==='firefox' ? await firefox.launch({headless:true}) : await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1280,height:900}});
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
 const page=await context.newPage();page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(60000);const errors=[],expectedNavigationErrors=[];page.on('pageerror',e=>{if(process.env.REPLY_BROWSER==='webkit' && e.message.includes('due to access control checks.')) expectedNavigationErrors.push(e.message);else errors.push(e.message);});


 await setFlags({images:false});
 await page.goto(base+'/compose/thread');
 await page.locator('[name="body"]').fill('Reply continuation fixture');
 await page.locator('.compose-form-actions > button').click();
 await page.waitForURL(/\/threads\//);
 const threadUrl=base+new URL(page.url()).pathname;

 await page.goto(threadUrl);
 const form=page.locator('[data-inline-reply-compose] form');
 const body=form.locator('[name="body"]');
 let release;const held=new Promise(resolve=>release=resolve);
 await page.route('**/api/prepare_reply',async route=>{await held;await route.continue();});
 await body.fill('First immediate reply');
 await form.locator('.compose-form-actions > button').click();
 await page.locator('[data-reply-operation]').waitFor();
 assert.equal(await body.inputValue(),'');
 assert.equal(await body.evaluate(el=>el===document.activeElement),true);
 await body.fill('Newer untouched draft');
 release();await page.locator('[data-reply-state="accepted"]').waitFor();
 assert.equal(await body.inputValue(),'Newer untouched draft');
 assert.equal(page.url(),threadUrl);
 await page.unroute('**/api/prepare_reply');
 await page.route('**/api/prepare_reply',route=>route.abort(),{times:1});
 await body.fill('Failed captured reply');
 await form.locator('.compose-form-actions > button').click();
 const failed=page.locator('[data-reply-state="failed"]');await failed.waitFor();
 await body.fill('Next draft survives retry');
 await failed.getByRole('button',{name:'Retry',exact:true}).click();
 await page.waitForFunction(()=>document.querySelectorAll('[data-reply-state="accepted"]').length===2);
 assert.equal(await body.inputValue(),'Next draft survives retry');
 // Multiple intentional sends proceed while prior requests are pending.
 const heldFinalizers=[];
 await page.route('**/api/create_prepared_post',route=>new Promise(resolve=>heldFinalizers.push(async()=>{await route.continue();resolve();})));
 for(let i=0;i<2;i++) {
  await body.fill('Identical intentional reply');await form.locator('.compose-form-actions > button').click();
 }
 await page.waitForFunction(()=>document.querySelectorAll('[data-reply-operation]').length===4);
 for(let i=0;i<100 && heldFinalizers.length<2;i++)await new Promise(r=>setTimeout(r,50));
 assert.equal(heldFinalizers.length,2);await heldFinalizers[1]();await heldFinalizers[0]();
 await page.waitForFunction(()=>document.querySelectorAll('[data-reply-state="accepted"]').length===4);
 await page.unroute('**/api/create_prepared_post');
 assert.deepEqual(await page.locator('[data-reply-operation] .body').allTextContents(),['First immediate reply','Failed captured reply','Identical intentional reply','Identical intentional reply']);
 // A lost accepted response must reconcile after reload without another post.
 await page.route('**/api/create_prepared_post',async route=>{await route.fetch();await route.abort();},{times:1});
 await body.fill('Lost-response reply');await form.locator('.compose-form-actions > button').click();
 await page.locator('[data-reply-state="failed"]').waitFor();
 await body.fill('Draft through reload');await page.reload();
 const restored=page.locator('[data-reply-state="failed"]');await restored.waitFor();
 assert.equal(await body.inputValue(),'Draft through reload');
 let repeatFinalize=0;await page.route('**/api/create_prepared_post',route=>{repeatFinalize++;return route.continue();});
 await restored.getByRole('button',{name:'Retry',exact:true}).click();
 await page.waitForFunction(()=>!document.querySelector('[data-reply-state="failed"]'));
 assert.equal(repeatFinalize,0,'known accepted operation reconciles without finalizing');
 assert.equal(await body.inputValue(),'Draft through reload');
 await page.reload();assert.equal(await page.locator('.post-card').filter({hasText:'Lost-response reply'}).count(),1);
 // Two tabs recover the same not-yet-prepared operation under a shared lock.
 await page.route('**/api/prepare_reply',route=>route.abort(),{times:1});
 await body.fill('Two-tab recovered reply');await form.locator('.compose-form-actions > button').click();await page.locator('[data-reply-state="failed"]').waitFor();
 const second=await context.newPage();await second.goto(threadUrl);
 const secondRetry=second.locator('[data-reply-state="failed"] button');await secondRetry.waitFor();
 await Promise.all([page.locator('[data-reply-state="failed"] button').evaluate(el=>el.click()),secondRetry.evaluate(el=>el.click())]);
 await page.locator('[data-reply-state="accepted"]').waitFor();await second.close();
 await page.reload();assert.equal(await page.locator('.post-card').filter({hasText:'Two-tab recovered reply'}).count(),1);
 await body.fill('Next draft survives retry');
 // Storage errors leave the original editable copy intact.
 await page.evaluate(()=>{window.originalStorageSet=Storage.prototype.setItem;Storage.prototype.setItem=function(k,v){if(k.startsWith('forum-reply-continuation:'))throw new Error('Storage full');return originalStorageSet.call(this,k,v);};});
 await form.locator('.compose-form-actions > button').click();
 await page.locator('[data-kind="error"]').waitFor();
 assert.equal(await body.inputValue(),'Next draft survives retry');
 await page.evaluate(()=>{Storage.prototype.setItem=originalStorageSet;});
 await setFlags({images:true});await page.goto(threadUrl);
 await form.locator('.image-compose-fields:not([hidden])').waitFor();
 const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC','base64');
 let releaseImages;const imageGate=new Promise(resolve=>releaseImages=resolve);
 await page.unroute('**/api/create_prepared_post');
 await page.route('**/api/create_prepared_post',async route=>{await imageGate;await route.continue();});
 for(let i=0;i<2;i++) {
  await form.locator('[name="image"]').setInputFiles({name:'test.png',mimeType:'image/png',buffer:png});
  await form.locator('.image-upload-preview img').waitFor();
  await body.fill('Captured image '+i);
  await form.locator('[name="image_description"]').fill('Image description '+i);
  await form.locator('.compose-form-actions > button').click();
  assert.equal(await body.inputValue(),'');
  assert.equal(await form.locator('.image-upload-preview').isVisible(),false);
 }
 await body.fill('Newer image draft');
 releaseImages();await page.waitForFunction(()=>document.querySelectorAll('[data-reply-state="accepted"]').length===2);
 assert.equal(await body.inputValue(),'Newer image draft');
 await page.unroute('**/api/create_prepared_post');
 await page.route('**/api/create_prepared_post',async route=>{await route.fetch();await route.abort();},{times:1});
 await form.locator('[name="image"]').setInputFiles({name:'retry.png',mimeType:'image/png',buffer:png});
 await form.locator('.image-upload-preview img').waitFor();await body.fill('Image lost response');
 await form.locator('.compose-form-actions > button').click();await page.locator('[data-reply-state="failed"]').waitFor();
 await body.fill('Image recovery newer draft');await page.reload();
 await page.locator('[data-reply-state="failed"] img').waitFor();
 await page.locator('[data-reply-state="failed"] button').click();
 await page.waitForFunction(()=>!document.querySelector('[data-reply-state="failed"]'));
 assert.equal(await body.inputValue(),'Image recovery newer draft');
 await page.reload();assert.equal(await page.locator('.post-card').filter({hasText:'Image lost response'}).count(),1);
 assert.equal(await page.locator('.post-card').filter({hasText:'Captured image 0'}).locator('.post-image img').count(),1);
 // Failed blob persistence retains its memory copy and exposes recovery.
 await page.evaluate(()=>{window.oldIdbOpen=IDBFactory.prototype.open;IDBFactory.prototype.open=function(name,...args){if(name==='forum-reply-attachments-v1')throw new Error('Quota exceeded');return oldIdbOpen.call(this,name,...args);};});
 await form.locator('.image-compose-fields:not([hidden])').waitFor();
 await form.locator('[name="image"]').setInputFiles({name:'quota.png',mimeType:'image/png',buffer:png});
 await form.locator('.image-upload-preview img').waitFor();await body.fill('Image storage recovery');
 await form.locator('.compose-form-actions > button').click();
 await page.locator('[data-reply-state="failed"]').waitFor();
 assert.match(await page.locator('[data-reply-state="failed"]').textContent(),/Keep this page open/);
 await body.fill('Draft after image storage failure');
 await page.evaluate(()=>{IDBFactory.prototype.open=oldIdbOpen;});
 await page.locator('[data-reply-state="failed"] button').click();await page.locator('[data-reply-state="accepted"]').waitFor();
 assert.equal(await body.inputValue(),'Draft after image storage failure');
 // Dedicated entry retains its parent and allows consecutive sends in place.
 const threadId=new URL(threadUrl).pathname.split('/').pop();
 await page.goto(base+'/compose/reply?thread_id='+threadId+'&parent_id='+threadId);
 const dedicated=page.locator('[data-phone-reply]');const dedicatedBody=dedicated.locator('[name="body"]');
 const dedicatedUrl=page.url();
 const acceptedBefore=await page.locator('[data-reply-state="accepted"]').count();
 for(let i=0;i<2;i++) {
  await dedicatedBody.fill('Dedicated continuation '+i);
  await dedicatedBody.scrollIntoViewIfNeeded();
  const before=await dedicatedBody.boundingBox();
  await dedicated.evaluate(el=>el.requestSubmit(el.querySelector('.compose-form-actions > button')));
  await page.waitForFunction(n=>document.querySelectorAll('[data-reply-state="accepted"]').length===n,acceptedBefore+i+1);
  assert.equal(await dedicatedBody.inputValue(),'');
  assert.equal(await dedicatedBody.evaluate(el=>document.activeElement===el),true);
  assert.equal(await dedicated.locator('[name="parent_id"]').inputValue(),threadId);
  assert.ok(Math.abs((await dedicatedBody.boundingBox()).y-before.y)<3,'editor retains its desktop writing position');
 }
 assert.equal(page.url(),dedicatedUrl);
 await dedicatedBody.fill('Long newer draft '+('word '.repeat(300)));
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 const phoneContext=await browser.newContext({storageState:await context.storageState(),serviceWorkers:'block',hasTouch:true,viewport:{width:390,height:844}});
 const phonePage=await phoneContext.newPage();phonePage.on('pageerror',e=>errors.push(e.message));
 if(process.env.REPLY_CONTINUATION_SCREENSHOTS)await mkdir(process.env.REPLY_CONTINUATION_SCREENSHOTS,{recursive:true});
 for(const entry of ['inline','dedicated'])for(const theme of ['light','word97','chicago','midnight']) {
  await phonePage.goto(entry==='inline'?threadUrl:dedicatedUrl);
  await phonePage.evaluate(t=>document.documentElement.dataset.theme=t,theme);
  await phonePage.addStyleTag({content:readFileSync(join(project,'public/assets/theme-'+theme+'.css'),'utf8')});
  const pf=phonePage.locator('[data-phone-reply]'),pb=pf.locator('[name="body"]');
  let resume;const gate=new Promise(resolve=>resume=resolve);
  await phonePage.route('**/api/prepare_reply',async route=>{await gate;await route.continue();});
  await pb.fill('Phone '+entry+' '+theme+' visible previous reply');
  await pf.evaluate(el=>el.requestSubmit(el.querySelector('.compose-form-actions > button')));
  await phonePage.locator('[data-reply-reading="1"]').waitFor();
  await phonePage.waitForFunction(()=>document.querySelector('[data-reply-reading="1"]').getBoundingClientRect().bottom<=innerHeight);
  assert.equal(await pb.inputValue(),'');assert.equal(await pb.evaluate(el=>el===document.activeElement),false);
  await phonePage.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  const reading=await phonePage.locator('[data-reply-reading="1"]').boundingBox();
  assert.ok(reading.y>=0 && reading.y+reading.height<=844,'compact phone entry stays within viewport');
  const card=phonePage.locator('[data-reply-operation]').last();
  const cardBox=await card.boundingBox();
  if(process.env.REPLY_CONTINUATION_SCREENSHOTS)await phonePage.screenshot({path:process.env.REPLY_CONTINUATION_SCREENSHOTS+'/'+entry+'-'+theme+'-reading.png'});
  assert.ok(cardBox.y+cardBox.height<=reading.y+2 && cardBox.y+cardBox.height>0,'previous reply visible above compact entry '+JSON.stringify({theme,entry,cardBox,reading}));
  assert.equal(await phonePage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  if(process.env.REPLY_CONTINUATION_SCREENSHOTS)await phonePage.screenshot({path:process.env.REPLY_CONTINUATION_SCREENSHOTS+'/'+entry+'-'+theme+'-reading.png'});
  await pb.tap();assert.equal(await pb.evaluate(el=>el===document.activeElement),true);
  assert.equal(await phonePage.locator('[data-reply-reading="1"]').count(),0);
  await pb.fill('Next phone draft');resume();
  await card.locator('a').waitFor();assert.equal(await pb.inputValue(),'Next phone draft');
  await phonePage.unroute('**/api/prepare_reply');
 }
 await phoneContext.close();
 assert.deepEqual(errors,[]);
 console.log('PASS immediate text reply before network, focus, captured failure/retry, newer draft isolation, storage failure');
} catch(error){console.error(log);throw error;} finally {await browser?.close();server.kill();await once(server,'exit').catch(()=>{});await rm(root,{recursive:true,force:true});}
