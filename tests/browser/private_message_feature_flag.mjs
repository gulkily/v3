import assert from 'node:assert/strict';

export async function checkMessagingFeatureFlag(page, context, base, seed) {
  const flag = enabled => seed({ action: 'messaging-flag', enabled });
  const draft = () => page.evaluate(() => JSON.parse(localStorage.getItem('forum_private_message_draft:alice:bob')));
  flag(true);
  await page.goto(base + '/messages/conversation/bob');
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  const field = page.locator('[data-private-message-composer] textarea');
  const send = page.getByRole('button', { name: 'Send private message', exact: true });
  await field.fill('Draft retained while messaging disabled');
  flag(false);
  // Background refresh may notice shutdown before a user can click Send.
  await page.locator('[data-private-message-form]').evaluate(form => form.requestSubmit());
  await page.locator('[data-messaging-disabled-notice]').waitFor();
  await page.waitForFunction(() => document.querySelector('[data-private-message-form] button[type="submit"]').disabled);
  assert.equal((await draft()).plaintext, 'Draft retained while messaging disabled');
  assert.equal(await field.inputValue(), 'Draft retained while messaging disabled');
  let foregroundRequests = 0;
  const count = request => { if (/\/api\/private_messages(?:$|\/recipient_keys)/.test(new URL(request.url()).pathname)) foregroundRequests++; };
  page.on('request', count);
  await page.evaluate(async () => {
    for (let i = 0; i < 2; i++) await window.ForumPrivateMessages.recipientKeys('bob').catch(() => {});
    document.querySelector('[data-private-message-form]').requestSubmit();
  });
  assert.equal(foregroundRequests, 0);
  page.off('request', count);
  for (const path of ['/messages', '/messages/inbox', '/api/private_messages/inbox', '/api/private_messages/history_sync/work']) {
    const response = await context.request.get(base + path);
    assert.equal(response.status(), 403);
    assert.match(response.headers()['cache-control'], /no-store/);
  }
  flag(true);
  await page.reload();
  assert.equal(await field.inputValue(), 'Draft retained while messaging disabled');
  await send.click();
  await page.locator('[data-private-message-id]').getByText('Draft retained while messaging disabled', { exact: true }).waitFor();

  // A committed send whose response is lost must remain the same pending attempt.
  let accepted, release, attempt;
  const acceptedPromise = new Promise(resolve => { accepted = resolve; });
  const releasePromise = new Promise(resolve => { release = resolve; });
  const intercept = async route => {
    attempt = route.request().postDataJSON();
    const response = await route.fetch();
    assert.equal(response.status(), 201);
    accepted();
    await releasePromise;
    await route.abort();
  };
  await page.route('**/api/private_messages', intercept);
  await field.fill('Accepted before shutdown');
  await send.click();
  await acceptedPromise;
  await field.fill('Newer unsent draft');
  flag(false);
  release();
  await page.locator('[data-pending-message-id] [data-role="send-status"]').filter({ hasText: 'Not confirmed' }).waitFor();
  await page.evaluate(() => window.ForumPrivateMessages.recipientKeys('bob').catch(() => {}));
  await page.locator('[data-messaging-disabled-notice]').waitFor();
  assert.equal((await draft()).attempt.messageId, attempt.message_id);
  assert.equal((await draft()).plaintext, 'Newer unsent draft');
  await page.unroute('**/api/private_messages', intercept);
  flag(true);
  await page.reload();
  assert.equal(await field.inputValue(), 'Newer unsent draft');
  const unconfirmed = page.locator(`[data-private-message-id="${attempt.message_id}"]`);
  await unconfirmed.getByRole('button', { name: 'Check', exact: true }).click();
  await unconfirmed.locator('[data-role="send-status"]').waitFor({ state: 'detached' });
  assert.equal(await field.inputValue(), 'Newer unsent draft');
  const messages = (await (await context.request.get(base + '/api/private_messages/conversation?username_token=bob')).json()).messages;
  assert.equal(messages.filter(row => row.message_id === attempt.message_id).length, 1);
  assert.equal((await draft()).attempt, null);
}

export async function checkMessagingBackgroundGate(page, context, base, seed) {
  const flag = enabled => seed({ action: 'messaging-flag', enabled });
  for (const source of ['unread', 'sync', 'seen']) {
    console.log('Checking disabled background source: ' + source);
    flag(true);
    let deniedRead;
    if (source === 'seen') {
      deniedRead = async route => { flag(false); await route.continue(); };
      await page.route('**/api/private_messages/read', deniedRead);
    }
    await page.goto(base + (source === 'seen' ? '/messages/conversation/bob' : '/'));
    if (source !== 'seen') {
      await page.evaluate(() => window.ForumPrivateMessageHistorySync.refresh());
      flag(false);
      await page.evaluate(source => source === 'unread'
        ? window.ForumPrivateMessageUnread.refresh()
        : window.ForumPrivateMessageHistorySync.refresh(), source);
    }
    await page.locator('[data-messaging-disabled-notice]').waitFor();
    await page.waitForFunction(() => window.ForumPrivateMessageHistorySync.state().state === 'disabled');
    if (deniedRead) await page.unroute('**/api/private_messages/read', deniedRead);
    const requests = [];
    const count = request => { if (new URL(request.url()).pathname.startsWith('/api/private_messages')) requests.push(request.url()); };
    page.on('request', count);
    await page.evaluate(async () => {
      window.dispatchEvent(new Event('focus'));
      window.dispatchEvent(new Event('scroll'));
      document.dispatchEvent(new Event('visibilitychange'));
      document.dispatchEvent(new CustomEvent('private-message-rows-changed'));
      await Promise.all([window.ForumPrivateMessageUnread.refresh(), window.ForumPrivateMessageHistorySync.refresh()]);
      window.ForumPrivateMessageHistorySync.confirm('irrelevant', 'irrelevant');
      await window.ForumPrivateMessageHistorySync.candidates('irrelevant', 'irrelevant');
      await new Promise(resolve => setTimeout(resolve, 100));
    });
    assert.deepEqual(requests, [], source + ' shutdown must stop all follow-up messaging requests');
    page.off('request', count);
    flag(true);
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready');
    assert.equal(await page.locator('[data-messaging-disabled-notice]').count(), 0);
  }
}
