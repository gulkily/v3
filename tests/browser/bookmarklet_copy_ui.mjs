// Start tests/Support/bookmarklet_browser.php with BOOKMARKLET_TEST_ROOT first.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
const base = process.env.BOOKMARKLET_BASE_URL || 'http://127.0.0.1:18765';
const browser = await chromium.launch({headless:true, executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args:['--no-sandbox']});
// The copied text is unencoded one-line JavaScript; the link href stays percent-encoded.
const oneLine=href=>'javascript:'+decodeURIComponent(href.slice('javascript:'.length)).replace(/\s*[\r\n]+\s*/g,' ');
try {
  const context = await browser.newContext({permissions:['clipboard-read','clipboard-write'], serviceWorkers:'block'});
  if (process.env.BOOKMARKLET_EXPECT_STATIC === '1') {
    // Exercise the anonymous static path even after background runtime requests set cookies.
    await context.route('**/tools/**', async route => {
      const response = await fetch(route.request().url());
      return route.fulfill({status:response.status, contentType:'text/html', body:await response.text()});
    });
  }
  const page = await context.newPage();
  await page.goto(base + '/tools/');
  await page.locator('a[href="/tools/bookmarklets/"]').first().click();
  await page.waitForURL('**/tools/bookmarklets/');
  await page.locator('[data-bookmarklet-copy]:enabled').first().waitFor();
  if (process.env.BOOKMARKLET_EXPECT_STATIC === '1') {
    assert((await page.content()).includes('route-source: static-html'));
  }
  assert.equal(await page.locator('[data-bookmarklet-copy]').count(), 7);
  for (let i=0; i<7; i++) {
    const button=page.locator('[data-bookmarklet-copy]').nth(i);
    await button.focus();
    await page.keyboard.press(i%2 ? 'Space' : 'Enter');
    await page.waitForFunction(i=>document.querySelectorAll('[data-bookmarklet-feedback]')[i].textContent==='Copied', i);
    assert.equal(await page.evaluate(()=>navigator.clipboard.readText()), oneLine(await page.locator('[data-bookmarklet]').nth(i).getAttribute('href')));
    assert.equal(await button.evaluate(b=>b===document.activeElement), true);
  }
  // Probe fallback success/failure, cleanup, and retry independently of OS permissions.
  for (const mode of ['denied','unavailable']) {
    await page.reload();
    await page.evaluate(mode=>{
      Object.defineProperty(navigator,'clipboard',{configurable:true,value:mode==='unavailable'?undefined:{writeText:()=>Promise.reject(Error('denied'))}});
      window.copies=[];
      document.execCommand=()=>{window.copies.push(document.activeElement.value);return true;};
    },mode);
    await page.locator('[data-bookmarklet-copy]').first().click();
    await page.waitForFunction(()=>document.querySelector('[data-bookmarklet-feedback]').textContent==='Copied');
    assert.equal(await page.evaluate(()=>window.copies[0]),oneLine(await page.locator('[data-bookmarklet]').first().getAttribute('href')));
    assert.equal(await page.locator('textarea').count(),0);
    await page.evaluate(()=>document.execCommand=()=>false);
    await page.locator('[data-bookmarklet-copy]').first().click();
    await page.waitForFunction(()=>document.querySelector('[data-bookmarklet-feedback]').textContent==='Copy failed. Try again.');
    assert.equal(await page.locator('textarea').count(),0);
    await page.evaluate(()=>document.execCommand=()=>true);
    await page.locator('[data-bookmarklet-copy]').first().click();
    await page.waitForFunction(()=>document.querySelector('[data-bookmarklet-feedback]').textContent==='Copied');
    assert.equal(await page.locator('[data-bookmarklet-copy]').first().evaluate(b=>b===document.activeElement),true);
  }
  const themes=await page.locator('[data-theme-option]').evaluateAll(nodes=>nodes.map(n=>n.dataset.themeOption));
  for(const width of [1280,375]) {
    await page.setViewportSize({width,height:900});
    for(const theme of themes) {
      await page.evaluate(theme=>localStorage.setItem(window.forumBrowserRuntime.themeStorageKey,theme),theme);
      await page.reload();
      await page.waitForFunction(theme=>document.documentElement.getAttribute('data-theme')===(theme==='auto'?null:theme),theme);
      const buttons=page.locator('[data-bookmarklet-copy]');
      for(let i=0;i<7;i++) {
        const button=buttons.nth(i); await button.scrollIntoViewIfNeeded();
        const box=await button.boundingBox();
        assert(box && box.width>=24 && box.width<=48 && box.height>=24 && box.x>=0 && box.x+box.width<=width, `${theme}/${width}: clipped button`);
        assert(await button.getAttribute('aria-label'));
        await button.focus(); await page.keyboard.press('ArrowLeft');
        assert.equal(await button.evaluate(b=>b===document.activeElement && getComputedStyle(b).outlineStyle!=='none'),true,`${theme}/${width}/${i}: focus`);
      }
      assert.equal(await page.locator('textarea').count(),0);
    }
  }
  await page.screenshot({path:'/tmp/bookmarklet-copy-mobile.png',fullPage:true});
  console.log('PASS real clipboard, seven keyboard controls, recovery/retry/cleanup, available themes at two widths');
} finally { await browser.close(); }
