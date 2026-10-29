import assert from 'node:assert/strict';
import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createServer as createSocket } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';
const project = resolve(new URL('../..', import.meta.url).pathname);
const root = await mkdtemp(join(tmpdir(), 'fastmod-browser-'));
let server, browser;
const calls = [];
let output = '0.95';
const provider = createServer(async (req, res) => {
  let body = ''; for await (const chunk of req) body += chunk;
  calls.push(JSON.parse(body));
  res.setHeader('Content-Type', 'application/json');
  res.end(JSON.stringify({ id: 'local-fastmod', choices: [{ message: { content: output }, finish_reason: 'stop' }] }));
});
await new Promise(resolve => provider.listen(0, '127.0.0.1', resolve));
try {
  runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
  const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'fastmod-browser' }], format: 'armored' });
  const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
  const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', FORUM_SECRETS_PATH: join(root, 'secrets.php'),
    SIGNED_REACTION_TEST_ROOT: root, FORUM_REPOSITORY_ROOT:join(root,'repository'), FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'tasks.sqlite3'),
    FORUM_PUBLIC_ARTIFACT_ROOT: join(root,'public'), FORUM_STATIC_HTML_ROOT: join(root,'static'), FORUM_DATABASE_PATH: join(root,'read.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const settings = { FASTMOD_META_TAGGING_ENABLED: false, FAST_SCORING_ENABLED: true, FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED: true,
    FAST_SCORING_DATABASE_PATH: join(root, 'scores.sqlite3'), LLM_PROVIDER: 'openai', LLM_API_KEY: 'local-test-key',
    LLM_API_BASE_URL: `http://127.0.0.1:${provider.address().port}`, LLM_MODEL: 'test', LLM_CONVERSATION_RECORDING_ENABLED: false };
  await writeFile(env.FORUM_SECRETS_PATH, '<?php return ' + '[' + Object.entries(settings).map(([k,v]) => JSON.stringify(k) + ' => ' + JSON.stringify(v)).join(',') + '];');
  const seed = spawnSync('php', [join(project, 'tests/Support/fastmod_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
  assert.equal(seed.status, 0, seed.stderr);
  const { identity } = JSON.parse(seed.stdout);
  const socket = createSocket().listen(0, '127.0.0.1'); await once(socket, 'listening');
  const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'ignore'] });
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block' });
  await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
  await context.addInitScript(({ keys, fingerprint }) => {
    for (const [key, value] of Object.entries({ private_key: keys.privateKey, public_key: keys.publicKey, fingerprint, published_fingerprint: fingerprint, username: 'fastmod-browser' })) localStorage.setItem('forum_pki_' + key, value);
  }, { keys, fingerprint });
  const page = await context.newPage();
  async function settleAuth() {
    if (await page.locator('[data-public-auth-resume="true"]').count()) {
      await page.evaluate(() => window.PrivateSiteAuth.authenticate({ navigate: false }));
      await page.reload();
    }
  }
  const flagKey='FASTMOD_AUTOMATIC_TAGGING_ENABLED';
  const flagRow=()=>page.locator('#flag-'+flagKey);
  async function setTagging(on) {
    await page.goto(base+'/tools/feature-flags/');
    await settleAuth();
    const control=flagRow().getByRole('switch',{name:'Automatic Fastmod tagging',exact:true});
    if ((await control.getAttribute('aria-checked')) !== String(on)) {
      await control.focus();await page.keyboard.press('Space');
      try { await page.waitForFunction(({key,on})=>document.querySelector('#flag-'+key+' [role="switch"]').getAttribute('aria-checked')===String(on),{key:flagKey,on}); }
      catch(error) { throw new Error(error.message+' Row: '+await flagRow().innerText()); }
    }
  }
  await page.goto(base+'/tools/feature-flags/');
  await settleAuth();
  assert.equal(await flagRow().getByRole('switch').getAttribute('aria-checked'),'false');
  const anonymous=await browser.newContext();
  const denied=await anonymous.request.post(base+'/api/prepare_feature_flag_change',{form:{key:flagKey,value:'true'}});
  assert.ok(denied.status()>=400);await anonymous.close();
  await page.route('**/api/finalize_feature_flag_change',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({status:'error',error:'Temporary save failure. Try again.'})}));
  await flagRow().getByRole('switch').click();
  await flagRow().locator('[data-role="feature-flag-status"]').filter({hasText:'Temporary save failure'}).waitFor();
  assert.equal(await flagRow().getByRole('switch').getAttribute('aria-checked'),'false');
  await page.unroute('**/api/finalize_feature_flag_change');
  await setTagging(true);
  assert.equal(calls.length,0,'Signed flag saves do not score');
  for (const width of [390,1280]) {
    await page.setViewportSize({width,height:900});
    await page.getByRole('searchbox',{name:'Search flags'}).fill('Automatic Fastmod tagging');
    for (const theme of ['light','dark','console','word97','chicago','midnight']) {
      await page.evaluate(theme=>document.documentElement.dataset.theme=theme,theme);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${theme}/${width} flag overflow`);
      assert.equal(await flagRow().getByRole('switch').isVisible(),true);
      await page.screenshot({path:`/tmp/fastmod-flag-${theme}-${width}.png`,fullPage:true});
    }
  }
  await page.goto(base + '/tools/fastmod/');
  await settleAuth();
  assert.equal(await page.locator('dialog').count(), 0);
  for (const id of ['meta', 'moderation']) {
    assert.equal(await page.locator('#fastmod-prompt-' + id).textContent(), readFileSync(join(project, 'prompts/fastmod/' + id + '.txt'), 'utf8').trim());
    assert.equal(await page.locator('#fastmod-prompt-' + id).isVisible(), true);
  }
  await page.getByLabel('Moderation', { exact: true }).uncheck();
  await page.getByLabel('Meta', { exact: true }).uncheck();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  assert.match(await page.locator('[data-fastmod-status]').textContent(), /at least one/);
  await page.getByLabel('Meta', { exact: true }).check();
  await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save', exact: true }).click()]);
  assert.deepEqual(JSON.parse(await readFile(join(root, 'state/private/fastmod_rubrics.json'), 'utf8')), ['meta']);
  assert.equal(calls.length, 0, 'Saving must not trigger scoring');
  const published = await context.request.post(base + '/api/create_thread', { form: { board_tags: 'general', subject: 'Support', body: 'Please help with the application interface.' } });
  const text = await published.text();
  const postId = /^post_id=(.+)$/m.exec(text)?.[1];
  assert.ok(postId, text);
  // Spawn asynchronously so the local HTTP provider remains responsive.
  async function worker(action = '') {
    const child = spawn('php', [join(project, 'tests/Support/fastmod_browser_worker.php'), root, action], { env });
    let stdout = '', stderr = ''; child.stdout.on('data', data => stdout += data); child.stderr.on('data', data => stderr += data);
    const [code] = await once(child, 'exit'); assert.equal(code, 0, stderr); return JSON.parse(stdout);
  }
  const first = await worker();
  assert.equal(first.summary.scored, 1); assert.equal(calls.length, 1);
  assert.deepEqual(first.work[0].scores, { meta: 0.95 });
  assert.deepEqual((await worker('state')).classification_tables, [], 'Scoring creates no classification tables');
  assert.ok(calls[0].messages[0].content.includes('- Meta:'));
  assert.ok(!calls[0].messages[0].content.includes('- Moderation:'));
  assert.equal(calls[0].response_format, undefined);
  await page.goto(base + '/posts/' + postId);
  assert.match(await page.locator('body').textContent(), /Meta: 0.95/);
  await page.goto(base + '/tags/meta');
  assert.ok(await page.getByRole('link', { name: 'Support', exact: true }).count(), 'Scored root appears under meta');
  const plain = await context.request.get(base+'/api/get_thread?thread_id='+postId);
  assert.match(await plain.text(), /^Labels: meta$/m);
  const noScript = await browser.newContext({ javaScriptEnabled: false, serviceWorkers: 'block' });
  const initial = await noScript.newPage();
  await initial.goto(base+'/tags/meta');
  assert.equal(await initial.getByRole('link',{name:'Support',exact:true}).isVisible(),true,'Derived discovery is present without client mutation');
  await initial.goto(base+'/forte/?tag=meta&selected='+postId);
  const forteRow = initial.locator(`[data-paned-thread-id="${postId}"]`);
  assert.equal(await forteRow.isVisible(),true);
  assert.ok((await forteRow.getAttribute('data-paned-thread-tags')).split(',').includes('meta'));
  await noScript.close();

  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const theme of ['light','dark','console','word97','chicago','midnight']) {
      await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${theme}/${width} overflow`);
      assert.equal(await page.getByRole('link', { name: 'Support', exact: true }).isVisible(), true);
      await page.screenshot({ path: `/tmp/fastmod-meta-${theme}-${width}.png`, fullPage: true });
    }
  }
  assert.equal((await worker('rebuild')).rebuilt,true);
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Support',exact:true}).isVisible(),true,'Tags survive read-model rebuild');
  assert.equal(calls.length,1,'Rebuild makes no provider calls');
  const artifacts = await worker('artifacts');
  assert.ok(artifacts.labels.find(row => row.root_post_id === postId).thread_labels_json.includes('meta'));
  assert.deepEqual(artifacts.private_tables, []);
  assert.match(await readFile(join(root,'static/tags/meta.html'),'utf8'), /Support/);
  function cli(...args) {
    const result = spawnSync('php',[join(project,'scripts/fast_score.php'),...args,'--json'],{env,encoding:'utf8'});
    assert.equal(result.status,0,result.stderr);return JSON.parse(result.stdout);
  }
  await setTagging(false);
  await page.goto(base + '/threads/' + postId);
  assert.ok(!(await page.locator('body').textContent()).includes('Labels: meta'));
  const disabledArtifacts = await worker('artifacts');
  assert.equal(disabledArtifacts.labels.find(row => row.root_post_id === postId).thread_labels_json, '[]');
  await setTagging(true);
  await page.goto(base + '/tags/meta');
  assert.equal(await page.getByRole('link', { name: 'Support', exact: true }).isVisible(), true);
  await worker('legacy-suppress:' + postId);
  await page.goto(base + '/threads/' + postId);
  assert.ok(!(await page.locator('body').textContent()).includes('Labels: meta'));
  assert.equal(calls.length,1,'Policy, rebuild and legacy fixture reads must not score');
  await worker();
  await page.goto(base + '/tags/meta');
  assert.equal(await page.getByRole('link', { name: 'Support', exact: true }).count(),0,'Worker cannot restore legacy suppression');
  await page.goto(base + '/tools/fastmod/');
  await page.getByLabel('Moderation', { exact: true }).check();
  await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save', exact: true }).click()]);
  assert.equal(calls.length, 1);
  output = '0.8, 0.2';
  await context.request.post(base + '/api/create_thread', { form: { board_tags: 'general', subject: 'More support', body: 'Another interface question.' } });
  const both = await worker();
  assert.equal(both.summary.provider_calls, 1);
  assert.equal(calls.length, 2);
  assert.deepEqual(both.work.find(row => row.score_status === 'scored' && Object.keys(row.scores).length === 2).scores, { meta: 0.8, moderation: 0.2 });
  assert.ok(calls[1].messages[0].content.includes('- Moderation:'));
  output = '0.8,invalid';
  await context.request.post(base + '/api/create_thread', { form: { board_tags: 'general', subject: 'Failure', body: 'A support request with malformed model output.' } });
  const failed = await worker();
  assert.equal(failed.summary.failed, 1);
  assert.equal(failed.work.find(row => row.score_status === 'invalid_response').state, 'pending');
  assert.deepEqual(failed.work.find(row => row.score_status === 'invalid_response').scores, []);
  output = '0.9,0.1';
  const recovered = await worker('retry');
  assert.equal(recovered.summary.scored, 1);
  assert.equal(calls.length, 4);
  // A signed root follows the same eligibility path.
  output = '0.95,0.1';
  const preparedResponse=await context.request.post(base+'/api/prepare_thread',{form:{author_identity_id:identity,board_tags:'general',subject:'Signed support',body:'Please help with this platform.'}});
  const prepared=await preparedResponse.json();assert.equal(prepared.status,'ok',JSON.stringify(prepared));
  const signature=await globalThis.openpgp.sign({message:await globalThis.openpgp.createMessage({text:prepared.canonical_record}),signingKeys:await globalThis.openpgp.readPrivateKey({armoredKey:keys.privateKey}),detached:true,format:'armored'});
  const signedResponse=await context.request.post(base+'/api/create_prepared_post',{form:{prepare_token:prepared.prepare_token,post_id:prepared.post_id,record_path:prepared.record_path,canonical_record:prepared.canonical_record,author_identity_id:identity,detached_signature:signature}});
  assert.equal((await signedResponse.json()).status,'ok');
  const headBefore=spawnSync('git',['-C',join(root,'repository'),'rev-parse','HEAD'],{encoding:'utf8'}).stdout;
  await worker();
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Signed support',exact:true}).isVisible(),true);
  assert.equal(spawnSync('git',['-C',join(root,'repository'),'rev-parse','HEAD'],{encoding:'utf8'}).stdout,headBefore,'Scoring/tagging creates no Git commit');
  const replyResponse=await context.request.post(base+'/api/create_reply',{form:{thread_id:postId,parent_id:postId,body:'Another application support question.'}});
  const replyId=/^post_id=(.+)$/m.exec(await replyResponse.text())?.[1];assert.ok(replyId);
  await worker();
  await page.goto(base+'/tags/meta');
  assert.equal(await page.locator(`a[href="/posts/${replyId}"]`).count(),0,'Replies never appear as tagged roots');
  settings.FASTMOD_META_TAGGING_ENABLED=true;
  await writeFile(env.FORUM_SECRETS_PATH,'<?php return ['+Object.entries(settings).map(([k,v])=>JSON.stringify(k)+' => '+JSON.stringify(v)).join(',')+'];');
  await setTagging(false);
  assert.equal(cli('meta-status').enabled,false);
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Signed support',exact:true}).count(),0,'Disabling removes derived tags');
  const callsBeforeOff=calls.length;
  const offResponse=await context.request.post(base+'/api/create_thread',{form:{board_tags:'general',subject:'Support while tagging off',body:'Help with the interface.'}});
  const offId=/^post_id=(.+)$/m.exec(await offResponse.text())?.[1];assert.ok(offId);
  await worker();assert.equal(calls.length,callsBeforeOff+1,'Scoring continues independently');
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Support while tagging off',exact:true}).count(),0);
  const callsBeforeEnable=calls.length;
  await setTagging(true);
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Support while tagging off',exact:true}).isVisible(),true,'Existing scores become eligible without rescoring');
  assert.equal(calls.length,callsBeforeEnable);
  const refreshed = await worker('artifacts');
  assert.ok(refreshed.labels.find(row=>row.root_post_id===offId).thread_labels_json.includes('meta'));
  await worker('prune');
  await page.goto(base+'/tags/meta');
  assert.equal(await page.getByRole('link',{name:'Support while tagging off',exact:true}).count(),0,'Pruning removes derived tags');
  const pruned = await worker('artifacts');
  assert.ok(pruned.labels.every(row=>!row.thread_labels_json.includes('meta')));
  assert.equal(calls.length,callsBeforeEnable,'Refresh/pruning never calls provider');

  console.log(JSON.stringify({ status: 'passed', checks: ['inline controls and rubric prompts', 'empty selection rejected', 'signed meta-only save', 'save does not score', 'publication queues work', 'one text provider call', 'disabled rubric omitted', 'named score recorded and displayed', 'two scores still one call', 'malformed output recorded without partial scores', 'explicit retry recovers','root meta discovery across phone/desktop/themes','static/offline labels without private scores','legacy suppression retained','signed root tags without Git writes','replies excluded','shared signed tagging flag with failed-save recovery','flag phone/desktop/themes','site off hides tags and preserves scoring','existing scores qualify after enable','pruning removes live and refreshed tags','no classification tables created','no-script first render and text/Forte parity'] }));
} finally {
  if (browser) await browser.close();
  if (server) { server.kill(); await once(server, 'exit'); }
  await new Promise(resolve => provider.close(resolve));
  await rm(root, { recursive: true, force: true });
}
