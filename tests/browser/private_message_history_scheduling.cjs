const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const combined=process.argv.includes('--combined'),paired=process.argv.includes('--paired');
const events={},values=new Map(),requests=[];
const source='a'.repeat(40),target='b'.repeat(40);
let clock=100000,slow=false,interrupt=false,shutdown=false;
const realNow=Date.now;Date.now=()=>clock;
global.sessionStorage={getItem:k=>values.get(k),setItem:(k,v)=>values.set(k,v)};
global.localStorage={getItem:k=>k==='forum_pki_username'?'alice':'saved-key'};
global.CustomEvent=class {constructor(type){this.type=type;}};
global.MutationObserver=class {observe(){} disconnect(){}};
global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),querySelectorAll:()=>[],addEventListener:(k,v)=>events[k]=v,dispatchEvent:event=>{if(events[event.type])events[event.type](event)}};
global.window={addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>({readPrivateKey:async()=>({getFingerprint:()=>source})})}};
window.ForumPrivateMessageUnread={historyEligibility:()=>true};
window.ForumPrivateMessageHistoryCrypto={digest:()=> 'digest',extractKeys:async()=>{if(slow){clock+=10001;slow=false;}return ['secret'];},readOriginal:async()=> 'verified',wrapHistoryKeys:async()=> 'encrypted'};
global.fetch=async(url,options)=>{
 const body=options.body?JSON.parse(options.body):null;
 if(!combined && url.endsWith('/work') && options.method==='POST')return {ok:false,status:405,json:async()=>({status:'error'})};
 requests.push({url,body});
 if(interrupt){interrupt=false;document.hidden=true;events.visibilitychange();}
 if(shutdown)return {status:403,clone:()=>({json:async()=>({code:'messaging_disabled'})})};
 const sender=combined ? values.get('forum_history_sync_next_mode:alice')==='sender' : url.endsWith('?mode=sender');
 const work={status:'ok',account:sender?'bob':'alice',source_account:'alice',mode:sender?'sender':'account',source,target,target_key:'target',
   messages:['one','two'].map(message_id=>({message_id,digest:'digest',encrypted_envelope:'original',sender_keys:['sender']})),checkpoint:{target,after_id:'two'},cycle_end:false};
 return {ok:true,json:async()=>url.includes('/work')?(combined?{status:'ok',mode:'combined',account:'alice',source,work:paired?[work,{...work,mode:sender?'account':'sender',account:sender?'alice':'bob'}]:[work],complete:[],continuation:true}:work):{status:'ok'}};
};
function install(){vm.runInThisContext(fs.readFileSync('public/assets/private_message_availability.js','utf8'));vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));events.DOMContentLoaded();return window.ForumPrivateMessageHistorySync.refresh();}
(async()=>{
 await install();
 assert.equal(requests.filter(r=>r.url.includes('/work')).length,paired?4:8);
 if(!combined) {assert.equal(requests.filter(r=>r.url.endsWith('?mode=sender')).length,4);assert.equal(requests.filter(r=>r.url.endsWith('/work')).length,4);}
 // Short visit: a delayed response must not upload/acknowledge after visibility cancellation.
 requests.length=0;sessionStorage.setItem('forum_history_sync_next_mode:alice','sender');interrupt=true;
 await install();assert.equal(requests.length,1);assert(requests[0].url.endsWith(combined?'/work':'?mode=sender'));
 assert.equal(values.get('forum_history_sync_next_mode:alice'),combined?'sender':'account');
 document.hidden=false;requests.length=0;await install();
 assert(requests[0].url.endsWith('/work'),'Next visit must preserve the other mode’s turn');
 // A partial sender batch may upload verified entries, but cannot advance past unfinished work.
 requests.length=0;sessionStorage.setItem('forum_history_sync_next_mode:alice','sender');slow=true;
 await install();
 const partial=requests.find(r=>r.url.endsWith('/acknowledge')).body;
 assert.equal(partial.mode,'sender');assert.equal(partial.checkpoint,null);
 assert.deepEqual(partial.items.map(r=>r.message_id),['one']);
 assert.equal(requests.filter(r=>r.url.includes('/work')).length,1);
 requests.length=0;await window.ForumPrivateMessageHistorySync.refresh();
 const resumed=requests.find(r=>r.body?.mode==='sender').body;
 assert.deepEqual(resumed.items.map(r=>r.message_id),['one','two']);assert.equal(resumed.checkpoint.after_id,'two');
 assert([...values.values()].every(v=>['account','sender'].includes(v)));
 requests.length=0;shutdown=true;await install();
 assert.equal(requests.length,1,'Disabled response must not be retried');
 assert.equal(window.ForumPrivateMessageHistorySync.state().state,'disabled');
 await window.ForumPrivateMessageHistorySync.refresh();
 await Promise.all(Array.from({length:8},(_,i)=>window.ForumPrivateMessageHistorySync.candidates('queued-'+i,'original')));
 window.ForumPrivateMessageHistorySync.confirm('one','original');
 assert.equal(requests.length,1,'Disabled background work remains stopped');
 requests.length=0;
 const running=install();
 const queued=Array.from({length:8},(_,i)=>window.ForumPrivateMessageHistorySync.candidates('racing-'+i,'original'));
 await Promise.allSettled([running,...queued]);
 const admitted=requests.length;
 assert(admitted>=1 && admitted<=3,'Only already-admitted requests may race with disable');
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.length,admitted,'Queued requests must drain without network retries');
 // Re-enable during an old in-flight request: no overlapping run or stale shutdown.
 shutdown=false;
 window.ForumPrivateMessageAvailability.setEnabled(true);
 const realFetch=global.fetch;
 let release, held=false;
 global.fetch=async(url,options)=>{
   if(!held){held=true;return new Promise(resolve=>{release=()=>resolve({status:403,clone:()=>({json:async()=>({code:'messaging_disabled'})})});});}
   return realFetch(url,options);
 };
 requests.length=0;
 const oldRun=window.ForumPrivateMessageHistorySync.start({dataset:{viewer:'alice'}});
 while(!held) await new Promise(resolve=>setImmediate(resolve));
 window.ForumPrivateMessageAvailability.setEnabled(false);
 window.ForumPrivateMessageAvailability.setEnabled(true);
 const restarted=window.ForumPrivateMessageHistorySync.start({dataset:{viewer:'alice'}});
 assert.equal(requests.length,0,'A new run must wait for cancelled work to drain');
 release();
 await Promise.all([oldRun,restarted]);
 assert.equal(window.ForumPrivateMessageAvailability.isDisabled(),false);
 assert.equal(requests.filter(r=>r.url.includes('/work')).length,paired?4:8,'One bounded run resumes');
 assert.notEqual(window.ForumPrivateMessageHistorySync.state().state,'disabled');
 window.ForumPrivateMessageHistorySync.stop();
 const stopped=requests.length;
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.length,stopped,'Explicit stop prevents focus/refresh work');
 global.fetch=realFetch;
 console.log('Shared budget, reload fairness, visibility cancellation and interrupted sender retry passed.');
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{Date.now=realNow});
