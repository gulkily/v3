import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkHistory(page, context, base, artifacts, seed, incoming, mixed) {
  const messages = Array.from({ length: 63 }, (_, i) => ({ id: 'older-' + String(i + 1).padStart(2, '0'),
    time: '2026-10-07T12:00:00Z', sender: 'bob', envelope: i > 1 && i % 11 === 0 ? mixed.invalid : (i > 1 && i % 7 === 0 ? mixed.long : incoming) }));
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
    const anchor = await page.locator('[data-private-message-id]').evaluateAll(cards => {
      const card = cards.find(node => node.getBoundingClientRect().bottom > 0 && node.getBoundingClientRect().top < innerHeight);
      return card ? { id: card.dataset.privateMessageId, top: card.getBoundingClientRect().top } : null;
    });
    await load.evaluate(button => { button.click(); button.click(); });
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
    assert.equal(requests, before + 1, 'Repeated activation must share one request');
    if (anchor) {
      const top = await page.locator(`[data-private-message-id="${anchor.id}"]`).evaluate(node => node.getBoundingClientRect().top);
      assert.ok(Math.abs(top - anchor.top) <= 5, `History anchor moved ${top - anchor.top}px`);
    }
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
  await checkHistoryPosition(page, artifacts);
}

async function visibleAnchor(page) {
  return page.locator('[data-private-message-id]').evaluateAll(cards => {
    const card = cards.find(node => node.getBoundingClientRect().bottom > 0 && node.getBoundingClientRect().top < visualViewport.height);
    return card ? { id: card.dataset.privateMessageId, top: card.getBoundingClientRect().top } : null;
  });
}
async function assertAnchor(page, anchor) {
  assert.ok(anchor, 'Fixture must have a visible message');
  const top = await page.locator(`[data-private-message-id="${anchor.id}"]`).evaluate(node => node.getBoundingClientRect().top);
  assert.ok(Math.abs(top - anchor.top) <= 5, `History anchor moved ${top - anchor.top}px`);
}
async function checkHistoryPosition(page, artifacts) {
  const cdp = await page.context().newCDPSession(page);
  for (const mode of ['desktop', 'mobile', 'zoom', 'navigate', 'latest', 'reply']) {
    await page.setViewportSize({ width: mode === 'mobile' ? 375 : 1100, height: 800 });
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled === '1');
    await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: mode === 'zoom' ? 2 : 1 });
    await page.locator('textarea').fill(mode === 'reply' ? 'Reply during older history' : 'History position draft');
    await page.evaluate(() => {
      const original = window.ForumPrivateMessageReader.readCard;
      const gate = new Promise(resolve => { window.releaseHistoryReads = resolve; });
      window.historyPending = 0;
      window.ForumPrivateMessageReader.readCard = async function (...args) {
        if (args[1].dataset.privateMessageId.startsWith('older-')) {
          const order = window.historyPending++;
          await gate;
          await new Promise(resolve => setTimeout(resolve, (order % 3) * 80));
        }
        return original.apply(this, args);
      };
      document.querySelector('[data-role="history-load"]').scrollIntoView();
    });
    const anchor = await visibleAnchor(page);
    await page.locator('[data-role="history-load"]').evaluate(button => button.click());
    await page.waitForFunction(() => window.historyPending > 0);
    let chosen;
    if (mode === 'navigate') {
      await page.mouse.move(200, 200);
      await page.mouse.wheel(0, 500);
      await page.waitForTimeout(150);
      chosen = await visibleAnchor(page);
    } else if (mode === 'latest') {
      await page.locator('[data-role="private-message-latest"]').evaluate(button => button.click());
      chosen = await visibleAnchor(page);
    } else if (mode === 'reply') {
      await page.evaluate(() => document.querySelector('[data-private-message-form]').requestSubmit());
      await page.getByText('Reply during older history', { exact: true }).waitFor();
    }
    await page.evaluate(() => window.releaseHistoryReads());
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
    await page.waitForTimeout(100);
    if (mode === 'navigate' || mode === 'latest') {
      // User navigation cancels our old anchor; native anchoring follows their new position.
      assert.ok(chosen.id !== anchor.id || Math.abs(chosen.top - anchor.top) > 100, 'Navigation must move away from the original position');
      await assertAnchor(page, chosen);
    } else {
      await assertAnchor(page, anchor);
    }
    assert.equal(await page.evaluate(() => document.documentElement.style.overflowAnchor), '');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (mode === 'mobile') await page.screenshot({ path: join(artifacts, 'history-mobile.png') });
    await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 1 });
  }
  await cdp.detach();
  await page.locator('textarea').fill('');
}
