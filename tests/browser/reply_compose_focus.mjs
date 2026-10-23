import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
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
 const page=await context.newPage();page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(60000);const errors=[],expectedNavigationErrors=[];page.on('pageerror',e=>{if(process.env.REPLY_BROWSER==='webkit' && e.message.includes('due to access control checks.')) expectedNavigationErrors.push(e.message);else errors.push(e.message);});


 await setFlags({images:false});
 await page.goto(base+'/compose/thread');
 await page.locator('[name="body"]').fill('Inline reply focus fixture');
 await page.locator('.compose-form-actions > button').click();
 await page.waitForURL(/\/threads\//);
 const threadUrl=base+new URL(page.url()).pathname;

 // Freeze deferred scripts to verify the server-rendered first paint.
 let releaseScripts;const scriptsReady=new Promise(resolve=>{releaseScripts=resolve;});
 await page.route('**/assets/*.js*',async route=>{await scriptsReady;await route.continue();});
 try {
  await page.goto(threadUrl,{waitUntil:'commit'});
  const editor=page.locator('[data-inline-reply-compose] textarea');
  await editor.waitFor();
  await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
  const before=await editor.boundingBox();
  assert.equal(await page.locator('[data-inline-reply-compose] .compose-form-actions').isVisible(),false,'Post is collapsed before deferred scripts execute');
  releaseScripts();await page.waitForLoadState('load');
  await page.waitForFunction(()=>Boolean(window.ForumBrowserSigning));
  await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
  assert.deepEqual(await editor.boundingBox(),before,'initialization must not move or resize the collapsed editor');
  assert.equal(await page.locator('[data-inline-reply-compose] .compose-form-actions').isVisible(),false);
  console.log('PASS collapsed first paint and unchanged editor geometry after initialization');
 } finally {releaseScripts();await page.unroute('**/assets/*.js*');}
 for (const width of [390,1280]) {
  await page.setViewportSize({width,height:844});
  for(const theme of ['light','word97','chicago','midnight']) {
   await page.goto(threadUrl);
   await page.evaluate(theme=>document.documentElement.dataset.theme=theme,theme);
   await page.addStyleTag({content:readFileSync(join(project,'public/assets/theme-'+theme+'.css'),'utf8')});
   const composer=page.locator('[data-inline-reply-compose]');
   const body=composer.locator('textarea[name="body"]');
   const actions=composer.locator('.compose-form-actions');
   assert.equal(await composer.locator('textarea').count(),1);
   assert.equal(await composer.locator('summary').count(),1,'only the secondary-action menu summary remains');
   assert.equal(await page.locator('[data-inline-reply-trigger]').count(),0);
   await page.evaluate(()=>{
    window.editor=document.querySelector('[data-inline-reply-compose] textarea');window.focusEvents=[];window.scrollCalls=0;window.focusCalls=0;
    editor.addEventListener('focus',()=>focusEvents.push(editor));
    const focus=editor.focus;editor.focus=function(...args){focusCalls++;return focus.apply(this,args);};
    Element.prototype.scrollIntoView=function(){scrollCalls++;};
   });
   assert.equal(await actions.isVisible(),false);
   const collapsedHeight=(await body.boundingBox()).height;
   if(process.env.REPLY_FOCUS_SCREENSHOTS) await page.screenshot({path:process.env.REPLY_FOCUS_SCREENSHOTS+'/'+theme+'-'+width+'-collapsed.png'});
   await body.click();
   await page.waitForFunction(()=>document.querySelector('[data-inline-reply-compose]').dataset.inlineReplyExpanded==='1');
   assert.equal(await actions.isVisible(),true);
   assert.ok((await body.boundingBox()).height>collapsedHeight+4,'the prompt expands into a taller editor');
   if(process.env.REPLY_FOCUS_SCREENSHOTS) await page.screenshot({path:process.env.REPLY_FOCUS_SCREENSHOTS+'/'+theme+'-'+width+'-expanded.png'});
   assert.deepEqual(await page.evaluate(()=>({same:editor===document.activeElement,focus:focusCalls,scroll:scrollCalls,events:focusEvents.length})),{same:true,focus:0,scroll:0,events:1});
   const draft='Reply draft '+theme+' '+width;
   await body.fill(draft);await body.evaluate(el=>el.setSelectionRange(3,8));
   await page.locator('a').first().focus();
   assert.equal(await body.inputValue(),draft);assert.equal(await actions.isVisible(),true);
   await body.focus();assert.deepEqual(await body.evaluate(el=>[el.selectionStart,el.selectionEnd]),[3,8]);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   await body.fill('');
   await page.locator('a').first().focus();
   await page.waitForFunction(()=>document.querySelector('[data-inline-reply-compose]').dataset.inlineReplyExpanded==='0');
   assert.equal(await actions.isVisible(),false);
   assert.equal((await body.boundingBox()).height,collapsedHeight,'empty focus exit restores the compact editor');
   console.log('PASS single inline reply editor, direct first focus, no scripted scroll, draft/selection, empty collapse: '+theme+' '+width);
  }
 }
 await page.setViewportSize({width:390,height:844});
 await page.goto(threadUrl);
 const form=page.locator('[data-inline-reply-compose] form');
 const body=form.locator('[name="body"]');
 await body.fill('One inline reply after retry');
 await page.route('**/api/prepare_reply',r=>r.abort(),{times:1});
 await form.locator('.compose-form-actions > button').click();
 await page.locator('[data-reply-state="failed"]').waitFor();
 assert.equal(await body.inputValue(),'');
 assert.match(await page.locator('[data-reply-state="failed"]').textContent(),/One inline reply after retry/);
 await page.locator('[data-reply-state="failed"] button').click();
 await page.locator('[data-reply-state="accepted"]').waitFor();
 assert.equal(await page.locator('.post-card').filter({hasText:'One inline reply after retry'}).count(),1);
 const noJs=await browser.newContext({javaScriptEnabled:false});const fallback=await noJs.newPage();await fallback.goto(threadUrl);
 assert.equal(await fallback.locator('[data-inline-reply-compose] textarea').isVisible(),true);
 assert.equal(await fallback.locator('[data-inline-reply-compose] button[type="submit"]').first().isVisible(),true);
 await noJs.close();assert.deepEqual(errors,[]);
 console.log('PASS real inline reply failure/retry, one published reply, no-JavaScript controls');
} catch(error) {
 console.error(error);throw error;
} finally {
 if(browser) await browser.close();server.kill();await once(server,'exit');await rm(root,{recursive:true,force:true});
}
