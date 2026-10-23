// Real HTTP, real CLI worker, isolated state; baseline discovery uses explicit reloads.
const {chromium} = require('playwright-core');
const {spawn} = require('node:child_process');
const {readFileSync, appendFileSync} = require('node:fs');
const {join} = require('node:path');
const assert = require('node:assert/strict');
const directory = process.argv[2];
const base = process.argv[3];
const env = {...process.env, ...JSON.parse(readFileSync(join(directory, 'environment.json')))};
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH || '/opt/google/chrome/chrome', headless:true, args:['--no-sandbox']});
  try {
    const context = await browser.newContext({serviceWorkers:'block', viewport:{width:process.argv.includes('--mobile')?375:1280,height:800}});
    await context.addCookies([{name:'identity_hint',value:'guest',url:base}]);
    const page = await context.newPage();
    const created = await context.request.post(base + '/api/create_thread', {form:{board_tags:'general',body:'i slept in today, and now i am behind schedule. how do i address this?'}});
    const text = await created.text();
    const source = text.match(/^post_id=(.+)$/m)?.[1];
    assert.ok(source, text);
    await page.goto(base + (process.argv.includes('--post')?'/posts/':'/threads/') + source);
    const automatic = process.argv.includes('--auto');
    const trigger = page.locator('[data-inline-reply-compose] textarea').first();
    if (await trigger.count()) await trigger.click();
    const draft = page.locator('textarea:not([readonly]):visible').first();
    if (await draft.count()) await draft.fill('Keep this unfinished draft.');
    if (process.argv.includes('--busy')) {
      const holder = spawn('php', ['-r', '$h=fopen($argv[1],"c+");flock($h,LOCK_EX);echo "ready\\n";sleep(3);',
        join(directory,'private/forum-rewrite-task-queue.lock')]);
      await new Promise(resolve => holder.stdout.once('data', resolve));
    }
    const started = performance.now();
    await page.locator('[data-action="request-agent-reply"]').first().click();
    await page.locator('[data-response-mode="scripture_oriented_analysis"]').click();
    await page.getByText('Scripture-oriented analysis requested.',{exact:true}).waitFor();
    const queued = performance.now();
    if (process.argv.includes('--reload-pending')) await page.reload();
    // Existing deployments need a worker invocation. This is the public CLI, not direct fulfillment.
    if (!automatic) {
    const worker = spawn('php', [join(directory,'app/scripts/task_queue.php'),'run','--limit=10'], {env});
    let output = '';
    worker.stdout.on('data', x => output += x);
    worker.stderr.on('data', x => output += x);
    const code = await new Promise(resolve => worker.on('exit', resolve));
    appendFileSync(join(directory,'worker.log'), output);
    assert.equal(code,0,output);
    await page.reload();
    }
    await page.getByText('Agent-authored reply',{exact:true}).first().waitFor();
    assert.equal(await page.locator('.post-card[data-agent-authored="reply-agent"]').count(),1);
    if (automatic && !process.argv.includes('--reload-pending') && await draft.count()) assert.equal(await draft.inputValue(),'Keep this unfinished draft.');
    const status = await context.request.get(base + '/api/scripture_reply_status?post_id=' + source);
    if (automatic) {
      assert.equal(status.status(),200);
      const before = await status.json();
      const duplicate = await context.request.post(base + '/api/generate_agent_reply', {form:{post_id:source,response_mode:'scripture_oriented_analysis'}});
      assert.equal((await duplicate.json()).agent_post_id,before.agent_post_id);
    }
    const result = {source,request_ms:queued-started,total_ms:performance.now()-started,
      discovery:automatic?'automatic worker and status polling':'explicit browser reload after public CLI worker',provider:'stub'};
    appendFileSync(join(directory,'measurements.jsonl'),JSON.stringify(result)+'\n');
    console.log(JSON.stringify(result));
    if (process.argv.includes('--expand')) {
      if (process.argv.includes('--busy')) {
        const holder = spawn('php', ['-r', '$h=fopen($argv[1],"c+");flock($h,LOCK_EX);echo "ready\\n";sleep(3);',
          join(directory,'private/forum-rewrite-task-queue.lock')]);
        await new Promise(resolve => holder.stdout.once('data', resolve));
      }
      const expansionStarted = performance.now();
      await page.locator('[data-action="expand-scripture"]').click();
      await page.getByText('Expansion ready.',{exact:true}).waitFor();
      assert.notEqual(await page.locator('[data-expansion-details]').getAttribute('open'),null);
      const initialId = await page.locator('.post-card[data-agent-authored="reply-agent"]').getAttribute('data-post-id');
      const original = await context.request.get(base + '/api/agent_expansion_status?post_id=' + initialId);
      const duplicate = await context.request.post(base + '/api/expand_agent_reply',{form:{post_id:initialId}});
      assert.equal((await original.json()).agent_post_id,(await duplicate.json()).agent_post_id);
      console.log(JSON.stringify({expansion_ms:performance.now()-expansionStarted,initialId}));
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
