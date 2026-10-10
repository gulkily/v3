import assert from 'node:assert/strict';

export async function checkUnreadApi(context, outsider, base, seed, incoming) {
  const request = context.request;
  const state = async () => {
    const response = await request.get(base + '/api/private_messages/unread?counterparts[]=bob');
    assert.equal(response.status(), 200);
    assert.match(response.headers()['cache-control'], /no-store/);
    const result = await response.json();
    assert.equal(result.viewer, 'alice');
    assert.equal('encrypted_envelope' in result, false);
    return result;
  };
  const read = (token, headers = { 'X-Requested-With': 'ForumPrivateMessages' }) => request.post(base + '/api/private_messages/read', {
    headers, data: { counterpart: 'bob', read_token: token },
  });
  const page = await (await request.get(base + '/api/private_messages/conversation?username_token=bob')).json();
  assert.equal(typeof page.read_token, 'string');
  assert.equal((await read(page.read_token, {})).status(), 403);
  assert.equal((await read(page.read_token, { 'X-Requested-With': 'ForumPrivateMessages', 'Sec-Fetch-Site': 'cross-site' })).status(), 403);
  assert.equal((await read(page.page_cursor)).status(), 400);
  assert.equal((await read(page.read_token + 'x')).status(), 400);
  assert.equal((await outsider.request.post(base + '/api/private_messages/read', { headers: { 'X-Requested-With': 'ForumPrivateMessages' }, data: { counterpart: 'bob', read_token: page.read_token } })).status(), 400);
  seed({ action: 'chat', messages: [{ id: 'unread-api-late', time: '2020-01-01T00:00:00Z', sender: 'bob', envelope: incoming }] });
  assert.equal((await read(page.read_token)).status(), 200);
  assert.equal((await read(page.read_token)).status(), 200);
  assert.equal((await state()).conversations.bob, true);
  const fresh = await (await request.get(base + '/api/private_messages/conversation?username_token=bob')).json();
  const accepted = await read(fresh.read_token);
  assert.match(accepted.headers()['cache-control'], /no-store/);
  assert.equal((await accepted.json()).conversations.bob, false);
  assert.equal((await state()).conversations.bob, false);
  assert.equal((await request.get(base + '/api/private_messages/read')).status(), 405);
  assert.equal((await request.post(base + '/api/private_messages/unread')).status(), 405);
  assert.equal((await request.get(base + '/api/private_messages/unread?counterparts=bad')).status(), 400);
  assert.equal((await request.get(base + '/api/private_messages/unread?counterparts[0][]=bad')).status(), 400);
}
