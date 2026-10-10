import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkHistory(page, context, base, artifacts, seed, incoming) {
  const messages = Array.from({ length: 63 }, (_, i) => ({ id: 'older-' + String(i + 1).padStart(2, '0'),
    time: '2026-10-07T12:00:00Z', sender: 'bob', envelope: incoming }));
  seed({ action: 'chat', messages });
  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.locator('[data-conversation-row][data-counterpart="bob"] a').click();
  await page.waitForURL('**/messages/conversation/bob');
  await page.locator('[data-mailbox="conversation"]').waitFor();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  const initialCursor = await page.locator('[data-mailbox="conversation"]').getAttribute('data-history-page-cursor');
  let cursor = initialCursor, expected = [];
  do {
    const response = await context.request.get(base + '/api/private_messages/conversation?username_token=bob&cursor=' + encodeURIComponent(cursor));
    const result = await response.json();
    expected = result.messages.map(message => message.message_id).concat(expected);
    cursor = result.next_cursor;
  } while (cursor);
  assert.ok(expected.length >= 63);
  assert.equal(await page.locator('[data-private-message-id]').count(), 25);
  seed({ action: 'chat', messages: [{ id: 'history-interleaved', time: '2026-10-06T12:00:00Z', sender: 'bob', envelope: incoming }] });
  let requests = 0;
  const countRequest = route => { requests++; return route.continue(); };
  await page.route('**/api/private_messages/conversation?*', countRequest);
  const load = page.getByRole('button', { name: 'Load older', exact: true });
  await page.locator('textarea').fill('Draft while reading history');
  while (await load.isVisible()) {
    const before = requests;
    await load.scrollIntoViewIfNeeded();
    await load.evaluate(button => { button.click(); button.click(); });
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
    assert.equal(requests, before + 1, 'Repeated activation must share one request');
  }
  const ids = await page.locator('[data-private-message-id]').evaluateAll(cards => cards.map(card => card.dataset.privateMessageId));
  assert.deepEqual(ids, expected);
  assert.equal(new Set(ids).size, ids.length);
  assert.equal(ids.includes('history-interleaved'), false);
  assert.equal(await page.locator('textarea').inputValue(), 'Draft while reading history');
  await page.getByText('All history loaded.', { exact: true }).waitFor();
  const oldest = page.locator('[data-private-message-id="older-01"]');
  await oldest.getByText('Incoming fixture preview', { exact: true }).waitFor();
  assert.equal(await oldest.getAttribute('aria-label'), 'Message from bob');
  assert.equal(await page.locator('[data-private-message-id="older-02"]').evaluate(node => node.classList.contains('is-grouped')), true);
  // Old-message retries must use the retained envelope, not re-query the recent window.
  await page.evaluate(async () => {
    const key = localStorage.getItem('forum_pki_private_key');
    localStorage.removeItem('forum_pki_private_key');
    await window.ForumPrivateMessageReader.readCard('conversation', document.querySelector('[data-private-message-id="older-01"]'), 'bob');
    localStorage.setItem('forum_pki_private_key', key);
  });
  const beforeRetry = requests;
  await oldest.getByRole('button', { name: 'Retry reading message' }).click();
  await oldest.getByText('Incoming fixture preview', { exact: true }).waitFor();
  assert.equal(requests, beforeRetry);
  await page.screenshot({ path: join(artifacts, 'history-loaded.png') });
  await page.locator('textarea').fill('');
  await page.unroute('**/api/private_messages/conversation?*', countRequest);
}
