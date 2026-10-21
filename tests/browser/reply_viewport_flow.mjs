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
 const page=await context.newPage();page.setDefaultTimeout(15000);const errors=[],expectedNavigationErrors=[];page.on('pageerror',e=>{if(process.env.REPLY_BROWSER==='webkit' && e.message.includes('due to access control checks.')) expectedNavigationErrors.push(e.message);else errors.push(e.message);});

 await setFlags({images:false});
 await page.goto(base+'/compose/thread');
 await page.locator('[name="body"]').fill('Phone reply acceptance thread');
 await page.locator('.compose-form-actions > button').click();
 await page.waitForURL(/\/threads\//);
 const threadId=new URL(page.url()).pathname.split('/').pop();
 for(const entry of ['dedicated','inline']) {
  await page.goto(base+(entry==='dedicated' ? '/compose/reply?thread_id='+threadId+'&parent_id='+threadId : '/threads/'+threadId));
  if(entry==='inline') await page.locator('[data-inline-reply-compose] textarea').click();
  const form=page.locator('[data-phone-reply]');
  const body=form.locator('[name="body"]');
  const button=form.locator('.compose-form-actions > button');
  const draft='Verified '+entry+' phone reply';
  await body.fill(draft);
  await page.waitForFunction(()=>document.querySelector('[data-phone-reply]').classList.contains('reply-viewport-active'));
  // A failed request must expose retry and preserve the draft on the real page.
  await page.route('**/api/prepare_reply',route=>route.abort(),{times:1});
  await button.click();
  await page.locator('[data-role="compose-identity-status"][data-kind="error"]').waitFor();
  assert.equal(await body.inputValue(),draft);
  assert.equal(await button.isEnabled(),true);
  await body.focus();
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
  console.log('PASS '+entry+': real phone route, failed request, preserved draft, retry, duplicate pending submit suppressed, exactly one persisted reply');
 }
 assert.deepEqual(errors,[]);
 console.log('Navigation-cancelled WebKit background requests:',expectedNavigationErrors.length);
} finally {
 if(browser) await browser.close();
 server.kill();await once(server,'exit');await rm(root,{recursive:true,force:true});
}
