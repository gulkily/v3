import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkUnavailable(page, context, base, artifacts, seed, unavailable, invalid) {
  seed({ action: 'chat', messages: Array.from({ length: 20 }, (_, i) => ({
    id: 'unavailable-' + String(i).padStart(2, '0'), time: `2040-06-01T12:${String(i).padStart(2, '0')}:00Z`,
    sender: 'user-56', envelope: unavailable,
  })) });
  await page.goto(base + '/');
  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.locator('[data-conversation-row][data-counterpart="user-56"] a').click();
  await page.waitForFunction(() => document.querySelector('.private-conversation')?.dataset.privateMessageReaderSettled === '1');
  const groups = page.locator('[data-role="unavailable-group"]');
  assert.equal(await groups.count(), 1);
  const button = groups.getByRole('button');
  assert.match(await button.innerText(), /20 messages unavailable · user-56/);
  assert.equal(await button.getAttribute('aria-expanded'), 'false');
  assert.equal(await page.locator('[data-reader-state="decryption-failed"][hidden]').count(), 20);
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.readState === 'acknowledged');
  await button.focus();
  await button.press('Enter');
  assert.equal(await button.getAttribute('aria-expanded'), 'true');
  assert.equal(await page.locator('[data-reader-state="decryption-failed"]:not([hidden])').count(), 20);
  assert.deepEqual(await page.locator('[data-reader-state="decryption-failed"]').evaluateAll(cards => cards.map(card => card.dataset.privateMessageId)), Array.from({ length: 20 }, (_, i) => 'unavailable-' + String(i).padStart(2, '0')));
  const first = page.locator('[data-private-message-id="unavailable-00"]');
  await first.locator('summary').click();
  await first.getByRole('button', { name: 'Retry reading message' }).waitFor();
  assert.match(await first.innerText(), /unsuitable key cannot recover/);
  await button.click();
  assert.equal(await button.getAttribute('aria-expanded'), 'false');
  await page.setViewportSize({ width: 375, height: 812 });
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: join(artifacts, 'unavailable-mobile.png') });
  await page.setViewportSize({ width: 1100, height: 800 });

  seed({ action: 'chat', messages: Array.from({ length: 7 }, (_, i) => ({
    id: 'mixed-unavailable-' + i, time: `2041-06-${i >= 5 ? '02' : '01'}T12:0${i}:00Z`, sender: 'user-55', envelope: i === 2 ? invalid : unavailable,
  })) });
  await page.goto(base + '/messages/conversation/user-55');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  assert.equal(await groups.count(), 3, 'Warnings and dates break runs');
  const warning = page.locator('[data-private-message-id="mixed-unavailable-2"]');
  assert.equal(await warning.getAttribute('hidden'), null);
  assert.match(await warning.innerText(), /signature.*not.*verified/i);
  assert.equal(await warning.locator('[data-role="private-message-plaintext"]').innerText(), '');
}
