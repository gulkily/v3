const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function node() {
  return { dataset: {}, hidden: false, disabled: false, textContent: '', listeners: {},
    focus() { this.focused = true; },
    addEventListener(name, fn) { this.listeners[name] = fn; },
    setAttribute(name, value) { this[name] = value; } };
}
function row() {
  const item = node();
  item.nodes = Object.fromEntries(['a', 'counterpart', 'time', 'preview', 'verification', 'retry-preview'].map(key => [key, node()]));
  item.querySelector = selector => item.nodes[selector === 'a' ? 'a' : selector.match(/"([^"]+)"/)[1]];
  item.cloneNode = row;
  return item;
}
function root() {
  const nodes = Object.fromEntries(['rows', 'row-template', 'load-more', 'retry-list', 'restart-list', 'list-status', 'empty'].map(key => [key, node()]));
  nodes.rows.children = [];
  nodes.rows.appendChild = child => nodes.rows.children.push(child);
  nodes.rows.querySelectorAll = () => nodes.rows.children;
  nodes['row-template'].content = { firstElementChild: row() };
  return { dataset: { pageCursor: 'opening' }, nodes,
    querySelector: selector => nodes[selector.match(/"([^"]+)"/)[1]] };
}
const windowListeners = {};
global.window = { addEventListener(name, fn) { windowListeners[name] = fn; }, location: { reload() { this.reloaded = true; } } };
global.document = { addEventListener() {} };
let keysFail = false;
let storageWrites = 0;
window.localStorage = { setItem() { storageWrites++; } };
window.ForumPrivateMessages = { async recipientKeys() { if (keysFail) throw new Error('keys unavailable'); return ['key']; } };
window.ForumPrivateMessageReader = { async decryptEnvelope({ encryptedEnvelope }) {
  if (encryptedEnvelope === 'bad') return { kind: 'bad-signature', message: 'Signature could not be verified.', plaintext: 'DO NOT SHOW' };
  return { kind: 'verified', plaintext: '<script>safe text</script>\n preview' };
} };
vm.runInThisContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/message_time.js'), 'utf8'));
vm.runInThisContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/private_message_list.js'), 'utf8'));
const message = counterpart => ({ counterpart, message_id: counterpart, sender_username_token: counterpart, encrypted_envelope: counterpart, created_at: '2026-10-09T12:00:00Z' });
const reply = (conversations, next_cursor) => ({ ok: true, json: async () => ({ status: 'ok', conversations, next_cursor }) });

(async () => {
  let resolveFirst;
  const urls = [];
  const replies = [new Promise(resolve => { resolveFirst = resolve; }), new Error('Network failed'), reply([message('bob'), message('carol')], 'third'), reply([message('dave')], null)];
  global.fetch = async (url, options) => {
    urls.push(url);
    assert.equal(options.cache, 'no-store');
    const response = replies.shift();
    if (response instanceof Error) throw response;
    return response;
  };
  const page = root();
  const controller = window.ForumPrivateMessageList.bind(page);
  await controller.load();
  assert.equal(urls.length, 1, 'Concurrent loads must be serialized');
  resolveFirst(reply([message('bob')], 'second'));
  await controller.ready;
  await controller.load();
  assert.equal(page.nodes['retry-list'].hidden, false);
  await page.nodes['retry-list'].listeners.click();
  assert.equal(urls[1], urls[2], 'Retry must reuse the failed cursor');
  await controller.load();
  assert.deepEqual(page.nodes.rows.children.map(row => row.dataset.counterpart), ['bob', 'carol', 'dave']);
  assert.equal(page.nodes['load-more'].hidden, true);
  await controller.load();
  assert.equal(urls.length, 4, 'Exhausted lists make no further request');
  assert.equal(page.nodes.empty.hidden, true);
  const verifiedRow = page.nodes.rows.children[0];
  assert.equal(verifiedRow.nodes.preview.textContent, '<script>safe text</script> preview');
  assert.equal(verifiedRow.nodes.verification.hidden, false);
  assert.equal(verifiedRow.nodes.time.dateTime, '2026-10-09T12:00:00Z');
  assert.match(verifiedRow.nodes.time.title, /2026-10-09T12:00:00Z/);
  const mixed = root();
  global.fetch = async () => reply([message('good'), message('bad')], null);
  await window.ForumPrivateMessageList.bind(mixed).ready;
  assert.equal(mixed.nodes.rows.children[0].nodes.verification.hidden, false);
  assert.equal(mixed.nodes.rows.children[1].nodes.verification.hidden, true);
  assert.equal(mixed.nodes.rows.children[1].nodes.preview.textContent, 'Signature could not be verified.');
  const unavailable = root();
  keysFail = true;
  global.fetch = async () => reply([message('retry')], null);
  await window.ForumPrivateMessageList.bind(unavailable).ready;
  const failedRow = unavailable.nodes.rows.children[0];
  assert.equal(failedRow.nodes['retry-preview'].hidden, false);
  assert.equal(failedRow.nodes.a.href, '/messages/conversation/retry');
  keysFail = false;
  await failedRow.nodes['retry-preview'].onclick();
  assert.equal(failedRow.nodes.verification.hidden, false);
  assert.equal(failedRow.nodes['retry-preview'].hidden, true);
  assert.equal(storageWrites, 0);
  process.env.TZ = 'America/New_York';
  const time = node();
  window.ForumMessageTime.render(time, '2026-10-09T01:00:00Z', new Date('2026-10-09T16:00:00Z'));
  assert.equal(time.textContent, 'Yesterday', 'Relative dates must use the viewer time zone');
  const expired = root();
  global.fetch = async () => ({ ok: false, json: async () => ({ status: 'error', error: 'Reload Messages', restart: true }) });
  await window.ForumPrivateMessageList.bind(expired).ready;
  assert.equal(expired.nodes['restart-list'].hidden, false);
  assert.equal(expired.nodes['retry-list'].hidden, true);
  windowListeners.pageshow({ persisted: true });
  assert.equal(window.location.reloaded, true, 'Returning from browser cache must refresh activity');
  const form = node(), field = node(), submit = node(), feedback = node(), action = node(), details = node();
  form.querySelector = selector => selector.includes('username') ? field : submit;
  const recipientRoot = { dataset: { viewer: 'alice' }, querySelector(selector) {
    return { 'recipient-form': form, 'recipient-feedback': feedback, 'new-message': selector.includes('data-action') ? action : details }[selector.match(/"([^"]+)"/)[1]];
  } };
  window.ForumPrivateMessageList.bindRecipient(recipientRoot);
  action.listeners.click();
  assert.equal(details.open, true);
  assert.equal(field.focused, true);
  const event = { preventDefault() {} };
  field.value = ' Alice ';
  await form.listeners.submit(event);
  assert.match(feedback.textContent, /another user/);
  assert.equal(field.value, ' Alice ');
  field.value = 'invalid recipient';
  await form.listeners.submit(event);
  assert.match(feedback.textContent, /valid username/);
  field.value = ' BOB ';
  keysFail = true;
  await form.listeners.submit(event);
  assert.match(feedback.textContent, /keys unavailable/);
  assert.equal(field.value, ' BOB ');
  keysFail = false;
  let redirected;
  window.location.assign = url => { redirected = url; };
  await form.listeners.submit(event);
  assert.equal(redirected, '/messages/conversation/bob');
  assert.equal(submit.disabled, false);
})().catch(error => { console.error(error); process.exitCode = 1; });
