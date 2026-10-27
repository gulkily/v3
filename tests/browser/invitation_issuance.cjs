const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { webcrypto } = require('node:crypto');
const assets = path.resolve(__dirname, '../../public/assets');

function invitationPage() {
  const node = (extra = {}) => ({ hidden: false, value: '', dataset: {}, listeners: {},
    addEventListener(name, fn) { this.listeners[name] = fn; },
    setAttribute() {}, focus() {}, select() {}, ...extra });
  const feedback = node({ hidden: true });
  const result = node({ hidden: true });
  const link = node();
  const copy = node();
  const submit = node({ disabled: false });
  const qr = node({ hidden: true });
  const rectangles = [];
  const context = { fillRect(...args) { rectangles.push([this.fillStyle, ...args]); } };
  const canvas = node({ getContext() { return context; } });
  const form = node({ elements: { include_destination: node({ checked: false }), destination: node() },
    querySelector(selector) { return selector === 'button[type="submit"]' ? submit : null; } });
  const nodes = { '[data-invitation-issue-form]': form, '[data-role=invitation-feedback]': feedback,
    '[data-role=invitation-result]': result, '[data-role=invitation-link]': link,
    '[data-action=copy-invitation-link]': copy, '[data-role=invitation-qr]': qr,
    '[data-role=invitation-qr-code]': canvas };
  const root = { querySelector(selector) { return nodes[selector] || null; } };
  const document = node({ querySelector() { return root; } });
  const calls = [], payloads = [];
  const state = { failure: '', pause: null, copied: '' };
  let themeChanged;
  const window = { location: { origin: 'https://forum.test' },
    MutationObserver: class { constructor(callback) { themeChanged = callback; } observe() {} },
    ForumBrowserSigning: {
      async ensureActionIdentity() { if (state.pause) await state.pause; },
      async signCanonicalRecord() { if (state.failure === 'sign') throw Error('sign failed'); return 'signature'; }
    } };
  const sandbox = { window, document, crypto: webcrypto, TextEncoder, URLSearchParams,
    navigator: { clipboard: { async writeText(value) { state.copied = value; } } },
    fetch: async (endpoint, options) => {
      calls.push({ endpoint, values: new URLSearchParams(options.body) });
      const failing = (state.failure === 'prepare' && endpoint === '/api/prepare_invitation')
        || (state.failure === 'finalize' && endpoint === '/api/create_prepared_invitation');
      return { json: async () => failing ? { status: 'error', error: 'issuance failed' }
        : { status: 'ok', canonical_record: 'Author-Identity-ID: openpgp:test', prepare_token: 'prepared', post_id: 'post', record_path: 'path' } };
    }
  };
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(path.join(assets, 'qrcode-generator.js'), 'utf8'), sandbox);
  window.qrcode = (...args) => {
    const code = sandbox.qrcode(...args), add = code.addData;
    code.addData = value => { payloads.push(value); add(value); };
    return code;
  };
  vm.runInContext(fs.readFileSync(path.join(assets, 'invite_issuance.js'), 'utf8'), sandbox);
  document.listeners.DOMContentLoaded();
  return { state, window, form, submit, feedback, result, link, copy, qr, canvas, calls, payloads, rectangles,
    themeChanged: () => themeChanged(), stylesheetLoaded: () => document.listeners.load({target:{tagName:'LINK',rel:'stylesheet'}}),
    issue: () => form.listeners.submit({ preventDefault() {} }) };
}

async function main() {
  const page = invitationPage();
  assert.equal(page.qr.hidden, true);
  await page.issue();
  assert.equal(page.result.hidden, false);
  assert.equal(page.qr.hidden, false);
  assert.match(page.link.value, /^https:\/\/forum\.test\/lobby\/#invite=[a-f0-9]{32}$/);
  assert.deepEqual(page.payloads, [page.link.value]);
  assert.equal(page.canvas.width, page.canvas.height);
  assert(page.rectangles.length > 100);
  await page.copy.listeners.click();
  assert.equal(page.state.copied, page.link.value);

  for (const [foreground, background, expected, forced] of [
    ['rgb(39, 50, 32)', 'rgb(223, 232, 188)', ['rgb(39, 50, 32)', 'rgb(223, 232, 188)']],
    ['rgb(217, 231, 255)', 'rgb(15, 23, 41)', ['rgb(15, 23, 41)', 'rgb(217, 231, 255)']],
    ['rgb(100, 100, 100)', 'rgb(130, 130, 130)', ['#000', '#fff']],
    ['invalid', 'rgb(255, 255, 255)', ['#000', '#fff']],
    ['rgba(0, 0, 0, 0.5)', 'rgb(255, 255, 255)', ['#000', '#fff']],
    ['rgb(0, 0, 0)', 'rgba(255, 255, 255, 0)', ['#000', '#fff']],
    ['rgb(39, 50, 32)', 'rgb(223, 232, 188)', ['#000', '#fff'], true]
  ]) {
    const themed = invitationPage();
    themed.window.getComputedStyle = () => ({ color: foreground, backgroundColor: background });
    themed.window.matchMedia = () => ({ matches: Boolean(forced) });
    await themed.issue();
    assert.equal(themed.rectangles[0][0], expected[1]);
    assert.equal(themed.rectangles[1][0], expected[0]);
    assert.equal(themed.payloads.at(-1), themed.link.value);
    assert.equal(themed.rectangles[0][3], themed.canvas.width);
    assert(themed.rectangles.slice(1).every(rect => rect[1] >= 16 && rect[2] >= 16
      && rect[1] + rect[3] <= themed.canvas.width - 16 && rect[2] + rect[4] <= themed.canvas.height - 16));
  }

  for (const failure of ['missing', 'throws', 'canvas']) {
    const failed = invitationPage();
    if (failure === 'missing') delete failed.window.qrcode;
    if (failure === 'throws') failed.window.qrcode = () => { throw Error('encoder failed'); };
    if (failure === 'canvas') failed.canvas.getContext = () => null;
    await failed.issue();
    assert.equal(failed.result.hidden, false);
    assert.equal(failed.qr.hidden, true);
    assert.match(failed.feedback.textContent, /Invitation created\. QR code unavailable/);
    await failed.copy.listeners.click();
    assert.equal(failed.state.copied, failed.link.value);
    assert.equal(failed.calls.length, 2);
    assert.equal(failed.submit.disabled, false);
  }

  const firstLink = page.link.value;
  page.window.getComputedStyle = () => ({ color: 'rgb(39, 50, 32)', backgroundColor: 'rgb(223, 232, 188)' });
  page.themeChanged();
  assert.equal(page.rectangles.at(-1)[0], 'rgb(39, 50, 32)');
  page.stylesheetLoaded();
  assert.equal(page.payloads.at(-1), firstLink);
  assert.equal(page.calls.length, 2);
  await page.issue();
  assert.notEqual(page.link.value, firstLink);
  assert.equal(page.payloads.at(-1), page.link.value);
  assert.equal(page.submit.disabled, false);

  for (const failure of ['prepare', 'sign', 'finalize']) {
    const retry = invitationPage();
    await retry.issue();
    retry.state.failure = failure;
    await retry.issue();
    assert.equal(retry.result.hidden, true, failure);
    assert.equal(retry.link.value, '', failure);
    assert.equal(retry.qr.hidden, true, failure);
    assert.equal(retry.canvas.width, 0, failure);
    const rendered = retry.payloads.length;
    retry.themeChanged(); retry.stylesheetLoaded();
    assert.equal(retry.payloads.length, rendered);
    assert.equal(retry.feedback.dataset.status, 'error', failure);
    assert.equal(retry.submit.disabled, false, failure);
    retry.state.failure = '';
    await retry.issue();
    assert.equal(retry.result.hidden, false, failure);
    assert.equal(retry.qr.hidden, false, failure);
    assert.equal(retry.payloads.at(-1), retry.link.value, failure);
  }

  const delayed = invitationPage();
  await delayed.issue();
  let resume;
  delayed.state.pause = new Promise(resolve => { resume = resolve; });
  const pending = delayed.issue();
  assert.equal(delayed.result.hidden, true);
  assert.equal(delayed.qr.hidden, true);
  assert.equal(delayed.link.value, '');
  assert.equal(delayed.submit.disabled, true);
  delayed.themeChanged(); delayed.stylesheetLoaded();
  assert.equal(delayed.payloads.length, 1);
  await delayed.issue(); // A second submit event must be ignored even if dispatched programmatically.
  assert.equal(delayed.calls.length, 2);
  resume();
  await pending;
  assert.equal(delayed.calls.length, 4);
  assert.equal(delayed.payloads.length, 2);
  assert.equal(delayed.payloads.at(-1), delayed.link.value);
  assert.equal(delayed.submit.disabled, false);

  delete delayed.window.qrcode;
  delayed.themeChanged();
  assert.equal(delayed.qr.hidden, true);
  assert.equal(delayed.result.hidden, false);
  assert.match(delayed.feedback.textContent, /QR code unavailable/);
  await delayed.issue();
  assert.equal(delayed.qr.hidden, true);
  assert.equal(delayed.canvas.width, 0);
  assert.equal(delayed.result.hidden, false);
  await delayed.copy.listeners.click();
  assert.equal(delayed.state.copied, delayed.link.value);
  console.log('Invitation QR, copy fallback, repeated generation, failures, and concurrent-submit checks passed.');
}

if (require.main === module) main().catch(error => { console.error(error); process.exitCode = 1; });
module.exports = { invitationPage };
