import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import { checkOperatorMessagingFlag } from './private_message_operator_flag.mjs';
import { mkdtemp, writeFile, rename, mkdir, rmdir, copyFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const scale=process.argv.includes('--scale');
const senderAssisted=process.argv.includes('--sender'), direct=process.argv.includes('--direct');
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(readFileSync(join(project,'public/assets/openpgp.min.js'),'utf8'));
const pgp=globalThis.openpgp,root=await mkdtemp(join(tmpdir(),'history-sync-browser-'));
const fixture=input=>{
 const result=spawnSync('php',[join(project,'tests/Support/private_message_history_sync_fixture.php'),root],{input:JSON.stringify(input),encoding:'utf8',env:{...process.env,FORUM_SECRETS_PATH:join(root,'unused.php')}});
 assert.equal(result.status,0,result.stderr);return result.stdout;
};
fixture({action:'init'});
async function key(name) {
 const pair=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name}],format:'armored'});
 const publicKey=await pgp.readKey({armoredKey:pair.publicKey});
 return {...pair,name,fingerprint:publicKey.getFingerprint(),public:publicKey,private:await pgp.readPrivateKey({armoredKey:pair.privateKey})};
}
const [oldA,oldB,bob,target,later]=await Promise.all((senderAssisted?['bob','bob','alice','alice','alice']:['alice','alice','bob','alice','alice']).map(key));
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
const base=`http://127.0.0.1:${port}`,syncPath=join(root,'state/private/message_history_sync.sqlite3'),messagePath=join(root,'state/private/messages.sqlite3');
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/private_message_browser_router.php')],{env:{...process.env,PRIVATE_MESSAGE_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'unused.php'),PRIVATE_MESSAGE_DATABASE_PATH:messagePath,PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH:syncPath,VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')},stdio:['ignore','ignore','pipe']});
let log='',browser;server.stderr.on('data',data=>{log+=data});
const errors=[],syncBodies=[],historyFailures=[],headers={'X-Requested-With':'ForumPrivateMessages'};
const sign=(who,text)=>pgp.createMessage({text}).then(message=>pgp.sign({message,signingKeys:who.private,detached:true}));
async function json(response,status=200) {assert.equal(response.status(),status,await response.text());return response.json();}
async function retainedCandidates(ctx,id) {
 for(let n=0;n<3;n++) {
   const response=await ctx.request.get(base+'/api/private_messages/history_sync/transfers?message_id='+encodeURIComponent(id));
   if(response.status()!==429)return json(response);
   const seconds=Number(response.headers()['retry-after']);assert(seconds>=1);
   await new Promise(resolve=>setTimeout(resolve,seconds*1000));
 }
 throw Error('Retained-candidate inspection remained throttled');
}
async function publish(ctx,who) {
 const prepared=await json(await ctx.request.post(base+'/api/prepare_identity',{data:{public_key:who.publicKey}}));
 await json(await ctx.request.post(base+'/api/create_identity',{data:{prepare_token:prepared.prepare_token,canonical_record:prepared.canonical_record,detached_signature:await sign(who,prepared.canonical_record)}}));
}
async function authenticate(ctx,who) {
 const challenge=(await (await ctx.request.get(base+'/api/auth_challenge')).text()).match(/challenge=(\w+)/)[1];
 const response=await ctx.request.post(base+'/api/authenticate_identity',{data:{challenge,identity_id:'openpgp:'+who.fingerprint,detached_signature:await sign(who,challenge)}});
 assert.equal(response.status(),200,await response.text());
 await ctx.request.get(base+'/api/set_identity_hint?identity_hint='+encodeURIComponent('openpgp:'+who.fingerprint));
}
async function approve(ctx,source,destination) {
 const prepared=await json(await ctx.request.post(base+'/api/prepare_approval',{data:{profile_slug:'openpgp-'+destination.fingerprint}}));
 await json(await ctx.request.post(base+'/api/create_prepared_approval',{data:{prepare_token:prepared.prepare_token,post_id:prepared.post_id,record_path:prepared.record_path,author_identity_id:'openpgp:'+source.fingerprint,canonical_record:prepared.canonical_record,detached_signature:await sign(source,prepared.canonical_record)}}));
}
async function device(who) {
 const ctx=await browser.newContext({viewport:{width:1100,height:800},serviceWorkers:'block'});
 await authenticate(ctx,who);
 await ctx.addInitScript(({name,publicKey,privateKey,fingerprint})=>{
   if (!/^https?:$/.test(location.protocol)) return;
   for(const [k,v] of Object.entries({username:name,public_key:publicKey,private_key:privateKey,fingerprint:fingerprint.toUpperCase(),published_fingerprint:fingerprint.toUpperCase()})) localStorage.setItem('forum_pki_'+k,v);
 },{name:who.name,publicKey:who.publicKey,privateKey:who.privateKey,fingerprint:who.fingerprint});
 ctx.on('page',page=>page.on('pageerror',error=>errors.push(error.message)));
 ctx.on('request',request=>{if(request.url().includes('/history_sync/')) syncBodies.push(request.postData()||'');});
 ctx.on('response',response=>{if(response.status()>=400 && response.url().includes('/history_sync/'))historyFailures.push({path:new URL(response.url()).pathname,status:response.status()});});
 return ctx;
}
async function settled(page, timeout=180000) {
 await page.waitForFunction(()=>window.ForumPrivateMessageUnread?.historyEligibility()===true);
 await page.waitForFunction(()=>{const s=window.ForumPrivateMessageHistorySync?.state();return s && !s.pending && ['ready','waiting'].includes(s.state) && (s.state==='ready' || s.verified+s.contributed+s.unavailable>0);},null,{timeout});
}
async function visit(ctx, contribution=null) {
 const page=await ctx.newPage();await page.goto(base+'/');
 await settled(page);
 if(contribution)assert.equal(Number(fixture({action:'contributions',...contribution})),contribution.count,'One natural donor visit must converge');
 await page.close();
}
async function readAll(ctx,expected) {
 const page=await ctx.newPage();await page.goto(base+'/messages');
 await page.waitForFunction(()=>document.querySelector('[data-role="list-status"]')?.textContent==='All conversations loaded.');
 await page.locator('[data-conversation-row] a').click();await page.waitForURL('**/messages/conversation/bob');
 await page.waitForFunction(()=>document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled==='1');
 while(await page.getByRole('button',{name:'Load older',exact:true}).isVisible()) {
   await page.getByRole('button',{name:'Load older',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading!=='1');
 }
 await page.waitForFunction(count=>document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length===count,expected);
 const ids=await page.locator('[data-private-message-id]').evaluateAll(cards=>cards.map(card=>card.dataset.privateMessageId));
 assert.equal(new Set(ids).size,expected);return page;
}
try {
 for(let n=0;n<60;n++){try{await fetch(base+'/api/version');break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
 const anonymous=await browser.newContext();
 const denied=await anonymous.request.get(base+'/api/private_messages/history_sync/work');assert.equal(denied.status(),401);assert.match(denied.headers()['cache-control'],/no-store/);
 for(const who of [oldA,oldB,bob,target,later]) await publish(anonymous,who);
 fixture({action:'seed-approvals',fingerprints:[oldA,oldB,bob].map(who=>who.fingerprint)});
 const a=await device(oldA),b=await device(oldB),t=await device(target);
 assert.equal((await t.request.get(base+'/api/private_messages/history_sync/work')).status(),403);
 const unused=await a.newPage(),unusedRequests=[];
 unused.on('request',r=>{if(r.url().includes('/history_sync/'))unusedRequests.push(r.url());});
 await unused.goto(base+'/');
 await unused.waitForFunction(()=>window.ForumPrivateMessageUnread?.historyEligibility()===false);
 await unused.evaluate(()=>window.ForumPrivateMessageHistorySync.refresh());
 assert.equal(unusedRequests.length,0,'Unused account must make no dedicated history-sync requests');
 await unused.close();
 const messages=[];
 for(let n=1;n<=(direct?1:scale?19:31);n++) {
   const donor=scale||n<=20?oldA:oldB,sender=senderAssisted?donor:(n%2?bob:donor);
   const envelope=await pgp.encrypt({message:await pgp.createMessage({text:'History secret '+n}),encryptionKeys:[donor.public,bob.public],signingKeys:sender.private,format:'armored'});
   messages.push({id:'history-'+String(n).padStart(2,'0'),time:`2026-10-09T12:${String(n).padStart(2,'0')}:00Z`,sender:sender.name,recipient:senderAssisted?'alice':(sender===bob?'alice':'bob'),fingerprint:sender.fingerprint,envelope});
 }
 fixture({action:'messages',messages});
 console.log('Approving target through signed canonical approval APIs');
 if(senderAssisted) {const approver=await device(bob);await approve(approver,bob,target);await approver.close();}
 else await approve(a,oldA,target);
 const before=messages.map(row=>({message_id:row.id,encrypted_envelope:row.envelope}));
 if(scale) {
   console.log('Seeding 115 real approved account keys and previously settled inactive devices');
   const extra=await Promise.all(Array.from({length:112},()=>key('alice'))),inactive=[oldB,...extra];
   const entries=[];
   for(const message of messages)entries.push({message_id:message.id,digest:createHash('sha256').update(message.envelope).digest('hex'),keys:(await pgp.decryptSessionKeys({message:await pgp.readMessage({armoredMessage:message.envelope}),decryptionKeys:oldA.private})).map(k=>({algorithm:k.algorithm,data:Buffer.from(k.data).toString('base64')}))});
   const transfers=[];
   for(const destination of [...inactive,bob]) {
     const selected=destination===bob?entries.filter(e=>messages.find(m=>m.id===e.message_id).sender==='alice'):entries;
     for(let offset=0;offset<selected.length;offset+=10) {
       const batch=selected.slice(offset,offset+10),account=destination.name;
       const payload={version:account==='alice'?1:2,account,source:oldA.fingerprint,target:destination.fingerprint,entries:batch,...(account==='alice'?{}:{source_account:'alice',purpose:'sender-history'})};
       const ciphertext=await pgp.encrypt({message:await pgp.createMessage({text:JSON.stringify(payload)}),encryptionKeys:destination.public,signingKeys:oldA.private,format:'armored'});
       transfers.push({account,target:destination.fingerprint,ciphertext,items:batch.map(({message_id,digest})=>({message_id,digest}))});
     }
   }
   assert.equal(Number(fixture({action:'settled-scale',source:oldA.fingerprint,keys:extra.map(({fingerprint,publicKey})=>({fingerprint,publicKey})),transfers})),115);
   const traffic=[],responses=[];
   for(const ctx of [a,t])ctx.on('response',response=>{if(response.url().includes('/history_sync/'))responses.push((async()=>{
     let text;try{text=await response.text();}catch{traffic.push({status:response.status(),url:response.url(),aborted:true});return;}
     assert(Buffer.byteLength(text)<=4*1048576);traffic.push({status:response.status(),url:response.url(),bytes:Buffer.byteLength(text)});
   })().catch(error=>errors.push(error.message)));});
   // The new key's ordinary visit records foreground activity; donor remains closed until then.
   const newPage=await t.newPage();await newPage.goto(base+'/messages/conversation/bob');
   await newPage.waitForFunction(()=>document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled==='1');
   await newPage.close();
   console.log('Natural donor visit supplies the active key, then becomes idle');
   await visit(a,{source:oldA.fingerprint,target:target.fingerprint,count:19});
   let page=await readAll(t,19);await settled(page);
   assert.equal(await page.locator('[data-reader-state="verified"]').count(),19,'Both sent and received originals must verify');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.screenshot({path:join(root,'scale-desktop.png'),fullPage:true});
   await page.setViewportSize({width:375,height:812});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.screenshot({path:join(root,'scale-mobile.png'),fullPage:true});await page.close();
   const idle=[];page=await a.newPage();page.on('request',r=>{if(r.url().includes('/history_sync/'))idle.push(r.url());});
   await page.goto(base+'/');await settled(page);await page.waitForTimeout(1500);
   assert.deepEqual(idle.map(u=>new URL(u).pathname),['/api/private_messages/history_sync/work'],'Settled natural visit has one request and no continuation');
   await page.close();await Promise.all(responses);
   const idleResponse=traffic.at(-1);assert(idleResponse.bytes<=8192);
   assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before);
   page=await t.newPage();await page.goto(base+'/messages/conversation/bob');
   let throttled=false;
   for(let n=0;n<17;n++) {
     const response=await t.request.post(base+'/api/private_messages/history_sync/work',{headers,data:{}});
     if(response.status()===429){assert(Number(response.headers()['retry-after'])>=1);throttled=true;break;}
   }
   assert(throttled,'Fixture must reach history admission before checking ordinary sending');
   await page.locator('textarea').fill('Send during history throttling');
   await page.getByRole('button',{name:'Send',exact:true}).click();
   await page.getByText('Send during history throttling',{exact:true}).waitFor();await page.close();
   assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals.filter(row=>row.message_id.startsWith('history-')),before);
   assert.deepEqual(errors,[]);
   await writeFile(join(root,'scale-report.txt'),`Approved keys: 115
Originals: 19, sent and received
Flow: Natural new-key visit, natural donor visit, ordinary conversation read
Forced sync refresh calls: 0
Idle visit requests: ${idle.length}
Idle response bytes: ${idleResponse.bytes}
Total history responses: ${traffic.length}
Rate-limit responses: ${traffic.filter(r=>r.status===429).length}
Ordinary send during confirmed history 429: Passed
Membership fixture: Real public keys, seeded inactive membership; active target approved through signed API
Production replay: Not performed
`);
   console.log('115-key natural recovery and one-request idle check passed. Artifacts: '+root);
 } else {

 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/work',{data:{}})).status(),403);
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/work',{headers:{...headers,'Sec-Fetch-Site':'cross-site'},data:{}})).status(),403);
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/work',{headers,data:{complete:'invalid'}})).status(),400);
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/acknowledge',{data:{items:[]}})).status(),403);
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/acknowledge',{headers:{...headers,'Sec-Fetch-Site':'cross-site'},data:{items:[]}})).status(),403);
 const work=await t.request.get(base+'/api/private_messages/history_sync/work');assert.equal(work.status(),200);assert.match(work.headers()['cache-control'],/no-store/);
 const pendingPage=await t.newPage();await pendingPage.goto(base+'/messages');
 await pendingPage.waitForFunction(()=>document.querySelector('[data-conversation-row]')?.dataset.previewState==='decryption-failed');
 await pendingPage.close();
 if(direct) {
   const donorPage=await a.newPage();await donorPage.goto(base+'/');
   await donorPage.evaluate(async ({message,targetKey,targetFp,sourceFp,sourceAccount})=>{
     const c=window.ForumPrivateMessageHistoryCrypto,privateKey=localStorage.getItem('forum_pki_private_key');
     const keys=await c.extractKeys(message.envelope,privateKey);
     const entries=[{message_id:message.id,digest:c.digest(message.envelope),keys}];
     const ciphertext=await c.wrapHistoryKeys({account:'alice',source_account:sourceAccount,source:sourceFp,target:targetFp,privateKey,targetKey,entries});
     const response=await fetch('/api/private_messages/history_sync/transfers',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'ForumPrivateMessages'},body:JSON.stringify({account:'alice',target:targetFp,ciphertext,items:entries.map(({message_id,digest})=>({message_id,digest}))})});
     if(!response.ok) throw Error(await response.text());
   },{message:messages[0],targetKey:target.publicKey,targetFp:target.fingerprint,sourceFp:oldA.fingerprint,sourceAccount:oldA.name});
   await a.close();await b.close();
   let page=await readAll(t,1);
   assert.equal(await page.locator('[data-private-message-history-status]').count(),0);
   await page.reload();await page.waitForFunction(()=>document.querySelectorAll('[data-reader-state="verified"]').length===1);
   await t.close();
   assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before);
   assert.deepEqual(errors,[]);
   console.log('Single sender transfer → canonical preview/read → retained reload passed. Artifacts: '+root);
 } else {
 console.log('Donor A visits a non-Messages page; target returns after donor closes');
 await visit(a);await a.close();
 const partial=await t.newPage();await partial.goto(base+'/messages/conversation/bob');
 await partial.waitForFunction(()=>document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled==='1');
 await partial.waitForFunction(()=>document.querySelectorAll('[data-reader-state="verified"]').length>0 && document.querySelectorAll('[data-reader-state="decryption-failed"]').length>0);
 assert.ok(await partial.locator('[data-reader-state="verified"]').count()>0);
 assert.ok(await partial.locator('[data-reader-state="decryption-failed"]').count()>0);
 await partial.locator('textarea').fill('Draft during partial recovery');
 console.log('Donor B supplies remaining history; target reload preserves the draft and recovers');
 await visit(b);await b.close();await partial.bringToFront();
 await partial.reload();await settled(partial);
 assert.equal(await partial.locator('[data-private-message-history-status]').count(),0);
 assert.equal(await partial.getByRole('button',{name:'Retry history',exact:true}).count(),0);
 await partial.waitForFunction(()=>document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length===25);
 assert.equal(await partial.locator('textarea').inputValue(),'Draft during partial recovery');
 await partial.locator('textarea').fill('');await partial.close();
 let page=await readAll(t,31);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:join(root,'restored-desktop.png'),fullPage:true});
 if(senderAssisted) {
   const forwarded=await retainedCandidates(t,'history-01');
   assert(forwarded.transfers.some(row=>row.source===oldB.fingerprint && row.source_account==='bob'),'A restored sender key must also be able to contribute');
 }
 await page.setViewportSize({width:375,height:812});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:join(root,'restored-mobile.png'),fullPage:true});
 await page.reload();await page.waitForFunction(()=>document.querySelectorAll('[data-reader-state="verified"]').length===25);await page.close();
 assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before,'Synchronization must leave original ciphertext unchanged');
 console.log('Restored target approves and supplies a later device, with original donors closed');
 const idlePage=await t.newPage();await idlePage.goto(base+'/');
 await idlePage.waitForFunction(()=>window.ForumPrivateMessageUnread?.historyEligibility()===true);
 let idleRequests=[];
 idlePage.on('request',r=>{if(r.url().includes('/history_sync/'))idleRequests.push({url:r.url(),method:r.method()});});
 await settled(idlePage);idleRequests=[];await idlePage.reload();await settled(idlePage);
 assert.deepEqual(idleRequests.map(r=>[new URL(r.url).pathname,r.method]),[['/api/private_messages/history_sync/work','POST']],'Settled history needs just one combined check');
 await idlePage.close();
 await approve(t,target,later);await visit(t,{source:target.fingerprint,target:later.fingerprint,count:31});await t.close();
 const l=await device(later);page=await readAll(l,31);await page.close();
 const candidate=await retainedCandidates(l,'history-01');
 assert.ok(candidate.transfers.some(row=>row.source===target.fingerprint));
 await l.close();
 console.log('Coordinated private backup/restore and sync-store outage');
 await copyFile(messagePath,messagePath+'.backup');await copyFile(syncPath,syncPath+'.backup');
 await rename(syncPath,syncPath+'.held');await mkdir(syncPath);
 const restored=await device(target);page=await restored.newPage();await page.goto(base+'/messages/conversation/bob');
 await page.waitForFunction(()=>window.ForumPrivateMessageUnread?.historyEligibility()===true);
 await page.evaluate(()=>window.ForumPrivateMessageHistorySync.refresh());
 assert.equal(await page.evaluate(()=>window.ForumPrivateMessageHistorySync.state().state),'error');
 assert.equal(await page.locator('[data-private-message-history-status]').count(),0);
 await page.locator('textarea').fill('Ordinary send during sync outage');await page.getByRole('button',{name:'Send',exact:true}).click();
 await page.getByText('Ordinary send during sync outage',{exact:true}).waitFor();
 await restored.close();await rmdir(syncPath);await rename(syncPath+'.held',syncPath);
 // Quiescent isolated stores: restore the coordinated pair, removing the test outage send.
 await copyFile(messagePath+'.backup',messagePath);await copyFile(syncPath+'.backup',syncPath);
 const fresh=await device(target);page=await readAll(fresh,31);await page.close();await fresh.close();
 const operator=await device(oldA),member=await device(target);
 await checkOperatorMessagingFlag(operator,member,base,root,project);
 await operator.close();await member.close();
 assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before);
 assert.deepEqual(errors,[]);
 for(const body of syncBodies) {assert(!body.includes('History secret'));assert(!body.includes('PRIVATE KEY BLOCK'));}
 assert(!log.includes('History secret'));assert(!log.includes('PRIVATE KEY BLOCK'));
 await writeFile(join(root,'report.json'),JSON.stringify({passed:true,mode:senderAssisted?'sender-assisted':'same-account',messages:31,checks:['no global history-sync notice or retry button','real signed approvals',...(senderAssisted?['recipient donors absent','restored sender contribution']:[]),'separate donor visits','partial donors','three-plus batches','retained reload','restored-device forwarding','mobile','draft preservation','no-store/auth/origin','original ciphertext unchanged','private paired backup/restore','sync outage with normal send','no plaintext/key logging','signed messaging toggle and member denial','disabled messaging preserves both private stores','environment overrides true and false lock operator control'],syncRequests:syncBodies.length},null,2));
 console.log('History sync browser checks passed. Artifacts: '+root);
 }
 }
} catch(error) {
 console.error('Browser artifacts: '+root);console.error(error);
 console.error('Recent history HTTP failures:',historyFailures.slice(-30));
 if(browser)for(const ctx of browser.contexts())for(const page of ctx.pages()) {
   try {console.error(await page.evaluate(()=>({path:location.pathname,sync:window.ForumPrivateMessageHistorySync?.state(),cards:Array.from(document.querySelectorAll('[data-private-message-id]')).map(c=>({id:c.dataset.privateMessageId,state:c.dataset.readerState}))})));}catch{}
 }
 process.exitCode=1;
}
finally {if(browser)await browser.close();server.kill();await writeFile(join(root,'server.log'),log);}
