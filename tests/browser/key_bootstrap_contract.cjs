// Deterministic checks for public/assets/key_bootstrap.js; no browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || 'public/assets/key_bootstrap.js', 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';

function fixture({ storage = {}, cookie = '', pageScripts = [] } = {}) {
  const calls = [], appended = [];
  const document = {
    readyState: 'complete', cookie, scripts: pageScripts.map(src => ({ src })),
    currentScript: { dataset: { identityScripts: '/assets/openpgp_loader.b2de6e856fd0.js /assets/browser_signing.71c3d69646e6.js /assets/private_site_auth.2bce4bffea91.js' } },
    head: { appendChild(node) { appended.push(node.src); setImmediate(() => node.onload()); } },
    createElement: () => ({}), addEventListener() {},
  };
  const window = {
    localStorage: { getItem: k => storage[k] || '', setItem: (k, v) => { storage[k] = v; }, removeItem: k => { delete storage[k]; } },
    PrivateSiteAuth: { authenticateInPlace: async (options) => { calls.push(['sign-in', options]); return { status: 'approved' }; } },
  };
  const context = { window, document, Promise, setImmediate, fetch: async (url) => { calls.push(['fetch', url]); return { ok: true, text: async () => '' }; } };
  vm.createContext(context); vm.runInContext(source, context);
  return { calls, appended, storage };
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
  console.log('ok');
})().catch(error => { console.error(error); process.exit(1); });
