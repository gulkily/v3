import assert from 'node:assert/strict';

export async function checkHistoryRecovery(page) {
  await page.reload();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.privateMessageReaderSettled === '1');
  const root = page.locator('[data-mailbox="conversation"]');
  const field = page.locator('textarea');
  const ids = () => page.locator('[data-private-message-id]').evaluateAll(cards => cards.map(card => card.dataset.privateMessageId));
  const loaded = await ids();
  const cursor = await root.getAttribute('data-history-next-cursor');
  await field.fill('Keep this history recovery draft');
  let mode = 'abort', release, arrived;
  const urls = [];
  const intercept = async route => {
    urls.push(route.request().url());
    if (mode === 'abort') return route.abort();
    if (mode === 'malformed') return route.fulfill({ status: 200, body: '{not json' });
    if (mode === 'shape') return route.fulfill({ status: 200, json: { status: 'ok', messages: [], page_cursor: 'foreign', next_cursor: null } });
    if (mode === 'auth') return route.fulfill({ status: 403, json: { status: 'error', error: 'Not eligible' } });
    if (mode === 'stale') return route.fulfill({ status: 400, json: { status: 'error', restart: true } });
    if (mode === 'hold') {
      const response = await route.fetch();
      const held = new Promise(resolve => { release = () => resolve(response); });
      arrived();
      return route.fulfill({ response: await held });
    }
    return route.continue();
  };
  await page.route('**/api/private_messages/conversation?*', intercept);
  const settled = () => page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.historyLoading === '0');
  for (const failure of ['abort', 'malformed', 'shape', 'auth', 'stale']) {
    mode = failure;
    await page.locator('[data-role="history-load"]').click();
    await settled();
    assert.deepEqual(await ids(), loaded, failure + ' must preserve loaded content');
    assert.equal(await field.inputValue(), 'Keep this history recovery draft');
    assert.equal(new URL(urls.at(-1)).searchParams.get('cursor'), cursor, 'Retry must reuse the exact cursor');
    if (failure === 'auth') assert.match(await page.locator('[data-role="history-status"]').innerText(), /sign-in and messaging eligibility/);
  }
  await page.getByRole('button', { name: 'Restart history', exact: true }).waitFor();
  mode = 'abort';
  await page.getByRole('button', { name: 'Restart history', exact: true }).click();
  await settled();
  assert.deepEqual(await ids(), loaded, 'Failed restart must keep the prior window');
  assert.equal(new URL(urls.at(-1)).searchParams.has('cursor'), false);
  assert.equal(await field.inputValue(), 'Keep this history recovery draft');

  mode = 'hold';
  const freshArrived = new Promise(resolve => { arrived = resolve; });
  await page.getByRole('button', { name: 'Restart history', exact: true }).click();
  await freshArrived;
  let releaseSend, sendArrived;
  const accepted = new Promise(resolve => { sendArrived = resolve; });
  const sendRoute = async route => {
    const response = await route.fetch();
    assert.equal(response.status(), 201);
    const held = new Promise(resolve => { releaseSend = resolve; });
    sendArrived();
    await held;
    return route.fulfill({ response });
  };
  await page.route('**/api/private_messages', sendRoute);
  await field.fill('Confirmed while history restarts');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await accepted;
  await field.fill('Newer draft must survive restart and send');
  releaseSend();
  await page.getByText('Confirmed while history restarts', { exact: true }).waitFor();
  const reply = await page.locator('[data-private-message-id]').filter({ hasText: 'Confirmed while history restarts' }).getAttribute('data-private-message-id');
  release();
  await settled();
  assert.equal((await ids()).filter(id => id === reply).length, 1);
  assert.equal((await ids()).length, 26, 'Fresh 25-message window must also retain the concurrent confirmation');
  assert.equal(await field.inputValue(), 'Newer draft must survive restart and send');
  await page.unroute('**/api/private_messages', sendRoute);

  // Navigation during the network wait must become the new anchor at prepend time.
  const olderArrived = new Promise(resolve => { arrived = resolve; });
  await page.locator('[data-role="history-load"]').click();
  await olderArrived;
  await page.mouse.move(200, 200);
  await page.mouse.wheel(0, 600);
  await page.waitForTimeout(150);
  const chosen = await page.locator('[data-private-message-id]').evaluateAll(cards => {
    const card = cards.find(node => node.getBoundingClientRect().bottom > 0 && node.getBoundingClientRect().top < innerHeight);
    return { id: card.dataset.privateMessageId, top: card.getBoundingClientRect().top };
  });
  release();
  await settled();
  const newTop = await page.locator(`[data-private-message-id="${chosen.id}"]`).evaluate(node => node.getBoundingClientRect().top);
  assert.ok(Math.abs(newTop - chosen.top) <= 5);

  // A response for a removed conversation must not mutate that obsolete view.
  const obsoleteArrived = new Promise(resolve => { arrived = resolve; });
  await page.locator('[data-role="history-load"]').click();
  await obsoleteArrived;
  const obsoleteCount = (await ids()).length;
  await page.evaluate(() => {
    window.obsoleteConversation = document.querySelector('[data-mailbox="conversation"]');
    window.obsoleteConversation.remove();
  });
  release();
  await page.waitForTimeout(300);
  assert.equal(await page.evaluate(() => window.obsoleteConversation.querySelectorAll('[data-private-message-id]').length), obsoleteCount);
  await page.unroute('**/api/private_messages/conversation?*', intercept);
  await page.reload();
  await field.fill('');
}
