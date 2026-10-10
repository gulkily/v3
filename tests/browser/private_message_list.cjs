const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function node() {
  return { dataset: {}, hidden: false, disabled: false, textContent: '', listeners: {},
    addEventListener(name, fn) { this.listeners[name] = fn; },
    setAttribute(name, value) { this[name] = value; } };
}
function row() {
  const item = node();
  item.nodes = Object.fromEntries(['a', 'counterpart', 'time', 'preview'].map(key => [key, node()]));
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
vm.runInThisContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/private_message_list.js'), 'utf8'));
const message = counterpart => ({ counterpart, message_id: counterpart, created_at: '2026-10-09T12:00:00Z' });
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
  const expired = root();
  global.fetch = async () => ({ ok: false, json: async () => ({ status: 'error', error: 'Reload Messages', restart: true }) });
  await window.ForumPrivateMessageList.bind(expired).ready;
  assert.equal(expired.nodes['restart-list'].hidden, false);
  assert.equal(expired.nodes['retry-list'].hidden, true);
  windowListeners.pageshow({ persisted: true });
  assert.equal(window.location.reloaded, true, 'Returning from browser cache must refresh activity');
})().catch(error => { console.error(error); process.exitCode = 1; });
