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
  const window = { location: { origin: 'https://forum.test' },
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
  }
  console.log('Invitation QR and copy fallback checks passed.');
}

if (require.main === module) main().catch(error => { console.error(error); process.exitCode = 1; });
module.exports = { invitationPage };
