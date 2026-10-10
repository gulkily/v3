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

  // More than two windows form one run, retaining its open choice and scroll anchor.
  seed({ action: 'chat', messages: Array.from({ length: 60 }, (_, i) => ({
    id: 'unavailable-history-' + String(i).padStart(2, '0'), time: `2042-06-01T12:${String(i).padStart(2, '0')}:00Z`,
    sender: 'user-54', envelope: unavailable,
  })) });
  await page.goto(base + '/messages/conversation/user-54');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  assert.match(await groups.innerText(), /25 messages unavailable/);
  await groups.getByRole('button').click();
  await page.locator('textarea').fill('Draft while expanding history');
  const load = page.getByRole('button', { name: 'Load older', exact: true });
  await load.scrollIntoViewIfNeeded();
  const anchor = await groups.evaluate(node => node.getBoundingClientRect().top);
  await load.evaluate(node => node.click());
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.historyLoading === '0');
  assert.equal(await groups.count(), 1);
  assert.match(await groups.innerText(), /50 messages unavailable/);
  assert.equal(await groups.getByRole('button').getAttribute('aria-expanded'), 'true');
  assert.ok(Math.abs(await groups.evaluate(node => node.getBoundingClientRect().top) - anchor) <= 5, 'Merged summary must retain reading position');
  await groups.getByRole('button').click();
  await load.click();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.historyLoading === '0');
  assert.match(await groups.innerText(), /60 messages unavailable/);
  assert.equal(await groups.getByRole('button').getAttribute('aria-expanded'), 'false');
  const ids = await page.locator('[data-reader-state="decryption-failed"]').evaluateAll(cards => cards.map(card => card.dataset.privateMessageId));
  assert.deepEqual(ids, Array.from({ length: 60 }, (_, i) => 'unavailable-history-' + String(i).padStart(2, '0')));
  assert.equal(await page.locator('textarea').inputValue(), 'Draft while expanding history');

  await page.reload();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  const stale = route => route.fulfill({ status: 400, json: { status: 'error', restart: true } });
  await page.route('**/api/private_messages/conversation?*', stale);
  await load.click();
  await page.getByRole('button', { name: 'Restart history', exact: true }).waitFor();
  assert.match(await groups.innerText(), /25 messages unavailable/);
  await page.unroute('**/api/private_messages/conversation?*', stale);
  await page.getByRole('button', { name: 'Restart history', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.historyLoading === '0');
  assert.equal(await groups.count(), 1);
  assert.equal(await page.locator('[data-private-message-id]').count(), 25);
  assert.match(await groups.innerText(), /25 messages unavailable/);
  assert.equal(await page.locator('textarea').inputValue(), 'Draft while expanding history');
  await page.locator('textarea').fill('');
}
