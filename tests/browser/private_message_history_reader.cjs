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
   transfers[id]=await c.wrapHistoryKeys({account:'alice',source:donor.fp,target:target.fp,privateKey:donor.privateKey,targetKey:target.publicKey,entries:[{message_id:id,digest:c.digest(envelopes[id]),keys}]});
 }
 const receipts=[];let retrievals=0;
 global.fetch=async(url,options)=>{
   let result={status:'ok'};
   if(url.endsWith('/work')) Object.assign(result,{account:'alice',source:target.fp,target:target.fp,target_key:target.publicKey,messages:[],checkpoint:{target:target.fp,after_id:''},cycle_end:true,total:2});
   else if(url.includes('/transfers?')) {
     retrievals++;const id=new URL('http://local'+url).searchParams.get('message_id');
     Object.assign(result,{account:'alice',target:target.fp,message_id:id,digest:c.digest(envelopes[id]),next:null,transfers:[
       {account:'alice',source:donor.fp,target:target.fp,source_key:donor.publicKey,transfer_id:'bad',ciphertext:'invalid'},
       {account:'alice',source:donor.fp,target:target.fp,source_key:donor.publicKey,transfer_id:id,ciphertext:transfers[id]}
     ]});
   } else if(url.endsWith('/acknowledge')) receipts.push(JSON.parse(options.body));
   return {ok:true,json:async()=>result};
 };
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_reader.js','utf8'));
 listeners.DOMContentLoaded.forEach(fn=>fn());await window.ForumPrivateMessageHistorySync.refresh();
 const read=id=>window.ForumPrivateMessageReader.decryptEnvelope({messageId:id,encryptedEnvelope:envelopes[id],senderPublicKeyArmors:[sender.publicKey]});
 const good=await read('signed');assert.equal(good.kind,'verified');assert.equal(good.plaintext,'signed historical secret');
 const bad=await read('unsigned');assert.equal(bad.kind,'bad-signature');assert(!('plaintext' in bad));
 // A fresh coordinator has no recovered secret cache; retained server ciphertext still works.
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));
 listeners.DOMContentLoaded.at(-1)();await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal((await read('signed')).kind,'verified');
 await new Promise(resolve=>setTimeout(resolve,0));
 assert(retrievals>=3);
 assert(receipts.some(r=>r.items.some(i=>i.transfer_id==='signed')));
 assert(!receipts.some(r=>r.items.some(i=>i.message_id==='unsigned')));
 console.log('Canonical reader restores after reload, skips bad donors, preserves unsigned warnings, and confirms only verified originals.');
})().catch(e=>{console.error(e);process.exit(1)});
