(function () {
  'use strict';
  // Remembers whether this browser's current identity was last known approved,
  // so shared pages can show member-only navigation before paint (layout.php's
  // head script reads the same keys). It only decides visibility; every member
  // route still checks authorization on the server.
  const approvedKey = 'forum_pki_approved_fingerprint';
  const checkedKey = 'forum_pki_approval_checked';
  const root = document.documentElement;

  function read(key, area) {
    try { return (area || window.localStorage).getItem(key) || ''; } catch (error) { return ''; }
  }
  function write(key, value, area) {
    try {
      const storage = area || window.localStorage;
      if (value) storage.setItem(key, value); else storage.removeItem(key);
    } catch (error) {}
  }
  function fingerprint() {
    const value = read('forum_pki_fingerprint').trim().toLowerCase();
    return /^[a-f0-9]{40}$/.test(value) && read('forum_pki_public_key') && read('forum_pki_private_key') ? value : '';
  }
  function isApproved() {
    const current = fingerprint();
    return current !== '' && read(approvedKey) === current;
  }
  function apply() {
    const approved = isApproved();
    if ((root.getAttribute('data-member') === 'approved') === approved) return;
    if (approved) root.setAttribute('data-member', 'approved'); else root.removeAttribute('data-member');
    window.dispatchEvent(new CustomEvent('forum:member-approval-changed', { detail: { approved: approved } }));
  }
  // Records what the server said about `identityFingerprint`. A response for an
  // identity the browser has since replaced is ignored.
  function record(identityFingerprint, approved) {
    const value = String(identityFingerprint || '').trim().toLowerCase();
    if (!value || value !== fingerprint()) return;
    if (approved) write(approvedKey, value);
    else if (read(approvedKey) === value) write(approvedKey, '');
    apply();
  }
  function forgetReplacedIdentity() {
    const stored = read(approvedKey);
    if (stored && stored !== fingerprint()) write(approvedKey, '');
    apply();
  }
  // A registered identity with no remembered status asks once per browser
  // session, so members who signed in before this memory existed still get it.
  // A 401 can mean an expired session, so it never clears a remembered approval.
  async function learn() {
    const current = fingerprint();
    if (!current || read(approvedKey) === current || read(checkedKey, window.sessionStorage) === current) return;
    if (root.dataset.approvedMembersOnly !== '1'
      && read('forum_pki_published_fingerprint').trim().toLowerCase() !== current) return;
    write(checkedKey, current, window.sessionStorage);
    try {
      const response = await fetch('/api/auth_status', { credentials: 'same-origin', cache: 'no-store' });
      if (response.ok) record(current, true);
    } catch (error) {}
  }

  window.ForumMemberApproval = { fingerprint: fingerprint, record: record, isApproved: isApproved };
  window.addEventListener('forum:identity-changed', forgetReplacedIdentity);
  window.addEventListener('storage', function (event) {
    if (event.key === null || event.key === approvedKey || /^forum_pki_/.test(event.key)) forgetReplacedIdentity();
  });
  apply();
  learn();
})();
