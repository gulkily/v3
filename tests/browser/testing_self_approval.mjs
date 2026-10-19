import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile, rm, mkdir } from 'node:fs/promises';
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
const root = await mkdtemp(join(tmpdir(), 'approval-browser-'));
let server, browser, logs = '', activityDebug = [];
try {
  const fixture = spawnSync('php', [join(project, 'tests/Support/invite_browser_fixture.php'), root], { input: JSON.stringify(operator), encoding: 'utf8' });
  assert.equal(fixture.status, 0, fixture.stderr);
  const reset = spawnSync('php', ['-r', `require $argv[1].'/autoload.php'; array_map('unlink', glob($argv[2].'/repository/records/approval-seeds/*.txt')); passthru('git -C '.escapeshellarg($argv[2].'/repository').' add -A'); passthru('git -C '.escapeshellarg($argv[2].'/repository').' commit -qm reset'); (new \\ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2].'/repository', $argv[2].'/read.sqlite3', new \\ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2].'/repository')))->rebuild();`, project, root], {encoding:'utf8'});
  assert.equal(reset.status, 0, reset.stderr);
  await writeFile(join(root, '.test-self-approval'), '');
  const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/invite_browser_router.php')], {
    env: { ...process.env, INVITE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused.php'), FORUM_APPROVED_MEMBERS_ONLY: process.env.TEST_MEMBERS_ONLY || 'false',
      FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL: 'false', PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'pipe'] });
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

  const button = page.getByRole('button', {name:'Approve current account', exact:true});
  await button.waitFor();
  const token = await button.getAttribute('data-test-self-approval');
  async function submit(form, headers = {}) {
    return context.request.post(base + '/api/test_self_approval', {form, headers: {Origin:base, ...headers}});
  }
  assert.equal((await context.request.get(base+'/api/test_self_approval')).status(),405);
  assert.equal((await submit({token:'bad'})).status(),403);
  assert.equal((await submit({token}, {Origin:'https://foreign.example'})).status(),403);
  assert.equal((await submit({token, identity_id:'openpgp:'+'a'.repeat(40)})).status(),403);
  const anonymous = await browser.newContext();
  assert.equal((await anonymous.request.post(base+'/api/test_self_approval',{form:{token},headers:{Origin:base}})).status(),403);
  // A lobby hint is deliberately weaker than proof of the account key.
  const weakSession = spawnSync('php', ['-r', `session_save_path($argv[1]); session_id('weak-self-approval'); session_start(); $_SESSION['lobby_identity_id']=$argv[2]; $_SESSION['testing_self_approval_token']=$argv[3]; session_write_close();`, join(root,'sessions'), 'openpgp:'+fingerprint, token], {encoding:'utf8'});
  assert.equal(weakSession.status,0,weakSession.stderr);
  await anonymous.addCookies([{name:'PHPSESSID',value:'weak-self-approval',url:base}]);
  assert.equal((await anonymous.request.post(base+'/api/test_self_approval',{form:{token},headers:{Origin:base}})).status(),403);
  await anonymous.close();
  await rm(join(root,'.test-self-approval'));
  assert.equal((await submit({token})).status(),403);
  await page.reload();
  assert.equal(await button.count(),0);
  await writeFile(join(root,'.test-self-approval'),'enabled');
  await page.reload(); await button.waitFor();
  for (const width of [390,1280]) {
    await page.setViewportSize({width,height:844});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
    if (process.env.SELF_APPROVAL_SCREENSHOTS) await page.screenshot({path:join(process.env.SELF_APPROVAL_SCREENSHOTS, 'account-'+width+'.png'),fullPage:true});
  }
  const activeToken = await button.getAttribute('data-test-self-approval');
  // Force artifact retirement to fail after canonical approval persists.
  await mkdir(join(root,'static/current'),{recursive:true});
  await button.click();
  await page.waitForFunction(()=>/persisted/.test(document.getElementById('simple-status').textContent));
  assert.match(await page.locator('#simple-status').textContent(), /repair|rebuild|retry/i);
  await rm(join(root,'static/current'),{recursive:true,force:true});
  const repair = spawnSync(join(project,'v3'), ['rebuild',join(root,'repository'),join(root,'read.sqlite3')], {
    env:{...process.env,FORUM_PUBLIC_ARTIFACT_ROOT:join(root,'static'),FORUM_STATIC_HTML_ROOT:join(root,'static'),FORUM_SECRETS_PATH:join(root,'unused.php')},encoding:'utf8'
  });
  assert.equal(repair.status,0,repair.stderr);
  await button.click();
  await page.waitForFunction(()=>!document.querySelector('[data-test-self-approval]'));
  assert.equal((await context.request.get(base+'/api/auth_status')).status(),200);
  const profile = await context.request.get(base+'/api/get_profile?profile_slug=openpgp-'+fingerprint);
  assert.match(await profile.text(), /Approved: yes/);
  const lobby = await context.request.get(base+'/lobby/');
  assert.match(await lobby.text(), /Enter the site/);
  assert.equal((await submit({token:activeToken})).status(),200);
  const seed = await readFile(join(root,'repository/records/approval-seeds/openpgp-'+fingerprint+'.txt'),'utf8');
  assert.match(seed,/Seed-Reason: initial approved user/);
  console.log('PASS testing self-approval: authenticated browser, marker removal/re-enable, request protection, one click, immediate access, persisted-failure/CLI repair/retry, repeat, mobile/desktop');
} catch(error) { console.error(logs.slice(-3000)); throw error; }
finally {
  if(browser) await browser.close();
  if(server) { const stopped=once(server,'exit'); server.kill(); await stopped; }
  await rm(root,{recursive:true,force:true});
}
