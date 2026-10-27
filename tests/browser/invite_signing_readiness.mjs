// Run: node tests/browser/invite_signing_readiness.mjs; --baseline verifies the original defect.
import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
const project = resolve(new URL('../..', import.meta.url).pathname);
const baseline = process.argv.includes('--baseline');
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const keys = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'browser-inviter' }], format: 'armored', date: new Date(Date.now() - 60000) });
const fingerprint = (await pgp.readKey({ armoredKey: keys.publicKey })).getFingerprint();
 const keysB=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name:'second-inviter'}],format:'armored',date:new Date(Date.now()-60000)});
 const fpB=(await pgp.readKey({armoredKey:keysB.publicKey})).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'invite-browser-'));
const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify({...keys, additionalPublicKeys:[keysB.publicKey]}), encoding: 'utf8' });
assert.equal(fixture.status, 0, fixture.stderr);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
let server, logs = '', lastPage;
const contexts = [];
async function start(privateSite, usernameChanges = true) {
  if (server) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: String(privateSite), FORUM_USERNAME_CHANGES_ENABLED: String(usernameChanges),
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  const base = `http://127.0.0.1:${port}`;
  for (let n = 0; n < 60; n++) { try { await fetch(base + '/api/version'); return base; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  throw Error('Server did not start');
}
async function contextWithIdentity(base, identity) {
  const context = await browser.newContext({ serviceWorkers: 'block' }); contexts.push(context);
  if (identity) await context.addInitScript(({ keys, fingerprint }) => {
    if (!localStorage.getItem('forum_pki_private_key')) {
      localStorage.setItem('forum_pki_username', 'browser-inviter');
      localStorage.setItem('forum_pki_public_key', keys.publicKey);
      localStorage.setItem('forum_pki_private_key', keys.privateKey);
      localStorage.setItem('forum_pki_fingerprint', fingerprint);
      localStorage.setItem('forum_pki_published_fingerprint', fingerprint.toUpperCase());
    }
  }, identity);
  const page = await context.newPage(); lastPage = page; page.setDefaultTimeout(20000);
  return { context, page };
}
try {
 for(const privateSite of [false,true]) {
  const base=await start(privateSite);
  const {context,page}=await contextWithIdentity(base,{keys:keysB,fingerprint});
  await context.addInitScript(fp=>{localStorage.setItem('forum_pki_published_fingerprint',fp.toUpperCase());},fpB);
  // Establish a real session for A through authentication APIs, without visiting Account.
  const challenge=(await (await context.request.get(base+'/api/auth_challenge')).text()).match(/^challenge=([a-f0-9]+)$/m)[1];
  const signature=await pgp.sign({message:await pgp.createMessage({text:challenge}),signingKeys:await pgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true,format:'armored'});
  const auth=await context.request.post(base+'/api/authenticate_identity',{form:{identity_id:'openpgp:'+fingerprint,challenge,detached_signature:signature}});assert.equal(auth.status(),200,await auth.text());
  await page.goto(base+'/invites/');
  const requests=[];
  page.on('request',r=>{if(r.url().endsWith('/api/authenticate_identity'))requests.push('authenticate');});
  async function issue(){
   const preparedPromise=page.waitForResponse(r=>r.url().endsWith('/api/prepare_invitation'));
   const finalPromise=page.waitForResponse(r=>r.url().endsWith('/api/create_prepared_invitation'));
   await page.locator('[data-invitation-issue-form] button[type=submit]').click();
   const prepared=await(await preparedPromise).json(); const final=await(await finalPromise).json();
   return {author:prepared.canonical_record.match(/^Author-Identity-ID: (.+)$/m)[1],status:final.status,error:final.error||''};
  }
  const before=await page.evaluate(()=>[localStorage.getItem('forum_pki_public_key'),localStorage.getItem('forum_pki_private_key')]);
  const first=await issue();
  if (baseline) {
    const second=await issue();
    assert.equal(first.author,'openpgp:'+fingerprint);
    assert.equal(first.error,'Detached signature verification failed: signature_verification_failed');
    assert.equal(second.error,first.error);
    assert.equal(requests.length,0);
    await page.goto(base+'/account/key/');
    await page.waitForFunction(fp=>document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId==='openpgp:'+fp,fpB);
    await page.goto(base+'/invites/');
    assert.equal((await issue()).status,'ok');
  } else {
    assert.equal(first.status,'ok',first.error);
    assert.equal(first.author,'openpgp:'+fpB);
  }
  assert.deepEqual(await page.evaluate(()=>[localStorage.getItem('forum_pki_public_key'),localStorage.getItem('forum_pki_private_key')]),before);
  assert.equal(await page.locator('[data-role=invitation-qr]').isVisible(),true);
  console.log(`PASS ${privateSite?'members-only':'public'}: ${baseline?'baseline exact failure and Account recovery':'first-attempt recovery without Account'}, keys unchanged`);

 }
} finally {await browser.close();if(server)server.kill();await rm(root,{recursive:true,force:true});}
