import assert from 'node:assert/strict';

export async function checkSeenRecovery(page, context, device, base, seed, incoming) {
  const fresh = async () => (await (await device.request.get(base + '/api/private_messages/conversation?username_token=bob')).json()).read_token;
  const ack = async token => device.request.post(base + '/api/private_messages/read', { headers: { 'X-Requested-With': 'ForumPrivateMessages' }, data: { counterpart: 'bob', read_token: token } });
  const unread = async () => (await (await device.request.get(base + '/api/private_messages/unread?counterparts[]=bob')).json());
  const add = id => seed({ action: 'chat', messages: [{ id, time: '2032-01-01T00:00:00Z', sender: 'bob', envelope: incoming }] });
  add('seen-recovery');
  let releaseRead, acceptedRead, captured, calls = 0;
  const accepted = new Promise(resolve => { acceptedRead = resolve; });
  const lost = async route => {
    calls++;
    const input = route.request().postDataJSON();
    if (calls > 1) { assert.deepEqual(input, captured, 'Retry must retain the exact receipt'); return route.continue(); }
    captured = input;
    const response = await route.fetch();
    assert.equal(response.status(), 200);
    const held = new Promise(resolve => { releaseRead = resolve; });
    acceptedRead();
    await held;
    return route.abort();
  };
  await page.route('**/api/private_messages/read', lost);
  await page.reload();
  await accepted;
  assert.equal((await unread()).conversations.bob, false, 'Second device sees the accepted update');
  let releaseSend, acceptedSend;
  const sent = new Promise(resolve => { acceptedSend = resolve; });
  const holdSend = async route => {
    const response = await route.fetch();
    const held = new Promise(resolve => { releaseSend = resolve; });
    acceptedSend(); await held;
    return route.fulfill({ response });
  };
  await page.route('**/api/private_messages', holdSend);
  await page.locator('textarea').fill('Send pending during seen recovery');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await sent;
  await page.locator('textarea').fill('Newer draft during seen recovery');
  add('seen-after-lost-ack');
  releaseRead();
  await page.getByRole('button', { name: 'Retry seen status', exact: true }).waitFor();
  await page.getByRole('button', { name: 'Retry seen status', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.readState === 'acknowledged');
  assert.equal((await unread()).conversations.bob, true, 'Retry cannot clear a later arrival');
  releaseSend();
  await page.getByText('Send pending during seen recovery', { exact: true }).waitFor();
  assert.equal(await page.locator('textarea').inputValue(), 'Newer draft during seen recovery');
  await page.unroute('**/api/private_messages', holdSend);
  await page.unroute('**/api/private_messages/read', lost);

  for (const failure of ['malformed', 'denied', 'reopen']) {
    const block = route => route.fulfill({ status: failure === 'malformed' ? 200 : failure === 'denied' ? 403 : 400,
      json: failure === 'malformed' ? { status: 'ok', viewer: 'alice' } : { status: 'error', reopen: failure === 'reopen' } });
    await page.route('**/api/private_messages/read', block);
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.readState === 'failed');
    assert.equal(await page.locator('textarea').inputValue(), 'Newer draft during seen recovery');
    assert.equal((await unread()).conversations.bob, true);
    await page.unroute('**/api/private_messages/read', block);
  }
  await page.getByRole('link', { name: 'Reopen conversation', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]')?.dataset.readState === 'acknowledged');
  assert.equal(await page.locator('textarea').inputValue(), 'Newer draft during seen recovery');
  assert.equal((await unread()).conversations.bob, false);
  await page.locator('textarea').fill('');

  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'ready');
  const otherPage = await device.newPage();
  await otherPage.goto(base + '/messages');
  await otherPage.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'ready');
  add('seen-reversed-refresh');
  let release, arrived, first = true;
  const ready = new Promise(resolve => { arrived = resolve; });
  const oldRefresh = async route => {
    if (!first) return route.continue();
    first = false;
    const response = await route.fetch();
    const held = new Promise(resolve => { release = resolve; });
    arrived(); await held;
    return route.fulfill({ response });
  };
  await page.route('**/api/private_messages/unread?*', oldRefresh);
  await page.evaluate(() => { window.oldUnreadRefresh = window.ForumPrivateMessageUnread.refresh(); });
  await ready;
  await ack(await fresh());
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  const correctCount = String((await unread()).unread_count);
  release();
  await page.evaluate(() => window.oldUnreadRefresh);
  assert.equal(await page.locator('[data-private-message-unread]').getAttribute('data-unread-count'), correctCount);
  await page.unroute('**/api/private_messages/unread?*', oldRefresh);
  await otherPage.bringToFront();
  await otherPage.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  assert.equal(await otherPage.locator('[data-private-message-unread]').getAttribute('data-unread-count'), correctCount);
  await otherPage.close();
  await page.bringToFront();

  await page.evaluate(() => {
    window.savedUnreadKey = localStorage.getItem('forum_pki_public_key');
    localStorage.setItem('forum_pki_public_key', 'changed identity');
    dispatchEvent(new StorageEvent('storage', { key: 'forum_pki_public_key' }));
  });
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'unavailable');
  assert.equal(await page.locator('[data-private-message-unread]').getAttribute('data-unread-count'), null);
  await page.evaluate(() => {
    localStorage.setItem('forum_pki_public_key', window.savedUnreadKey);
    dispatchEvent(new StorageEvent('storage', { key: 'forum_pki_public_key' }));
  });
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'ready');
  const wrongViewer = route => route.fulfill({ json: { status: 'ok', viewer: 'outsider', unread_count: 999, conversations: {}, revision: 'wrong' } });
  await page.route('**/api/private_messages/unread?*', wrongViewer);
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  assert.equal(await page.locator('[data-private-message-unread]').getAttribute('data-unread-state'), 'unavailable');
  await page.unroute('**/api/private_messages/unread?*', wrongViewer);
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  await page.goto(base + '/user/bob');
  await page.goBack();
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'ready');
  assert.equal(await page.locator('[data-private-message-unread]').getAttribute('data-unread-count'), String((await unread()).unread_count));
}
