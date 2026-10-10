import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkChatRecovery(page, context, base, artifacts, seed, envelopes) {
  const field = page.locator('textarea');
  let accepted, release, lost = true, attempt;
  const acceptedPromise = new Promise(resolve => { accepted = resolve; });
  const releasePromise = new Promise(resolve => { release = resolve; });
  const intercept = async route => {
    if (!lost) return route.continue();
    lost = false;
    attempt = route.request().postDataJSON();
    const response = await route.fetch();
    assert.equal(response.status(), 201);
    accepted();
    await releasePromise;
    await route.abort();
  };
  await page.route('**/api/private_messages', intercept);
  await field.fill('Accepted but acknowledgment lost');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await acceptedPromise;
  await field.fill('Browser follow-up');
  release();
  await page.locator('[data-role="private-message-feedback"]').filter({ hasText: 'Delivery is unconfirmed' }).waitFor();
  await page.reload();
  assert.equal(await field.inputValue(), 'Browser follow-up');
  await page.locator('[data-role="private-message-plaintext"]').filter({ hasText: 'Accepted but acknowledgment lost' }).waitFor();
  const count = await page.locator('[data-private-message-id]').count();
  await page.getByRole('button', { name: 'Check previous send', exact: true }).click();
  await page.getByText('Private message sent.', { exact: true }).waitFor();
  assert.equal(await field.inputValue(), 'Browser follow-up');
  assert.equal(await page.locator('[data-private-message-id]').count(), count);
  const rows = (await (await context.request.get(base + '/api/private_messages/conversation?username_token=bob')).json()).messages;
  assert.equal(rows.filter(row => row.message_id === attempt.message_id).length, 1);
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await page.waitForFunction(expected => document.querySelectorAll('[data-private-message-id]').length === expected, count + 1);
  await page.unroute('**/api/private_messages', intercept);

  seed({ action: 'chat', messages: [
    { id: 'chat-valid', time: '2026-10-08T23:50:00Z', sender: 'bob', envelope: envelopes.valid },
    { id: 'chat-long', time: '2026-10-09T04:10:00Z', sender: 'bob', envelope: envelopes.long },
    { id: 'chat-invalid', time: '2026-10-09T04:15:00Z', sender: 'bob', envelope: envelopes.invalid },
    { id: 'chat-unsigned', time: '2026-10-09T04:16:00Z', sender: 'bob', envelope: envelopes.unsigned },
  ] });
  let keysUnavailable = true;
  const keyRoute = route => keysUnavailable ? route.abort() : route.continue();
  await page.route('**/api/private_messages/recipient_keys?username_token=bob', keyRoute);
  await page.reload();
  const valid = page.locator('[data-private-message-id="chat-valid"]');
  await valid.getByRole('button', { name: 'Retry reading message' }).waitFor();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  assert.ok(await page.locator('.is-outgoing [data-role="private-message-verification"]:not([hidden])').count() > 0, 'Neighbors must remain readable');
  keysUnavailable = false;
  await valid.getByRole('button', { name: 'Retry reading message' }).click();
  await valid.getByText('Incoming fixture preview', { exact: true }).waitFor();
  await page.evaluate(async () => {
    window.savedChatPrivateKey = localStorage.getItem('forum_pki_private_key');
    localStorage.removeItem('forum_pki_private_key');
    await window.ForumPrivateMessageReader.readCard('conversation', document.querySelector('[data-private-message-id="chat-valid"]'), 'bob');
  });
  await valid.getByText('This browser has no private key for this message.').waitFor();
  await page.evaluate(() => {
    localStorage.setItem('forum_pki_private_key', window.savedChatPrivateKey);
    delete window.savedChatPrivateKey;
  });
  await valid.getByRole('button', { name: 'Retry reading message' }).click();
  await valid.getByText('Incoming fixture preview', { exact: true }).waitFor();
  for (const id of ['chat-long', 'chat-invalid', 'chat-unsigned']) {
    await page.locator(`[data-private-message-id="${id}"]`).getByRole('button', { name: 'Retry reading message' }).click();
  }
  await page.locator('[data-private-message-id="chat-long"] [data-role="private-message-plaintext"]').waitFor();
  await page.locator('[data-private-message-id="chat-invalid"]').getByText('The sender signature could not be verified.').waitFor();
  await page.locator('[data-private-message-id="chat-unsigned"]').getByText('This message has no verifiable sender signature.').waitFor();
  assert.equal(await page.getByText('Unverified chat secret', { exact: true }).count(), 0);
  assert.ok(await page.locator('[data-role="message-date"]').count() >= 2);
  assert.ok(await valid.locator('time').getAttribute('title'));
  await page.setViewportSize({ width: 375, height: 812 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Long messages must wrap');
  await valid.scrollIntoViewIfNeeded();
  await page.screenshot({ path: join(artifacts, 'chat-recovery-mobile.png'), fullPage: true });
  await page.unroute('**/api/private_messages/recipient_keys?username_token=bob', keyRoute);

  // Initial scrolling must wait for decryption, and user navigation cancels it.
  let unblock, requested;
  const requestArrived = new Promise(resolve => { requested = resolve; });
  const blocked = new Promise(resolve => { unblock = resolve; });
  const delay = async route => { requested(); await blocked; return route.continue(); };
  await page.route('**/api/private_messages/conversation?*', delay);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await requestArrived;
  await page.evaluate(() => {
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home' }));
    window.scrollTo(0, 0);
  });
  unblock();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  assert.equal(await page.evaluate(() => scrollY), 0, 'Delayed decryption must respect early navigation');
  assert.equal(await page.evaluate(() => document.activeElement === document.querySelector('textarea')), false);
  await page.unroute('**/api/private_messages/conversation?*', delay);
  await page.reload();
  await page.waitForFunction(() => document.querySelector('[data-mailbox="conversation"]').dataset.privateMessageReaderSettled === '1');
  assert.ok(await page.evaluate(() => scrollY) > 0, 'Untouched initial chat should show newest message');
  await page.setViewportSize({ width: 1100, height: 800 });
}
