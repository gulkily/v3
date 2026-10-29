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
  return { ...context, context, storage, state, status, navigations, events, fetches, ready: () => ready() };
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
  // Each caller owns navigation, regardless of which one starts signing.
  for (const backgroundFirst of [true, false]) {
    const f = fixture({ approved: true });
    let release; f.window.__forumOpenPgpLoader.ready = new Promise(r => { release = r; });
    const background = () => f.window.PrivateSiteAuth.authenticate({ navigate: false, quiet: true });
    const explicit = () => f.window.PrivateSiteAuth.authenticate({ returnTo: '/requested/' });
    const first = backgroundFirst ? background() : explicit();
    const second = backgroundFirst ? explicit() : background();
    release(); await Promise.all([first, second]);
    assert.equal(f.fetches.length, 2, 'compatible callers share one challenge/signature');
    assert.deepEqual(f.navigations, ['/requested/']);
  }
  {
    const f = fixture({ approved: true });
    let release; f.window.__forumOpenPgpLoader.ready = new Promise(r => { release = r; });
    const background = f.window.PrivateSiteAuth.authenticate({ navigate: false });
    const forced = f.window.PrivateSiteAuth.authenticate({ force: true, navigate: false, existingIdentityOnly: true });
    release(); await Promise.all([background, forced]);
    assert.equal(f.fetches.length, 4, 'forced verification gets a fresh serialized challenge');
    assert.deepEqual(f.navigations, []);
  }
  for (const phase of ['loader', 'signature', 'response']) {
    const f = fixture();
    let release;
    if (phase === 'loader') f.window.__forumOpenPgpLoader.ready = new Promise(r => { release = r; });
    else if (phase === 'signature') f.window.openpgp.sign = () => new Promise(r => { release = () => r('signature'); });
    else {
      const fetch = f.context.fetch;
      f.context.fetch = async url => {
        const response = await fetch(url);
        if (url.includes('authenticate_identity')) await new Promise(r => { release = r; });
        return response;
      };
    }
    const pending = f.window.PrivateSiteAuth.authenticate({ returnTo: '/requested/' });
    const rejected = assert.rejects(pending, /Browser identity changed/);
    while (!release) await new Promise(r => setImmediate(r));
    f.storage.forum_pki_private_key = 'a different key';
    release(); await rejected;
    assert.deepEqual(f.navigations, []);
    if (phase !== 'response') assert.ok(!f.fetches.includes('/api/authenticate_identity'), 'do not submit the obsolete key');
  }
  {
    const f = fixture();
    let release; f.window.__forumOpenPgpLoader.ready = new Promise(r => { release = r; });
    const first = f.window.PrivateSiteAuth.authenticate({ navigate: false });
    const queued = f.window.PrivateSiteAuth.authenticate({ force: true, navigate: false });
    const rejected = Promise.all([assert.rejects(first, /Browser identity changed/), assert.rejects(queued, /Browser identity changed/)]);
    f.storage.forum_pki_private_key = 'new identity while queued';
    release(); await rejected;
    assert.equal(f.fetches.length, 0, 'a queued request cannot silently adopt a different key');
  }
  console.log('PASS in-place authentication contract');
})().catch(error => { console.error(error); process.exitCode = 1; });
