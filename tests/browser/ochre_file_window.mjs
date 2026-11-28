import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const assets = 'public/assets/';
const [site, ochre, gloss, script] = await Promise.all(
  ['site.css', 'theme-ochre.css', 'theme-gloss.css', 'file_windows.js'].map(file => readFile(assets + file, 'utf8')),
);
const files = [
  ['Source', 'post.txt'], ['Commit', 'commit.txt'], ['Signature', 'post.txt.asc'], ['Public key', 'key.asc'],
];
const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',
  headless: true, args: ['--no-sandbox'],
});
try {
  for (const width of [390, 1280]) {
    const mobile = width === 390;
    const page = await browser.newPage({ viewport: { width, height: 800 }, isMobile: mobile, hasTouch: mobile });
    page.setDefaultTimeout(5000);
    await page.route('http://fixture.test/**', route => {
      const { pathname } = new URL(route.request().url());
      if (pathname.startsWith('/source/')) {
        const filename = pathname.split('/').at(-1);
        if (filename === 'unavailable.txt' && !route.request().isNavigationRequest()) {
          return route.fulfill({ status: 503, body: 'Temporary failure' });
        }
        return route.fulfill({ contentType: 'text/plain', body: `Record: ${filename}` });
      }
      return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html data-theme="ochre">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>${site}\n${ochre}\n${gloss}</style><body>
        <section class="card" id="ordinary"><table class="codebase-facts post-facts" data-file-windows>
          ${[...files, ['Unavailable', 'unavailable.txt']].map(([label, name]) =>
            `<tr><th>${label}</th><td><a aria-label="${label}" href="/source/${name}">${name}</a></td></tr>`).join('')}
        </table></section><p>Post text behind the window</p>
        <dialog open class="card pending-user-dialog" id="pending">Pending user summary</dialog>
        <script>${script}</script>` });
    });
    await page.goto('http://fixture.test/posts/example');
    const background = locator => locator.evaluate(el => getComputedStyle(el).backgroundColor);
    const transparent = 'rgba(0, 0, 0, 0)';
    const panel = 'rgb(246, 246, 239)';
    assert.equal(await background(page.locator('#ordinary')), transparent);
    assert.equal(await background(page.locator('#pending')), panel);
    await page.locator('#pending').evaluate(el => el.close());

    const window = page.locator('dialog.file-window[open]');
    for (const [label, filename] of files) {
      const link = page.getByRole('link', { name: label, exact: true });
      if (mobile) await link.tap();
      else await link.press('Enter');
      await window.getByText(`Record: ${filename}`, { exact: true }).waitFor();
      assert.equal(await background(window), panel, `Ochre ${label} window is opaque at ${width}px`);
      assert.equal(await window.getByRole('link', { name: 'Raw' }).getAttribute('href'), `http://fixture.test/source/${filename}`);
      assert.equal(new URL(page.url()).pathname, '/posts/example');
      const close = window.getByRole('button', { name: 'Close' });
      if (mobile && label === 'Source') await close.tap();
      else await close.press('Escape');
      await window.waitFor({ state: 'detached' });
      assert.ok(await link.evaluate(el => el === document.activeElement));
    }

    await page.evaluate(() => { document.documentElement.dataset.theme = 'gloss'; });
    await page.getByRole('link', { name: 'Source', exact: true }).click();
    await window.getByText('Record: post.txt', { exact: true }).waitFor();
    const glossSurface = await background(window);
    assert.notEqual(glossSurface, panel, 'Gloss does not pick up the Ochre color');
    assert.notEqual(glossSurface, transparent, 'Gloss keeps its own opaque surface');
    await window.getByRole('button', { name: 'Close' }).click();
    const unavailable = page.getByRole('link', { name: 'Unavailable' });
    await Promise.all([page.waitForURL('**/source/unavailable.txt'), unavailable.click()]);
    assert.match(await page.locator('body').innerText(), /Record: unavailable\.txt/);
    await page.close();
  }
  console.log('PASS Ochre file-window surface, other cards/theme, link/close and raw fallback at 390px and 1280px');
} finally {
  await browser.close();
}
