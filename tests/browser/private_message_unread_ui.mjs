import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkUnreadIndicators(page, context, base, artifacts) {
  await page.getByRole('link', { name: 'Messages', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-role="list-status"]').textContent.includes('25 conversations'));
  while (await page.getByRole('button', { name: 'Load more', exact: true }).isVisible()) {
    await page.getByRole('button', { name: 'Load more', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('[data-role="rows"]').getAttribute('aria-busy') === 'false');
  }
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  const nav = page.locator('[data-private-message-unread]');
  assert.equal(await nav.getAttribute('data-unread-state'), 'ready');
  const names = await page.locator('[data-conversation-row]').evaluateAll(rows => rows.map(row => row.dataset.counterpart));
  assert.ok(names.length > 25);
  for (let index = 0; index < names.length; index += 25) {
    const query = new URLSearchParams();
    names.slice(index, index + 25).forEach(name => query.append('counterparts[]', name));
    const state = await (await context.request.get(base + '/api/private_messages/unread?' + query)).json();
    assert.equal(Number(await nav.getAttribute('data-unread-count')), state.unread_count);
    for (const [name, unread] of Object.entries(state.conversations)) {
      assert.equal(await page.locator(`[data-conversation-row][data-counterpart="${name}"] [data-role="unread-indicator"]`).innerText(), unread ? 'Unread' : '');
    }
  }
  const failure = route => route.abort();
  await page.route('**/api/private_messages/unread?*', failure);
  await page.evaluate(() => window.ForumPrivateMessageUnread.refresh());
  assert.equal(await nav.getAttribute('data-unread-state'), 'unavailable');
  assert.equal(await nav.locator('[data-role="unread-count"]').innerText(), '?');
  await page.unroute('**/api/private_messages/unread?*', failure);
  await page.getByRole('button', { name: 'Retry unread status', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready');
  await page.setViewportSize({ width: 375, height: 812 });
  await page.screenshot({ path: join(artifacts, 'unread-mobile.png') });
  const overflow = await page.evaluate(() => Array.from(document.querySelectorAll('body *')).filter(node => node.getBoundingClientRect().right > innerWidth + 1).slice(0, 8).map(node => ({ tag: node.tagName, class: node.className, text: node.textContent.slice(0, 80), width: node.getBoundingClientRect().width })));
  const dimensions = await page.evaluate(() => ({ width: innerWidth, content: document.documentElement.scrollWidth, x: scrollX }));
  const wide = await page.evaluate(() => Array.from(document.querySelectorAll('body *')).filter(node => node.scrollWidth > node.clientWidth + 2).slice(0, 12).map(node => ({ tag: node.tagName, class: node.className, role: node.dataset.role, width: node.clientWidth, content: node.scrollWidth })));
  assert.ok(dimensions.content <= dimensions.width, JSON.stringify({ dimensions, overflow, wide }));
  await nav.focus();
  assert.equal(await nav.evaluate(node => node === document.activeElement), true);
  await page.screenshot({ path: join(artifacts, 'unread-mobile.png') });
  await page.goto(base + '/user/bob');
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]').dataset.unreadState === 'ready');
  assert.match(await nav.getAttribute('title'), /unread conversation/);
  await page.setViewportSize({ width: 1100, height: 800 });
}
