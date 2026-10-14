const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/private_message_availability.js', 'utf8');
const events = [], calls = [];
let reply, notice;
const context = {
  window: {}, Error, CustomEvent: class { constructor(type) { this.type = type; } },
  MutationObserver: class { observe() {} disconnect() {} },
  document: {
    querySelectorAll: () => [], querySelector: () => notice || null,
    createElement: () => ({ dataset: {}, setAttribute() {} }),
    body: { prepend(node) { notice = node; } },
    dispatchEvent(event) { events.push(event.type); },
  },
  fetch: async (url, options) => { calls.push([url, options]); return reply(); },
};
vm.runInNewContext(source, context);
const availability = context.window.ForumPrivateMessageAvailability;
(async () => {
  reply = () => new Response(JSON.stringify({ error: 'Approval required' }), { status: 403 });
  assert.equal((await availability.request('/api/private_messages')).status, 403);
  assert.equal(availability.isDisabled(), false, 'Ordinary authorization failures must remain recoverable');
  let finish;
  reply = () => new Promise(resolve => { finish = resolve; });
  const admitted = availability.request('/api/private_messages', { method: 'POST' });
  reply = () => new Response(JSON.stringify({ code: 'messaging_disabled' }), { status: 403 });
  await assert.rejects(availability.request('/api/private_messages/recipient_keys'), error => error.code === 'messaging_disabled');
  assert.equal(availability.isDisabled(), true);
  assert.match(notice.textContent, /Reload after messaging is re-enabled/);
  const count = calls.length;
  await assert.rejects(availability.request('/api/private_messages'), error => error.code === 'messaging_disabled');
  assert.equal(calls.length, count, 'No request after disable detection');
  finish(new Response(JSON.stringify({ status: 'ok' }), { status: 201 }));
  assert.equal((await admitted).status, 201, 'An admitted send can still return a confirmed success');
  assert.deepEqual(events, ['private-messaging-disabled']);
  availability.setEnabled(true);
  assert.equal(availability.isDisabled(), false);
  let oldFinish;
  reply = () => new Promise(resolve => { oldFinish = resolve; });
  const stale = availability.request('/api/private_messages/unread');
  availability.setEnabled(false);
  availability.setEnabled(true);
  oldFinish(new Response(JSON.stringify({ code: 'messaging_disabled' }), { status: 403 }));
  await assert.rejects(stale);
  assert.equal(availability.isDisabled(), false, 'An old disabled response cannot undo a confirmed enable');
  console.log('Messaging availability checks passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
