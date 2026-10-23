import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm, readdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
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
  await page.goto(base+security);await page.getByText('No access changes recorded yet.').waitFor();
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
  await page.locator('.activity-security-notice').waitFor();
  assert.equal((await context.request.get(base+security)).status(),200);
  // Expire again and verify automatic canonical authentication also resumes.
  await context.clearCookies();
  await page.goto(base+security);
  await page.locator('.activity-security-notice').waitFor();
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
  const recentIds=await page.locator('.access-history-event').evaluateAll(nodes=>nodes.map(n=>n.dataset.accessHistoryId));
  await page.getByRole('link',{name:'View all',exact:true}).click();
  await page.getByRole('heading',{name:'Access history',exact:true}).waitFor();
  const fullIds=await page.locator('.access-history-event').evaluateAll(nodes=>nodes.map(n=>n.dataset.accessHistoryId));
  assert.equal(fullIds.length,6);assert.deepEqual(fullIds.slice(0,5),recentIds);
  await page.getByRole('link',{name:'User Access',exact:true}).click();
  await page.getByRole('heading',{name:'User Access',exact:true}).waitFor();
  // Canonical legacy fixtures exercise shared-date cursor boundaries without fabricating receipts.
  for(let i=0;i<105;i++) {
    const id='legacy-access-'+String(i).padStart(3,'0');
    const tags=i===104?'identity invitation':'identity approval';
    const body=i===104?'Invitation-Action: redeem\nVerification-Hash: sha256:'+ 'a'.repeat(64):'Historical approval fixture.';
    await writeFile(join(root,'repository/records/posts',id+'.txt'),`Post-ID: ${id}\nCreated-At: 2026-01-01T12:00:00Z\nBoard-Tags: ${tags}\nAuthor-Identity-ID: openpgp:${fingerprint}\n\n${body}\n`);
  }
  for(const [command,args] of [['git',['-C',join(root,'repository'),'add','records/posts']],['git',['-C',join(root,'repository'),'commit','-qm','Add isolated legacy access history']],['php',[join(project,'scripts/rebuild_read_model.php'),join(root,'repository'),join(root,'read.sqlite3')]]]) {
    const result=spawnSync(command,args,{encoding:'utf8'});assert.equal(result.status,0,result.stdout+result.stderr);
  }
  await page.goto(base+security);
  const firstPage=await page.locator('.access-history-event').evaluateAll(nodes=>nodes.map(n=>n.dataset.accessHistoryId));
  assert.equal(firstPage.length,100);
  assert.equal(await page.getByText('Invitation accepted by browser-inviter',{exact:true}).count(),1);
  await page.getByRole('link',{name:'Next',exact:true}).click();
  const secondPage=await page.locator('.access-history-event').evaluateAll(nodes=>nodes.map(n=>n.dataset.accessHistoryId));
  assert.equal(secondPage.length,11);assert.equal(new Set([...firstPage,...secondPage]).size,111);
  await page.locator('.access-history-event > summary').first().click();
  await page.getByText('Historical acceptance classification, count, authority, and policy are unknown.',{exact:false}).first().waitFor();
  await page.getByRole('link',{name:'Previous',exact:true}).click();
  assert.equal(await page.locator('.access-history-event').count(),100);
  await page.goto(base+security+'&position=invalid');
  await page.getByRole('link',{name:'First page',exact:true}).click();
  await page.goto(base+'/forte/activity/?view=security');
  const firstRow=page.locator('[data-paned-activity-id]:visible').first();
  const firstId=await firstRow.getAttribute('data-paned-activity-id');
  const detail=await (await context.request.get(base+'/api/forte_activity_detail?view=security&id='+firstId)).json();
  assert.match(detail.html,/access-history-event/);
  assert.match(detail.html,/Source evidence/);
  const more=page.locator('[data-paned-activity-load-more][data-paned-activity-view="security"]');
  await more.click();
  await page.waitForFunction(()=>document.querySelectorAll('[data-paned-activity-id]:not([hidden])').length>40);
  const forteIds=await page.locator('[data-paned-activity-id]:visible').evaluateAll(nodes=>nodes.map(n=>n.dataset.panedActivityId));
  assert.equal(new Set(forteIds).size,forteIds.length);
  await page.getByRole('link',{name:'User Access',exact:true}).click();
  // Exercise a real member session, including a forged operator identity hint.
  const memberKeys=await keys('history-member');
  const memberFingerprint=(await pgp.readKey({armoredKey:memberKeys.publicKey})).getFingerprint();
  const linkedText=await (await context.request.post(base+'/api/link_identity',{form:{public_key:memberKeys.publicKey}})).text();
  const memberSlug=/^profile_slug=(.+)$/m.exec(linkedText)[1];
  await page.goto(base+'/tools/user-access/profiles/'+memberSlug);
  page.once('dialog',dialog=>dialog.accept());
  await page.locator('[data-signed-approval-form] button').click();
  await page.waitForURL(/approval=success/);
  const member=await browser.newContext();
  await member.addInitScript(({keys,fingerprint})=>{
    for(const [key,value] of Object.entries({username:'history-member',private_key:keys.privateKey,public_key:keys.publicKey,fingerprint,published_fingerprint:fingerprint.toUpperCase()}))localStorage.setItem('forum_pki_'+key,value);
  },{keys:memberKeys,fingerprint:memberFingerprint});
  const memberPage=await member.newPage();await memberPage.goto(base+'/account/key/');
  await memberPage.waitForFunction(()=>document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId!=='');
  await member.addCookies([{name:'identity_hint',value:'openpgp:'+fingerprint,url:base}]);
  await memberPage.goto(base+'/tools/user-access/');
  assert.equal(await memberPage.locator('#access-history').count(),0);
  const memberDenied=await member.request.get(base+security);
  assert.equal(memberDenied.status(),403);assert.doesNotMatch(await memberDenied.text(),/data-access-history-id/);
  await member.close();
  await context.clearCookies();await context.addCookies([{name:'identity_hint',value:'openpgp:'+fingerprint,url:base}]);
  const resume=await context.request.get(base+'/tools/user-access/');
  assert.match(await resume.text(),/Sign in with your operator account/);
  assert.doesNotMatch(await resume.text(),/data-access-history-id/);
  await page.goto(base+'/tools/user-access/');await page.locator('.access-history-event').first().waitFor();
  const screenshotDir=process.env.ACCESS_HISTORY_SCREENSHOT_DIR || '/tmp';
  const themes=(await readdir(join(project,'public/assets'))).map(f=>f.match(/^theme-([a-z0-9]+)\.css$/)?.[1]).filter(Boolean);
  async function theme(name){await page.evaluate(async name=>{
    if(!document.querySelector('[data-history-theme="'+name+'"]')) {
      const link=document.createElement('link');link.rel='stylesheet';link.href='/assets/theme-'+name+'.css';link.dataset.historyTheme=name;
      const loaded=new Promise((resolve,reject)=>{link.onload=resolve;link.onerror=reject;});document.head.append(link);await loaded;
    }document.documentElement.dataset.theme=name;
  },name);}
  for(const width of [390,1280]){
    await page.setViewportSize({width,height:900});
    await page.goto(base+'/tools/user-access/');
    const summary=page.locator('.access-history-event > summary').first();
    await summary.focus();await page.keyboard.press('Enter');
    assert.equal(await page.locator('.access-history-event').first().getAttribute('open'),'');
    for(const name of themes){await theme(name);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`User Access ${name}/${width}`);
      assert.equal(await page.getByRole('link',{name:'View all',exact:true}).isVisible(),true);
      if(['word97','chicago','midnight'].includes(name)) {
        const icon=await page.locator('.tools-nav a[href="/tools/user-access/"]').evaluate(el=>getComputedStyle(el,'::before').backgroundImage);
        assert.notEqual(icon,'none',`User Access icon ${name}`);
      }
      await page.screenshot({path:join(screenshotDir,`access-history-${name}-${width}.png`),fullPage:true});
    }
    await page.getByRole('link',{name:'View all',exact:true}).click();
    await page.locator('.access-history-event > summary').first().click();
    await page.locator('.access-history-source > summary').first().click();
    for(const name of themes){await theme(name);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`Full history ${name}/${width}`);}
    await page.getByRole('link',{name:'User Access',exact:true}).click();
    await page.getByRole('link',{name:'Pending approvals',exact:true}).click();
    await page.getByRole('heading',{name:'Users Awaiting Approval',exact:true}).waitFor();
    await page.goto(base+'/tools/');
    for(const name of ['word97','chicago','midnight']){await theme(name);assert.notEqual(await page.locator('.tool-launcher-button[href="/tools/user-access/"]').evaluate(el=>getComputedStyle(el,'::before').backgroundImage),'none');}
  }
  console.log(`PASS access history: recovery/roles, recent/full evidence, 111-event tied-date pagination, Forte and ${themes.length} themes at phone/desktop widths`);
} finally {
  await browser?.close();
  if(server){const stopped=once(server,'exit');server.kill();await stopped;}
  await rm(root,{recursive:true,force:true});
}
