import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {chromium, webkit, firefox} from 'playwright-core';
const form = execFileSync('php', ['-r', `
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$partial = function($name, $vars = []) {extract($vars); ob_start(); include 'templates/'.$name; return ob_get_clean();};
$body=''; $threadId='test'; $parentId=''; $boardTags='general'; $phoneReplyEnabled=true; $showBodyLabel=false;
include 'templates/partials/reply_form.php';
`], {encoding:'utf8'});
const asset = name => readFileSync('public/assets/'+name,'utf8');
const engine = process.env.REPLY_BROWSER || 'chromium';
const browser = engine === 'chromium'
 ? await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',args:['--no-sandbox']})
 : await ({webkit, firefox}[engine]).launch({headless:true});
const settle = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
try {
 for (const theme of ['light','word97','chicago','midnight']) {
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true});
  const page=await context.newPage();
  page.setDefaultTimeout(10000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://reply.test/**', route=>route.fulfill({contentType:'text/html',body:`<html data-theme="${theme}"><head><style>${asset('site.css')}${asset('compose.css')}${asset('theme-'+theme+'.css')}</style></head><body><main class="card"><div style="height:230px"></div><div data-reply-panel>${form}<p id="recovery" data-kind="error" hidden>Connection failed. Retry your reply.</p></div><button id="outside">Outside</button></main><script>
  window.testViewport=new EventTarget();Object.assign(testViewport,{height:420,width:390,offsetTop:0,offsetLeft:0,scale:1});
  Object.defineProperty(window,'visualViewport',{value:testViewport});
  ${asset('reply_viewport.js')}</script></body></html>`}));
  await page.goto('http://reply.test/');
  const body=page.locator('[name="body"]'); const actions=page.locator('.compose-form-actions');
  const draft='A long draft\n'.repeat(80);
  await body.fill(draft);
  await body.evaluate(el=>el.setSelectionRange(20,40));
  await page.waitForFunction(()=>document.querySelector('form').classList.contains('reply-viewport-active'));
  const check=async()=>{
   const rect=await actions.boundingBox();
   const vv=await page.evaluate(()=>({height:testViewport.height,top:testViewport.offsetTop,width:testViewport.width}));
   assert.ok(rect.y>=vv.top && rect.y+rect.height<=vv.top+vv.height,`${theme}: actions within visible viewport ${JSON.stringify({rect,vv})}`);
   assert.ok(rect.x>=0 && rect.x+rect.width<=vv.width);
   const editor=await body.boundingBox();
   assert.ok(editor.y>=vv.top && editor.y+editor.height<=rect.y,`${theme}: editor and actions must not overlap`);
   assert.ok(editor.height>=80,`${theme}: usable editor height`);
   for(const feedback of await page.locator('[data-reply-panel] [data-kind="error"]:visible').all()) {
    const error=await feedback.boundingBox();
    assert.ok(error.y>=vv.top && error.y+error.height<=vv.top+vv.height);
    assert.ok(error.y>=editor.y+editor.height || error.y+error.height<=editor.y,'error must not cover editor');
    assert.ok(error.y>=rect.y+rect.height || error.y+error.height<=rect.y,'error must not cover actions');
   }

   assert.equal(await body.inputValue(),draft);
   assert.deepEqual(await body.evaluate(el=>[el.selectionStart,el.selectionEnd]),[20,40]);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  };
  await check();
  // The "Post as" row stays in the docked panel, between the editor and the actions.
  const identityRow=page.locator('[data-compose-identity]');
  assert.equal(await identityRow.isVisible(),true,`${theme}: name row docked`);
  assert.ok((await identityRow.boundingBox()).y>=(await body.boundingBox()).y+(await body.boundingBox()).height,`${theme}: name row below the editor`);
  await page.locator('#recovery').evaluate(el=>{el.hidden=false;});await settle(page);await check();
  await page.locator('#recovery').evaluate(el=>{el.hidden=true;});await settle(page);
  // Oversized recovery content and tiny viewports must return to normal flow.
  await page.locator('#recovery').evaluate(el=>{el.hidden=false;el.style.height='600px';});await settle(page);
  assert.equal(await page.locator('.reply-viewport-active').count(),0,'large recovery feedback falls back without clipping');
  assert.equal(await page.locator('#recovery').isVisible(),true);
  assert.equal(await actions.evaluate(el=>getComputedStyle(el).position),'static');
  await page.locator('#recovery').evaluate(el=>{el.hidden=true;el.style.height='';});await settle(page);await check();
  await page.evaluate(()=>{testViewport.height=170;testViewport.dispatchEvent(new Event('resize'));});await settle(page);
  assert.equal(await page.locator('.reply-viewport-active').count(),0,'tiny viewport uses ordinary flow');
  await page.evaluate(()=>{testViewport.height=420;testViewport.dispatchEvent(new Event('resize'));});await settle(page);await check();
  // Repositioning the document must not make the action row cover the editor.
  await page.evaluate(()=>{document.querySelector('main > div').style.height='500px';window.dispatchEvent(new Event('scroll'));});await settle(page);await check();
  if (process.env.REPLY_SCREENSHOTS) await page.screenshot({path:process.env.REPLY_SCREENSHOTS+'/'+theme+'-editing.png'});
  for(const height of [280,600,420]) {
   await page.evaluate(h=>{testViewport.height=h;testViewport.offsetTop=35;testViewport.dispatchEvent(new Event('resize'));},height);
   await settle(page);await check();
  }
  await page.evaluate(()=>window.scrollTo(0,150));await settle(page);await check();
  await page.locator('.compose-action-menu-toggle').click();
  const clear=page.locator('[data-action="clear-compose-fields"]');await clear.waitFor({state:'visible'});
  if (process.env.REPLY_SCREENSHOTS) await page.screenshot({path:process.env.REPLY_SCREENSHOTS+'/'+theme+'-menu.png'});
  assert.ok((await clear.boundingBox()).width>=150, `${theme}: readable menu width`);
  assert.ok((await clear.boundingBox()).y>=35,`${theme}: menu within viewport`);
  const list=await page.locator('.compose-action-menu-list').boundingBox();
  const writing=await body.boundingBox();
  assert.ok(list.y>=writing.y+writing.height,`${theme}: menu has reserved space clear of editor`);
  // Safari may leave focus on the document after tapping a menu control.
  await page.evaluate(()=>{document.activeElement.blur();testViewport.dispatchEvent(new Event('resize'));});
  await settle(page);
  assert.equal(await page.locator('.reply-viewport-active').count(),1,'open menu remains docked without focused summary');
  await page.locator('#outside').focus();
  await page.waitForFunction(()=>!document.querySelector('form').classList.contains('reply-viewport-active'));
  assert.equal(await actions.evaluate(el=>getComputedStyle(el).position),'static');
  await body.focus();
  await page.evaluate(()=>{testViewport.scale=2;testViewport.dispatchEvent(new Event('resize'));});
  await settle(page);
  assert.equal(await page.locator('.reply-viewport-active').count(),0,'zoom falls back to normal flow');
  await page.evaluate(()=>{testViewport.scale=1;testViewport.dispatchEvent(new Event('resize'));});
  await settle(page);
  // Rotation uses the same draft and nodes.
  await page.setViewportSize({width:844,height:390});
  await page.evaluate(()=>{Object.assign(testViewport,{width:844,height:240,offsetTop:0});testViewport.dispatchEvent(new Event('resize'));});
  await settle(page);await check();
  assert.equal(await identityRow.isVisible(),false,`${theme}: a landscape keyboard leaves room for the editor and actions only`);
  // The original submit button remains the native submitter; retry uses the same form.
  await page.evaluate(()=>{window.submits=[];document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();submits.push(e.submitter.textContent);});});
  await page.locator('.compose-form-actions > button').click();
  assert.deepEqual(await page.evaluate(()=>submits),['Create reply']);
  assert.equal(await body.inputValue(),draft);
  if (!await page.locator('.compose-action-menu').evaluate(el=>el.open)) await page.locator('.compose-action-menu-toggle').click();
  await page.locator('[data-action="submit-anonymous-compose"]').click();
  assert.deepEqual(await page.evaluate(()=>submits),['Create reply','Post anonymous reply']);
  // Forte never opts in, even on a touch phone.
  // Rebind on a fresh non-opted-in document to match Forte's server output.
  await page.setContent(`<style>${asset('compose.css')}</style><div data-reply-panel>${form.replace(' data-phone-reply','')}</div><script>${asset('reply_viewport.js')}</script>`);
  await body.focus();await settle(page);
  assert.equal(await page.locator('.reply-viewport-active').count(),0);
  assert.deepEqual(errors,[]);
  console.log(`PASS ${theme}: viewport changes, scroll, rotation, draft/selection, menu, native submitters, zoom fallback, Forte`);
  await context.close();
 }
 const page=await browser.newPage({viewport:{width:1280,height:900}});
 await page.setContent(`<style>${asset('compose.css')}</style><div data-reply-panel>${form}</div><script>${asset('reply_viewport.js')}</script>`);
 await page.locator('[name="body"]').focus();await settle(page);
 assert.equal(await page.locator('.reply-viewport-active').count(),0);
 console.log('PASS desktop does not activate');
} finally {await browser.close();}
