import assert from 'node:assert/strict';
import { mkdtemp, readFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium, firefox } from 'playwright-core';
import reencode from '../Support/png_reencode.cjs';
const format=process.env.FORMAT||'text';
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(readFileSync(join(project,'public/assets/openpgp.min.js'),'utf8'));
const pgp=globalThis.openpgp;
const root=await mkdtemp(join(tmpdir(),'key-backup-browser-'));
const keys=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'alice'}],format:'armored'});
keys.privateKey=keys.privateKey.replace(/\r?\n/g,'\r\n');
const fingerprint=(await pgp.readKey({armoredKey:keys.publicKey})).getFingerprint();
const envelope=await pgp.encrypt({message:await pgp.createMessage({text:'Original private conversation'}),encryptionKeys:await pgp.readKey({armoredKey:keys.publicKey}),signingKeys:await pgp.readPrivateKey({armoredKey:keys.privateKey}),format:'armored'});
const profiles=[{name:'alice',fingerprint,publicKey:keys.publicKey},...Array.from({length:60},(_,i)=>({name:'user-'+String(i+1).padStart(2,'0'),fingerprint,publicKey:keys.publicKey}))];
const seeded=spawnSync('php',[join(project,'tests/Support/private_message_browser_fixture.php'),root],{input:JSON.stringify({profiles,incoming:envelope,outgoing:envelope,canonical_identity:profiles[0]}),encoding:'utf8'});
assert.equal(seeded.status,0,seeded.stderr);
const socket=createServer().listen(0,'127.0.0.1'); await once(socket,'listening'); const port=socket.address().port; await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/private_message_browser_router.php')],{env:{...process.env,PRIVATE_MESSAGE_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'unused-secrets.php'),PRIVATE_MESSAGE_DATABASE_PATH:join(root,'state/private/messages.sqlite3'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3'),FORUM_APPROVED_MEMBERS_ONLY:process.env.MEMBERS_ONLY||'false'},stdio:['ignore','ignore','pipe']});
let serverLog=''; server.stderr.on('data',d=>{serverLog+=d;});
let browser, restoreBrowser, lastPage;
try {
  for(let i=0;i<80;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  const engine=process.env.BROWSER||'chromium';
  browser=await (engine==='firefox'?firefox:chromium).launch({headless:true,...(engine==='firefox'?{}:{executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',args:['--no-sandbox']})});
  const context=await browser.newContext({serviceWorkers:'block',acceptDownloads:true});
  if(process.env.BUNDLE==='v5')await context.addInitScript(()=>Object.defineProperty(window,'isSecureContext',{value:false}));
  const page=await context.newPage(); lastPage=page; page.setDefaultTimeout(15000); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/account/key/');
  await page.evaluate(({keys,fingerprint})=>{
    for(const [name,value] of Object.entries({username:'alice',private_key:keys.privateKey,public_key:keys.publicKey,fingerprint:fingerprint.toUpperCase(),published_fingerprint:fingerprint.toUpperCase()}))localStorage.setItem('forum_pki_'+name,value);
  },{keys,fingerprint});
  await page.reload();
  await page.waitForFunction(()=>Boolean(document.querySelector('[data-account-key-root]').dataset.authenticatedIdentityId));
  await page.goto(base+'/messages');
  if(await page.locator('[data-key-backup-reminder]').count()===0)throw new Error('Messages entry: '+(await page.locator('main').innerText()).slice(0,800));
  await page.locator('[data-key-backup-reminder]').waitFor({state:'visible'});
  await page.getByRole('link',{name:'Back up your key',exact:true}).click();
  const passphrase='a long unique starfield passphrase';
  const requests=[]; context.on('request',r=>requests.push(r.url()+' '+(r.postData()||'')));
  await page.locator('[data-backup-password]').fill(passphrase);
  await page.locator('[data-backup-confirm]').fill('does not match');
  await page.locator('[data-backup-download="'+format+'"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('do not match'));
  assert.equal(await page.evaluate(fp=>localStorage.getItem('forum_pki_backup_verified_'+fp),fingerprint),null);
  await page.locator('[data-backup-password]').fill(passphrase);
  await page.locator('[data-backup-confirm]').fill(passphrase);
  assert.equal(await page.locator('[data-backup-password]').inputValue(),passphrase);
  const pending=page.waitForEvent('download'); pending.catch(()=>{}); await page.locator('[data-backup-download="'+format+'"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent !== 'Working locally…');
  assert.match(await page.locator('[data-backup-status]').textContent(),/download started/,JSON.stringify(errors));
  const download=await pending, downloaded=await readFile(await download.path());
  const backup=format==='png'&&process.env.REENCODE==='1'?reencode(downloaded):downloaded;
  await page.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('download started'));
  assert(!backup.includes(Buffer.from('PRIVATE KEY')));
  assert.equal(await page.evaluate(fp=>localStorage.getItem('forum_pki_backup_verified_'+fp),fingerprint),'1');
  await page.goto(base+'/messages');
  assert.equal(await page.locator('[data-key-backup-reminder]').isVisible(),false);
  await page.goto(base+'/messages/conversation/user-01');
  assert.equal(await page.locator('[data-key-backup-reminder]').isVisible(),false);
  await page.evaluate(()=>{localStorage.setItem('forum_pki_fingerprint','a'.repeat(40));window.ForumKeyBackupStatus.refresh();});
  assert.equal(await page.locator('[data-key-backup-reminder]').isVisible(),true);
  await page.evaluate(fp=>localStorage.setItem('forum_pki_fingerprint',fp.toUpperCase()),fingerprint);
  await page.evaluate(()=>{
    const get=Storage.prototype.getItem;Storage.prototype.getItem=()=>{throw new Error('Storage unavailable');};
    try{window.ForumKeyBackupStatus.refresh();}finally{Storage.prototype.getItem=get;}
  });
  assert.equal(await page.locator('[data-key-backup-reminder]').isVisible(),true);
  const restoreEngine=engine==='chromium'?firefox:chromium;
  restoreBrowser=await restoreEngine.launch({headless:true,...(restoreEngine===chromium?{executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',args:['--no-sandbox']}:{})});
  const fresh=await restoreBrowser.newContext({serviceWorkers:'block'});
  if(process.env.RESTORE_BUNDLE==='v5')await fresh.addInitScript(()=>Object.defineProperty(window,'isSecureContext',{value:false}));
  const restored=await fresh.newPage(); lastPage=restored; restored.setDefaultTimeout(15000);
  fresh.on('request',r=>requests.push(r.url()+' '+(r.postData()||'')));
  restored.on('pageerror',e=>errors.push(e.message));
  await restored.goto(base+'/account/key/');
  const upload=()=>restored.locator('[data-backup-file]').setInputFiles({name:format==='png'?'backup.png':'backup.txt',mimeType:format==='png'?'image/png':'text/plain',buffer:backup});
  if(format==='png'){
    const jpeg=await page.evaluate(async base64=>{
      const img=new Image();img.src='data:image/png;base64,'+base64;await img.decode();
      const canvas=document.createElement('canvas');canvas.width=img.width;canvas.height=img.height;
      canvas.getContext('2d').drawImage(img,0,0);
      const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg'));
      return Array.from(new Uint8Array(await blob.arrayBuffer()));
    },backup.toString('base64'));
    const damaged=Buffer.from(backup);damaged[100]^=1;
    for(const bad of [{name:'converted.jpg',mimeType:'image/jpeg',buffer:Buffer.from(jpeg)},{name:'damaged.png',mimeType:'image/png',buffer:damaged}]){
      await restored.locator('[data-backup-file]').setInputFiles(bad);
      await restored.locator('[data-restore-password]').fill(passphrase);await restored.locator('[data-backup-restore]').click();
      await restored.waitForFunction(()=>/original/i.test(document.querySelector('[data-backup-status]').textContent));
      assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),null);
    }
  }
  await upload(); await restored.locator('[data-restore-password]').fill('wrong'); await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('Could not unlock'));
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),null);
  await restored.evaluate(()=>{
    const original=Storage.prototype.setItem;
    Storage.prototype.setItem=function(key,value){
      if(key==='forum_pki_private_key'){Storage.prototype.setItem=original;throw new Error('Simulated storage failure');}
      return original.call(this,key,value);
    };
  });
  await restored.locator('[data-restore-password]').fill(passphrase); await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('Simulated storage failure'));
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),null);
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_username')),null);
  await restored.route('**/api/set_identity_hint?*',route=>route.abort());
  await restored.locator('[data-restore-password]').fill(passphrase);await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent!=='Working locally…');
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),null);
  await restored.unroute('**/api/set_identity_hint?*');
  await restored.route('**/api/set_identity_hint?*',async route=>{
    await restored.evaluate(()=>localStorage.setItem('forum_pki_private_key','newer identity from another tab'));
    await route.abort();
  });
  await restored.locator('[data-restore-password]').fill(passphrase);await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent!=='Working locally…');
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),'newer identity from another tab');
  await restored.unroute('**/api/set_identity_hint?*');
  await restored.evaluate(()=>localStorage.clear());


  await restored.locator('[data-restore-password]').fill(passphrase); await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>Boolean(document.querySelector('[data-account-key-root]')?.dataset.authenticatedIdentityId));
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),keys.privateKey);
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_fingerprint').toLowerCase()),fingerprint);
  assert.equal(await restored.locator('[data-role="simple-status-badge"]').textContent(),'This browser is ready');
  assert.equal(await restored.locator('[data-action="clear-browser-identity"]').isVisible(),true);
  // No manual reload: the restored session must work immediately on Messages.
  await restored.goto(base+'/messages/conversation/user-01');
  await restored.locator('[data-role="private-message-plaintext"]').filter({hasText:'Original private conversation'}).first().waitFor({state:'visible'});
  await restored.goto(base+'/account/key/');
  await restored.evaluate(()=>{
    for(const key of ['forum_pki_username','forum_pki_public_key','forum_pki_private_key','forum_pki_fingerprint'])localStorage.removeItem(key);
    window.dispatchEvent(new Event('storage'));
  });
  assert.equal(await restored.locator('[data-role="simple-status-badge"]').textContent(),'Not set up yet');
  let navigated=false;const onNavigation=frame=>{if(frame===restored.mainFrame())navigated=true;};
  restored.on('framenavigated',onNavigation);
  await upload();await restored.locator('[data-restore-password]').fill(passphrase);await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('Backup restored'));
  assert.equal(await restored.locator('[data-role="simple-status-badge"]').textContent(),'This browser is ready');
  assert.match(await restored.locator('#simple-status').textContent(),/All set! Posting as alice/);
  assert.equal(await restored.locator('[data-action="clear-browser-identity"]').isVisible(),true);
  assert.equal(navigated,false);restored.off('framenavigated',onNavigation);
  const other=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'other'}],format:'armored'});
  const different=await restored.evaluate(async({key,passphrase})=>(await window.ForumKeyBackup.createBackup(key,passphrase)).text,{key:other.privateKey,passphrase});
  await restored.locator('[data-backup-file]').setInputFiles({name:'different.txt',mimeType:'text/plain',buffer:Buffer.from(different)});
  await restored.locator('[data-restore-password]').fill(passphrase);await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('fingerprint does not match'));
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),keys.privateKey);
  assert.equal(await page.evaluate(()=>window.__forumOpenPgpLoader.selectedVersion),process.env.BUNDLE||'v6');
  assert.equal(await restored.evaluate(()=>window.__forumOpenPgpLoader.selectedVersion),process.env.RESTORE_BUNDLE||'v6');
  assert(requests.every(r=>!r.includes(passphrase)&&!r.includes('PRIVATE KEY')&&!r.includes(encodeURIComponent(passphrase))));
  assert.deepEqual(errors,[]);
  console.log(engine+' → '+restoreEngine.name()+' '+format+' '+(process.env.BUNDLE||'v6')+' → '+(process.env.RESTORE_BUNDLE||'v6')+' members='+Boolean(process.env.MEMBERS_ONLY==='true')+': download, reminder, rejection, storage rollback, exact restore, original-message decryption and privacy passed');
} catch(error) { console.error('Fixture:',root); if(lastPage)console.error('Page:',lastPage.url(),'Status:',await lastPage.locator('[data-backup-status]').textContent({timeout:1000}).catch(()=>'')); throw error; }
finally { if(restoreBrowser)await restoreBrowser.close(); if(browser)await browser.close(); server.kill(); }
