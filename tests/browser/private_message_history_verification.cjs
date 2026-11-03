const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),{spawn}=require('child_process'),readline=require('readline');
global.crypto=require('crypto').webcrypto;
vm.runInThisContext(fs.readFileSync('public/assets/openpgp.min.js','utf8'));
(async()=>{
 const keys=await Promise.all(Array.from({length:115},async(_,i)=>{
  const k=await openpgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'key'+i}],format:'armored'});
  return {...k,fp:(await openpgp.readKey({armoredKey:k.publicKey})).getFingerprint()};
 }));
 keys.sort((a,b)=>a.fp.localeCompare(b.fp));const source=keys[0],signer=keys.at(-1);
 const envelope=await openpgp.encrypt({message:await openpgp.createMessage({text:'preserved original'}),encryptionKeys:await openpgp.readKey({armoredKey:source.publicKey}),signingKeys:await openpgp.readPrivateKey({armoredKey:signer.privateKey}),format:'armored'});
 const service=spawn('php',['tests/browser/history_verification_service.php']);
 const pending=[];readline.createInterface({input:service.stdout}).on('line',line=>pending.shift()(JSON.parse(line)));
 service.stderr.pipe(process.stderr);
 const call=input=>new Promise(resolve=>{pending.push(resolve);service.stdin.write(JSON.stringify(input)+'\n');});
 try {
  assert.equal((await call({action:'setup',keys:keys.map(({fp,publicKey})=>({fp,publicKey})),source:source.fp,envelope,submitting:keys[1].fp,retired:process.argv.includes('--retired')?[keys[1].fp,signer.fp]:[]})).status,'ok');
  const values={forum_pki_username:'alice',forum_pki_public_key:source.publicKey,forum_pki_private_key:source.privateKey};
  global.localStorage={getItem:k=>values[k]||'',setItem:()=>assert.fail('No secrets persisted')};
  global.sessionStorage={getItem:()=>'',setItem:()=>{}};
  global.CustomEvent=class {constructor(type,options){this.type=type;this.detail=options.detail;}};
  const events={};global.document={hidden:false,querySelector:()=>({dataset:{viewer:'alice'}}),addEventListener:(n,f)=>events[n]=f,dispatchEvent:()=>{}};
  global.window={localStorage,addEventListener:()=>{},__forumBrowserIdentity:{ensureOpenPgpApi:async()=>openpgp}};
  vm.runInThisContext(fs.readFileSync('public/assets/private_message_history_crypto.js','utf8'));
  window.ForumPrivateMessageUnread={historyEligibility:()=>true};
  let pages=0,verified=false,lookups=0;
  global.fetch=async(url,options)=>{
   const u=new URL(url,'http://test'),action=u.pathname.split('/').at(-1);
   const body=options.body?JSON.parse(options.body):Object.fromEntries(u.searchParams);
   // Scope this harness to the source's own recovery. Other stages test target scheduling.
   if(action==='work')body.complete=['sender'];
   const out=await call({action,body});assert.equal(out.status,'ok',out.error);
   assert(Buffer.byteLength(JSON.stringify(out))<=4*1048576);assert(out.usage.keys<=64);assert(out.usage.decisions<=160);
   if(action==='transfers' && !body.ciphertext)lookups++;
   if(action==='work')pages++;
   if(action==='acknowledge' && body.items?.length)verified=true;
   return {ok:true,json:async()=>out};
  };
  vm.runInThisContext(fs.readFileSync('public/assets/private_message_availability.js','utf8'));
  vm.runInThisContext(fs.readFileSync(process.argv.includes('--modern')?'public/assets/private_message_history_sync.js':'tests/browser/fixtures/private_message_history_sync_legacy.js','utf8'));
  events.DOMContentLoaded();await window.ForumPrivateMessageHistorySync.refresh();
  // Legacy clients require ordinary later visits after their old eight-bundle window.
  for(let visit=0;visit<5&&!verified;visit++)await window.ForumPrivateMessageHistorySync.refresh(true);
  assert(verified,'An unchanged legacy coordinator must reach the later actual signer');
  assert(pages>8,'Signer must be beyond the first verification pages');
  const receipt=await call({action:'receipt',target:source.fp,envelope});assert(receipt.receipt);
  if(process.argv.includes('--modern'))assert.equal(lookups,0,'Wrong signer must not fetch transfer candidates');
  console.log(`Real 115-key ${process.argv.includes('--modern')?'modern':'legacy'} recovery passed: ${pages} bounded work pages; ${lookups} legacy candidate lookups (modern dedup pending).`);
 } finally {window.ForumPrivateMessageHistorySync?.stop();service.stdin.end();}
})().catch(e=>{console.error(e);process.exitCode=1});
