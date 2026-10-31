import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const source = await readFile('public/assets/version_check.js', 'utf8');
const siteCss = await readFile('public/assets/site.css', 'utf8');
const themeCss = await readFile('public/assets/theme-light.css', 'utf8');
const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',
  headless: true, args: ['--no-sandbox'],
});
const cases = [
  {name: 'reply', card: 'post-card', attribute: 'data-post-id', anchor: 'post-new'},
  {name: 'board cards', card: 'thread-card', attribute: 'data-thread-id', anchor: 'thread-new'},
  {name: 'board rows', card: 'thread-row', attribute: 'data-thread-id', anchor: 'thread-new'},
  {name: 'paned rows', card: 'paned-list-row', attribute: 'data-paned-thread-id', anchor: 'thread-new'},
  {name: 'unchanged', card: 'post-card', attribute: 'data-post-id', anchor: ''},
  {name: 'changed existing body', card: 'post-card', attribute: 'data-post-id', anchor: ''},
  {name: 'hidden new post', card: 'post-card', attribute: 'data-post-id', anchor: ''},
];
try {
  for (const width of [390, 1280]) for (const scenario of cases) {
    const context = await browser.newContext({viewport: {width, height: 800}});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let updated = false;
    await context.route('http://reload.test/**', route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/site.css') return route.fulfill({body: siteCss, contentType: 'text/css'});
      if (url.pathname === '/theme.css') return route.fulfill({body: themeCss, contentType: 'text/css'});
      if (url.pathname === '/version_check.js') return route.fulfill({body: source, contentType: 'text/javascript'});
      if (url.pathname === '/api/version') return route.fulfill({body: 'next'});
      const extra = updated && !['unchanged', 'changed existing body'].includes(scenario.name)
        ? `<article class="${scenario.card}" ${scenario.attribute}="new" ${scenario.name === 'hidden new post' ? 'hidden' : ''}>New</article>` : '';
      return route.fulfill({contentType: 'text/html', body: `<!doctype html>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="app-version" content="${updated ? 'next' : 'old'}">
        <link rel="stylesheet" href="/theme.css"><link rel="stylesheet" href="/site.css">
        <div class="app-version-banner" data-role="app-version-banner" hidden><div class="app-version-banner__inner"><span class="app-version-banner__text">A new version is available.</span><button class="app-version-banner__reload" data-action="reload-for-new-version">Reload</button></div></div>
        <div class="shell" data-shell="app"><header class="site-header site-header--app">
          <div class="site-header-actions"><nav class="nav"><a href="/">Board</a><a href="/users/">Users</a></nav>
          <button>New post</button><details class="account-menu"><summary class="account-menu__trigger">You</summary></details></div>
        </header><main>
        <article class="${scenario.card}" ${scenario.attribute}="old" style="height:1800px">${updated && scenario.name === 'changed existing body' ? 'Changed body' : 'Old'}</article>
        ${extra}<footer style="height:1200px"></footer></main></div><script src="/version_check.js" defer></script>`});
    });
    await page.goto('http://reload.test/threads/root');
    updated = true;
    const mainBefore = await page.locator('main').boundingBox();
    // Exercise the real version poll, without waiting for its 15-second timer.
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await page.locator('[data-role="app-version-banner"]:visible').waitFor();
    assert.equal((await page.locator('main').boundingBox()).y, mainBefore.y, 'Notice shifted the page');
    // Use a real click: phone navigation must not intercept Reload.
    await page.locator('[data-action="reload-for-new-version"]').click();
    await page.waitForURL(url => scenario.anchor ? url.hash === '#' + scenario.anchor : url.searchParams.get('__v') === 'next' || !url.search);
    await page.waitForFunction(() => document.querySelector('meta[name="app-version"]').content === 'next');
    await page.waitForFunction(() => !sessionStorage.getItem('forum_pending_app_version_content'));
    const result = await page.evaluate(() => ({hash: location.hash, y: scrollY,
      targetY: location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))).getBoundingClientRect().top : null}));
    assert.equal(result.hash, scenario.anchor ? '#' + scenario.anchor : '', `${scenario.name}/${width}`);
    if (scenario.anchor) {
      assert(result.y > 1000, `${scenario.name}/${width} did not scroll`);
      assert(result.targetY >= -1 && result.targetY <= 32, `${scenario.name}/${width} missed target`);
      await page.reload();
      await page.waitForFunction(() => scrollY > 1000);
      assert.equal(new URL(page.url()).hash, '#' + scenario.anchor);
    }
    assert.deepEqual(errors, [], `${scenario.name}/${width} errors`);
    console.log(`PASS ${scenario.name}/${width}: ${JSON.stringify(result)}`);
    await context.close();
  }
} finally {
  await browser.close();
}
