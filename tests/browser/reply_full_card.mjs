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
 await page.goto(base+'/compose/thread');await page.locator('[name="body"]').fill('Full reply reconciliation fixture');
 await page.locator('.compose-form-actions > button').click();await page.waitForURL(/\/threads\//);
 const threadUrl=base+new URL(page.url()).pathname,threadId=new URL(threadUrl).pathname.split('/').pop();
 if(process.env.REPLY_CARD_SCREENSHOTS)await mkdir(process.env.REPLY_CARD_SCREENSHOTS,{recursive:true});
 for(const entry of ['inline','dedicated']) {
  await page.goto(entry==='inline'?threadUrl:base+'/compose/reply?thread_id='+threadId+'&parent_id='+threadId);
  const form=page.locator('[data-phone-reply]'),body=form.locator('[name="body"]');
  let release;const held=new Promise(resolve=>release=resolve);
  await page.route('**/threads/**',async route=>{if(route.request().isNavigationRequest())return route.continue();await held;return route.continue();});
  const text='Full '+entry+' reply\n\n'+('Long content with https://example.com/reply and formatting.\n'.repeat(30))+'END OF COMPLETE REPLY';
  await body.fill(text);await form.locator('.compose-form-actions > button').click();
  const card=page.locator('[data-reply-operation]').last();await card.waitFor();
  assert.match(await card.textContent(),/END OF COMPLETE REPLY/);
  const operation=await card.getAttribute('data-reply-operation');
  await page.locator('[data-reply-operation="'+operation+'"][data-reply-state="accepted"]').waitFor();
  await body.fill('Newer draft stays here');await body.evaluate(el=>el.setSelectionRange(3,8));
  release();const full=page.locator('[data-reply-operation="'+operation+'"][data-reply-rendered="1"]');await full.waitFor();
  const postId=await full.getAttribute('data-post-id');
  const html=await (await context.request.get(threadUrl+'?created_post_id='+postId)).text();
  const canonicalBody=await page.evaluate(({html,postId})=>new DOMParser().parseFromString(html,'text/html').getElementById('post-'+postId).querySelector('.body').innerHTML,{html,postId});
  assert.equal(await full.locator('.body').innerHTML(),canonicalBody);
  assert.equal(await page.locator('.reply-continuation-stack').count(),0,'no separate reply scrolling area');
  assert.equal(await full.evaluate(el=>el.nextElementSibling?.matches('[data-compose-root], [data-reply-panel]')),true,'new reply is a normal sibling immediately before the composer');
  if(entry==='inline') {
   const parity=await page.evaluate(html=>{
    const doc=new DOMParser().parseFromString(html,'text/html');
    return [...document.querySelectorAll('.post-card[data-post-id]')].map(live=>{
     const expected=doc.getElementById(live.id);
     return {id:live.id,actual:[live.classList.contains('continuation'),live.classList.contains('meta-deferred'),live.querySelector(':scope > [data-post-byline]')?.innerHTML||''],expected:[expected.classList.contains('continuation'),expected.classList.contains('meta-deferred'),expected.querySelector(':scope > [data-post-byline]')?.innerHTML||'']};
    });
   },html);
   for(const row of parity)assert.deepEqual(row.actual,row.expected,'reload grouping/byline parity '+row.id);
  }

  assert.match(await full.textContent(),/END OF COMPLETE REPLY/);
  assert.equal(await full.locator('.post-card-actions').count(),1);
  assert.equal(await full.getByRole('link',{name:'Open reply',exact:true}).count(),0);
  assert.equal(await body.inputValue(),'Newer draft stays here');assert.deepEqual(await body.evaluate(el=>[el.selectionStart,el.selectionEnd]),[3,8]);
  if(process.env.REPLY_CARD_SCREENSHOTS)await page.screenshot({path:process.env.REPLY_CARD_SCREENSHOTS+'/'+entry+'-desktop.png'});
  await full.getByRole('button',{name:'Like',exact:true}).click();
  await full.getByRole('button',{name:'Liked',exact:true}).waitFor();
  await page.unroute('**/threads/**');
  // A rendering failure is recoverable without publishing another post.
  await page.route('**/threads/**',route=>route.request().isNavigationRequest()?route.continue():route.abort(),{times:1});
  await body.fill('Render recovery '+entry);await form.locator('.compose-form-actions > button').click();
  const failedRender=page.locator('[data-reply-state="accepted"]').filter({hasText:'Reply posted. Retry loading its full view.'});await failedRender.waitFor();
  let sends=0;await page.route('**/api/create_prepared_post',route=>{sends++;return route.continue();});
  await failedRender.getByRole('button',{name:'Retry',exact:true}).click();
  await page.locator('[data-reply-rendered="1"]').filter({hasText:'Render recovery '+entry}).waitFor();assert.equal(sends,0);
  await page.unroute('**/api/create_prepared_post');await page.unroute('**/threads/**');
  if(entry==='inline') {
   const current=await (await context.request.get(threadUrl)).text();
   const groups=await page.evaluate(html=>{
    const doc=new DOMParser().parseFromString(html,'text/html');
    return [...document.querySelectorAll('.post-card[data-post-id]')].map(live=>{
     const server=doc.getElementById(live.id);
     return {id:live.id,actual:[live.classList.contains('continuation'),live.classList.contains('meta-deferred'),live.querySelector(':scope > [data-post-byline]')?.innerHTML||''],expected:[server.classList.contains('continuation'),server.classList.contains('meta-deferred'),server.querySelector(':scope > [data-post-byline]')?.innerHTML||'']};
    });
   },current);
   for(const group of groups)assert.deepEqual(group.actual,group.expected,'multiple-send reload grouping '+group.id);
  }

  console.log('PASS '+entry+': complete canonical body/actions, active draft/selection, signed Like, render-only retry without resend');
 }
 // Restore multiple saved replies with one shared canonical page request.
 const restore=await context.newPage();let restoreFetches=0;
 await restore.route('**/threads/**',route=>{if(!route.request().isNavigationRequest())restoreFetches++;return route.continue();});
 await restore.goto(base+'/compose/reply?thread_id='+threadId+'&parent_id='+threadId);
 await restore.waitForFunction(()=>document.querySelectorAll('[data-reply-rendered="1"]').length===4);
 assert.equal(restoreFetches,1,'saved replies share one canonical page fetch');
 await restore.close();
 const phoneContext=await browser.newContext({storageState:await context.storageState(),hasTouch:true,viewport:{width:390,height:844},serviceWorkers:'block'});
 const phone=await phoneContext.newPage();phone.on('pageerror',e=>errors.push(e.message));
 if(process.env.REPLY_CARD_SCREENSHOTS)await mkdir(process.env.REPLY_CARD_SCREENSHOTS,{recursive:true});
 for(const entry of ['inline','dedicated'])for(const theme of ['light','word97','chicago','midnight']) {
  await phone.goto(entry==='inline'?threadUrl:base+'/compose/reply?thread_id='+threadId+'&parent_id='+threadId);
  await phone.evaluate(theme=>document.documentElement.dataset.theme=theme,theme);
  await phone.addStyleTag({content:readFileSync(join(project,'public/assets/theme-'+theme+'.css'),'utf8')});
  const f=phone.locator('[data-phone-reply]'),b=f.locator('[name="body"]');
  await b.fill('Full phone '+entry+' '+theme+'\n'+('Complete reply text.\n'.repeat(8))+'END OF PHONE REPLY');
  await f.evaluate(el=>el.requestSubmit(el.querySelector('.compose-form-actions > button')));
  const id=await phone.locator('[data-reply-operation]').last().getAttribute('data-reply-operation');
  const full=phone.locator('[data-reply-operation="'+id+'"][data-reply-rendered="1"]');await full.waitFor();
  await phone.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  assert.match(await full.locator('.body').textContent(),/END OF PHONE REPLY/);
  assert.equal(await full.locator('.post-card-actions').count(),1);
  const reading=await phone.locator('[data-reply-reading="1"]').boundingBox(),card=await full.boundingBox();
  assert.ok(card.y+card.height<=reading.y+2 && card.y+card.height>0,'full phone card above re-entry '+entry+' '+theme+' '+JSON.stringify({card,reading}));
  assert.equal(await phone.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  if(process.env.REPLY_CARD_SCREENSHOTS)await phone.screenshot({path:process.env.REPLY_CARD_SCREENSHOTS+'/'+entry+'-'+theme+'.png'});
  await b.tap();assert.equal(await b.evaluate(el=>document.activeElement===el),true);
 }
 await phoneContext.close();
 console.log('PASS full canonical phone cards and one-tap re-entry: both routes, four themes');
 assert.deepEqual(errors,[]);
} catch(error){console.error(log.slice(-4000));throw error;} finally {await browser?.close();server.kill();await once(server,'exit').catch(()=>{});await rm(root,{recursive:true,force:true});}
