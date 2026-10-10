import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rename, mkdir, rmdir, copyFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

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
const [oldA,oldB,bob,target,later]=await Promise.all(['alice','alice','bob','alice','alice'].map(key));
const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
const base=`http://127.0.0.1:${port}`,syncPath=join(root,'state/private/message_history_sync.sqlite3'),messagePath=join(root,'state/private/messages.sqlite3');
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/private_message_browser_router.php')],{env:{...process.env,PRIVATE_MESSAGE_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'unused.php'),PRIVATE_MESSAGE_DATABASE_PATH:messagePath,PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH:syncPath,VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')},stdio:['ignore','ignore','pipe']});
let log='',browser;server.stderr.on('data',data=>{log+=data});
const errors=[],syncBodies=[],headers={'X-Requested-With':'ForumPrivateMessages'};
const sign=(who,text)=>pgp.createMessage({text}).then(message=>pgp.sign({message,signingKeys:who.private,detached:true}));
async function json(response,status=200) {assert.equal(response.status(),status,await response.text());return response.json();}
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
 return ctx;
}
async function visit(ctx) {
 const page=await ctx.newPage();await page.goto(base+'/');
 for(let n=0;n<3;n++) await page.evaluate(()=>window.ForumPrivateMessageHistorySync.refresh());
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
 const messages=[];
 for(let n=1;n<=31;n++) {
   const donor=n<=20?oldA:oldB,sender=n%2?bob:donor;
   const envelope=await pgp.encrypt({message:await pgp.createMessage({text:'History secret '+n}),encryptionKeys:[donor.public,bob.public],signingKeys:sender.private,format:'armored'});
   messages.push({id:'history-'+String(n).padStart(2,'0'),time:`2026-10-09T12:${String(n).padStart(2,'0')}:00Z`,sender:sender.name,recipient:sender===bob?'alice':'bob',fingerprint:sender.fingerprint,envelope});
 }
 fixture({action:'messages',messages});
 console.log('Approving target through signed canonical approval APIs');
 await approve(a,oldA,target);
 const before=messages.map(row=>({message_id:row.id,encrypted_envelope:row.envelope}));
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/acknowledge',{data:{items:[]}})).status(),403);
 assert.equal((await t.request.post(base+'/api/private_messages/history_sync/acknowledge',{headers:{...headers,'Sec-Fetch-Site':'cross-site'},data:{items:[]}})).status(),403);
 const work=await t.request.get(base+'/api/private_messages/history_sync/work');assert.equal(work.status(),200);assert.match(work.headers()['cache-control'],/no-store/);
 const pendingPage=await t.newPage();await pendingPage.goto(base+'/messages');
 await pendingPage.waitForFunction(()=>document.querySelector('[data-conversation-row]')?.dataset.previewState==='decryption-failed');
 await pendingPage.close();
 console.log('Donor A visits a non-Messages page; target returns after donor closes');
 await visit(a);await a.close();
 const partial=await t.newPage();await partial.goto(base+'/messages/conversation/bob');
 await partial.waitForFunction(()=>document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled==='1');
 assert.ok(await partial.locator('[data-reader-state="verified"]').count()>0);
 assert.ok(await partial.locator('[data-reader-state="decryption-failed"]').count()>0);
 await partial.locator('textarea').fill('Draft during partial recovery');
 console.log('Donor B supplies the remaining history; target retries in place');
 await visit(b);await b.close();await partial.bringToFront();
 await partial.evaluate(()=>window.ForumPrivateMessageHistorySync.refresh());
 await partial.getByRole('button',{name:'Retry history',exact:true}).click();
 await partial.waitForFunction(()=>document.querySelectorAll('[data-private-message-id][data-reader-state="verified"]').length===25);
 assert.equal(await partial.locator('textarea').inputValue(),'Draft during partial recovery');
 await partial.locator('textarea').fill('');await partial.close();
 let page=await readAll(t,31);
 await page.setViewportSize({width:375,height:812});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:join(root,'restored-mobile.png'),fullPage:true});
 await page.reload();await page.waitForFunction(()=>document.querySelectorAll('[data-reader-state="verified"]').length===25);await page.close();
 assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before,'Synchronization must leave original ciphertext unchanged');
 console.log('Restored target approves and supplies a later device, with original donors closed');
 await approve(t,target,later);await visit(t);await t.close();
 const l=await device(later);page=await readAll(l,31);await page.close();
 const candidate=await json(await l.request.get(base+'/api/private_messages/history_sync/transfers?message_id=history-01'));
 assert.ok(candidate.transfers.some(row=>row.source===target.fingerprint));
 await l.close();
 console.log('Coordinated private backup/restore and sync-store outage');
 await copyFile(messagePath,messagePath+'.backup');await copyFile(syncPath,syncPath+'.backup');
 await rename(syncPath,syncPath+'.held');await mkdir(syncPath);
 const restored=await device(target);page=await restored.newPage();await page.goto(base+'/messages/conversation/bob');
 await page.locator('[data-role="sync-status"]').filter({hasText:'History check could not finish'}).waitFor();
 await page.locator('textarea').fill('Ordinary send during sync outage');await page.getByRole('button',{name:'Send private message',exact:true}).click();
 await page.getByText('Ordinary send during sync outage',{exact:true}).waitFor();
 await restored.close();await rmdir(syncPath);await rename(syncPath+'.held',syncPath);
 // Quiescent isolated stores: restore the coordinated pair, removing the test outage send.
 await copyFile(messagePath+'.backup',messagePath);await copyFile(syncPath+'.backup',syncPath);
 const fresh=await device(target);page=await readAll(fresh,31);await page.close();await fresh.close();
 assert.deepEqual(JSON.parse(fixture({action:'inspect'})).originals,before);
 assert.deepEqual(errors,[]);
 for(const body of syncBodies) {assert(!body.includes('History secret'));assert(!body.includes('PRIVATE KEY BLOCK'));}
 assert(!log.includes('History secret'));assert(!log.includes('PRIVATE KEY BLOCK'));
 await writeFile(join(root,'report.json'),JSON.stringify({passed:true,messages:31,checks:['real signed approvals','separate donor visits','partial donors','three-plus batches','retained reload','restored-device forwarding','mobile','draft preservation','no-store/auth/origin','original ciphertext unchanged','private paired backup/restore','sync outage with normal send','no plaintext/key logging'],syncRequests:syncBodies.length},null,2));
 console.log('History sync browser checks passed. Artifacts: '+root);
} catch(error) {console.error('Browser artifacts: '+root);console.error(error);process.exitCode=1;}
finally {if(browser)await browser.close();server.kill();await writeFile(join(root,'server.log'),log);}
