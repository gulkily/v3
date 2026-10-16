// One paid-trial browser flow; provider calls are guarded by the local budget proxy.
const {chromium} = require('playwright-core');
const {spawn} = require('node:child_process');
const {readFileSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const assert = require('node:assert/strict');
const [directory, base, trialPath] = process.argv.slice(2);
const trial = JSON.parse(readFileSync(trialPath));
const env = {...process.env, ...JSON.parse(readFileSync(join(directory,'environment.json')))};
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH || '/opt/google/chrome/chrome',headless:true,args:['--no-sandbox']});
  let result = {sample:trial.sample,case:trial.case,kind:trial.kind};
  try {
    const context = await browser.newContext({serviceWorkers:'block'});
    context.setDefaultTimeout(130000);
    await context.addCookies([{name:'identity_hint',value:'guest',url:base}]);
    const page = await context.newPage();
    let source = trial.source;
    if (trial.kind === 'initial') {
      await page.goto(base + '/');
      const prepared = await page.evaluate(question => window.__forumComposeNormalization.normalizeComposeAscii(question), trial.question);
      assert.equal(prepared.unsupportedCount, 0);
      result.submitted_question = prepared.text;
      const created = await context.request.post(base+'/api/create_thread',{form:{board_tags:'general',body:prepared.text}});
      const response = await created.text();
      source = response.match(/^post_id=(.+)$/m)?.[1];
      assert.ok(source,response);
    }
    result.source = source;
    await page.goto(base + '/threads/' + (trial.thread || source));
    const started = performance.now();
    result.submission_at_ms = Date.now();
    if (trial.kind === 'initial') {
      await page.locator('[data-action="request-agent-reply"]').first().click();
      await page.locator('[data-response-mode="scripture_oriented_analysis"]').click();
      await page.getByText('Scripture-oriented analysis requested.',{exact:true}).waitFor();
    } else {
      await Promise.all([
        page.waitForResponse(response => response.url().includes('/api/expand_agent_reply') && response.request().method() === 'POST'),
        page.locator('[data-action="expand-scripture"]').click(),
      ]);
    }
    result.request_ms = performance.now()-started;
    if (!trial.automatic) {
      const worker = spawn('php',[join(directory,'app/scripts/task_queue.php'),'run','--limit=10'],{env});
      let log='';worker.stdout.on('data',x=>log+=x);worker.stderr.on('data',x=>log+=x);
      const code=await new Promise(resolve=>worker.on('exit',resolve));
      writeFileSync(trialPath+'.worker.log',log);
      assert.equal(code,0,log);
      await page.reload();
    }
    const endpoint = trial.kind === 'initial' ? '/api/scripture_reply_status' : '/api/agent_expansion_status';
    const selector = trial.kind === 'initial' ? '.post-card[data-agent-authored="reply-agent"]' : '[data-expansion-details][open]';
    while (performance.now()-started < 130000) {
      const visible = page.locator(selector).first();
      if (await visible.count() && await visible.isVisible()) {
        result.total_ms = performance.now()-started;
        result.visible_at_ms = Date.now();
        result.rendered_text = await visible.innerText();
        result.visible_body_text = await visible.locator('.body').first().innerText();
        const response = await context.request.get(base+endpoint+'?post_id='+source);
        result.status = await response.json();
        result.outcome='complete';
        break;
      }
      const response = await context.request.get(base+endpoint+'?post_id='+source);
      result.status = await response.json();
      if (['failed','skipped'].includes(result.status.status) || ['failed','skipped'].includes(result.status.generation_status)) {
        result.outcome='failed';result.total_ms=performance.now()-started;break;
      }
      await new Promise(resolve=>setTimeout(resolve,500));
    }
    if (!result.outcome) {result.outcome='timeout';result.total_ms=performance.now()-started;}
    await page.screenshot({path:trialPath+'.png',fullPage:true});
  } catch(error) {result.outcome='harness_error';result.error=String(error);}
  finally {writeFileSync(trialPath+'.result.json',JSON.stringify(result,null,2));await browser.close();}
  console.log(JSON.stringify({sample:result.sample,outcome:result.outcome,total_ms:result.total_ms}));
})().catch(error=>{console.error(error);process.exitCode=1;});
