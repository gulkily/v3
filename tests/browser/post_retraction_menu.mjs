import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (const mobile of [false, true]) {
    const context = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : {});
    const page = await context.newPage();
    page.setDefaultTimeout(5000);
    await page.setContent(`<style>${await readFile('public/assets/content-interactions.css', 'utf8')}</style><div style="margin:3rem"><div data-role="retraction-control" class="post-retraction-control"><details class="post-more-actions"><summary aria-label="More actions">…</summary><div class="post-more-actions-panel"><button data-action="retract-post" data-post-id="test">Retract post</button></div></details><p data-role="retraction-feedback" hidden></p></div></div>`);
    await page.addScriptTag({ content: await readFile('public/assets/post_retraction.js', 'utf8') });
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), false);
    const summary = page.locator('summary[aria-label="More actions"]');
    if (mobile) await summary.tap();
    else { await summary.focus(); await page.keyboard.press('Enter'); }
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), true);
    await page.getByRole('button', { name: 'Retract post' }).focus();
    await page.keyboard.press('Escape');
    assert.equal(await summary.evaluate(el => el === document.activeElement), true);
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), false);
    await context.close();
  }
  console.log('PASS retraction menu keyboard, Escape/focus, and touch');
} finally { await browser.close(); }
