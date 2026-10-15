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
    const context = await browser.newContext({serviceWorkers:'block'});
    await context.addCookies([{name:'identity_hint',value:'guest',url:base}]);
    const page = await context.newPage();
    const created = await context.request.post(base + '/api/create_thread', {form:{board_tags:'general',body:'i slept in today, and now i am behind schedule. how do i address this?'}});
    const text = await created.text();
    const source = text.match(/^post_id=(.+)$/m)?.[1];
    assert.ok(source, text);
    await page.goto(base + '/threads/' + source);
    const started = performance.now();
    await page.locator('[data-action="request-agent-reply"]').first().click();
    await page.locator('[data-response-mode="scripture_oriented_analysis"]').click();
    await page.getByText('Scripture-oriented analysis requested.',{exact:true}).waitFor();
    const queued = performance.now();
    // Existing deployments need a worker invocation. This is the public CLI, not direct fulfillment.
    const worker = spawn('php', [join(directory,'app/scripts/task_queue.php'),'run','--limit=10'], {env});
    let output = '';
    worker.stdout.on('data', x => output += x);
    worker.stderr.on('data', x => output += x);
    const code = await new Promise(resolve => worker.on('exit', resolve));
    appendFileSync(join(directory,'worker.log'), output);
    assert.equal(code,0,output);
    await page.reload();
    await page.getByText('Agent-authored reply',{exact:true}).first().waitFor();
    const result = {source,request_ms:queued-started,total_ms:performance.now()-started,
      discovery:'explicit browser reload after public CLI worker',provider:'stub'};
    appendFileSync(join(directory,'measurements.jsonl'),JSON.stringify(result)+'\n');
    console.log(JSON.stringify(result));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
