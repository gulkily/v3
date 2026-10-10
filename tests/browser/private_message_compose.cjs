const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2], 'utf8');
const values = new Map([['forum_pki_public_key', 'alice-key'], ['forum_pki_username', 'alice']]);
let sequence = 0, preparations = 0, request;
const calls = [];
const event = { preventDefault() {} };
function composer(sender = 'alice', brokenStorage = false) {
  const input = {}, events = {}, retries = {};
  const field = { value: '', addEventListener(n, f) { input[n] = f; } };
  const button = {}, feedback = {}, retry = { addEventListener(n, f) { retries[n] = f; } };
  const form = { querySelector(s) { return s === '[name="plaintext"]' ? field : button; }, addEventListener(n, f) { events[n] = f; } };
  const root = { dataset: { senderUsernameToken: sender, recipientUsernameToken: 'bob' }, querySelector(s) {
    return s === '[data-private-message-form]' ? form : s.includes('send-retry') ? retry : feedback;
  } };
  const window = { crypto: { randomUUID: () => String(++sequence) },
    localStorage: { getItem: k => values.get(k), setItem(k, v) { if (brokenStorage) throw Error('quota'); values.set(k, v); }, removeItem(k) { if (brokenStorage) throw Error('quota'); values.delete(k); } },
    __forumBrowserIdentity: { async ensureActionIdentity() {} },
    ForumPrivateMessages: { async prepareEnvelope() { return { encryptedEnvelope: `cipher-${++preparations}` }; } } };
  const context = { window, document: { addEventListener() {} }, fetch: async (url, options) => {
    calls.push(JSON.parse(options.body));
    return request();
  } };
  vm.runInNewContext(source, context);
  window.ForumPrivateMessageComposer.bind(root);
  return { field, feedback, retry, type(text) { field.value = text; input.input(); }, send: () => events.submit(event), check: () => retries.click(event) };
}
async function main() {
  let finish;
  request = () => new Promise(resolve => { finish = resolve; });
  const first = composer();
  first.type('first message');
  const sending = first.send();
  while (!finish) await new Promise(resolve => setImmediate(resolve));
  first.type('newer draft');
  finish({ ok: false, async json() { return { status: 'error' }; } });
  await sending;
  const saved = JSON.parse(values.get('forum_private_message_draft:alice:bob'));
  assert.equal(saved.plaintext, 'newer draft');
  assert.equal(saved.attempt.encryptedEnvelope, 'cipher-1');
  assert.equal(JSON.stringify(saved.attempt).includes('first message'), false);
  const reload = composer();
  assert.equal(reload.field.value, 'newer draft');
  await reload.send();
  assert.equal(calls.length, 1, 'edited draft must not replace unresolved attempt');
  request = async () => ({ ok: true, async json() { return { status: 'ok', message: { message_id: calls.at(-1).message_id, sender_username_token: 'alice', recipient_username_token: 'bob', created_at: '2026-10-09T12:00:00Z' } }; } });
  await reload.check();
  assert.deepEqual(calls[1], calls[0]);
  assert.equal(preparations, 1);
  assert.equal(reload.field.value, 'newer draft');
  await reload.send();
  assert.notEqual(calls[2].message_id, calls[0].message_id);
  assert.equal(reload.field.value, '');
  assert.equal(values.has('forum_private_message_draft:alice:bob'), false);
  const other = composer('mallory');
  other.type('other account');
  assert.equal(composer().field.value, '');
  values.set('forum_private_message_draft:bob', JSON.stringify({ plaintext: 'legacy draft' }));
  const legacy = composer();
  assert.equal(legacy.field.value, 'legacy draft');
  legacy.type('migrated draft');
  assert.equal(values.has('forum_private_message_draft:bob'), false);
  values.set('forum_pki_public_key', 'different-key');
  await legacy.send();
  assert.match(legacy.feedback.textContent, /identity changed/);
  const unavailable = composer('alice', true);
  unavailable.type('unsaved');
  assert.match(unavailable.feedback.textContent, /recovery is unavailable/);
  await unavailable.send();
  assert.match(unavailable.feedback.textContent, /cannot be saved/);
  const malformed = composer('alice', true);
  malformed.type('retain on malformed acknowledgment');
  request = async () => ({ ok: true, async json() { return { status: 'ok', message: { message_id: 'wrong' } }; } });
  await malformed.send();
  assert.equal(malformed.field.value, 'retain on malformed acknowledgment');
  assert.match(malformed.feedback.textContent, /did not match/);
  process.stdout.write('draft recovery checks passed\n');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
