import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {chromium, webkit, firefox} from 'playwright-core';
const form = execFileSync('php', ['-r', `
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$partial = function($name, $vars = []) {extract($vars); ob_start(); include 'templates/'.$name; return ob_get_clean();};
$body=''; $threadId='test'; $parentId=''; $boardTags='general'; $phoneReplyEnabled=true;
include 'templates/partials/reply_form.php';
`], {encoding:'utf8'});
const asset = name => readFileSync('public/assets/'+name,'utf8');
const engine = process.env.REPLY_BROWSER || 'chromium';
const browser = engine === 'chromium'
 ? await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',args:['--no-sandbox']})
 : await ({webkit, firefox}[engine]).launch({headless:true});
try {
 for (const theme of ['light','word97','chicago','midnight']) {
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true});
  const page=await context.newPage();
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://reply.test/**', route=>route.fulfill({contentType:'text/html',body:`<html data-theme="${theme}"><head><style>${asset('site.css')}${asset('compose.css')}${asset('theme-'+theme+'.css')}</style></head><body><main class="card">${form}<button id="outside">Outside</button></main><script>
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
   assert.equal(await body.inputValue(),draft);
   assert.deepEqual(await body.evaluate(el=>[el.selectionStart,el.selectionEnd]),[20,40]);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  };
  await check();
  for(const height of [280,600,420]) {
   await page.evaluate(h=>{testViewport.height=h;testViewport.offsetTop=35;testViewport.dispatchEvent(new Event('resize'));},height);
   await page.waitForTimeout(60);await check();
  }
  await page.evaluate(()=>window.scrollTo(0,150));await page.waitForTimeout(60);await check();
  await page.locator('.compose-action-menu-toggle').click();
  const clear=page.locator('[data-action="clear-compose-fields"]');await clear.waitFor({state:'visible'});
  assert.ok((await clear.boundingBox()).y>=35,`${theme}: menu within viewport`);
  await page.locator('#outside').focus();
  await page.waitForFunction(()=>!document.querySelector('form').classList.contains('reply-viewport-active'));
  assert.equal(await actions.evaluate(el=>getComputedStyle(el).position),'static');
  await body.focus();
  await page.evaluate(()=>{testViewport.scale=2;testViewport.dispatchEvent(new Event('resize'));});
  await page.waitForTimeout(60);
  assert.equal(await page.locator('.reply-viewport-active').count(),0,'zoom falls back to normal flow');
  await page.evaluate(()=>{testViewport.scale=1;testViewport.dispatchEvent(new Event('resize'));});
  await page.waitForTimeout(60);
  // Rotation uses the same draft and nodes.
  await page.setViewportSize({width:844,height:390});
  await page.evaluate(()=>{Object.assign(testViewport,{width:844,height:240,offsetTop:0});testViewport.dispatchEvent(new Event('resize'));});
  await page.waitForTimeout(60);await check();
  // The original submit button remains the native submitter; retry uses the same form.
  await page.evaluate(()=>{window.submits=[];document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();submits.push(e.submitter.textContent);});});
  await page.locator('.compose-form-actions > button').click();
  assert.deepEqual(await page.evaluate(()=>submits),['Create reply']);
  assert.equal(await body.inputValue(),draft);
  if (!await page.locator('.compose-action-menu').evaluate(el=>el.open)) await page.locator('.compose-action-menu-toggle').click();
  await page.locator('[data-action="submit-anonymous-compose"]').click();
  assert.deepEqual(await page.evaluate(()=>submits),['Create reply','Post anonymous reply']);
  // Forte never opts in, even on a touch phone.
  await page.locator('form').evaluate(el=>el.removeAttribute('data-phone-reply'));
  await page.goto('http://reply.test/');
  await page.locator('form').evaluate(el=>el.removeAttribute('data-phone-reply'));
  // Rebind on a fresh non-opted-in document to match Forte's server output.
  await page.setContent(`<style>${asset('compose.css')}</style>${form.replace(' data-phone-reply','')}<script>${asset('reply_viewport.js')}</script>`);
  await body.focus();await page.waitForTimeout(60);
  assert.equal(await page.locator('.reply-viewport-active').count(),0);
  assert.deepEqual(errors,[]);
  console.log(`PASS ${theme}: viewport changes, scroll, rotation, draft/selection, menu, native submitters, zoom fallback, Forte`);
  await context.close();
 }
 const page=await browser.newPage({viewport:{width:1280,height:900}});
 await page.setContent(`<style>${asset('compose.css')}</style>${form}<script>${asset('reply_viewport.js')}</script>`);
 await page.locator('[name="body"]').focus();await page.waitForTimeout(60);
 assert.equal(await page.locator('.reply-viewport-active').count(),0);
 console.log('PASS desktop does not activate');
} finally {await browser.close();}
