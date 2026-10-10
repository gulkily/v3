import assert from 'node:assert/strict';

export async function checkUnavailableSeen(context, base, seed, unavailable) {
  seed({ action: 'chat', messages: Array.from({ length: 30 }, (_, i) => ({
    id: 'group-seen-' + String(i).padStart(2, '0'), time: `2045-06-01T12:${String(i).padStart(2, '0')}:00Z`, sender: 'user-53', envelope: unavailable,
  })) });
  const unread = async () => (await (await context.request.get(base + '/api/private_messages/unread?counterparts[]=user-53')).json()).conversations['user-53'];
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(() => {
    window.groupHidden = true; window.groupFocused = false;
    Object.defineProperty(document, 'hidden', { get: () => window.groupHidden });
    document.hasFocus = () => window.groupFocused;
  });
  await page.goto(base + '/messages/conversation/user-53');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  const group = page.locator('[data-role="unavailable-group"]');
  assert.match(await group.innerText(), /25 messages unavailable/);
  await page.waitForTimeout(100);
  assert.equal(await unread(), true, 'Hidden summary cannot acknowledge');
  await page.evaluate(() => { window.groupHidden = false; document.dispatchEvent(new Event('visibilitychange')); });
  await page.waitForTimeout(100);
  assert.equal(await unread(), true, 'Unfocused summary cannot acknowledge');
  await group.getByRole('button').click();
  const latest = page.locator('[data-private-message-id="group-seen-29"]');
  await latest.locator('summary').click();
  let unblockRead, readStarted;
  const reading = new Promise(resolve => { readStarted = resolve; });
  const readGate = new Promise(resolve => { unblockRead = resolve; });
  const holdRead = async route => { readStarted(); await readGate; return route.continue(); };
  await page.route('**/api/private_messages/recipient_keys?username_token=user-53', holdRead);
  await latest.getByRole('button', { name: 'Retry reading message' }).click();
  await reading;
  await page.evaluate(() => { window.groupFocused = true; window.dispatchEvent(new Event('focus')); });
  await page.waitForTimeout(100);
  assert.equal(await unread(), true, 'A pending latest read must settle before acknowledgment');

  let releaseAck, accepted;
  const acknowledgment = new Promise(resolve => { accepted = resolve; });
  const ackGate = new Promise(resolve => { releaseAck = resolve; });
  const receipts = [];
  const uncertain = async route => {
    receipts.push(route.request().postDataJSON().read_token);
    if (receipts.length > 1) return route.continue();
    const response = await route.fetch();
    assert.equal(response.status(), 200);
    accepted(); await ackGate; return route.abort();
  };
  await page.route('**/api/private_messages/read', uncertain);
  unblockRead();
  await acknowledgment;
  assert.equal(await unread(), false, 'Settled visible representation acknowledges its opened boundary');
  seed({ action: 'chat', messages: [{ id: 'group-seen-later', time: '2020-01-01T00:00:00Z', sender: 'user-53', envelope: unavailable }] });
  releaseAck();
  await page.getByRole('button', { name: 'Retry seen status' }).click();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.readState === 'acknowledged');
  assert.equal(receipts.length, 2);
  assert.equal(receipts[0], receipts[1], 'Uncertain retry retains the exact boundary');
  assert.equal(await unread(), true, 'Later backdated arrival survives retry');
  await page.unroute('**/api/private_messages/recipient_keys?username_token=user-53', holdRead);
  await group.getByRole('button').click();
  await group.getByRole('button').click();
  await page.locator('textarea').fill('Grouped seen own reply');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await page.getByText('Grouped seen own reply', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Load older', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.historyLoading === '0');
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.equal(await unread(), true);
  assert.equal(receipts.length, 2, 'Expansion, old history, and own send cannot create a broader receipt');

  await page.evaluate(() => {
    window.groupPublicKey = localStorage.getItem('forum_pki_public_key');
    localStorage.setItem('forum_pki_public_key', 'changed identity');
    window.dispatchEvent(new StorageEvent('storage', { key: 'forum_pki_public_key' }));
  });
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'unavailable');
  assert.equal(receipts.length, 2);
  await page.evaluate(() => {
    localStorage.setItem('forum_pki_public_key', window.groupPublicKey);
    window.dispatchEvent(new StorageEvent('storage', { key: 'forum_pki_public_key' }));
  });
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready');
  assert.equal(await unread(), true);
  await page.unroute('**/api/private_messages/read', uncertain);
  assert.deepEqual(errors, []);
  await page.close();
}
