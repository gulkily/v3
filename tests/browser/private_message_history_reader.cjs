const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
global.crypto=require('crypto').webcrypto;
vm.runInThisContext(fs.readFileSync('public/assets/openpgp.min.js','utf8'));
(async()=>{
 const gen=async name=>{const k=await openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name}],format:'armored'});return {...k,fp:(await openpgp.readKey({armoredKey:k.publicKey})).getFingerprint()};};
 const [donor,target,sender]=await Promise.all(['donor','target','sender'].map(gen));
 const values={forum_pki_username:'alice',forum_pki_public_key:target.publicKey,forum_pki_private_key:target.privateKey};
 global.localStorage={getItem:k=>values[k]||'',setItem:()=>{throw Error('Persistent secret write')}};
 global.CustomEvent=class {constructor(type,options){this.type=type;this.detail=options.detail}};
 const listeners={};global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),querySelectorAll:()=>[],addEventListener:(name,fn)=>{(listeners[name]??=[]).push(fn)},dispatchEvent:()=>{}};
 global.window={localStorage,addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>openpgp}};
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_crypto.js','utf8'));
 const c=window.ForumPrivateMessageHistoryCrypto,envelopes={},transfers={};
 for(const id of ['signed','unsigned']) {
   envelopes[id]=await openpgp.encrypt({message:await openpgp.createMessage({text:id+' historical secret'}),encryptionKeys:await openpgp.readKey({armoredKey:donor.publicKey}),...(id==='signed'?{signingKeys:await openpgp.readPrivateKey({armoredKey:sender.privateKey})}:{}),format:'armored'});
   const keys=await c.extractKeys(envelopes[id],donor.privateKey);
   transfers[id]=await c.wrapHistoryKeys({account:'alice',source_account:'bob',source:donor.fp,target:target.fp,privateKey:donor.privateKey,targetKey:target.publicKey,entries:[{message_id:id,digest:c.digest(envelopes[id]),keys}]});
 }
 const receipts=[];let retrievals=0,workRequests=0,throttleNext=false;
 let enteredThrottle,releaseThrottle,unsignedRequestAt=0;
 const throttleEntered=new Promise(resolve=>{enteredThrottle=resolve;});
 const throttleReleased=new Promise(resolve=>{releaseThrottle=resolve;});
 global.fetch=async(url,options)=>{
   let result={status:'ok'};
   if(url.endsWith('/work')) {workRequests++;Object.assign(result,{account:'alice',source:target.fp,target:target.fp,target_key:target.publicKey,messages:[],checkpoint:{target:target.fp,after_id:''},cycle_end:true,total:2});}
   else if(url.includes('/transfers?')) {
     if(throttleNext){throttleNext=false;enteredThrottle();await throttleReleased;return {ok:false,status:429,headers:{get:()=> '1'},json:async()=>({status:'error'})};}
     retrievals++;const id=new URL('http://local'+url).searchParams.get('message_id');
     if(id==='unsigned' && !unsignedRequestAt)unsignedRequestAt=Date.now();
     Object.assign(result,{account:'alice',target:target.fp,message_id:id,digest:c.digest(envelopes[id]),next:null,transfers:[
       {account:'alice',source_account:'bob',source:donor.fp,target:target.fp,source_key:donor.publicKey,transfer_id:'bad',ciphertext:'invalid'},
       {account:'alice',source_account:'bob',source:donor.fp,target:target.fp,source_key:donor.publicKey,transfer_id:id,ciphertext:transfers[id]}
     ]});
   } else if(url.endsWith('/acknowledge')) receipts.push(JSON.parse(options.body));
   return {ok:true,json:async()=>result};
 };
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_availability.js','utf8'));vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_reader.js','utf8'));
 listeners.DOMContentLoaded.forEach(fn=>fn());await window.ForumPrivateMessageHistorySync.refresh();
 const read=id=>window.ForumPrivateMessageReader.decryptEnvelope({messageId:id,encryptedEnvelope:envelopes[id],senderPublicKeyArmors:[sender.publicKey]});
 throttleNext=true;const started=Date.now();
 const reading=read('signed');await throttleEntered;
 const queuedReading=read('unsigned');await new Promise(resolve=>setTimeout(resolve,100));
 const releasedAt=Date.now();releaseThrottle();await new Promise(resolve=>setTimeout(resolve,100));
 const workBefore=workRequests;await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(workRequests,workBefore,'Background continuation must yield while foreground recovery is waiting');
 const good=await reading;assert.equal(good.kind,'verified');assert.equal(good.plaintext,'signed historical secret');
 assert.equal((await queuedReading).kind,'bad-signature');
 assert(unsignedRequestAt-releasedAt>=1000,'A reader queued before 429 must recheck the newly learned deadline');
 assert(Date.now()-started>=1000,'Foreground recovery must honor Retry-After and resume without a manual retry');
 const supplied={message_id:'signed',digest:c.digest(envelopes.signed),keys:await c.extractKeys(envelopes.signed,donor.privateKey),transfer_id:'signed',isCurrent:()=>true};
 const previous=retrievals;
 assert.equal((await window.ForumPrivateMessageReader.decryptEnvelope({messageId:'signed',encryptedEnvelope:envelopes.signed,senderPublicKeyArmors:[sender.publicKey],historyCandidate:supplied})).kind,'verified');
 assert.equal(retrievals,previous,'A current recovery handoff must not refetch the transfer');
 supplied.isCurrent=()=>false;
 Object.defineProperty(supplied,'keys',{get(){throw Error('Stale handoff used');}});
 assert.equal((await window.ForumPrivateMessageReader.decryptEnvelope({messageId:'signed',encryptedEnvelope:envelopes.signed,senderPublicKeyArmors:[sender.publicKey],historyCandidate:supplied})).kind,'verified','Discard stale handoffs while allowing current-generation candidate reuse');
 const bad=await read('unsigned');assert.equal(bad.kind,'bad-signature');assert(!('plaintext' in bad));
 // A fresh coordinator has no recovered secret cache; retained server ciphertext still works.
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_availability.js','utf8'));vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));
 listeners.DOMContentLoaded.at(-1)();await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal((await read('signed')).kind,'verified');
 await new Promise(resolve=>setTimeout(resolve,75));
 assert(retrievals>=3);
 assert(receipts.some(r=>r.items.some(i=>i.transfer_id==='signed')));
 assert(!receipts.some(r=>r.items.some(i=>i.message_id==='unsigned')));
 const before=receipts.length;
 for(let i=0;i<10;i++)window.ForumPrivateMessageHistorySync.confirm('coalesced-'+i,envelopes.signed,'signed');
 await new Promise(resolve=>setTimeout(resolve,75));
 assert.equal(receipts.length,before+1);assert.equal(receipts.at(-1).items.length,10);
 window.ForumPrivateMessageHistorySync.stop();
 console.log('Canonical reader restores after reload, skips bad donors, preserves unsigned warnings, and confirms only verified originals.');
})().catch(e=>{console.error(e);process.exit(1)});
