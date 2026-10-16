const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/assets/invite_redemption.js'), 'utf8');
async function run({ targeted = true, failure = '', token = 'a'.repeat(32) } = {}) {
  const calls = [], readiness = [], navigations = [], auth = [];
  const status = { dataset: {} }, button = { addEventListener(_, fn) { this.click = fn; } };
  const root = { querySelector(s) { return s.includes('feedback') ? status : button; } };
  let ready;
  const fingerprint = 'b'.repeat(40);
  const sandbox = { crypto: webcrypto, Uint8Array, URLSearchParams,
    localStorage: { getItem() { if (failure === 'storage') throw Error('Storage unavailable'); return fingerprint; } },
    document: { addEventListener(_, fn) { ready = fn; }, querySelector() { return root; } },
    window: { location: { hash: '#invite=' + token, replace(url) { navigations.push(url); } },
      __forumBrowserIdentity: { async ensureActionIdentity(_root, _status, opts) { readiness.push(opts); } },
      ForumBrowserSigning: { async signCanonicalRecord() { return 'signature'; } },
      PrivateSiteAuth: { async authenticate(opts) { auth.push(opts); return { status: failure === 'auth' ? 'pending' : 'approved', identityId: 'openpgp:' + fingerprint }; } } },
    fetch: async (url, options) => {
      calls.push(url);
      if (failure === 'network') { failure = ''; throw Error('Connection lost'); }
      return { ok: true, async json() {
        if (url.includes('inspect')) return { status: 'ok', targeted };
        return { status: 'ok', canonical_record: 'record', prepare_token: 'prepared', destination: failure === 'unsafe' ? '//evil.test' : '/threads/a?q=1#reply' };
      } };
    }
  };
  vm.runInNewContext(source, sandbox);
  const flush = async () => { for (let n = 0; n < 20; n++) await new Promise(resolve => setImmediate(resolve)); };
  ready(); await flush();
  return { calls, readiness, navigations, auth, button, status, flush };
}
(async () => {
  const auto = await run();
  assert.equal(auto.readiness.length, 1);
  assert.equal(auto.readiness[0].temporaryName, "guest");
  assert.deepEqual(auto.navigations, ['/threads/a?q=1#reply']);
  assert.equal(auto.auth[0].navigate, false);
  assert.equal(auto.auth[0].force, true);
  const manual = await run({ targeted: false });
  assert.equal(manual.readiness.length, 0);
  manual.button.click(); await manual.flush();
  assert.equal(manual.readiness.length, 1);
  assert.equal(manual.readiness[0].temporaryName, undefined);
  const retry = await run({ failure: 'network' });
  assert.equal(retry.readiness.length, 0);
  assert.equal(retry.button.hidden, false);
  retry.button.click(); await retry.flush();
  assert.equal(retry.navigations.length, 1);
  for (const failure of ['storage', 'auth', 'unsafe']) {
    const failed = await run({ failure });
    assert.equal(failed.navigations.length, 0, failure);
    assert.equal(failed.status.dataset.status, 'error');
    assert.equal(failed.button.hidden, false);
  }
  const invalid = await run({ token: 'bad' });
  assert.equal(invalid.calls.length, 0);
  assert.equal(invalid.readiness.length, 0);
  console.log('PASS targeted/manual redemption, target/auth contract, failures and retry');
})().catch(error => { console.error(error); process.exitCode = 1; });
