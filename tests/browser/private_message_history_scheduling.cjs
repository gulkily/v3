const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const events={},values=new Map(),requests=[];
const source='a'.repeat(40),target='b'.repeat(40);
let clock=100000,slow=false,interrupt=false;
const realNow=Date.now;Date.now=()=>clock;
global.sessionStorage={getItem:k=>values.get(k),setItem:(k,v)=>values.set(k,v)};
global.localStorage={getItem:k=>k==='forum_pki_username'?'alice':'saved-key'};
global.CustomEvent=class {};
global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),addEventListener:(k,v)=>events[k]=v,dispatchEvent:()=>{}};
global.window={addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>({readPrivateKey:async()=>({getFingerprint:()=>source})})}};
window.ForumPrivateMessageHistoryCrypto={digest:()=> 'digest',extractKeys:async()=>{if(slow){clock+=10001;slow=false;}return ['secret'];},readOriginal:async()=> 'verified',wrapHistoryKeys:async()=> 'encrypted'};
global.fetch=async(url,options)=>{
 const body=options.body?JSON.parse(options.body):null;requests.push({url,body});
 if(interrupt){interrupt=false;document.hidden=true;events.visibilitychange();}
 const sender=url.endsWith('?mode=sender');
 const work={status:'ok',account:sender?'bob':'alice',source_account:'alice',mode:sender?'sender':'account',source,target,target_key:'target',
   messages:['one','two'].map(message_id=>({message_id,digest:'digest',encrypted_envelope:'original',sender_keys:['sender']})),checkpoint:{target,after_id:'two'},cycle_end:false};
 return {ok:true,json:async()=>url.includes('/work')?work:{status:'ok'}};
};
function install(){vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));events.DOMContentLoaded();return window.ForumPrivateMessageHistorySync.refresh();}
(async()=>{
 await install();
 assert.equal(requests.filter(r=>r.url.includes('/work')).length,8);
 assert.equal(requests.filter(r=>r.url.endsWith('?mode=sender')).length,4);
 assert.equal(requests.filter(r=>r.url.endsWith('/work')).length,4);
 // Short visit: a delayed response must not upload/acknowledge after visibility cancellation.
 requests.length=0;sessionStorage.setItem('forum_history_sync_next_mode:alice','sender');interrupt=true;
 await install();assert.equal(requests.length,1);assert(requests[0].url.endsWith('?mode=sender'));
 assert.equal(values.get('forum_history_sync_next_mode:alice'),'account');
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
 console.log('Shared budget, reload fairness, visibility cancellation and interrupted sender retry passed.');
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{Date.now=realNow});
