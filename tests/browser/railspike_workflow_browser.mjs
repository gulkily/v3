import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { runInThisContext } from 'node:vm';
import { chromium } from 'playwright-core';
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(readFileSync(join(project,'public/assets/openpgp.min.js'),'utf8'));
const keys=await globalThis.openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'operator'}],format:'armored'});
const pub=await globalThis.openpgp.readKey({armoredKey:keys.publicKey}); const fingerprint=pub.getFingerprint();
const root=await mkdtemp(join(tmpdir(),'railspike-browser-'));
function fixture(data) {
 const r=spawnSync('php',[join(project,'tests/Support/railspike_browser_fixture.php'),root],{input:JSON.stringify(data),encoding:'utf8',env:{...process.env,FORUM_SECRETS_PATH:join(root,'unused-secrets.php')}});
 assert.equal(r.status,0,r.stderr); return r.stdout ? JSON.parse(r.stdout) : null;
}
const data=fixture({publicKey:keys.publicKey});
const socket=createServer().listen(0,'127.0.0.1'); await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/railspike_browser_router.php')],{env:{...process.env,FORUM_RAILSPIKE_ENABLED:'true',RAILSPIKE_TEST_ROOT:root,FORUM_TASK_QUEUE_DATABASE_PATH:join(root,'state/private/tasks.sqlite3'),FORUM_SECRETS_PATH:join(root,'unused-secrets.php')},stdio:['ignore','ignore','pipe']});
let log=''; server.stderr.on('data',s=>log+=s);
let browser;
try {
 for(let i=0;i<50;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:390,height:844},serviceWorkers:'block'});
 const denied=await context.request.get(base+'/api/railspike_workflow?thread_id='+data.thread);assert.equal(denied.status(),403);assert.match(denied.headers()['cache-control'],/no-store/);
 const challenge=(await (await context.request.get(base+'/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
 const privateKey=await globalThis.openpgp.readPrivateKey({armoredKey:keys.privateKey});
 const signature=await globalThis.openpgp.sign({message:await globalThis.openpgp.createMessage({text:challenge}),signingKeys:privateKey,detached:true});
 const login=await context.request.post(base+'/api/authenticate_identity',{form:{challenge,detached_signature:signature,identity_id:data.identity}});assert.equal(login.status(),200);
 const page=await context.newPage();
 await page.addInitScript(({keys,fingerprint})=>{
  for(const [k,v] of Object.entries({username:'operator',public_key:keys.publicKey,private_key:keys.privateKey,fingerprint:fingerprint.toUpperCase(),published_fingerprint:fingerprint.toUpperCase()}))localStorage.setItem('forum_pki_'+k,v);
 },{keys,fingerprint});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/threads/'+data.thread);
 const reply=page.locator(`[data-railspike-post="${data.reply}"]`);
 await reply.locator('..').locator(':scope > summary').click();
 await reply.locator('summary').first().waitFor();
 await reply.locator('summary').first().click();await reply.getByRole('button',{name:'Start development',exact:true}).click();
 await reply.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
 fixture({operation:'worker'});await page.evaluate(()=>ForumRailspike.refresh());
 await reply.getByLabel('Which navigation should improve?').fill('Main phone navigation');
 await reply.getByRole('button',{name:'Send answers',exact:true}).click();await reply.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
 fixture({operation:'worker'});await page.evaluate(()=>ForumRailspike.refresh());
 await reply.getByRole('button',{name:'Approve Step 1',exact:true}).waitFor();
 assert.equal(await page.evaluate(()=>window.pwned),undefined);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await reply.getByLabel('Requested changes').fill('Prefer a larger toolbar');
 await reply.getByRole('button',{name:'Request changes',exact:true}).click();await reply.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
 fixture({operation:'worker',fail:true});await page.evaluate(()=>ForumRailspike.refresh());await reply.getByRole('button',{name:'Retry assessment',exact:true}).click();
 await reply.getByText('Railspike Step 1: queued',{exact:true}).waitFor();fixture({operation:'worker'});await page.evaluate(()=>ForumRailspike.refresh());
 const preApproval=await (await context.request.get(base+'/api/railspike_workflow?thread_id='+data.thread)).json();
 const workflow=preApproval.workflows.find(w=>w.post_id===data.reply);
 await reply.getByRole('button',{name:'Approve Step 1',exact:true}).click();await reply.getByText('Railspike Step 1: approved',{exact:true}).waitFor();
 fixture({operation:'rebuild'});await page.reload();await reply.locator('..').locator(':scope > summary').click();await reply.getByText('Railspike Step 1: approved',{exact:true}).waitFor();
 // An old action, even with a valid browser signature, cannot approve a newer state.
 const stale=await page.evaluate(async w=>{try{await ForumRailspike.act('approve',w.post_id,w,{revision:w.revisions.at(-1).number,digest:w.revisions.at(-1).digest});return 'accepted';}catch(e){return e.message;}},workflow);
 assert.match(stale,/changed/);
 const unsigned=await context.request.post(base+'/api/railspike_workflow',{data:{operation:'prepare',action:'start',post_id:data.direct}});assert.equal(unsigned.status(),400);
 await page.goto(base+'/posts/'+data.direct);const direct=page.locator(`[data-railspike-post="${data.direct}"]`);await direct.locator('..').locator(':scope > summary').click();await direct.locator('summary').first().waitFor();await direct.locator('summary').first().click();await direct.getByRole('button',{name:'Start development',exact:true}).click();await direct.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
 const activityWorkflowRequests=[];
 const recordWorkflow=request=>{if(new URL(request.url()).pathname==='/api/railspike_workflow')activityWorkflowRequests.push(request.url());};
 page.on('request',recordWorkflow);
 await page.goto(base+'/activity/');
 await page.getByRole('heading',{name:'Activity',exact:true}).waitFor();
 assert.equal(await page.locator('[data-railspike-activity]').count(),0);
 await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
 await page.waitForTimeout(5500);
 assert.deepEqual(activityWorkflowRequests,[]);
 page.off('request',recordWorkflow);
 assert.equal(await page.getByText('Site development · operators only',{exact:true}).count(),0);
 const operatorState=await context.storageState();
 for(const operator of [true,false]) for(const javaScriptEnabled of [true,false]) for(const width of [390,1280]) {
  const check=await browser.newContext({javaScriptEnabled,storageState:operator?operatorState:undefined,viewport:{width,height:900},serviceWorkers:'block'});
  try {
   const checked=await check.newPage();const workflowRequests=[];
   checked.on('request',r=>{if(new URL(r.url()).pathname==='/api/railspike_workflow')workflowRequests.push(r.url());});
   await checked.goto(base+'/activity/');
   await checked.getByRole('heading',{name:'Activity',exact:true}).waitFor();
   assert.equal(await checked.locator('[data-railspike-activity]').count(),0);
   if(operator&&javaScriptEnabled&&width===390)await checked.screenshot({path:'/tmp/activity-workflow-removal-phone.png'});
   await checked.getByRole('link',{name:'Visible Content',exact:true}).click();
   await checked.waitForLoadState('load');assert.equal(new URL(checked.url()).searchParams.get('view'),'content');
   assert.equal(await checked.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
   assert.deepEqual(workflowRequests,[]);
  } finally {await check.close();}
 }
 const saved=await context.storageState(); saved.cookies=[];
 const resumed=await browser.newContext({storageState:saved,viewport:{width:390,height:844},serviceWorkers:'block'});
 const resumePage=await resumed.newPage();await resumePage.goto(base+'/posts/'+data.direct);await resumePage.locator(`[data-railspike-post="${data.direct}"]`).getByText('Railspike Step 1: queued',{exact:true}).waitFor({state:'attached'});await resumePage.locator(`[data-railspike-post="${data.direct}"]`).locator('..').evaluate(el=>el.open=true);await resumePage.locator(`[data-railspike-post="${data.direct}"]`).getByText('Railspike Step 1: queued',{exact:true}).waitFor();await resumed.close();
 const anonymous=await browser.newContext({serviceWorkers:'block'});const response=await anonymous.request.get(base+'/threads/'+data.thread);assert.equal((await response.text()).includes('Literal <script>'),false);assert.equal((await anonymous.request.get(base+'/api/railspike_workflow?activity=1')).status(),403);await anonymous.close();
 assert.deepEqual(errors,[]);
 console.log(JSON.stringify({status:'passed',viewport:390,checks:['reply-added hashtag','signed start/answer/revise/approve','failure and retry','read-model rebuild','stale approval','inert output','direct-tag standalone post','Activity without workflow UI or polling','operator/anonymous JS/no-JS phone/desktop matrix','anonymous privacy','no overflow']}));
} catch(e) {console.error(log.slice(-5000));throw e;} finally {await browser?.close();server.kill();}
