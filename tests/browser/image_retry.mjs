// A failed image is asked for once more, and only once. Run: node tests/browser/image_retry.mjs
import assert from 'node:assert/strict';
import {chromium, webkit} from 'playwright-core';
const browser = await (process.env.IMAGE_BROWSER==='webkit' ? webkit.launch({headless:true}) : chromium.launch({executablePath:process.env.BROWSER_PATH || '/usr/bin/google-chrome',headless:true,args:['--no-sandbox']}));
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', 'base64');
try {
  const page = await browser.newPage({viewport:{width:390,height:844}});
  const asked = {};
  // "/once/…" fails the first time and then works; "/never/…" always fails; "/fine/…" always works.
  await page.route('http://retry.test/**', route => {
    const path = new URL(route.request().url()).pathname;
    if (path === '/') return route.fulfill({contentType:'text/html', body:`<!doctype html><body>
      <img id="plain" src="/once/plain.png" width="10" height="10">
      <img id="set" src="/once/set-1x.png" srcset="/once/set-1x.png 1x, /once/set-2x.png 2x" width="10" height="10">
      <img id="never" src="/never/x.png" width="10" height="10">
      <img id="fine" src="/fine/x.png" width="10" height="10">
      <script src="/image_retry.js"></script></body>`});
    if (path === '/image_retry.js') return route.fulfill({contentType:'text/javascript', path:'public/assets/image_retry.js'});
    asked[path] = (asked[path] || 0) + 1;
    if (path.startsWith('/never/') || (path.startsWith('/once/') && asked[path] === 1)) return route.abort('failed');
    return route.fulfill({contentType:'image/png', body:PNG});
  });
  await page.goto('http://retry.test/');
  await page.waitForTimeout(600);
  // Before the wait is over nothing has been asked for twice.
  assert.deepEqual(Object.values(asked).filter(n => n > 1), []);
  await page.waitForTimeout(3500);
  const state = await page.evaluate(() => Object.fromEntries([...document.images].map(i => [i.id, {loaded: i.complete && i.naturalWidth > 0, retried: i.dataset.imageRetried || null}])));
  assert.deepEqual(state.plain, {loaded:true, retried:'1'});
  assert.deepEqual(state.set, {loaded:true, retried:'1'});
  assert.deepEqual(state.never, {loaded:false, retried:'1'});
  assert.deepEqual(state.fine, {loaded:true, retried:null});
  assert.equal(asked['/once/plain.png'], 2);
  assert.equal(asked['/never/x.png'], 2, 'a second failure is not tried again');
  assert.equal(asked['/fine/x.png'], 1);
  // The srcset image asked once for its first choice and once more after it failed.
  assert.equal((asked['/once/set-1x.png'] || 0) + (asked['/once/set-2x.png'] || 0), 2);
  await page.waitForTimeout(2500);
  assert.equal(asked['/never/x.png'], 2);
  console.log('image_retry ok', JSON.stringify(asked));
} finally { await browser.close(); }
