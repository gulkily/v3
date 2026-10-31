import assert from 'node:assert/strict';
import {mkdtemp,writeFile,readFile} from 'node:fs/promises';
import {readFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {runInThisContext} from 'node:vm';
import {spawn,spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {once} from 'node:events';
import {chromium} from 'playwright-core';
const project=resolve('.'),root=await mkdtemp(join(tmpdir(),'tweet-image-browser-'));
runInThisContext(readFileSync('public/assets/openpgp.min.js','utf8'));
const pgp=globalThis.openpgp;
const keys=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'Tweet image'}],format:'armored'});
const fingerprint=(await pgp.readKey({armoredKey:keys.publicKey})).getFingerprint();
const env={...process.env,IMAGE_TEST_ROOT:root,FORUM_SECRETS_PATH:root+'/unused.php',FORUM_STATIC_HTML_ROOT:root+'/static',FORUM_PUBLIC_ARTIFACT_ROOT:root+'/artifacts',FAST_SCORING_ENABLED:'false',FORUM_APP_VERSION_NOTIFICATION:'false'};
const result=spawnSync('php',['tests/Support/image_browser_fixture.php',root],{env,input:JSON.stringify({publicKey:keys.publicKey}),encoding:'utf8'});assert.equal(result.status,0,result.stderr);
let flags={images:true,members:false};const setFlags=async changes=>{Object.assign(flags,changes);await writeFile(root+'/flags.json',JSON.stringify(flags))};await setFlags({});
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));const base='http://127.0.0.1:'+port;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-d','upload_max_filesize=6M','-d','post_max_size=8M','-S',`127.0.0.1:${port}`,'tests/Support/image_browser_router.php'],{cwd:project,env});let log='';server.stderr.on('data',b=>log+=b);
let browser;
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC','base64');
const photo='https://pbs.twimg.com/media/First?format=png&name=small',second='https://pbs.twimg.com/media/Second?format=png&name=small';
let sequence=0;
const destination=(url=photo)=>base+'/compose/thread?clip_case='+(++sequence)+'&subject=Clipped&body='+encodeURIComponent('Captured tweet\n'+photo+'\n'+second)+'#tweet-image='+encodeURIComponent(url);
try {
 for(let i=0;i<100;i++){try{await fetch(base+'/api/version');break}catch{await new Promise(r=>setTimeout(r,50))}}
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({serviceWorkers:'block'});
 await context.addInitScript(({keys,fingerprint,base})=>{if(location.origin!==base)return;localStorage.setItem('forum_pki_public_key',keys.publicKey);localStorage.setItem('forum_pki_private_key',keys.privateKey);localStorage.setItem('forum_pki_fingerprint',fingerprint);localStorage.setItem('forum_pki_approved_fingerprint',fingerprint)}, {keys,fingerprint,base});
 const challenge=(await (await context.request.get(base+'/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
 const signature=await pgp.sign({message:await pgp.createMessage({text:challenge}),signingKeys:await pgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true});
 const auth=await context.request.post(base+'/api/authenticate_identity',{data:{challenge,detached_signature:signature,identity_id:'openpgp:'+fingerprint}});assert.equal(auth.status(),200,await auth.text());
 let download='ok',gate=null,release=null,downloads=0;
 await context.route('https://pbs.twimg.com/**',async route=>{
   if(route.request().resourceType()==='image')return route.fulfill({contentType:'image/png',body:png});
   downloads++;
   const mode=download;
   if(gate)await gate;
   if(mode==='blocked')return route.abort('failed');
   if(mode==='redirect')return route.fulfill({status:302,headers:{location:'https://example.com/private'}});
   if(mode==='oversized')return route.fulfill({contentType:'image/png',headers:{'content-length':String(33*1024*1024)},body:png});
   return route.fulfill({contentType:'image/png',body:mode==='broken'?Buffer.from('not an image'):png});
 });
 await context.route('https://x.com/**',route=>route.fulfill({contentType:'text/html',body:`<!doctype html><article data-testid="tweet"><div data-testid="User-Name">Author<br>@author</div><div data-testid="tweetText">Captured tweet</div><a href="/author/status/123"><time>Today</time></a><a href="/author/status/123/photo/1"><img src="${photo}"></a><a href="/author/status/123/photo/2"><img src="${second}"></a></article>`}));
 const page=await context.newPage(),errors=[];context.on('page',p=>p.on('pageerror',e=>errors.push(e.message)));page.on('pageerror',e=>errors.push(e.message));
 const ready=async p=>{await p.waitForFunction(()=>Boolean(document.querySelector('[data-compose-form]')?.__imageState?.file) || document.querySelector('[data-tweet-image-retry]')?.hidden===false);assert(await p.evaluate(()=>Boolean(document.querySelector('[data-compose-form]')?.__imageState?.file)),await p.locator('[data-tweet-image-status]').textContent());await p.locator('[data-tweet-image-import]').waitFor({state:'hidden'})};
 const failed=async p=>{await p.locator('[data-tweet-image-retry]').waitFor({state:'visible'});assert(await p.locator('[data-tweet-image-import]').isVisible());assert.equal(await p.locator('[name="body"]').inputValue(),'Captured tweet\n'+photo+'\n'+second)};
 // Normal Tools entry → installed bookmarklet → selected tweet → normal composer, both modes.
 await page.goto(base+'/tools/bookmarklets/');await page.locator('[data-bookmarklet-kind="tweet"]').first().waitFor();
 const entries=await page.locator('[data-bookmarklet-kind="tweet"]').evaluateAll(es=>es.map(e=>({mode:e.dataset.bookmarkletMode,href:e.href})));
 assert.equal(entries.length,2);
 for(const entry of entries){
   console.log('Launch '+entry.mode);
   await page.goto('https://x.com/author/status/123');
   let dest=page;
   if(entry.mode==='new-window'){const popup=context.waitForEvent('page');await page.evaluate(code=>(0,eval)(code),decodeURIComponent(entry.href.slice(11)));dest=await popup;await dest.waitForLoadState('domcontentloaded')}
   else {await page.evaluate(code=>(0,eval)(code),decodeURIComponent(entry.href.slice(11)));await page.waitForURL(base+'/compose/thread**')}
   await ready(dest);console.log('Imported '+entry.mode);assert(await dest.locator('.image-upload-preview img').isVisible());const body=await dest.locator('[name="body"]').inputValue();assert(body.includes(photo)&&body.includes(second));
   if(dest!==page)await dest.close();
 }
 // Explicit prefill wins over an unrelated saved draft.
 await page.goto(base+'/compose/thread');await page.locator('[name="body"]').fill('Unrelated saved draft');await page.goto(destination());await ready(page);assert((await page.locator('[name="body"]').inputValue()).startsWith('Captured tweet'));
 // Pending/failed imports block native, anonymous and Outbox paths, then allow explicit removal.
 gate=new Promise(r=>release=r);download='ok';await page.goto(destination());await page.waitForFunction(()=>Boolean(document.querySelector('[data-compose-form]').__tweetImageImport?.controller));
 const blocked=await page.evaluate(()=>{const f=document.querySelector('[data-compose-form]');return [null,f.querySelector('[data-action="submit-anonymous-compose"]')].map(submitter=>{const e=new SubmitEvent('submit',{bubbles:true,cancelable:true,submitter});return !f.dispatchEvent(e)})});assert.deepEqual(blocked,[true,true]);
 await page.locator('.compose-action-menu-toggle').click();await page.locator('[data-action="save-compose-outbox"]').click();assert.match(await page.locator('[data-tweet-image-status]').textContent(),/Outbox/);
 await page.locator('[data-tweet-image-remove]').click();release();gate=null;await page.waitForTimeout(100);assert.equal(await page.evaluate(()=>Boolean(document.querySelector('[data-compose-form]').__imageState)),false);assert.equal(new URL(page.url()).hash,'');
 // Manual replacement while a download is pending supersedes late response.
 gate=new Promise(r=>release=r);await page.goto(destination());await page.waitForFunction(()=>Boolean(document.querySelector('[data-compose-form]').__tweetImageImport?.controller));await page.locator('[name="image"]').setInputFiles({name:'replacement.png',mimeType:'image/png',buffer:png});await ready(page);release();gate=null;assert.equal(await page.evaluate(()=>document.querySelector('[data-compose-form]').__imageState.sourceFile.name),'replacement.png');
 await page.getByRole('button',{name:'Remove image',exact:true}).click();assert.equal(new URL(page.url()).hash,'');
 for(const mode of ['blocked','redirect','oversized','broken']){console.log('Recovery '+mode);download=mode;await page.goto(destination());await failed(page);download='ok';await page.locator('[data-tweet-image-retry]').click();await ready(page)}
 // Invalid destinations never download, but keep explicit intent/recovery.
 const before=downloads;await page.goto(destination('https://example.com/image.png'));await failed(page);assert.equal(downloads,before);assert.match(await page.locator('[data-tweet-image-status]').textContent(),/Invalid/);await page.locator('[data-tweet-image-remove]').click();
 // Eligibility loss preserves attachment and recovery; disabled feature has import recovery too.
 await page.route('**/api/get_profile?*',r=>r.fulfill({body:'Identity-ID: openpgp:'+fingerprint+'\nApproved: no\nImage-Upload-Allowed: no\n'}));await page.goto(destination());await failed(page);assert.match(await page.locator('[data-tweet-image-status]').textContent(),/unavailable/);await page.unroute('**/api/get_profile?*');await page.locator('[data-tweet-image-retry]').click();await ready(page);
 await setFlags({images:false});await page.goto(destination());await failed(page);assert.match(await page.locator('[data-tweet-image-status]').textContent(),/unavailable/);await page.locator('[data-tweet-image-remove]').click();await setFlags({images:true});
 // Posting retry uses the existing signed upload; an aborted first finalize retains the draft/photo.
 await page.goto(destination());await ready(page);let finalizes=0;
 await page.route('**/api/create_prepared_post',async route=>{finalizes++;if(finalizes===1)return route.abort('failed');return route.continue()});
 await page.locator('[data-compose-form] button[type="submit"]').first().click();await page.waitForFunction(()=>document.querySelector('[data-role="compose-identity-status"]')?.dataset.kind==='error');assert.equal(await page.locator('.image-upload-preview img').isVisible(),true);assert((await page.locator('[name="body"]').inputValue()).startsWith('Captured tweet'));
 await page.locator('[data-compose-form] button[type="submit"]').first().click();await page.waitForURL(/\/threads\//);assert.equal(finalizes,2);assert(await page.locator('.post-image').count()>0);console.log('Published: '+page.url());
 assert.deepEqual(errors,[]);
 console.log('PASS tweet bookmarklet image import: both modes, source links, draft precedence, pending/anonymous/Outbox protection, removal/replacement races, fetch/redirect/size/preparation recovery, eligibility/flag failure, signed publish retry');
 console.log('Evidence root: '+root);
} catch(e){if(browser)for(const c of browser.contexts())for(const p of c.pages()){console.error('URL: '+p.url());console.error(await p.locator('body').innerText().catch(()=>''));}console.error('Fixture: '+root+'\n'+log.slice(-2500));throw e}finally{if(browser)await browser.close();server.kill()}
