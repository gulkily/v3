// Self-contained: exercises public/assets/compose_disclosure.js against a fixture form.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright-core';
const script = readFileSync(new URL('../../public/assets/compose_disclosure.js', import.meta.url), 'utf8');
const browser = await chromium.launch({headless:true, executablePath:process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args:['--no-sandbox']});
try {
  const page = await browser.newPage();
  await page.setContent(`<form data-compose-form>
    <input name="subject" value="">
    <details data-compose-disclosure="tags"><summary># <span data-role="compose-disclosure-summary">general</span></summary>
      <input name="board_tags" value="general">
      <p data-role="compose-field-normalization-status" hidden>warn</p></details>
    <details data-compose-disclosure="event"><summary>Event <span data-role="compose-disclosure-summary"></span></summary>
      <input name="event_date" value=""><input name="event_time" value=""><input name="event_location" value=""></details>
    <details class="compose-action-menu"><summary aria-label="More actions">...</summary><div class="compose-action-menu-list">
      <button type="button" data-action="clear-compose-fields" onclick="document.querySelector('[name=board_tags]').value='general'">Clear</button></div></details>
    <button type="button" id="outside">Outside</button>
  </form>`);
  await page.addScriptTag({content: script});
  const open = name => page.$eval(`[data-compose-disclosure="${name}"]`, el => el.open);
  const summary = name => page.$eval(`[data-compose-disclosure="${name}"] [data-role="compose-disclosure-summary"]`, el => el.textContent);
  assert.equal(await open('tags'), false);
  await page.evaluate(() => { const i = document.querySelector('[name=board_tags]'); i.value = 'feature'; i.dispatchEvent(new Event('input', {bubbles:true})); });
  assert.equal(await summary('tags'), 'feature');
  await page.evaluate(() => { document.querySelector('[data-role="compose-field-normalization-status"]').hidden = false; });
  await page.waitForFunction(() => document.querySelector('[data-compose-disclosure="tags"]').open);
  assert.equal(await open('event'), false);
  await page.click('.compose-action-menu > summary');
  assert.equal(await page.$eval('.compose-action-menu', el => el.open), true);
  await page.keyboard.press('Escape');
  assert.equal(await page.$eval('.compose-action-menu', el => el.open), false);
  await page.click('.compose-action-menu > summary');
  await page.click('#outside');
  assert.equal(await page.$eval('.compose-action-menu', el => el.open), false);
  await page.click('.compose-action-menu > summary');
  await page.click('[data-action="clear-compose-fields"]');
  await page.waitForFunction(() => !document.querySelector('.compose-action-menu').open);
  await page.waitForFunction(() => document.querySelector('[data-compose-disclosure="tags"] [data-role="compose-disclosure-summary"]').textContent === 'general');
  console.log('compose_disclosure: ok');
} finally {
  await browser.close();
}
