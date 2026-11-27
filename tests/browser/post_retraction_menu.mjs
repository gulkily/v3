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
  // Zalgo transforms sibling cards. A panel can be visible in the DOM while
  // Activity or the composer actually receives the pointer at the menu item.
  const cardCss = (await Promise.all(['site.css', 'content-interactions.css', 'theme-zalgo.css']
    .map(file => readFile(`public/assets/${file}`, 'utf8')))).join('\n');
  for (const width of [390, 1280]) {
    const mobile = width === 390;
    const context = await browser.newContext({
      viewport: { width, height: 800 }, hasTouch: mobile, isMobile: mobile, reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1">
      <style>${cardCss}</style><section class="stack" style="margin:12px">
        <article class="card post-card thread-root-card">
          <div class="button-row button-row-natural post-card-actions thread-root-actions">
            <a href="#composer">Reply</a>
            <button class="thread-reaction-button">Like</button><button class="thread-reaction-button">Flag</button>
            <div class="post-retraction-control" data-role="retraction-control">
              <details class="post-more-actions"><summary aria-label="More actions">…</summary>
                <div class="post-more-actions-panel">
                  <button class="thread-reaction-button" data-action="switch-post-format">Show as plain text</button>
                  <button class="thread-reaction-button" data-action="retract-post">Retract post</button>
                </div>
              </details>
            </div>
          </div>
        </article>
        <details class="card thread-activity"><summary>Activity (1)</summary></details>
        <form class="card" id="composer"><textarea placeholder="Write a reply…"></textarea></form>
      </section>`);
    await page.addScriptTag({ content: await readFile('public/assets/post_retraction.js', 'utf8') });
    for (const theme of ['gloss', 'zalgo']) {
      await page.evaluate(theme => { document.documentElement.dataset.theme = theme; }, theme);
      const card = page.locator('.thread-root-card');
      const summary = page.getByLabel('More actions');
      const closedLayer = await card.evaluate(el => getComputedStyle(el).zIndex);
      if (mobile) await summary.tap();
      else await summary.press('Enter');
      for (const name of ['Show as plain text', 'Retract post']) {
        const hit = await page.getByRole('button', { name }).evaluate(el => {
          const rect = el.getBoundingClientRect();
          return el.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
        });
        assert.ok(hit, `${theme} at ${width}px: ${name} receives pointer over following cards`);
      }
      await page.keyboard.press('Escape');
      assert.ok(await summary.evaluate(el => !el.parentElement.open && document.activeElement === el));
      assert.equal(await card.evaluate(el => getComputedStyle(el).zIndex), closedLayer);
    }
    await context.close();
  }
  console.log('PASS retraction menu keyboard, Escape/focus, outside click, touch, and cross-card stacking');
} finally { await browser.close(); }
