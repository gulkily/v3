import assert from 'node:assert/strict';

export async function clearUnreadFixture(context, base) {
  let cursor;
  do {
    const listing = await (await context.request.get(base + '/api/private_messages/conversations' + (cursor ? '?cursor=' + encodeURIComponent(cursor) : ''))).json();
    for (const row of listing.conversations) {
      const response = await context.request.get(base + '/api/private_messages/conversation?username_token=' + row.counterpart);
      if (!response.ok()) continue;
      const window = await response.json();
      await context.request.post(base + '/api/private_messages/read', { headers: { 'X-Requested-With': 'ForumPrivateMessages' }, data: { counterpart: row.counterpart, read_token: window.read_token } });
    }
    cursor = listing.next_cursor;
  } while (cursor);
}

export async function checkSeenWindows(page, context, base, seed, incoming, invalid) {
  await clearUnreadFixture(context, base);
  const count = async () => (await (await context.request.get(base + '/api/private_messages/unread')).json()).unread_count;
  seed({ action: 'chat', messages: [
    { id: 'seen-window-bob', time: '2030-01-01T00:00:00Z', sender: 'bob', envelope: incoming },
    { id: 'seen-window-other', time: '2030-01-01T00:00:00Z', sender: 'user-58', envelope: incoming },
  ] });
  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadCount === '2');
  assert.equal(await count(), 2);

  const background = await context.newPage();
  // Deterministic hidden/focus gates; real focus/visibility also remain prerequisites in production.
  await background.addInitScript(() => {
    window.testHidden = true;
    Object.defineProperty(document, 'hidden', { get: () => window.testHidden });
    document.hasFocus = () => !window.testHidden;
  });
  await background.goto(base + '/messages/conversation/bob');
  await background.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  await background.waitForTimeout(150);
  assert.equal(await count(), 2, 'A background conversation must not acknowledge');
  await background.evaluate(() => { window.testHidden = false; document.dispatchEvent(new Event('visibilitychange')); });
  await background.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.readState === 'acknowledged');
  assert.equal(await count(), 1);
  await background.close();
  await page.bringToFront();
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  assert.equal(await page.locator('[data-private-message-unread]').getAttribute('data-unread-count'), '1');

  seed({ action: 'chat', messages: [{ id: 'seen-invalid', time: '2031-01-01T00:00:00Z', sender: 'bob', envelope: invalid }] });
  let unblock, requested;
  const arrived = new Promise(resolve => { requested = resolve; });
  const held = new Promise(resolve => { unblock = resolve; });
  const slow = async route => { requested(); await held; return route.continue(); };
  await page.route('**/api/private_messages/conversation?*', slow);
  await page.locator('[data-conversation-row][data-counterpart="bob"] a').click();
  await arrived;
  await page.evaluate(() => { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home' })); window.scrollTo(0, 0); });
  unblock();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  await page.waitForTimeout(150);
  assert.equal(await count(), 2, 'Early navigation away from latest must prevent acknowledgment');
  await page.unroute('**/api/private_messages/conversation?*', slow);
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.readState === 'acknowledged');
  assert.equal(await count(), 1, 'Displayed failed verification may be seen, never verified');
  await page.locator('[data-private-message-id="seen-invalid"]').getByText('The sender signature could not be verified.').waitFor();

  seed({ action: 'chat', messages: [{ id: 'seen-after-window', time: '2020-01-01T00:00:00Z', sender: 'bob', envelope: incoming }] });
  await page.locator('textarea').fill('Draft survives seen windows');
  await page.getByRole('button', { name: 'Load older', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.equal(await count(), 2, 'Older history cannot broaden the opened receipt');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await page.getByText('Draft survives seen windows', { exact: true }).waitFor();
  assert.equal(await count(), 2, 'An inline send must not acknowledge the later arrival');

  const stale = route => route.fulfill({ status: 400, json: { status: 'error', restart: true } });
  await page.route('**/api/private_messages/conversation?*', stale);
  await page.getByRole('button', { name: 'Load older', exact: true }).click();
  await page.getByRole('button', { name: 'Restart history', exact: true }).waitFor();
  await page.unroute('**/api/private_messages/conversation?*', stale);
  await page.getByRole('button', { name: 'Restart history', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.readState === 'acknowledged');
  assert.equal(await count(), 1, 'A successful visible fresh window acknowledges its new boundary');
}
