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
const format=process.env.FORMAT||'text';
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(readFileSync(join(project,'public/assets/openpgp.min.js'),'utf8'));
const pgp=globalThis.openpgp;
const root=await mkdtemp(join(tmpdir(),'key-backup-browser-'));
const keys=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'alice'}],format:'armored'});
keys.privateKey=keys.privateKey.replace(/\r?\n/g,'\r\n');
const fingerprint=(await pgp.readKey({armoredKey:keys.publicKey})).getFingerprint();
const envelope=await pgp.encrypt({message:await pgp.createMessage({text:'Original private conversation'}),encryptionKeys:await pgp.readKey({armoredKey:keys.publicKey}),format:'armored'});
const profiles=[{name:'alice',fingerprint,publicKey:keys.publicKey},...Array.from({length:60},(_,i)=>({name:'user-'+String(i+1).padStart(2,'0'),fingerprint,publicKey:keys.publicKey}))];
const seeded=spawnSync('php',[join(project,'tests/Support/private_message_browser_fixture.php'),root],{input:JSON.stringify({profiles,incoming:envelope,outgoing:envelope}),encoding:'utf8'});
assert.equal(seeded.status,0,seeded.stderr);
const socket=createServer().listen(0,'127.0.0.1'); await once(socket,'listening'); const port=socket.address().port; await new Promise(r=>socket.close(r));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/private_message_browser_router.php')],{env:{...process.env,PRIVATE_MESSAGE_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'unused-secrets.php'),PRIVATE_MESSAGE_DATABASE_PATH:join(root,'state/private/messages.sqlite3'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')},stdio:['ignore','ignore','pipe']});
let serverLog=''; server.stderr.on('data',d=>{serverLog+=d;});
let browser;
try {
  for(let i=0;i<80;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
  const engine=process.env.BROWSER||'chromium';
  browser=await (engine==='firefox'?firefox:chromium).launch({headless:true,...(engine==='firefox'?{}:{executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',args:['--no-sandbox']})});
  const context=await browser.newContext({serviceWorkers:'block',acceptDownloads:true});
  const page=await context.newPage(); const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/account/key/');
  await page.evaluate(({keys,fingerprint})=>{
    for(const [name,value] of Object.entries({username:'alice',private_key:keys.privateKey,public_key:keys.publicKey,fingerprint:fingerprint.toUpperCase(),published_fingerprint:fingerprint.toUpperCase()}))localStorage.setItem('forum_pki_'+name,value);
  },{keys,fingerprint});
  await page.reload();
  await page.waitForFunction(()=>Boolean(document.querySelector('[data-account-key-root]').dataset.authenticatedIdentityId));
  const passphrase='a long unique starfield passphrase';
  const requests=[]; context.on('request',r=>requests.push(r.url()+' '+(r.postData()||'')));
  await page.locator('[data-backup-password]').fill(passphrase);
  await page.locator('[data-backup-confirm]').fill(passphrase);
  assert.equal(await page.locator('[data-backup-password]').inputValue(),passphrase);
  const pending=page.waitForEvent('download'); pending.catch(()=>{}); await page.locator('[data-backup-download="'+format+'"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent !== 'Working locally…');
  assert.match(await page.locator('[data-backup-status]').textContent(),/download started/,JSON.stringify(errors));
  const download=await pending, backup=await readFile(await download.path());
  await page.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('download started'));
  assert(!backup.includes(Buffer.from('PRIVATE KEY')));
  const fresh=await browser.newContext({serviceWorkers:'block'}), restored=await fresh.newPage();
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
  await restored.locator('[data-restore-password]').fill(passphrase); await restored.locator('[data-backup-restore]').click();
  await restored.waitForFunction(()=>document.querySelector('[data-backup-status]').textContent.includes('Backup restored'));
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_private_key')),keys.privateKey);
  assert.equal(await restored.evaluate(()=>localStorage.getItem('forum_pki_fingerprint').toLowerCase()),fingerprint);
  assert(requests.every(r=>!r.includes(passphrase)&&!r.includes('PRIVATE KEY')&&!r.includes(encodeURIComponent(passphrase))));
  assert.deepEqual(errors,[]);
  console.log(engine+': normal Account Key '+format+' download, wrong password, fresh-profile restore, byte equality and request privacy passed');
} catch(error) { console.error('Fixture:',root); throw error; }
finally { if(browser)await browser.close(); server.kill(); }
