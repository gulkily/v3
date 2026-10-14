import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { join } from 'node:path';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

const key = 'FORUM_PRIVATE_MESSAGES_ENABLED';

async function checkOverride(context, root, project, value) {
  const socket = createServer().listen(0, '127.0.0.1');
  await once(socket, 'listening');
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`,
    join(project, 'tests/Support/private_message_browser_router.php')], {
    env: { ...process.env, [key]: String(value), PRIVATE_MESSAGE_TEST_ROOT: root,
      FORUM_SECRETS_PATH: join(root, 'unused.php'), PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'),
      VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') }, stdio: ['ignore', 'ignore', 'ignore'],
  });
  const stopped = once(server, 'exit');
  const page = await context.newPage();
  try {
    for (let i = 0; i < 50; i++) {
      try { await fetch(base + '/api/version'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    await page.goto(base + '/tools/feature-flags/');
    const row = page.locator(`[data-flag-key="${key}"]`);
    assert.equal(await row.getByRole('switch').isDisabled(), true);
    assert.equal(await row.getByRole('switch').getAttribute('aria-checked'), String(value));
    assert.match(await row.innerText(), /locked/i);
    const result = await context.request.get(base + '/api/private_messages/inbox');
    assert.equal(result.status(), value ? 200 : 403);
    const forbidden = await context.request.post(base + '/api/prepare_feature_flag_change', { data: { key, value: String(!value) } });
    assert.equal(forbidden.status(), 400, 'Deployment lock must also reject direct operator writes');
  } finally {
    await page.close();
    server.kill();
    await stopped;
  }
}

export async function checkOperatorMessagingFlag(operator, member, base, root, project) {
  const page = await operator.newPage();
  await page.goto(base + '/tools/feature-flags/');
  const row = page.locator(`[data-flag-key="${key}"]`);
  assert.equal(await row.getByRole('switch').getAttribute('aria-checked'), 'true');
  const denied = await member.request.post(base + '/api/prepare_feature_flag_change', { data: { key, value: 'false' } });
  assert.equal(denied.status(), 403, 'An ordinary approved member cannot change the flag');
  async function toggle(value) {
    const finalized = page.waitForResponse(response => response.url().endsWith('/api/finalize_feature_flag_change'));
    await row.getByRole('switch').click();
    const response = await finalized;
    const result = await response.json();
    assert.equal(response.status(), 200, JSON.stringify(result));
    assert.equal(result.status, 'ok');
    assert.equal(result.effective_value, String(value));
    await page.waitForFunction(({ key, value }) => document.querySelector(`[data-flag-key="${key}"] [role="switch"]`).getAttribute('aria-checked') === String(value), { key, value });
    assert.equal((await readFile(join(root, 'repository', result.record_path + '.asc'), 'utf8')).includes('PGP SIGNATURE'), true);
  }
  const navigation = await page.request.get(base + '/api/feature_flags/navigation');
  assert.match(navigation.headers()['cache-control'], /no-store/);
  assert.equal((await navigation.json()).messaging_enabled, true, 'Navigation resolves the authenticated session');
  await toggle(false);
  await page.waitForFunction(() => !document.querySelector('[data-private-message-unread]'));
  // Finish admitted work and let this stale settings page observe the disabled response.
  await page.evaluate(() => window.ForumPrivateMessageHistorySync.refresh());
  const digest = async name => createHash('sha256').update(await readFile(join(root, 'state/private', name))).digest('hex');
  const retained = await Promise.all(['messages.sqlite3', 'message_history_sync.sqlite3'].map(digest));
  for (const path of ['/messages', '/messages/inbox', '/messages/sent', '/messages/conversation/bob',
    '/api/private_messages', '/api/private_messages/inbox', '/api/private_messages/sent',
    '/api/private_messages/conversations', '/api/private_messages/conversation?username_token=bob',
    '/api/private_messages/recipient_keys?username_token=bob', '/api/private_messages/unread', '/api/private_messages/read',
    '/api/private_messages/history_sync/work', '/api/private_messages/history_sync/transfers', '/api/private_messages/history_sync/acknowledge']) {
    for (const method of ['get', 'post']) {
      const response = await member.request[method](base + path);
      assert.equal(response.status(), 403, method + ' ' + path);
      assert.match(response.headers()['cache-control'], /no-store/);
      if (path.startsWith('/api/')) assert.equal((await response.json()).code, 'messaging_disabled');
    }
  }
  assert.deepEqual(await Promise.all(['messages.sqlite3', 'message_history_sync.sqlite3'].map(digest)), retained);
  const fresh = await member.newPage();
  let activity = 0;
  fresh.on('request', request => { if (request.url().includes('/api/private_messages')) activity++; });
  await fresh.goto(base + '/');
  assert.equal(await fresh.locator('[data-private-message-unread]').count(), 0);
  assert.equal(await fresh.locator('script[src*="private_message"]').count(), 0);
  assert.equal(activity, 0);
  await fresh.close();
  await checkOverride(operator, root, project, true); // Overrides the saved false.
  await toggle(true);
  await checkOverride(operator, root, project, false); // Overrides the saved true.
  await page.waitForFunction(() => document.querySelector('[data-private-message-unread]')?.dataset.unreadState === 'ready');
  assert.equal(await page.locator('[data-private-message-unread]').count(), 1);
  assert.equal(await page.locator('[data-private-message-unread]').isVisible(), true);
  assert.equal(await page.locator('[data-messaging-disabled-notice]').count(), 0);
  await page.evaluate(() => window.ForumPrivateMessageHistorySync.refresh());
  assert.notEqual(await page.evaluate(() => window.ForumPrivateMessageHistorySync.state().state), 'disabled');
  assert.equal((await member.request.get(base + '/api/private_messages/conversations')).status(), 200);
  await page.close();
  console.log('Signed messaging toggle, retained stores, fresh pages, permissions and both deployment overrides passed.');
}
