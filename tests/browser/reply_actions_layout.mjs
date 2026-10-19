import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

// Render the actual reply form, including its shared actions menu.
const html = execFileSync('php', ['-r', `
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$partial = function($name, $vars = []) {
  extract($vars); ob_start(); include 'templates/'.$name; return ob_get_clean();
};
$body = ''; $threadId = 'test'; $parentId = ''; $boardTags = 'general';
include 'templates/partials/reply_form.php';
`], { encoding: 'utf8' });
const css = path => readFileSync('public/assets/' + path, 'utf8');
const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome',
  args: ['--no-sandbox']
});
try {
  const page = await browser.newPage();
  for (const theme of ['light', 'word97', 'chicago', 'midnight']) {
    for (const width of [320, 375, 390, 480, 768]) {
      await page.setViewportSize({ width, height: 800 });
      await page.setContent(`<html data-theme="${theme}"><head><style>
        ${css('site.css')}${css('compose.css')}${css('theme-' + theme + '.css')}
      </style></head><body><main class="card">${html}</main></body></html>`);
      const submit = page.locator('.compose-form-actions > button[type="submit"]');
      const menu = page.locator('.compose-action-menu-toggle');
      const buttonBox = await submit.boundingBox();
      const menuBox = await menu.boundingBox();
      assert.ok(Math.abs(buttonBox.y - menuBox.y) < 2, `${theme} ${width}: same row`);
      assert.ok(buttonBox.x + buttonBox.width <= menuBox.x, `${theme} ${width}: no overlap`);
      assert.ok(menuBox.x + menuBox.width <= width, `${theme} ${width}: fits viewport`);
      assert.ok(buttonBox.height >= 44 && menuBox.height >= 44);
      await menu.click();
      await page.locator('[data-action="clear-compose-fields"]').waitFor({ state: 'visible' });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    }
  }
  console.log('PASS reply actions share a row and menu opens across 20 theme/viewport combinations');
} finally {
  await browser.close();
}
