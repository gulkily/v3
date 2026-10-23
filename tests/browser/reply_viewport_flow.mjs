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
 await context.addInitScript(()=>{
  window.replyTestViewport=new EventTarget();
  Object.assign(replyTestViewport,{width:390,height:420,offsetTop:0,offsetLeft:0,scale:1});
  Object.defineProperty(window,'visualViewport',{value:replyTestViewport});
 });
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
 await page.locator('[name="body"]').fill('Phone reply acceptance thread');
 await page.locator('.compose-form-actions > button').click();
 await page.waitForURL(/\/threads\//);
 const threadId=new URL(page.url()).pathname.split('/').pop();
 for(const entry of ['dedicated','inline']) {
  await page.goto(base+(entry==='dedicated' ? '/compose/reply?thread_id='+threadId+'&parent_id='+threadId : '/threads/'+threadId));
  const form=page.locator('[data-phone-reply]');
  const body=form.locator('[name="body"]');
  const button=form.locator('.compose-form-actions > button');
  if(entry==='inline') {
   // The mocked VisualViewport does not resize browser hit testing. Use native
   // keyboard link activation here; inline_reply_link.mjs covers real clicks.
   await page.locator('[data-inline-reply-link]').first().focus();
   await page.keyboard.press('Enter');
   await page.waitForFunction(()=>document.querySelector('[data-phone-reply]').classList.contains('reply-viewport-active'));
  }
  const draft='Verified '+entry+' phone reply';
  await body.fill(draft);
  await page.waitForFunction(()=>document.querySelector('[data-phone-reply]').classList.contains('reply-viewport-active'));
  async function checkSeparation() {
   await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   const rectangles=await page.locator('[data-reply-panel]').evaluate(panel=>{
    const rect=node=>{const r=node.getBoundingClientRect();return {top:r.top,bottom:r.bottom,left:r.left,right:r.right};};
    return {panel:rect(panel),active:panel.classList.contains('reply-panel-active'),body:rect(panel.querySelector('textarea')),actions:rect(panel.querySelector('.compose-form-actions')),
     feedback:[...panel.querySelectorAll('[data-kind="error"],.feedback-error')].filter(el=>el.getClientRects().length).map(rect)};
   });
   assert.equal(rectangles.active,true,entry+': sufficient space uses the bounded panel');
   assert.ok(rectangles.body.bottom<=rectangles.actions.top,entry+': editor clear of actions');
   const height=await page.evaluate(()=>replyTestViewport.height);
   assert.ok(rectangles.actions.bottom<=height,entry+': Post above simulated keyboard');
   assert.ok(rectangles.panel.top>=8 && rectangles.panel.bottom<=height-8,entry+': entire panel stays inside the visible viewport');
   for(const error of rectangles.feedback) {
    assert.ok(error.bottom<=rectangles.body.top || error.top>=rectangles.body.bottom,'error clear of editor');
    assert.ok(error.bottom<=rectangles.actions.top || error.top>=rectangles.actions.bottom,'error clear of Post');
   }
  }
  await checkSeparation();
  await body.fill(draft+'\n'.repeat(30)+'Long draft tail');
  await body.evaluate(el=>el.setSelectionRange(3,8));
  await page.evaluate(()=>{replyTestViewport.height=280;replyTestViewport.dispatchEvent(new Event('resize'));});
  await checkSeparation();assert.deepEqual(await body.evaluate(el=>[el.selectionStart,el.selectionEnd]),[3,8]);
  await page.evaluate(()=>{replyTestViewport.height=420;replyTestViewport.dispatchEvent(new Event('resize'));});
  // Real normalization warning and its recovery action must have their own space.
  await body.fill(draft+'\u202e');
  const remove=form.locator('[data-action="remove-unsupported-compose-characters"][data-compose-field-remove-for="body"]');
  await remove.waitFor({state:'visible'});await checkSeparation();
  await remove.click();assert.equal(await body.inputValue(),draft);
  await body.focus();await checkSeparation();
  {
   for(const theme of ['light','word97','chicago','midnight']) {
    await page.addStyleTag({content:readFileSync(join(project,'public/assets/theme-'+theme+'.css'),'utf8')});
    await page.evaluate(theme=>{document.documentElement.dataset.theme=theme;replyTestViewport.dispatchEvent(new Event('resize'));},theme);
    await checkSeparation();
    if(process.env.REPLY_FLOW_SCREENSHOTS) await page.screenshot({path:process.env.REPLY_FLOW_SCREENSHOTS+'/'+entry+'-'+theme+'.png'});
   }
   await page.evaluate(()=>{document.documentElement.dataset.theme='light';replyTestViewport.dispatchEvent(new Event('resize'));});
  }

  // A failed request must expose retry and preserve the draft on the real page.
  await page.route('**/api/prepare_reply',route=>route.abort(),{times:1});
  await button.click();
  await page.locator('[data-role="compose-identity-status"][data-kind="error"]').waitFor();
  assert.equal(await body.inputValue(),draft);
  assert.equal(await button.isEnabled(),true);
  await body.focus();await checkSeparation();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  // Hold the preparation request, then repeat submit: only one publication may result.
  let release;const gate=new Promise(resolve=>{release=resolve;});
  let started;const pending=new Promise(resolve=>{started=resolve;});let requests=0;
  await page.route('**/api/prepare_reply',async route=>{requests++;started();await gate;await route.continue();});
  try {
   await button.click();await pending;
   assert.equal(await button.isDisabled(),true);
   await form.evaluate(f=>f.requestSubmit(f.querySelector('button[type="submit"]')));
   await page.waitForTimeout(100);assert.equal(requests,1);
  } finally {release();}
  await page.waitForURL(/\/threads\/.*#post-/);
  assert.equal(await page.locator('.post-card').filter({hasText:draft}).count(),1);
  await page.reload();
  assert.equal(await page.locator('.post-card').filter({hasText:draft}).count(),1,'exactly one persisted reply after retry');
  await page.unroute('**/api/prepare_reply');
  console.log('PASS '+entry+': simulated keyboard separation, validation recovery, long draft/selection, real phone route, failed request, preserved draft, retry, duplicate pending submit suppressed, exactly one persisted reply');
 }
 assert.deepEqual(errors,[]);
 console.log('Navigation-cancelled WebKit background requests:',expectedNavigationErrors.length);
} catch(error) {console.error(error);throw error;} finally {
 if(browser) await browser.close();
 server.kill();await once(server,'exit');await rm(root,{recursive:true,force:true});
}
