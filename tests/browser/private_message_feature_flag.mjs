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
  await send.click();
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
  await page.getByText('Private message sent.', { exact: true }).waitFor();

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
  await page.locator('[data-role="private-message-feedback"]').filter({ hasText: 'Delivery is unconfirmed' }).waitFor();
  await page.evaluate(() => window.ForumPrivateMessages.recipientKeys('bob').catch(() => {}));
  await page.locator('[data-messaging-disabled-notice]').waitFor();
  assert.equal((await draft()).attempt.messageId, attempt.message_id);
  assert.equal((await draft()).plaintext, 'Newer unsent draft');
  await page.unroute('**/api/private_messages', intercept);
  flag(true);
  await page.reload();
  assert.equal(await field.inputValue(), 'Newer unsent draft');
  await page.getByRole('button', { name: 'Check previous send', exact: true }).click();
  await page.getByText('Private message sent.', { exact: true }).waitFor();
  assert.equal(await field.inputValue(), 'Newer unsent draft');
  const messages = (await (await context.request.get(base + '/api/private_messages/conversation?username_token=bob')).json()).messages;
  assert.equal(messages.filter(row => row.message_id === attempt.message_id).length, 1);
  assert.equal((await draft()).attempt, null);
}
