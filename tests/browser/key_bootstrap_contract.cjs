// Deterministic checks for public/assets/key_bootstrap.js; no browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || 'public/assets/key_bootstrap.js', 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';

function fixture({ storage = {}, cookie = '', pageScripts = [], status = null, prepare = null } = {}) {
  const calls = [], appended = [], inserted = [];
  const document = {
    readyState: 'complete', cookie, scripts: pageScripts.map(src => ({ src })),
    currentScript: { dataset: { identityScripts: '/assets/openpgp_loader.b2de6e856fd0.js /assets/browser_signing.71c3d69646e6.js /assets/private_site_auth.2bce4bffea91.js' } },
    head: { appendChild(node) { appended.push(node.src); setImmediate(() => node.onload()); } },
    createElement: (tag) => { const node = { tag, children: [], attributes: {}, className: '', textContent: '', href: '',
      setAttribute(k, v) { this.attributes[k] = v; }, appendChild(child) { this.children.push(child); }, addEventListener() {}, remove() {} }; return node; },
    addEventListener() {},
    querySelector: () => null,
    body: { firstChild: null, insertBefore(node) { inserted.push(node); } },
  };
  const window = {
    localStorage: { getItem: k => storage[k] || '', setItem: (k, v) => { storage[k] = v; }, removeItem: k => { delete storage[k]; } },
    PrivateSiteAuth: { authenticateInPlace: async (options) => { calls.push(['sign-in', options]); return { status: 'approved' }; } },
    __forumBrowserIdentity: {
      ensureReadyIdentity: async (root, node, options) => { calls.push(['make-key', options.temporaryName]);
        storage.forum_pki_private_key = 'new-private'; storage.forum_pki_fingerprint = 'b'.repeat(40); },
      signCanonicalRecord: async (record, identityId) => { calls.push(['sign', identityId]); return 'signature'; },
    },
  };
  const answers = {
    '/api/key_bootstrap_status': status || {},
    '/api/prepare_key_bootstrap': prepare || { status: 'ok', prepare_token: 't', post_id: 'p', record_path: 'records/posts/p.txt', canonical_record: 'record' },
    '/api/finalize_key_bootstrap': { status: 'ok' },
  };
  const context = { window, document, Promise, setImmediate, URLSearchParams, decodeURIComponent,
    fetch: async (url, options) => { calls.push(['fetch', url, options && options.body ? String(options.body) : '']);
      return { ok: true, json: async () => answers[url] || {}, text: async () => '' }; } };
  vm.createContext(context); vm.runInContext(source, context);
  return { calls, appended, storage, inserted };
}
const tick = () => new Promise(resolve => setTimeout(resolve, 20));
const keyed = (extra = {}) => ({ forum_pki_private_key: 'private', forum_pki_fingerprint: fingerprint, forum_pki_published_fingerprint: fingerprint, ...extra });

(async () => {
  // A guest with no key and no member hint: no scripts, no requests.
  { const f = fixture(); await tick(); assert.deepEqual(f.calls, []); assert.deepEqual(f.appended, []); }
  // A keyed browser whose session has no device yet signs in again once, forced, after loading scripts.
  { const f = fixture({ storage: keyed() }); await tick();
    assert.equal(f.appended.length, 3);
    assert.deepEqual(f.calls.map(c => c[0]), ['sign-in']);
    assert.equal(f.calls[0][1].force, true); }
  // Scripts the page already loaded (another hash, same file) are not loaded twice.
  { const f = fixture({ storage: keyed(), pageScripts: ['https://zenmemes.com/assets/browser_signing.0123456789ab.js'] }); await tick();
    assert.deepEqual(f.appended, ['/assets/openpgp_loader.b2de6e856fd0.js', '/assets/private_site_auth.2bce4bffea91.js']); }
  // Already recorded for this key: nothing happens.
  { const f = fixture({ storage: keyed({ forum_session_device_recorded: fingerprint }) }); await tick();
    assert.deepEqual(f.calls, []); assert.deepEqual(f.appended, []); }
  // Recorded for another key: this key signs in again.
  { const f = fixture({ storage: keyed({ forum_session_device_recorded: 'f'.repeat(40) }) }); await tick();
    assert.deepEqual(f.calls.map(c => c[0]), ['sign-in']); }
  // A key that was never published has no session to describe.
  { const f = fixture({ storage: keyed({ forum_pki_published_fingerprint: '' }) }); await tick();
    assert.deepEqual(f.calls, []); }
  const member = 'openpgp:' + 'a'.repeat(40);
  const hint = 'identity_hint=' + encodeURIComponent(member);
  // No key, a member hint, an eligible session: a new key under that name, signed, accepted, signed in, one notice.
  { const f = fixture({ cookie: hint, status: { status: 'eligible', from_identity_id: member, username: 'lena', device_match: true } }); await tick();
    assert.deepEqual(f.calls.map(c => c[0] === 'fetch' ? c[1] : c[0]),
      ['/api/key_bootstrap_status', 'make-key', '/api/prepare_key_bootstrap', 'sign', '/api/finalize_key_bootstrap', 'sign-in']);
    assert.equal(f.calls[1][1], 'lena');
    assert.ok(f.calls[2][2].includes('identity_id=openpgp%3A' + 'b'.repeat(40)));
    assert.equal(f.inserted.length, 1);
    assert.equal(f.inserted[0].children[0].children[0].textContent, 'This browser lost its key and has a new one.');
    assert.equal(f.storage.forum_key_bootstrap_tried, member); }
  // A mismatched browser: the key is made and left pending; nothing is signed or finalized.
  { const f = fixture({ cookie: hint, status: { status: 'eligible', from_identity_id: member, username: 'lena', device_match: false }, prepare: { status: 'pending', reason: 'device' } }); await tick();
    assert.deepEqual(f.calls.map(c => c[0] === 'fetch' ? c[1] : c[0]), ['/api/key_bootstrap_status', 'make-key', '/api/prepare_key_bootstrap']);
    assert.equal(f.inserted[0].children[0].children[0].textContent, 'This browser lost its key. Its new key waits for approval.'); }
  // A refused session (signed out, used, flag off): no key is made, and later pages do not ask again.
  { const f = fixture({ cookie: hint, status: { status: 'refused', reason: 'signed_out' } }); await tick();
    assert.deepEqual(f.calls.map(c => c[1]), ['/api/key_bootstrap_status']); assert.equal(f.inserted.length, 0);
    const again = fixture({ cookie: hint, storage: { forum_key_bootstrap_tried: member } }); await tick();
    assert.deepEqual(again.calls, []); }
  // A guest hint, or a session for someone else than the hint: nothing.
  { const f = fixture({ cookie: 'identity_hint=guest' }); await tick(); assert.deepEqual(f.calls, []); }
  { const f = fixture({ cookie: hint, status: { status: 'eligible', from_identity_id: 'openpgp:' + 'c'.repeat(40), username: 'x', device_match: true } }); await tick();
    assert.deepEqual(f.calls.map(c => c[1]), ['/api/key_bootstrap_status']); }
  console.log('ok');
})().catch(error => { console.error(error); process.exit(1); });
