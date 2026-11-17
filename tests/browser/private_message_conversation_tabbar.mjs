import assert from 'node:assert/strict';

// DM conversation tab bar: on phones a conversation has no bottom tab bar; the back arrow leads to Messages, which keeps it.
export async function checkConversationTabBar(page, base) {
  const counterpart = await page.locator('.private-conversation').getAttribute('data-counterpart-username-token');
  const original = page.viewportSize();
  await page.setViewportSize({ width: 375, height: 667 });
  const barVisible = () => page.evaluate(() => {
    const bar = document.querySelector('.site-header--app .site-header-actions');
    if (!bar) return false;
    const box = bar.getBoundingClientRect();
    return getComputedStyle(bar).display !== 'none' && box.height > 0 && box.top < innerHeight;
  });

  await page.goto(base + '/messages/conversation/' + counterpart);
  assert.equal(await page.locator('.shell').getAttribute('data-tabbar-page'), 'none');
  assert.equal(await barVisible(), false, 'no tab bar inside a conversation');
  // The composer sits at the bottom edge, not where the bar was.
  const composerBottom = () => page.locator('[data-private-message-composer]').evaluate(node => Math.round(innerHeight - node.getBoundingClientRect().bottom));
  // The conversation is one window (card) that reaches the bottom edge, with the composer inside it.
  assert.ok(await page.locator('.private-conversation').evaluate(node => Math.round(innerHeight - node.getBoundingClientRect().bottom)) <= 1, 'conversation reaches the bottom edge');
  assert.ok(Math.abs(await composerBottom()) <= 1, 'composer sits at the bottom edge, inside the conversation');
  // Scrolling and typing never bring it back. A spacer makes the conversation long enough to scroll.
  await page.evaluate(() => {
    const spacer = document.createElement('div');
    spacer.dataset.testSpacer = '';
    spacer.style.height = '1500px';
    document.querySelector('[data-role="private-message-transcript"]').append(spacer);
  });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(150);
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  await page.waitForTimeout(150);
  assert.equal(await barVisible(), false, 'scrolling keeps the bar away');
  // At the end of a long conversation the composer stays flush; the card's padding doesn't lift it.
  assert.ok(Math.abs(await composerBottom()) <= 1, 'composer stays flush at the end of scrolling');
  await page.evaluate(() => document.querySelector('[data-test-spacer]').remove());
  await page.locator('[data-private-message-composer] textarea').focus();
  await page.waitForTimeout(150);
  assert.equal(await barVisible(), false, 'typing keeps the bar away');
  await page.locator('[data-private-message-composer] textarea').blur();

  // The back arrow returns to Messages, where the bar is back.
  await page.getByRole('link', { name: 'Back to Messages', exact: true }).click();
  await page.waitForURL(url => new URL(url).pathname.replace(/\/$/, '') === '/messages');
  assert.equal(await page.locator('.shell').getAttribute('data-tabbar-page'), null);
  assert.equal(await barVisible(), true, 'Messages keeps the tab bar');

  // Desktop width: the header navigation is unchanged on a conversation.
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto(base + '/messages/conversation/' + counterpart);
  assert.ok(await page.locator('.site-header--app .site-header-actions').isVisible(), 'desktop keeps the header navigation');
  if (original) await page.setViewportSize(original);
}
