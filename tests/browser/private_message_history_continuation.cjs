const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const nativeImmediate=setImmediate;
let now=100000,nextTimer=0,timers=new Map(),calls=[],seen=[],cursor=0,total=25,throttle=false,candidateMode=false,candidatePage=0,sticky=false,flaky=false;
const source='a'.repeat(40),events={},values=new Map();
const nativeNow=Date.now,nativeSetTimeout=global.setTimeout,nativeClearTimeout=global.clearTimeout;
Date.now=()=>now;global.setTimeout=(fn,ms)=>{const id=++nextTimer;timers.set(id,{fn,at:now+ms});return id};global.clearTimeout=id=>timers.delete(id);
global.localStorage={getItem:k=>k==='forum_pki_username'?'alice':'key'};
global.sessionStorage={getItem:k=>values.get(k),setItem:(k,v)=>values.set(k,v)};
global.CustomEvent=class {constructor(type,options){this.type=type;this.detail=options?.detail}};
global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),addEventListener:(k,v)=>events[k]=v,dispatchEvent:()=>{}};
global.window={addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>({readPrivateKey:async()=>({getFingerprint:()=>source})})}};
window.ForumPrivateMessageUnread={historyEligibility:()=>true};
window.ForumPrivateMessageHistoryCrypto={digest:()=> 'digest',extractKeys:async envelope=>{seen.push([now,envelope]);if(candidateMode)throw Error('Needs donor');return ['key'];},readOriginal:async()=> 'verified',unwrapHistoryKeys:async input=>{if(input.ciphertext!=='valid')throw Error('Bad donor');return [{message_id:'m0',digest:'digest',keys:['key']}];},wrapHistoryKeys:async()=> 'encrypted'};
global.fetch=async(url,options)=>{
 const body=options.body?JSON.parse(options.body):null;calls.push({at:now,url,body});
 if(flaky){flaky=false;return {ok:false,status:503,json:async()=>({status:'error'})};}
 if(throttle){throttle=false;return {ok:false,status:429,headers:{get:()=> '3'},json:async()=>({status:'error'})};}
 let result={status:'ok'};
 if(url.endsWith('/work')) {
  const end=Math.min(cursor+10,total),messages=Array.from({length:end-cursor},(_,i)=>({message_id:'m'+(cursor+i),digest:'digest',encrypted_envelope:'m'+(cursor+i),sender_keys:['key'],candidate_available:candidateMode}));
  Object.assign(result,{mode:'combined',account:'alice',source,revision:'revision',work:messages.length?[{mode:'account',account:'alice',source,target:source,target_key:'key',messages,checkpoint:{target:source,after_id:String(end)},cycle_end:end===total}]:[],complete:messages.length?['sender']:['account','sender'],continuation:messages.length>0});
 } else if(url.includes('/transfers?')) {
  candidatePage=Number(new URL(url,'http://test').searchParams.get('after')||0)+1;
  Object.assign(result,{account:'alice',target:source,message_id:'m0',digest:'digest',next:candidatePage<20?String(candidatePage):null,transfers:[{account:'alice',source,target:source,source_key:'key',transfer_id:'transfer',ciphertext:candidatePage===20?'valid':'invalid'}]});
 } else if(url.endsWith('/acknowledge') && body.checkpoint && !sticky)cursor=Number(body.checkpoint.after_id);
 return {ok:true,json:async()=>result};
};
const settle=()=>new Promise(resolve=>nativeImmediate(resolve));
async function install(){vm.runInThisContext(fs.readFileSync('public/assets/private_message_availability.js','utf8'));vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));events.DOMContentLoaded();await window.ForumPrivateMessageHistorySync.refresh();await settle();}
async function tick(){const entry=[...timers].sort((a,b)=>a[1].at-b[1].at)[0];assert(entry,'Expected continuation');assert(entry[1].at-now>=1000,'Continuation yields at least one second');now=entry[1].at;timers.delete(entry[0]);entry[1].fn();await settle();}
function reset(){window.ForumPrivateMessageHistorySync?.stop();timers.clear();calls=[];seen=[];cursor=0;candidateMode=false;candidatePage=0;sticky=false;flaky=false;}
(async()=>{
 await install();assert.equal(cursor,10);await tick();assert.equal(cursor,20);await tick();assert.equal(cursor,25);assert.equal(timers.size,0,'Settled work has no timer');
 for(const time of new Set(calls.map(c=>c.at)))assert(calls.filter(c=>c.at===time).length<=16);
 for(const time of new Set(seen.map(c=>c[0])))assert(new Set(seen.filter(c=>c[0]===time).map(c=>c[1])).size<=10);
 reset();total=1;throttle=true;const start=now;await install();assert.equal(calls.length,1);assert.equal([...timers.values()][0].at,start+3000);await tick();assert.equal(cursor,1);assert.equal(timers.size,0);
 reset();total=1;flaky=true;await install();assert.equal(calls.length,3,'One transient retry counts toward the turn');assert.equal(cursor,1);
 reset();total=25;sticky=true;await install();assert.equal(timers.size,0,'Repeated unchanged checkpoint stops continuation');
 reset();candidateMode=true;total=1;await install();assert.equal(calls.length,16);assert.equal(candidatePage,15);await tick();assert.equal(candidatePage,20,'Candidate continuation resumes after the last checked page');assert.equal(cursor,1);assert.equal(timers.size,0);
 reset();total=25;await install();assert(timers.size);document.hidden=true;events.visibilitychange();assert.equal(timers.size,0);document.hidden=false;
 reset();Object.defineProperty(global,'navigator',{configurable:true,value:{locks:{request:async(name,options,fn)=>{assert(options.ifAvailable);return fn(null);}}}});await install();assert.equal(calls.length,0,'Another tab holds the shared lock');assert.equal(timers.size,0);
 console.log('Natural continuation, 10 originals/16 attempts, Retry-After, candidate resume, hidden cancellation, idle stop and shared lock pass.');
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{window.ForumPrivateMessageHistorySync?.stop();global.setTimeout=nativeSetTimeout;global.clearTimeout=nativeClearTimeout;Date.now=nativeNow});
