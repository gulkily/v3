// Deterministic approval-memory checks for member-only navigation; no browser dependency.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || 'public/assets/member_approval.js', 'utf8');
const fingerprint = '0123456789abcdef0123456789abcdef01234567';
const other = 'fedcba9876543210fedcba9876543210fedcba98';

function area(values, blocked) {
  return {
    values,
    getItem(key) { if (blocked) throw new Error('blocked'); return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null; },
    setItem(key, value) { if (blocked) throw new Error('blocked'); values[key] = String(value); },
    removeItem(key) { if (blocked) throw new Error('blocked'); delete values[key]; },
  };
}
function identity(value = fingerprint, registered = true) {
  return { forum_pki_public_key: 'public', forum_pki_private_key: 'private', forum_pki_fingerprint: value.toUpperCase(),
    ...(registered ? { forum_pki_published_fingerprint: value.toUpperCase() } : {}) };
}
function load({ local = {}, session = {}, status = 200, membersOnly = false, blocked = false, fetchGate = null, messaging = null, preloaded = [] } = {}) {
  const attributes = {}, listeners = {}, events = [], fetches = [], appended = [], started = [];
  const localStorage = area(local, blocked), sessionStorage = area(session, blocked);
  const link = { isConnected: true };
  const scripts = preloaded.map(path => ({ src: 'https://forum.test' + path }));
  const window = {
    localStorage, sessionStorage, location: { href: 'https://forum.test/', origin: 'https://forum.test' },
    __forumAssetPaths: messaging ? { privateMessaging: messaging } : {},
    addEventListener(type, callback) { (listeners[type] = listeners[type] || []).push(callback); },
    dispatchEvent(event) { events.push(event); (listeners[event.type] || []).forEach(callback => callback(event)); },
  };
  const context = {
    window, console,
    CustomEvent: function (type, options) { this.type = type; this.detail = options && options.detail; },
    URL,
    document: {
      scripts, querySelector: selector => selector === '[data-private-message-unread]' && messaging ? link : null,
      createElement: () => ({}),
      head: { appendChild(script) {
        appended.push(new URL(script.src).pathname); scripts.push(script);
        if (script.src.endsWith('unread.js')) window.ForumPrivateMessageUnread = { start: node => started.push(['unread', node === link]), stop: () => started.push(['unread-stop']) };
        setImmediate(script.onload);
      } },
      documentElement: { dataset: { approvedMembersOnly: membersOnly ? '1' : '0' },
      getAttribute: name => attributes[name] ?? null, setAttribute: (name, value) => { attributes[name] = String(value); },
      removeAttribute: name => { delete attributes[name]; } } },
    fetch: async url => { fetches.push(url); if (fetchGate) await fetchGate; return { ok: status === 200, status }; },
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  const fire = (type, event = {}) => (listeners[type] || []).forEach(callback => callback({ type, ...event }));
  const settle = () => new Promise(resolve => setImmediate(resolve));
  return { api: window.ForumMemberApproval, attributes, events, fetches, local, session, fire, settle, appended, started };
}

(async () => {
  // No identity: nothing remembered, nothing requested.
  {
    const f = load(); await f.settle();
    assert.equal(f.fetches.length, 0);
    assert.equal(f.attributes['data-member'], undefined);
  }
  // A remembered approval for the current identity shows at load without a request.
  {
    const f = load({ local: { ...identity(), forum_pki_approved_fingerprint: fingerprint } }); await f.settle();
    assert.equal(f.attributes['data-member'], 'approved');
    assert.equal(f.fetches.length, 0);
  }
  // A registered identity with no memory learns once per browser session from auth_status.
  {
    const session = {};
    const f = load({ local: identity(), session }); await f.settle();
    assert.deepEqual(f.fetches, ['/api/auth_status']);
    assert.equal(f.local.forum_pki_approved_fingerprint, fingerprint);
    assert.equal(f.attributes['data-member'], 'approved');
    assert.equal(f.events.filter(e => e.type === 'forum:member-approval-changed').length, 1);
    const unapproved = load({ local: identity(other), session, status: 401 }); await unapproved.settle();
    assert.equal(unapproved.fetches.length, 1, 'a different identity gets its own check');
    const again = load({ local: identity(other), session, status: 401 }); await again.settle();
    assert.equal(again.fetches.length, 0, 'one check per identity per session');
    assert.equal(again.attributes['data-member'], undefined);
  }
  // A 401 never clears a remembered approval (it may be an expired session).
  {
    const f = load({ local: { ...identity(), forum_pki_approved_fingerprint: other }, status: 401 }); await f.settle();
    assert.equal(f.local.forum_pki_approved_fingerprint, other);
    assert.equal(f.attributes['data-member'], undefined, 'memory for another identity does not show links');
  }
  // Unregistered keys on a public site do not ask; members-only sites do.
  {
    const guest = load({ local: identity(fingerprint, false) }); await guest.settle();
    assert.equal(guest.fetches.length, 0);
    const privateSite = load({ local: identity(fingerprint, false), membersOnly: true }); await privateSite.settle();
    assert.equal(privateSite.fetches.length, 1);
  }
  // A response that completes after the identity changed is ignored.
  {
    let release; const fetchGate = new Promise(resolve => { release = resolve; });
    const f = load({ local: identity(), fetchGate });
    f.local.forum_pki_fingerprint = other.toUpperCase();
    release(); await f.settle(); await f.settle();
    assert.equal(f.local.forum_pki_approved_fingerprint, undefined);
    assert.equal(f.attributes['data-member'], undefined);
  }
  // Sign-in results: approval, a later not-approved answer, and a stale identity.
  {
    const f = load({ local: identity(), session: { forum_pki_approval_checked: fingerprint } }); await f.settle();
    f.api.record(fingerprint.toUpperCase(), true);
    assert.equal(f.attributes['data-member'], 'approved');
    f.api.record(other, false);
    assert.equal(f.local.forum_pki_approved_fingerprint, fingerprint, 'answers for another identity are ignored');
    f.api.record(fingerprint, false);
    assert.equal(f.local.forum_pki_approved_fingerprint, undefined);
    assert.equal(f.attributes['data-member'], undefined);
  }
  // Identity replacement and sign-out forget the old approval; other tabs follow.
  {
    const f = load({ local: { ...identity(), forum_pki_approved_fingerprint: fingerprint } }); await f.settle();
    f.local.forum_pki_fingerprint = other.toUpperCase();
    f.fire('forum:identity-changed');
    assert.equal(f.local.forum_pki_approved_fingerprint, undefined);
    assert.equal(f.attributes['data-member'], undefined);
    const signedOut = load({ local: { ...identity(), forum_pki_approved_fingerprint: fingerprint } }); await signedOut.settle();
    ['forum_pki_public_key', 'forum_pki_private_key', 'forum_pki_fingerprint'].forEach(key => delete signedOut.local[key]);
    signedOut.fire('storage', { key: 'forum_pki_private_key' });
    assert.equal(signedOut.local.forum_pki_approved_fingerprint, undefined);
    assert.equal(signedOut.attributes['data-member'], undefined);
    const otherTab = load({ local: identity(), session: { forum_pki_approval_checked: fingerprint } }); await otherTab.settle();
    otherTab.local.forum_pki_approved_fingerprint = fingerprint;
    otherTab.fire('storage', { key: 'forum_pki_approved_fingerprint' });
    assert.equal(otherTab.attributes['data-member'], 'approved');
  }
  // Messaging loads in order only for a remembered member, skips scripts the page
  // already has, starts once loaded, and stops when approval is lost.
  {
    const messaging = ['/assets/private_message_availability.js', '/assets/browser_signing.js', '/assets/private_message_unread.js'];
    const guest = load({ messaging }); await guest.settle();
    assert.deepEqual(guest.appended, [], 'no messaging downloads for other viewers');
    const member = load({ local: { ...identity(), forum_pki_approved_fingerprint: fingerprint }, messaging, preloaded: ['/assets/browser_signing.js'] });
    for (let i = 0; i < 5; i++) await member.settle();
    assert.deepEqual(member.appended, ['/assets/private_message_availability.js', '/assets/private_message_unread.js']);
    assert.deepEqual(member.started, [['unread', true]]);
    member.api.record(fingerprint, false);
    assert.deepEqual(member.started.at(-1), ['unread-stop']);
    const later = load({ local: identity(), session: { forum_pki_approval_checked: fingerprint }, messaging }); await later.settle();
    assert.deepEqual(later.appended, []);
    later.api.record(fingerprint, true);
    for (let i = 0; i < 5; i++) await later.settle();
    assert.equal(later.appended.length, 3, 'sign-in loads messaging in place');
  }
  // Blocked storage stays hidden and quiet.
  {
    const f = load({ blocked: true }); await f.settle();
    assert.equal(f.fetches.length, 0);
    assert.equal(f.attributes['data-member'], undefined);
    assert.equal(f.api.isApproved(), false);
  }
  console.log('member approval contract: ok');
})().catch(error => { console.error(error); process.exit(1); });
