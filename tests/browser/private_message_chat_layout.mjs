import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkChatLayout(page, artifacts) {
  const field = page.locator('textarea[name="plaintext"]');
  assert.match(await field.getAttribute('aria-label'), /Message to bob/);
  assert.equal(await page.locator('[data-private-message-composer] h2').count(), 0);
  assert.equal(await field.getAttribute('rows'), '3');
  await field.fill('Keyboard draft');
  await field.press('Enter');
  await field.press('Shift+Enter');
  assert.equal(await field.inputValue(), 'Keyboard draft\n\n');
  const count = await page.locator('[data-private-message-id]').count();
  await field.dispatchEvent('compositionstart');
  await field.press('Control+Enter');
  assert.equal(await page.locator('[data-private-message-id]').count(), count);
  await field.dispatchEvent('compositionend');
  await field.fill('Browser follow-up');
  await field.press('Control+Enter');
  await page.waitForFunction(expected => document.querySelectorAll('[data-private-message-id]').length === expected, count + 1);
  await page.waitForFunction(() => document.querySelectorAll('[data-role="private-message-verification"]:not([hidden])').length === document.querySelectorAll('[data-private-message-id]').length);
  await field.fill('Browser follow-up');
  await field.press('Meta+Enter');
  await page.waitForFunction(expected => document.querySelectorAll('[data-private-message-id]').length === expected, count + 2);
  await field.fill('Long draft line\n'.repeat(40));
  assert.ok(await field.evaluate(node => node.getBoundingClientRect().height <= 182));
  assert.equal(await field.evaluate(node => getComputedStyle(node).overflowY), 'auto');
  await page.setViewportSize({ width: 375, height: 320 });
  await page.waitForFunction(() => document.querySelector('.private-conversation').classList.contains('composer-inline'));
  assert.equal(await page.locator('[data-private-message-composer]').evaluate(node => getComputedStyle(node).position), 'static');
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.setViewportSize({ width: 375, height: 812 });
  await field.fill('');
  await page.waitForFunction(() => !document.querySelector('.private-conversation').classList.contains('composer-inline'));
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 2 });
  await page.waitForFunction(() => document.querySelector('.private-conversation').classList.contains('composer-inline'));
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 1 });
  await cdp.detach();
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.ok(await page.getByRole('button', { name: 'Latest message', exact: true }).evaluate(node => node.getBoundingClientRect().bottom <= innerHeight));
  await page.screenshot({ path: join(artifacts, 'chat-mobile.png') });
  await page.setViewportSize({ width: 1100, height: 800 });
  await page.screenshot({ path: join(artifacts, 'chat-desktop.png') });
  // A long transcript exercises the earlier-reading path independently of the history cap.
  await page.evaluate(() => {
    const transcript = document.querySelector('[data-role="private-message-transcript"]');
    const original = transcript.querySelector('[data-private-message-id]');
    for (let i = 0; i < 24; i++) {
      const clone = original.cloneNode(true);
      clone.dataset.privateMessageId = 'layout-fixture-' + i;
      clone.dataset.layoutFixture = '1';
      transcript.insertBefore(clone, original);
    }
    window.ForumPrivateMessageConversation.format(document.querySelector('.private-conversation'));
  });
  await field.fill('Browser follow-up');
  await page.evaluate(() => window.scrollTo(0, 100));
  const priorScroll = await page.evaluate(() => scrollY);
  const priorCount = await page.locator('[data-private-message-id]').count();
  await page.evaluate(() => document.querySelector('[data-private-message-form]').requestSubmit());
  await page.waitForFunction(expected => document.querySelectorAll('[data-private-message-id]').length === expected, priorCount + 1);
  await page.waitForFunction(() => document.querySelectorAll('[data-role="private-message-verification"]:not([hidden])').length === document.querySelectorAll('[data-private-message-id]').length);
  assert.ok(Math.abs(await page.evaluate(() => scrollY) - priorScroll) < 5, 'Sending must preserve earlier reading position');
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.ok(await page.evaluate(() => scrollY) > priorScroll);
  await page.evaluate(() => {
    document.querySelectorAll('[data-layout-fixture]').forEach(node => node.remove());
    window.ForumPrivateMessageConversation.format(document.querySelector('.private-conversation'));
  });
}
