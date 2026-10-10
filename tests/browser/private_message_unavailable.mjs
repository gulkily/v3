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
  const comparison = await page.evaluate(() => {
    const root = document.querySelector('.private-conversation');
    const legacy = document.createElement('div');
    legacy.className = 'private-conversation';
    Object.assign(legacy.style, { position: 'absolute', left: '-10000px', visibility: 'hidden', width: root.getBoundingClientRect().width + 'px' });
    root.querySelectorAll('[data-reader-state="decryption-failed"]').forEach(card => {
      const clone = card.cloneNode(true);
      clone.hidden = false;
      clone.querySelector('details').hidden = true;
      const error = clone.querySelector('[data-role="private-message-reader-error"]');
      error.hidden = false;
      error.textContent = 'This encrypted message could not be decrypted with the saved private key.';
      const retry = clone.querySelector('[data-role="private-message-read-retry"]');
      retry.hidden = false; clone.appendChild(retry);
      legacy.appendChild(clone);
    });
    document.body.appendChild(legacy);
    const legacyHeight = legacy.getBoundingClientRect().height;
    const collapsedHeight = root.querySelector('[data-role="unavailable-group"]').getBoundingClientRect().height;
    legacy.remove();
    return { baseline: 'reconstructed legacy error/retry presentation, identical twenty-message fixture and theme', legacyHeight, collapsedHeight };
  });
  assert.ok(comparison.legacyHeight > comparison.collapsedHeight * 10, 'Collapsed history must materially reduce repeated-error height');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.readState === 'acknowledged');
  await button.focus();
  await button.press('Enter');
  assert.equal(await button.getAttribute('aria-expanded'), 'true');
  assert.equal(await page.locator('[data-reader-state="decryption-failed"]:not([hidden])').count(), 20);
  assert.deepEqual(await page.locator('[data-reader-state="decryption-failed"]').evaluateAll(cards => cards.map(card => card.dataset.privateMessageId)), Array.from({ length: 20 }, (_, i) => 'unavailable-' + String(i).padStart(2, '0')));
  const first = page.locator('[data-private-message-id="unavailable-00"]');
  await first.locator('summary').click();
  await first.getByRole('button', { name: 'Retry reading message' }).waitFor();
  assert.match(await first.innerText(), /This can happen if you started using this device or browser after the message was sent/);
  assert.match(await first.innerText(), /Visit this site on a device that can still read the message, then retry history here/);
  assert.match(await first.innerText(), /If no device has access, a saved copy of the original private key may help/);
  await button.click();
  assert.equal(await button.getAttribute('aria-expanded'), 'false');
  await page.setViewportSize({ width: 375, height: 812 });
  await page.getByRole('button', { name: 'Latest message', exact: true }).click();
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: join(artifacts, 'unavailable-mobile.png') });
  await button.click();
  await first.evaluate(node => window.scrollBy({ top: node.getBoundingClientRect().top - 24, behavior: 'instant' }));
  assert.ok(await first.getByRole('button', { name: 'Retry reading message' }).evaluate(node => node.getBoundingClientRect().bottom < document.querySelector('[data-private-message-composer]').getBoundingClientRect().top));
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: join(artifacts, 'unavailable-details-mobile.png') });
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 2 });
  await page.waitForFunction(() => document.querySelector('.private-conversation').classList.contains('composer-inline'));
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: 1 });
  await cdp.detach();
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
  return comparison;
}

export async function checkUnavailableRecovery(page, base, recoveryKey) {
  await page.goto(base + '/messages/conversation/user-56');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  const groups = page.locator('[data-role="unavailable-group"]');
  await groups.getByRole('button').click();
  const middle = page.locator('[data-private-message-id="unavailable-09"]');
  await middle.locator('summary').click();
  const retry = middle.getByRole('button', { name: 'Retry reading message' });
  await retry.click();
  await page.waitForFunction(() => document.querySelector('[data-private-message-id="unavailable-09"]').dataset.readerState === 'decryption-failed');
  assert.equal(await groups.count(), 1, 'Failed retry rejoins its run');
  assert.equal(await groups.getByRole('button').getAttribute('aria-expanded'), 'true');
  assert.equal(await retry.evaluate(node => document.activeElement === node), true, 'Failed retry retains keyboard focus');
  await page.evaluate(key => { window.originalPrivateKey = localStorage.getItem('forum_pki_private_key'); localStorage.setItem('forum_pki_private_key', key); }, recoveryKey);
  await retry.click();
  await middle.getByText('Unavailable fixture secret', { exact: true }).waitFor();
  assert.equal(await groups.count(), 2, 'Recovered middle message splits the group');
  assert.equal(await middle.evaluate(node => document.activeElement === node), true, 'Recovered message receives focus when retry disappears');
  assert.equal(await middle.getAttribute('hidden'), null);
  await page.evaluate(() => { localStorage.setItem('forum_pki_private_key', window.originalPrivateKey); delete window.originalPrivateKey; });

  let accepted, release;
  const acceptedSend = new Promise(resolve => { accepted = resolve; });
  const heldSend = new Promise(resolve => { release = resolve; });
  const sendRoute = async route => { const response = await route.fetch(); assert.equal(response.status(), 201); accepted(); await heldSend; return route.fulfill({ response }); };
  await page.route('**/api/private_messages', sendRoute);
  await page.locator('textarea').fill('Reply while unavailable groups are open');
  await page.getByRole('button', { name: 'Send private message', exact: true }).click();
  await acceptedSend;
  await page.locator('textarea').fill('Newer group recovery draft');
  release();
  await page.getByText('Reply while unavailable groups are open', { exact: true }).waitFor();
  assert.equal(await page.locator('textarea').inputValue(), 'Newer group recovery draft');
  assert.deepEqual(await groups.getByRole('button').evaluateAll(nodes => nodes.map(node => node.getAttribute('aria-expanded'))), ['true', 'true']);
  await page.unroute('**/api/private_messages', sendRoute);

  // A delayed retry can finish after a history page joins the run.
  await page.goto(base + '/messages/conversation/user-54');
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  await groups.getByRole('button').click();
  const pending = page.locator('[data-private-message-id="unavailable-history-45"]');
  await pending.locator('summary').click();
  let started, unblock, first = true;
  const arrived = new Promise(resolve => { started = resolve; });
  const gate = new Promise(resolve => { unblock = resolve; });
  const delayed = async route => { if (first) { first = false; started(); await gate; } return route.continue(); };
  await page.route('**/api/private_messages/recipient_keys?username_token=user-54', delayed);
  await pending.getByRole('button', { name: 'Retry reading message' }).click();
  await arrived;
  await page.getByRole('button', { name: 'Load older', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('.private-conversation').dataset.historyLoading === '0');
  unblock();
  await page.waitForFunction(() => document.querySelector('[data-private-message-id="unavailable-history-45"]').dataset.readerState === 'decryption-failed');
  assert.equal(await groups.count(), 1);
  assert.match(await groups.innerText(), /50 messages unavailable/);
  assert.equal(await groups.getByRole('button').getAttribute('aria-expanded'), 'true');
  assert.equal(await page.locator('[data-private-message-id]').count(), 50);
  await page.unroute('**/api/private_messages/recipient_keys?username_token=user-54', delayed);
}

export async function checkUnavailableBoundaries(page, base, seed, unavailable, incoming) {
  seed({ action: 'chat', messages: Array.from({ length: 7 }, (_, i) => ({
    id: 'group-boundary-' + i, time: `2046-06-01T12:0${i}:00Z`, sender: i === 2 || i === 3 ? 'alice' : 'user-52',
    recipient: i === 2 || i === 3 ? 'user-52' : 'alice', envelope: i === 4 ? incoming : unavailable,
  })) });
  await page.goto(base + '/messages/conversation/user-52');
  const settled = () => page.waitForFunction(() => document.querySelector('.private-conversation').dataset.privateMessageReaderSettled === '1');
  await settled();
  const groups = page.locator('[data-role="unavailable-group"]');
  assert.equal(await groups.count(), 3, 'Sender changes and readable messages break groups');
  assert.match(await groups.nth(1).innerText(), /2 messages unavailable · You/);
  const readable = page.locator('[data-private-message-id="group-boundary-4"]');
  assert.equal(await readable.getAttribute('hidden'), null);
  assert.match(await readable.innerText(), /Incoming fixture preview/);
  const deny = route => route.abort();
  await page.route('**/api/private_messages/recipient_keys?username_token=user-52', deny);
  await page.reload();
  await settled();
  assert.equal(await groups.count(), 1, 'Loading failures must not enter groups');
  assert.equal(await page.locator('[data-reader-state="load-failed"]:not([hidden])').count(), 5);
  await readable.getByRole('button', { name: 'Retry reading message' }).waitFor();
  await page.unroute('**/api/private_messages/recipient_keys?username_token=user-52', deny);
  await readable.getByRole('button', { name: 'Retry reading message' }).click();
  await readable.getByText('Incoming fixture preview', { exact: true }).waitFor();
  const privateKey = await page.evaluate(() => localStorage.getItem('forum_pki_private_key'));
  await page.evaluate(() => localStorage.setItem('forum_pki_private_key', 'invalid private key'));
  await page.reload();
  await settled();
  assert.equal(await groups.count(), 0, 'An unreadable saved key is an actionable visible failure');
  assert.ok(await page.locator('[data-reader-state="read-failed"]:not([hidden])').count() >= 7);
  await page.evaluate(() => localStorage.removeItem('forum_pki_private_key'));
  // Reload would restore the harness key: exercise the retained cards instead.
  await page.evaluate(async () => {
    const root = document.querySelector('.private-conversation');
    for (const card of root.querySelectorAll('[data-private-message-id]')) await window.ForumPrivateMessageReader.readCard('conversation', card, 'user-52');
  });
  assert.equal(await groups.count(), 3, 'Missing-key placeholders can group, preserving direction changes');
  await page.evaluate(key => localStorage.setItem('forum_pki_private_key', key), privateKey);
  await page.evaluate(async () => {
    await Promise.all(['group-boundary-0', 'group-boundary-1'].map(id => window.ForumPrivateMessageReader.readCard('conversation', document.querySelector(`[data-private-message-id="${id}"]`), 'user-52')));
  });
  assert.equal(await page.evaluate(() => document.documentElement.style.overflowAnchor), '', 'Concurrent retry anchors must restore native scrolling');
}
