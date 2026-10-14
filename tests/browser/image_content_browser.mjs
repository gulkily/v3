import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import { mkdtemp, writeFile, readFile, mkdir } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium, webkit } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'image-content-browser-'));
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
 browser=await (process.env.IMAGE_BROWSER==='webkit' ? webkit.launch({headless:true}) : chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']}));
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
 const outcomes=[]; let loseResponse=true;
 const webkitRun=process.env.IMAGE_BROWSER==='webkit';
 // WebKit's Playwright route.fetch does not faithfully replay binary multipart files.
 // Exercise native transport there; Chromium separately covers lost-response replay.
 if(!webkitRun) await page.route('**/api/create_prepared_post', async route=>{const response=await route.fetch();outcomes.push(await response.json());if(loseResponse){loseResponse=false;await route.abort();}else await route.fulfill({response});});
 await page.goto(base+'/compose/thread');
 await page.waitForFunction(()=>document.querySelector('.image-compose-fields')?.hidden===false);
 await page.route('**/api/get_profile?*',route=>route.fulfill({status:200,body:'Identity-ID: openpgp:'+fingerprint+'\nApproved: no\nImage-Upload-Allowed: no\n'}));
 await page.evaluate(()=>ForumImageUpload.refreshEligibility(document.querySelector('[data-compose-form]')));
 assert.equal(await page.locator('.image-compose-fields').isVisible(),false);
 await page.unroute('**/api/get_profile?*');
 await page.evaluate(()=>ForumImageUpload.refreshEligibility(document.querySelector('[data-compose-form]')));
 assert.equal(await page.locator('.image-compose-fields').isVisible(),true);
 await context.setOffline(true);
 await page.evaluate(()=>ForumImageUpload.refreshEligibility(document.querySelector('[data-compose-form]')));
 assert.equal(await page.locator('.image-compose-fields').isVisible(),false);
 await context.setOffline(false);
 await page.evaluate(()=>ForumImageUpload.refreshEligibility(document.querySelector('[data-compose-form]')));
 const guestContext=await browser.newContext();const guestPage=await guestContext.newPage();await guestPage.goto(base+'/compose/thread');
 assert.equal(await guestPage.locator('.image-compose-fields').isVisible(),false);await guestContext.close();
 const png=Buffer.from(await page.evaluate(()=>{const c=document.createElement('canvas');c.width=5000;c.height=2;c.getContext('2d').fillRect(0,0,2,2);return c.toDataURL('image/png').split(',')[1];}),'base64');
 await page.waitForFunction(()=>document.querySelector('[name="image"]')?.disabled===false);
 await page.evaluate(()=>window.ForumBrowserSigning.ensureActionIdentity());
 await page.locator('[name="body"]').fill('Browser image thread');
 await page.locator('[name="subject"]').fill('Image acceptance');
 await page.locator('[name="image_description"]').fill('Two dark pixels & <caption>');
 assert.equal(await page.locator('[name="image_description"]').inputValue(),'Two dark pixels & <caption>', 'description after fill');
 await page.waitForFunction(()=>document.querySelector('[name="image"]')?.disabled===false);
 await page.setViewportSize({width:390,height:844});
 const choose=page.locator('.image-upload-choose');await choose.focus();
 assert.equal(await choose.evaluate(e=>document.activeElement===e),true);
 const target=await choose.boundingBox();assert.ok(target.width>=44 && target.height>=44);
 for(const method of ['drop','paste']) {
   await page.evaluate(({bytes,method})=>{
     const data=new DataTransfer();data.items.add(new File([Uint8Array.from(bytes)],'pixel.png',{type:'image/png'}));
     const form=document.querySelector('[data-compose-form]');
     form.dispatchEvent(method==='drop' ? new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:data}) : new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:data}));
   },{bytes:[...png],method});
   await page.waitForFunction(()=>Boolean(document.querySelector('[data-compose-form]').__imageState?.file));
   assert.equal(await page.locator('.image-upload-preview').isVisible(),true);
   await page.locator('.image-upload-remove').click();
   assert.equal(await page.locator('.image-upload-preview').isVisible(),false);
 }
 assert.equal(await page.evaluate(()=>{const data=new DataTransfer();data.setData('text/plain','ordinary text');const e=new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:data});document.querySelector('[name="body"]').dispatchEvent(e);return e.defaultPrevented;}),false);
 await page.setViewportSize({width:1280,height:900});
 await page.locator('[name="image"]').setInputFiles({name:'pixel.png',mimeType:'image/png',buffer:png});
 const preparedPng=Buffer.from(await page.evaluate(async()=>Array.from(new Uint8Array(await (await ForumImageUpload.ready(document.querySelector('[data-compose-form]'))).file.arrayBuffer()))));
 // Send correctly signed requests that bypass all browser preparation.
 const wide=Buffer.from(await page.evaluate(()=>{const c=document.createElement('canvas');c.width=2049;c.height=1;return c.toDataURL('image/png').split(',')[1];}),'base64');
 const chunk=Buffer.alloc(20);chunk.writeUInt32BE(8,0);chunk.write('acTL',4);chunk.writeUInt32BE(2,8);
 let crc=0xffffffff;for(const byte of chunk.subarray(4,16)){crc^=byte;for(let bit=0;bit<8;bit++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}chunk.writeUInt32BE((crc^0xffffffff)>>>0,16);
 const animated=Buffer.concat([preparedPng.subarray(0,33),chunk,preparedPng.subarray(33)]);
 await page.screenshot({path:join(root,'prepared-desktop.png')});
 await page.setViewportSize({width:390,height:844});await page.screenshot({path:join(root,'prepared-mobile.png'),fullPage:true});await page.setViewportSize({width:1280,height:900});
 const before=command('git',['-C',join(root,'repository'),'rev-parse','HEAD']);
 for(const bad of [
   {bytes:wide,width:2049,mime:'image/png'},
   {bytes:preparedPng.subarray(0,-6),mime:'image/png'},
   {bytes:animated,mime:'image/png'},
   {bytes:preparedPng,mime:'image/jpeg'},
   {bytes:preparedPng,mime:'image/png',hash:'0'.repeat(64)},
   {bytes:Buffer.alloc(5*1024*1024+1),mime:'image/png',referenceBytes:preparedPng}
 ]) {
   const referenceBytes=bad.referenceBytes||bad.bytes;
   const hash=bad.hash||createHash('sha256').update(referenceBytes).digest('hex');
   const preparedResponse=await context.request.post(base+'/api/prepare_thread',{data:{author_identity_id:'openpgp:'+fingerprint,subject:'Rejected upload',board_tags:'general',body:'Image-v1: '+[hash,'png',referenceBytes.length,bad.width||2048,1].join(' ')}});
   assert.equal(preparedResponse.status(),200,await preparedResponse.text());const prepared=await preparedResponse.json();
   const signature=await openpgp.sign({message:await openpgp.createMessage({text:prepared.canonical_record}),signingKeys:await openpgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true});
   const response=await context.request.post(base+'/api/create_prepared_post',{multipart:{prepare_token:prepared.prepare_token,post_id:prepared.post_id,record_path:prepared.record_path,author_identity_id:'openpgp:'+fingerprint,canonical_record_base64:Buffer.from(prepared.canonical_record).toString('base64'),detached_signature:signature,image:{name:'image.png',mimeType:bad.mime,buffer:bad.bytes}}});
   assert.equal(response.status(),400,await response.text());
   assert.equal(command('git',['-C',join(root,'repository'),'rev-parse','HEAD']),before);
 }

 assert.equal(await page.locator('[name="image_description"]').inputValue(),'Two dark pixels & <caption>', 'description after file selection');
 // Anonymous must preserve the draft and refuse the attachment.
 await page.locator('[data-action="submit-anonymous-compose"]').click();
 await page.getByText('Image attachments require signed publication by an approved member.',{exact:false}).waitFor();
 assert.equal(outcomes.length,0);
 assert.match(page.url(),/\/compose\/thread/);
 assert.equal(await page.locator('[name="body"]').inputValue(),'Browser image thread');
 assert.equal(await page.locator('[name="image_description"]').inputValue(),'Two dark pixels & <caption>');
 if(!webkitRun) {
   await page.locator('[data-compose-form] button[type="submit"]').first().click();
   await page.getByText(/Failed to fetch|Load failed/).waitFor();
   assert.equal(await page.locator('[name="body"]').inputValue(),'Browser image thread');
   assert.equal(outcomes[0].status,'ok',JSON.stringify(outcomes[0]));
 }
 const publication=page.waitForResponse(r=>r.url().endsWith('/api/create_prepared_post')).then(r=>webkitRun?r.json():outcomes.at(-1));
 await page.locator('[data-compose-form] button[type="submit"]').first().click();
 const thread=await publication;assert.equal(thread.status,'ok',JSON.stringify(thread));
 if(!webkitRun) assert.equal(thread.post_id,outcomes[0].post_id);
 await page.waitForURL(/\/threads\//);
 const image=page.locator('.post-image img').first();await image.waitFor();
 await image.evaluate(e=>e.decode()); assert.equal(await image.evaluate(e=>e.naturalWidth),2048);
 const imageUrl=await image.getAttribute('src');
 const imageResponse=await context.request.get(base+imageUrl);assert.equal(imageResponse.status(),200);assert.match(imageResponse.headers()['cache-control'],/no-store/);assert.equal(imageResponse.headers()['x-content-type-options'],'nosniff');assert.deepEqual(await imageResponse.body(),preparedPng);
 await page.goto(base+`/compose/reply?thread_id=${thread.thread_id}&parent_id=${thread.post_id}`);
 await page.waitForFunction(()=>document.querySelector('[name="image"]')?.disabled===false);
 await page.locator('[name="body"]').fill('Browser image reply');assert.equal(await page.locator('[name="image_description"]').inputValue(),'');
 await page.waitForFunction(()=>document.querySelector('[name="image"]')?.disabled===false);
 await page.locator('[name="image"]').setInputFiles({name:'pixel.png',mimeType:'image/png',buffer:png});
 const replyResponse=page.waitForResponse(r=>r.url().endsWith('/api/create_prepared_post')).then(r=>webkitRun?r.json():outcomes.at(-1));
 await page.locator('[data-compose-form] button[type="submit"]').first().click();
 const reply=await replyResponse;assert.equal(reply.status,'ok',JSON.stringify(reply));
 await page.waitForURL(/\/threads\//);
 await page.goto(base+'/tools/backup/');
 let imagesHref=await page.locator('a[href^="/downloads/images.tar"]').getAttribute('href');
 const repoHref=await page.locator('a[href^="/downloads/repository.tar.gz"]').getAttribute('href');
 const images=await context.request.get(base+imagesHref),repository=await context.request.get(base+repoHref);
 assert.equal(images.status(),200,await images.text());assert.equal(repository.status(),200);
 await writeFile(join(root,'images.tar'),await images.body());await writeFile(join(root,'repository.tar.gz'),await repository.body());
 await mkdir(join(root,'restore'));
 command('tar',['-xzf',join(root,'repository.tar.gz'),'-C',join(root,'restore')]);
 const restoredRepo=join(root,'restore/repository'),restoredDb=join(root,'restore/read.sqlite3');
 command('./v3',['images','verify',join(root,'images.tar'),restoredRepo,restoredDb]);
 command('./v3',['images','restore',join(root,'images.tar'),restoredRepo,restoredDb]);
 command('./v3',['rebuild',restoredRepo,restoredDb]);
 assert.deepEqual(await readFile(join(root,'restore/repository-images',imageUrl.split('/').pop())),preparedPng);
 await setFlags({restored:true});
 await page.goto(base+'/threads/'+thread.thread_id);
 await page.locator('.post-image img').first().evaluate(e=>e.decode());
 assert.equal(await page.locator('.post-image img').count(),2);
 assert.match(await (await context.request.get(base+'/api/get_post?post_id='+reply.post_id)).text(),/Image-v1:/);
 await page.goto(base+'/forte?selected='+thread.thread_id);
 await page.locator('.post-image img').first().waitFor();
 await page.locator('[data-paned-board-reply]').click();
 const forteReply=page.locator('[data-paned-compose-panel]');
 await forteReply.locator('.image-upload-choose').waitFor();
 await forteReply.locator('[name="image"]').setInputFiles({name:'pixel.png',mimeType:'image/png',buffer:png});
 await forteReply.locator('.image-upload-preview img').waitFor();
 await page.locator('[data-paned-board-new]').click();
 const forteNew=page.locator('[data-paned-new-thread-dialog]');await forteNew.locator('.image-upload-choose').waitFor();
 assert.equal(await forteNew.locator('.image-upload-preview').isVisible(),false);
 assert.equal(await forteReply.locator('.image-upload-preview').isVisible(),true);
 await forteNew.locator('[data-paned-new-thread-cancel]').click();
 await setFlags({site:'qdb'});
 assert.equal((await context.request.get(base+imageUrl)).status(),200);
 assert.match(await (await context.request.get(base+'/threads/'+thread.thread_id)).text(),/post-image/);
 await page.goto(base+'/add');
 await page.locator('.image-upload-choose').waitFor();
 await page.locator('[name="body"]').fill('<alice> A quote with an image');
 await page.locator('[name="image"]').setInputFiles({name:'pixel.png',mimeType:'image/png',buffer:png});
 const qdbResponse=page.waitForResponse(r=>r.url().endsWith('/api/create_prepared_post')).then(r=>webkitRun?r.json():outcomes.at(-1));
 await page.locator('[data-compose-form] button[type="submit"]').first().click();
 const qdbResult=await qdbResponse;assert.equal(qdbResult.status,'ok',JSON.stringify(qdbResult));
 await page.waitForURL(/\/threads\//);
 await setFlags({site:'zenmemes'});
 await page.goto(base+'/tools/backup/');imagesHref=await page.locator('a[href^="/downloads/images.tar"]').getAttribute('href');
 await setFlags({images:false});
 assert.equal((await context.request.get(base+imageUrl)).status(),404);
 assert.equal((await context.request.get(base+imagesHref)).status(),200);
 await page.goto(base+'/compose/thread');assert.equal(await page.locator('[name="image"]').count(),0);
 await setFlags({images:true,members:true});
 const anonymous=await browser.newContext();
 assert.notEqual((await anonymous.request.get(base+imageUrl)).status(),200);
 assert.notEqual((await anonymous.request.get(base+imagesHref)).status(),200);
 const challengeText=await (await context.request.get(base+'/api/auth_challenge')).text();
 const challenge=challengeText.match(/challenge=(\w+)/)[1];
 const signature=await openpgp.sign({message:await openpgp.createMessage({text:challenge}), signingKeys:await openpgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true});
 const authenticated=await context.request.post(base+'/api/authenticate_identity',{data:{challenge,detached_signature:signature,identity_id:'openpgp:'+fingerprint}});
 assert.equal(authenticated.status(),200,await authenticated.text());
 assert.equal((await context.request.get(base+imageUrl)).status(),200);
 assert.equal((await context.request.get(base+imagesHref)).status(),200);
 await page.goto(base+'/compose/thread');await page.waitForFunction(()=>document.querySelector('.image-compose-fields')?.hidden===false);
 await anonymous.close();
 assert.deepEqual(errors,[]);
 console.log(JSON.stringify({status:'passed',root,expectedOfflineErrors:expectedOfflineErrors.length,thread:thread.post_id,reply:reply.post_id,checks:['browser signed thread/reply',webkitRun?'native WebKit multipart upload':'lost-response retry without duplication','fresh-instance/Forte/QDB rendering','anonymous draft preservation','verified image delivery','revision-matched backups','CLI verify/restore/rebuild','identical restored bytes','flag off','member access']},null,2));
} catch(error){ if (browser) {const p=browser.contexts()[0]?.pages()[0]; if(p) {console.error('Page:',p.url(),await p.locator('[data-role="compose-identity-status"]').allTextContents());await p.screenshot({path:join(root,'failure.png')});}} console.error('Fixture:',root,'\nServer log tail:',log.slice(-10000));throw error;}
finally{if(browser)await browser.close();server.kill('SIGTERM');await once(server,'exit');}
