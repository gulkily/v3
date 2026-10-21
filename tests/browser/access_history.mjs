import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
import { checkRootApprovalPages } from './root_approval_checks.mjs';
const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(await readFile(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
async function keys(name) { return pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name }], format: 'armored', date: new Date(Date.now() - 60000) }); }
const operator = await keys('browser-inviter');
const fingerprint = (await pgp.readKey({ armoredKey: operator.publicKey })).getFingerprint();
const root = await mkdtemp(join(tmpdir(), 'access-history-browser-'));
let server, browser, logs = '', activityDebug = [];
try {
  const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
  assert.equal(fixture.status, 0, fixture.stderr);
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: 'false',
      PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { logs += data; });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  await context.addInitScript(({ operator, fingerprint }) => {
    for (const [key, value] of Object.entries({ username: 'browser-inviter', public_key: operator.publicKey, private_key: operator.privateKey, fingerprint, published_fingerprint: fingerprint.toUpperCase() })) localStorage.setItem('forum_pki_' + key, value);
  }, { operator, fingerprint });
  const page = await context.newPage(); page.setDefaultTimeout(30000);
  page.on('pageerror', error => activityDebug.push(error.message));
  page.on('request', request => { if (request.url().includes('forte_activity_')) activityDebug.push(request.url()); });
  await page.goto(base + '/account/key/');
  await page.waitForFunction(() => document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId !== '');
  await page.goto(base+'/tools/user-access/');
  await page.getByText('No access changes recorded yet.').waitFor();
  const security = '/activity/?view=security';
  await context.clearCookies();
  await context.addCookies([{name:'identity_hint', value:'openpgp:'+fingerprint, url:base}]);
  const denied = await context.request.get(base+security);
  assert.equal(denied.status(),403);
  assert.match(denied.headers()['cache-control'],/no-store/);
  assert.match(await denied.text(),/Sign in with your operator account/);
  assert.doesNotMatch(await denied.text(),/Acceptance receipt/);
  const apiDenied=await context.request.get(base+'/api/forte_activity_page?view=security');
  assert.equal(apiDenied.status(),403);
  await page.route('**/api/auth_challenge',route=>route.fulfill({status:503,body:'error=Temporary authentication failure. Try again.'}));
  await page.goto(base+security);
  await page.locator('[data-role="private-site-auth-status"]').filter({hasText:'Temporary authentication failure'}).waitFor();
  assert.equal(await page.getByRole('button',{name:'Sign in',exact:true}).isEnabled(),true);
  await page.unroute('**/api/auth_challenge');
  await page.getByRole('button',{name:'Sign in',exact:true}).click();
  await page.getByRole('heading',{name:'Security review',exact:true}).waitFor();
  assert.equal((await context.request.get(base+security)).status(),200);
  // Expire again and verify automatic canonical authentication also resumes.
  await context.clearCookies();
  await page.goto(base+security);
  await page.getByRole('heading',{name:'Security review',exact:true}).waitFor();
  const outsider=await browser.newContext();
  const outside=await outsider.newPage();
  await outside.goto(base+security+'&return_to=https://example.invalid/');
  await outside.getByRole('button',{name:'Sign in',exact:true}).click();
  await outside.getByText('Restore or choose an operator account, then return here and try again.').waitFor();
  assert.equal(new URL(outside.url()).origin,base);
  await outsider.close();
  const privateKey = await pgp.readPrivateKey({armoredKey:operator.privateKey});
  async function changePolicy(value) {
    const prepared=await (await context.request.post(base+'/api/prepare_feature_flag_change',{form:{key:'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL',value:String(value)}})).json();
    assert.equal(prepared.status,'ok',JSON.stringify(prepared));
    const signature=await pgp.sign({message:await pgp.createMessage({text:prepared.canonical_record}),signingKeys:privateKey,detached:true});
    const result=await (await context.request.post(base+'/api/finalize_feature_flag_change',{form:{prepare_token:prepared.prepare_token,record_id:prepared.record_id,record_path:prepared.record_path,canonical_record:prepared.canonical_record,detached_signature:signature}})).json();
    assert.equal(result.status,'ok',JSON.stringify(result));
  }
  for(let i=0;i<5;i++) await changePolicy(i%2===1);
  await page.goto(base+'/tools/user-access/');
  assert.equal(await page.locator('.access-history-event').count(),5);
  assert.equal(await page.getByRole('link',{name:'View all',exact:true}).count(),0);
  await changePolicy(true); await page.reload();
  assert.equal(await page.locator('.access-history-event').count(),5);
  await page.getByRole('link',{name:'View all',exact:true}).waitFor();
  await page.locator('.access-history-event > summary').first().click();
  await page.locator('.access-history-source > summary').first().click();
  assert.equal(await page.locator('.access-history-source').first().getByRole('link').first().isVisible(),true);
  console.log('PASS access history: five recent events, conditional View all, expandable source evidence and operator recovery');
} finally {
  await browser?.close();
  if(server){const stopped=once(server,'exit');server.kill();await stopped;}
  await rm(root,{recursive:true,force:true});
}
