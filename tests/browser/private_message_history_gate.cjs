const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const listeners={},values=new Map(),requests=[];
let hasHistory=false,username='alice',fail=false;
const nav={isConnected:true,dataset:{viewer:'alice'},querySelector:()=>({})};
global.localStorage={getItem:k=>k==='forum_pki_username'?username:'key'};
global.sessionStorage={getItem:()=>null,setItem:()=>{}};
global.CustomEvent=class {constructor(type){this.type=type;}};
function on(type,fn){(listeners[type]??=[]).push(fn);}
function emit(type){for(const fn of listeners[type]||[])fn({type});}
global.document={hidden:false,querySelector:()=>nav,querySelectorAll:()=>[],addEventListener:on,dispatchEvent:e=>emit(e.type)};
global.window={addEventListener:on,__forumBrowserIdentity:{ensureOpenPgpApi:async()=>({readPrivateKey:async()=>({getFingerprint:()=> 'a'.repeat(40)})})},ForumPrivateMessageHistoryCrypto:{}};
window.ForumPrivateMessageAvailability={isDisabled:()=>false,request:async(url)=>{
 requests.push(url);
 if(url.includes('/unread'))return {ok:!fail,json:async()=>({status:'ok',viewer:'alice',has_history:hasHistory,unread_count:0,revision:'1',conversations:{}})};
 return {ok:true,json:async()=>({status:'ok',mode:'combined',account:'alice',source:'a'.repeat(40),work:[],complete:['account','sender'],continuation:false})};
}};
for(const file of ['private_message_unread.js','private_message_history_sync.js'])vm.runInThisContext(fs.readFileSync('public/assets/'+file,'utf8'));
(async()=>{
 await window.ForumPrivateMessageHistorySync.start(nav);
 assert.equal(requests.length,0,'No sync before eligibility arrives');
 window.ForumPrivateMessageUnread.start(nav);
 await window.ForumPrivateMessageUnread.refresh();
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.filter(u=>u.includes('/history_sync/')).length,0);
 hasHistory=true;
 await window.ForumPrivateMessageUnread.refresh();
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.filter(u=>u.includes('/history_sync/')).length,1,'First message activates exactly one idle work check');
 requests.length=0;username='bob';
 await window.ForumPrivateMessageUnread.refresh();
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.length,0,'Changed identity cannot inherit eligibility');
 username='alice';window.ForumPrivateMessageUnread.stop();fail=true;
 window.ForumPrivateMessageUnread.start(nav);await window.ForumPrivateMessageUnread.refresh();
 assert.equal(window.ForumPrivateMessageUnread.historyEligibility(),null);
 fail=false;await window.ForumPrivateMessageUnread.refresh();
 assert.equal(window.ForumPrivateMessageUnread.historyEligibility(),true,'Failed initial refresh can recover');
 window.ForumPrivateMessageUnread.stop();
 console.log('History eligibility lifecycle passed');
})().catch(e=>{console.error(e);process.exitCode=1;});
