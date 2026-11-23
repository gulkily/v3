import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (const mobile of [false, true]) {
    const context = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : {});
    const page = await context.newPage();
    page.setDefaultTimeout(5000);
    await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${await readFile('public/assets/content-interactions.css', 'utf8')}</style><div style="margin:3rem"><div data-role="retraction-control" class="post-retraction-control"><details class="post-more-actions"><summary aria-label="More actions">…</summary><div class="post-more-actions-panel"><button data-action="retract-post" data-post-id="test">Retract post</button></div></details><p data-role="retraction-feedback" hidden></p></div><p id="outside">Post text</p><div data-role="retraction-control" class="post-retraction-control"><details class="post-more-actions" id="second"><summary aria-label="More actions">…</summary><div class="post-more-actions-panel"><button data-action="switch-post-format" data-post-id="other">Show as Markdown</button></div></details></div></div>`);
    await page.addScriptTag({ content: await readFile('public/assets/post_retraction.js', 'utf8') });
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), false);
    const summary = page.locator('summary[aria-label="More actions"]').first();
    if (mobile) await summary.tap();
    else { await summary.focus(); await page.keyboard.press('Enter'); }
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), true);
    await page.getByRole('button', { name: 'Retract post' }).focus();
    await page.keyboard.press('Escape');
    assert.equal(await summary.evaluate(el => el === document.activeElement), true);
    assert.equal(await page.getByRole('button', { name: 'Retract post' }).isVisible(), false);
    // Outside clicks and taps close it; clicks inside the open panel do not.
    const press = mobile ? (locator, options) => locator.tap(options) : (locator, options) => locator.click(options);
    const first = page.locator('details.post-more-actions').first();
    const second = page.locator('#second');
    await press(summary);
    assert.equal(await first.evaluate(el => el.open), true);
    const panel = first.locator('.post-more-actions-panel');
    await press(panel, { position: { x: (await panel.boundingBox()).width - 6, y: 6 } });
    assert.equal(await first.evaluate(el => el.open), true, 'a click inside the panel keeps it open');
    await press(page.locator('#outside'));
    assert.equal(await first.evaluate(el => el.open), false, 'an outside click closes it');
    await press(summary);
    await press(second.locator('summary'));
    assert.equal(await first.evaluate(el => el.open), false, 'opening another post menu closes the first');
    assert.equal(await second.evaluate(el => el.open), true);
    await press(page.locator('#outside'));
    assert.equal(await second.evaluate(el => el.open), false);
    await context.close();
  }
  console.log('PASS retraction menu keyboard, Escape/focus, outside click, and touch');
} finally { await browser.close(); }
