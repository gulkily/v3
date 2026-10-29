import assert from 'node:assert/strict';
async function toggle(page,enabled) {
 const row=page.locator('[data-flag-key="FORUM_RAILSPIKE_ENABLED"]');
 const response=page.waitForResponse(r=>r.url().endsWith('/api/finalize_feature_flag_change'));
 await row.getByRole('switch').click();const result=await response.catch(async error=>{throw new Error(error.message+'\n'+await row.innerText());});
 assert.equal(result.status(),200,await result.text());
 await page.waitForFunction(value=>document.querySelector('[data-flag-key="FORUM_RAILSPIKE_ENABLED"] [role="switch"]').getAttribute('aria-checked')===String(value),enabled);
}
export async function enableRailspike(page,base) {
 await page.goto(base+'/tools/feature-flags/');
 await page.getByRole('searchbox',{name:'Search flags'}).fill('FORUM_RAILSPIKE_ENABLED');
 assert.equal(await page.locator('[data-flag-key="FORUM_RAILSPIKE_ENABLED"] [role="switch"]').getAttribute('aria-checked'),'false');
 await toggle(page,true);
}
export async function checkRailspikeGating({page,context,browser,base,data,fixture}) {
 const flagPage=await context.newPage();await flagPage.goto(base+'/tools/feature-flags/');
 await flagPage.getByRole('searchbox',{name:'Search flags'}).fill('FORUM_RAILSPIKE_ENABLED');
 let workflowRequests=0;const count=r=>{if(r.url().includes('/api/railspike_workflow'))workflowRequests++;};page.on('request',count);
 await page.goto(base+'/threads/'+data.ordinary);
 await page.waitForTimeout(11000);assert.equal(workflowRequests,0,'ordinary thread makes no workflow requests');
 await page.goto(base+'/posts/'+data.direct);await page.waitForTimeout(11000);
 assert.ok(workflowRequests>=3,'eligible direct-post view polls');
 const shell=page.locator('[data-railspike-shell]');await shell.evaluate(el=>el.open=true);
 await toggle(flagPage,false);const beforeDisable=workflowRequests;
 await page.waitForTimeout(11000);assert.ok(workflowRequests-beforeDisable<=1,'stale page stops at first disabled response');
 assert.equal(fixture({operation:'worker'}).claimed,0,'disabled queued assessment does not start');
 const fresh=await context.newPage();let freshRequests=0;fresh.on('request',r=>{if(r.url().includes('/api/railspike_workflow'))freshRequests++;});
 await fresh.goto(base+'/posts/'+data.direct);await fresh.waitForTimeout(11000);
 assert.equal(freshRequests,0);assert.equal(await fresh.locator('[data-railspike-shell]').count(),0);
 const denied=await context.request.get(base+'/api/railspike_workflow?thread_id='+data.direct);
 assert.equal((await denied.json()).code,'railspike_disabled');assert.match(denied.headers()['cache-control'],/no-store/);
 await toggle(flagPage,true);await page.reload();await page.locator('[data-railspike-shell]').evaluate(el=>el.open=true);
 await page.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
 await fresh.close();await flagPage.close();page.off('request',count);
 // Delay initial status and compare the collapsed entry before/after loading.
 await page.route('**/api/railspike_workflow?*',async route=>{await new Promise(r=>setTimeout(r,1200));await route.continue();});
 await page.reload({waitUntil:'domcontentloaded'});
 const before=await page.locator('[data-railspike-shell] > summary').boundingBox();
 await page.waitForTimeout(1800);
 const after=await page.locator('[data-railspike-shell] > summary').boundingBox();assert.deepEqual(after,before,'private status does not shift collapsed entry');
 await page.unroute('**/api/railspike_workflow?*');
 const names=await page.evaluate(()=>Array.from(document.querySelectorAll('[data-theme-option]')).map(o=>o.dataset.themeOption).filter(v=>v&&v!=='auto'));
 assert.ok(names.includes('word97')&&names.includes('chicago')&&names.includes('midnight'));
 for(const width of [390,1280]) for(const theme of names) {
  await page.setViewportSize({width,height:900});
  await page.evaluate(theme=>localStorage.setItem(window.forumBrowserRuntime.themeStorageKey,theme),theme);
  await page.reload();
  const control=page.locator('[data-railspike-shell]');assert.equal(await control.isVisible(),true);
  await control.evaluate(el=>el.open=true);
  await page.getByText('Railspike Step 1: queued',{exact:true}).waitFor();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,theme+' overflow '+width);
  if(['word97','chicago','midnight'].includes(theme)&&width===390)await page.screenshot({path:'/tmp/railspike-'+theme+'-phone.png'});
 }
 const guest=await browser.newContext();const guestPage=await guest.newPage();let guestRequests=0;
 guestPage.on('request',r=>{if(r.url().includes('/api/railspike_workflow'))guestRequests++;});
 await guestPage.goto(base+'/threads/'+data.direct);await guestPage.waitForTimeout(11000);
 assert.equal(guestRequests,0);assert.equal(await guestPage.locator('[data-railspike-shell]').isVisible(),false);await guest.close();
 console.log(JSON.stringify({railspikeGating:'passed',themes:names,viewports:[390,1280],checks:['signed flag toggle','ordinary thread silence','eligible polling','stale disabled stop','queued-work suspension','fresh disabled silence','re-enable recovery','first-paint stability','guest silence']}));
}
