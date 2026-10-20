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
    SIGNED_REACTION_TEST_ROOT: root, FORUM_TASK_QUEUE_DATABASE_PATH: join(root, 'tasks.sqlite3'),
    FORUM_PUBLIC_ARTIFACT_ROOT: join(root,'public'), FORUM_STATIC_HTML_ROOT: join(root,'static'), FORUM_DATABASE_PATH: join(root,'read.sqlite3'),
    VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
  const settings = { FASTMOD_META_TAGGING_ENABLED: true, FAST_SCORING_ENABLED: true, FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED: true,
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
  await page.goto(base + '/tools/fastmod/');
  await page.waitForFunction(() => !document.querySelector('[data-public-auth-resume="true"]'));
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
  assert.ok(calls[0].messages[0].content.includes('- Meta:'));
  assert.ok(!calls[0].messages[0].content.includes('- Moderation:'));
  assert.equal(calls[0].response_format, undefined);
  await page.goto(base + '/posts/' + postId);
  assert.match(await page.locator('body').textContent(), /Meta: 0.95/);
  await page.goto(base + '/tags/meta');
  assert.ok(await page.getByRole('link', { name: 'Support', exact: true }).count(), 'Scored root appears under meta');
  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const theme of ['light','dark','console','word97','chicago','midnight']) {
      await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${theme}/${width} overflow`);
      assert.equal(await page.getByRole('link', { name: 'Support', exact: true }).isVisible(), true);
      await page.screenshot({ path: `/tmp/fastmod-meta-${theme}-${width}.png`, fullPage: true });
    }
  }
  const artifacts = await worker('artifacts');
  assert.ok(artifacts.labels.find(row => row.root_post_id === postId).thread_labels_json.includes('meta'));
  assert.deepEqual(artifacts.private_tables, []);
  assert.match(await readFile(join(root,'static/tags/meta.html'),'utf8'), /Support/);
  function cli(...args) {
    const result = spawnSync('php',[join(project,'scripts/fast_score.php'),...args],{env,encoding:'utf8'});
    assert.equal(result.status,0,result.stderr);return JSON.parse(result.stdout);
  }
  const removed = cli('meta-remove',`--post-id=${postId}`);
  assert.equal(removed.items.find(row=>row.post_id===postId).suppressed,1);
  await page.goto(base + '/threads/' + postId);
  assert.ok(!(await page.locator('body').textContent()).includes('Labels: meta'));
  assert.equal(calls.length,1,'Correction must not score');
  await worker();
  assert.equal(cli('meta-status').items.find(row=>row.post_id===postId).active,0,'Worker cannot restore suppression');
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
  assert.equal(cli('meta-status').items.find(row=>row.post_id===prepared.post_id).active,1);
  assert.equal(spawnSync('git',['-C',join(root,'repository'),'rev-parse','HEAD'],{encoding:'utf8'}).stdout,headBefore,'Scoring/tagging creates no Git commit');
  const replyResponse=await context.request.post(base+'/api/create_reply',{form:{thread_id:postId,parent_id:postId,body:'Another application support question.'}});
  const replyId=/^post_id=(.+)$/m.exec(await replyResponse.text())?.[1];assert.ok(replyId);
  await worker();assert.ok(!cli('meta-status').items.some(row=>row.post_id===replyId),'Replies never receive machine meta tags');
  cli('meta-rollback');
  assert.ok(cli('meta-status').items.every(row=>row.active===0));

  console.log(JSON.stringify({ status: 'passed', checks: ['inline controls and rubric prompts', 'empty selection rejected', 'signed meta-only save', 'save does not score', 'publication queues work', 'one text provider call', 'disabled rubric omitted', 'named score recorded and displayed', 'two scores still one call', 'malformed output recorded without partial scores', 'explicit retry recovers','root meta discovery across phone/desktop/themes','static/offline labels without private scores','CLI suppression and rollback','signed root tags without Git writes','replies excluded'] }));
} finally {
  if (browser) await browser.close();
  if (server) { server.kill(); await once(server, 'exit'); }
  await new Promise(resolve => provider.close(resolve));
  await rm(root, { recursive: true, force: true });
}
