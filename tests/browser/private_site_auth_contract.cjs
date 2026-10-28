// Deterministic authentication completion checks; no browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || 'public/assets/private_site_auth.js', 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';
function fixture({ approved = false, matching = false, keyless = false, shared = false, expires = false, fail = false } = {}) {
  const storage = keyless ? {} : { forum_pki_public_key: 'public', forum_pki_private_key: 'private', forum_pki_fingerprint: fingerprint, forum_pki_published_fingerprint: fingerprint };
  const state = { dataset: { publicAuthResume: 'true', authenticatedIdentityId: matching ? 'openpgp:' + fingerprint : '', authReturnTo: '/thread/' }, hasAttribute: () => false };
  const status = { hidden: true, textContent: '', dataset: {} };
  const navigations = [], events = [], fetches = [];
  let ready, attempts = 0;
  const context = { URLSearchParams, console: { error() {} }, CustomEvent: function(type, options) { this.type = type; this.detail = options.detail; },
    document: { documentElement: { dataset: { approvedMembersOnly: '0' } }, addEventListener(type, callback) { ready = callback; },
      querySelector(s) { return s === '[data-private-site-auth-state]' ? state : s.includes('private-site-auth-status') ? status : s.includes('v3-generated-at') && shared ? {} : null; } },
    window: { localStorage: { getItem: k => storage[k] || '' }, dispatchEvent: e => events.push(e),
      location: { pathname: '/thread/', search: '', replace: u => navigations.push(u), assign: u => navigations.push(u), reload: () => navigations.push('reload') },
      __forumOpenPgpLoader: { ready: Promise.resolve() },
      openpgp: { readKey: async () => ({ getFingerprint: () => fingerprint }), readPrivateKey: async () => ({ getFingerprint: () => fingerprint }), createMessage: async () => ({}), sign: async () => 'signature' } },
    fetch: async (url) => { fetches.push(url); if (url.includes('auth_challenge')) return { ok: true, text: async () => 'challenge=abcdef' };
      attempts++; return { ok: !fail && !(expires && attempts === 1), text: async () => fail ? 'error=Unavailable.' : expires && attempts === 1 ? 'error=Authentication challenge is missing or expired.' : 'approved=' + Number(approved) }; }
  };
  vm.createContext(context); vm.runInContext(source, context);
  return { ...context, storage, state, status, navigations, events, fetches, ready: () => ready() };
}
(async () => {
  for (const options of [{}, { approved: true }, { matching: true }, { keyless: true }, { expires: true }]) {
    const f = fixture(options);
    const result = await f.window.PrivateSiteAuth.authenticate({ navigate: false, returnTo: '/next/' });
    assert.deepEqual(f.navigations, [], 'in-place completion cannot navigate');
    if (!options.keyless) assert.equal(result.status, options.approved ? 'approved' : 'authenticated');
  }
  for (const options of [{}, { approved: true }, { expires: true }, { fail: true }, { keyless: true }, { shared: true }]) {
    const f = fixture(options); f.ready();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(f.navigations, [], 'public page-load sign-in cannot navigate');
    assert.equal(f.status.hidden, true, 'background sign-in stays quiet');
    if (!options.fail && !options.keyless && !options.shared) {
      assert.equal(f.state.dataset.authenticatedIdentityId, 'openpgp:' + fingerprint);
      assert.equal(f.events.length, 1);
      assert.equal(f.events[0].detail.approved, Boolean(options.approved));
    }
    if (options.keyless || options.shared) assert.equal(f.fetches.length, 0);
  }
  console.log('PASS in-place authentication contract');
})().catch(error => { console.error(error); process.exitCode = 1; });
