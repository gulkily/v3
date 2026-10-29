<script>
  // The browser's account state, decided before first paint from browser storage and the
  // server's view of this browser's key. Shared by Account and the Messages access page.
  window.ForumAccountState = window.ForumAccountState || (function () {
    function readStorageValue(key) {
      try {
        return window.localStorage.getItem(key) || '';
      } catch (error) {
        return '';
      }
    }

    function hasBrowserKeypair() {
      return readStorageValue('forum_pki_public_key') !== '' && readStorageValue('forum_pki_private_key') !== '';
    }

    function storedFingerprint() {
      return readStorageValue('forum_pki_fingerprint').trim().toLowerCase();
    }

    // A key is registered once the server has it; until then it stays in this browser.
    function isRegistered(root) {
      var fingerprint = storedFingerprint();
      return fingerprint !== '' && (readStorageValue('forum_pki_published_fingerprint').trim().toLowerCase() === fingerprint
        || root.dataset.viewerIdentityId === 'openpgp:' + fingerprint);
    }

    // Guest: no saved key, or a key not yet registered (shown under its name).
    // A registered key is a member whatever its name, `guest` included, so its
    // name, Remove and Save a copy are always reachable.
    // Otherwise waiting or approved; the server's approval counts only for this
    // browser's own key, and an unknown approval is looked up after paint.
    function accountState(root) {
      var fingerprint = storedFingerprint();
      var serverKnowsThisKey = fingerprint !== '' && root.dataset.viewerIdentityId === 'openpgp:' + fingerprint;
      var approval = serverKnowsThisKey ? (root.dataset.viewerApproved === '1' ? 'approved' : 'waiting') : (root.dataset.lookedUpApproval || '');
      if (!hasBrowserKeypair() || !isRegistered(root)) {
        return 'guest';
      }
      return approval || 'member';
    }

    return {
      readStorageValue: readStorageValue,
      hasBrowserKeypair: hasBrowserKeypair,
      storedFingerprint: storedFingerprint,
      isRegistered: isRegistered,
      accountState: accountState,
    };
  })();
</script>
