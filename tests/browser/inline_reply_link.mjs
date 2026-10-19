import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { chromium } from 'playwright-core';

const html = execFileSync('php', ['-r', `
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$post = ['thread_id' => 'test', 'post_id' => 'test'];
$template = file_get_contents('templates/partials/thread_root_card.php');
preg_match('/<a [^\\n]+data-inline-reply-link>Reply<\\/a>/', $template, $matches);
eval('?>' . $matches[0]);
$partial = function($name, $vars = []) {
  extract($vars); ob_start(); include 'templates/'.$name; return ob_get_clean();
};
echo '<div style="height:1800px"></div><article data-compose-root><details data-inline-reply-details><summary class="inline-reply-summary">Write a reply</summary>';
$body = ''; $threadId = 'test'; $parentId = 'test'; $boardTags = 'general';
include 'templates/partials/reply_form.php';
echo '</details></article>';
`], { encoding: 'utf8' });
const script = readFileSync('public/assets/inline_reply_form.js', 'utf8');
const server = createServer((request, response) => {
  response.setHeader('Content-Type', 'text/html');
  response.end(request.url === '/thread' ? `${html}<script>${script}</script>` : '<p>Dedicated reply page</p>');
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',
  args: ['--no-sandbox']
});
try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const url = origin + '/thread';
  const destination = origin + '/compose/reply?thread_id=test&parent_id=test';
  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto(url);
    await page.locator('[data-inline-reply-link]').click();
    await page.waitForFunction(() => document.activeElement?.matches('textarea[name="body"]'));
    await page.waitForFunction(() => {
      const rect = document.querySelector('textarea[name="body"]').getBoundingClientRect();
      return rect.top >= 0 && rect.bottom <= innerHeight;
    });
    assert.equal(page.url(), url);
    assert.equal(await page.locator('[data-inline-reply-details]').getAttribute('open'), '');
    await page.locator('textarea[name="body"]').fill('Keep my draft');
    await page.locator('[data-inline-reply-link]').click();
    await page.waitForFunction(() => document.activeElement?.matches('textarea[name="body"]'));
    assert.equal(await page.locator('textarea[name="body"]').inputValue(), 'Keep my draft');

    // Observe cancellation without allowing synthetic navigation to leave the fixture.
    for (const options of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
      const cancelled = await page.evaluate(options => {
        const link = document.querySelector('[data-inline-reply-link]');
        let cancelled;
        const observe = event => { cancelled = event.defaultPrevented; event.preventDefault(); };
        document.addEventListener('click', observe, { once: true });
        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ...options }));
        return cancelled;
      }, options);
      assert.equal(cancelled, false, JSON.stringify(options));
    }
    // A real middle-click opens the preserved URL in a new tab.
    const popupPromise = page.context().waitForEvent('page');
    await page.locator('[data-inline-reply-link]').click({ button: 'middle' });
    const popup = await popupPromise;
    await popup.waitForLoadState();
    assert.equal(popup.url(), destination);
    await popup.close();
  }
  const noJs = await browser.newContext({ javaScriptEnabled: false });
  const fallback = await noJs.newPage();
  await fallback.goto(url);
  await fallback.locator('[data-inline-reply-link]').click();
  await fallback.waitForURL(destination);
  await noJs.close();
  console.log('PASS inline reply scroll/focus, draft preservation, modified clicks, middle-click, and no-JavaScript fallback at phone/desktop sizes');
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
