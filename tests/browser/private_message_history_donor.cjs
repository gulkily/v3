const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const senderMode=process.argv.includes('--sender');
global.crypto=require('crypto').webcrypto;
vm.runInThisContext(fs.readFileSync('public/assets/openpgp.min.js','utf8'));
(async()=>{
 const gen=async name=>{const pair=await openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name}],format:'armored'});return {...pair,fp:(await openpgp.readKey({armoredKey:pair.publicKey})).getFingerprint()};};
 const [donor,target,sender]=await Promise.all(['donor','target','sender'].map(gen));
 const envelope=await openpgp.encrypt({message:await openpgp.createMessage({text:'private historical text'}),encryptionKeys:await openpgp.readKey({armoredKey:donor.publicKey}),signingKeys:await openpgp.readPrivateKey({armoredKey:sender.privateKey}),format:'armored'});
 const values={forum_pki_username:'alice',forum_pki_public_key:donor.publicKey,forum_pki_private_key:donor.privateKey};
 let storageWrites=0;global.localStorage={getItem:k=>values[k]||'',setItem:()=>storageWrites++};
 const events={};global.CustomEvent=class {constructor(type,options){this.type=type;this.detail=options.detail;}};
 global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),addEventListener:(name,fn)=>{events[name]=fn},dispatchEvent:()=>{}};
 global.window={localStorage,addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>openpgp}};
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_crypto.js','utf8'));
 const c=window.ForumPrivateMessageHistoryCrypto,requests=[];let loseReceipt=true;
 global.fetch=async(url,options)=>{
   const body=options.body?JSON.parse(options.body):null;requests.push({url,body});
   if(url.endsWith('/acknowledge') && loseReceipt) {loseReceipt=false;throw Error('Response lost after commit');}
   const row={message_id:'m1',encrypted_envelope:envelope,digest:c.digest(envelope),sender_keys:[sender.publicKey]};
   const work={status:'ok',account:'alice',source:donor.fp,target:target.fp,target_key:target.publicKey,messages:senderMode?[]:[row],checkpoint:{target:donor.fp,after_id:''},cycle_end:true};
   if(senderMode && url.endsWith('/work?mode=sender'))Object.assign(work,{mode:'sender',source_account:'alice',account:'bob',messages:[row],checkpoint:{target:'',after_id:''}});
   return {ok:true,json:async()=>url.includes('/work')?work:{status:'ok',transfer_id:'uploaded'}};
 };
 vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_sync.js','utf8'));
 events.DOMContentLoaded();await window.ForumPrivateMessageHistorySync.refresh();
 const uploaded=requests.find(r=>r.url.endsWith('/transfers')).body;
 assert.equal(uploaded.target,target.fp);assert.equal(storageWrites,0);
 assert(!JSON.stringify(requests).includes('private historical text'));
 assert(!JSON.stringify(requests).includes(donor.privateKey));
 const recovered=await c.unwrapHistoryKeys({account:senderMode?'bob':'alice',source_account:'alice',source:donor.fp,target:target.fp,sourceKey:donor.publicKey,privateKey:target.privateKey,ciphertext:uploaded.ciphertext});
 assert.equal(await c.readOriginal(envelope,recovered[0].keys,[sender.publicKey]),'private historical text');
 assert.equal(window.ForumPrivateMessageHistorySync.state().contributed,1);
 assert.equal(requests.filter(r=>r.url.endsWith('/acknowledge')).length,senderMode?3:2);
 if(senderMode) {
   assert.equal(uploaded.account,'bob');
   const receipt=requests.find(r=>r.body?.mode==='sender').body;
   assert.equal(receipt.items[0].message_id,'m1');assert(!('account' in receipt));
   assert(requests.some(r=>r.url.endsWith('/work')));assert(requests.some(r=>r.url.endsWith('/work?mode=sender')));
 }
 // Cancel a response arriving after the saved identity changes: no stale-key upload/receipt.
 const before=requests.length;
 global.fetch=async()=>{values.forum_pki_private_key=target.privateKey;return {ok:true,json:async()=>({status:'ok'})};};
 await window.ForumPrivateMessageHistorySync.refresh();
 assert.equal(requests.length,before);
 assert.equal(window.ForumPrivateMessageHistorySync.state().contributed,1);
 console.log('Automatic donor visit produces a target-only transfer; no plaintext/private-key/cache writes.');
})().catch(e=>{console.error(e);process.exit(1)});
