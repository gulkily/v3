(function () {
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

  // A key the server doesn't have yet posts as a guest under its name, with no profile to link.
  function isRegistered() {
    var fingerprint = readStorageValue('forum_pki_fingerprint').trim().toUpperCase();
    return fingerprint !== '' && readStorageValue('forum_pki_published_fingerprint').trim().toUpperCase() === fingerprint;
  }

  function localDisplayName() {
    return readStorageValue('forum_pki_username') || 'guest';
  }

  function syncIdentityIndicator() {
    var statusNodes = document.querySelectorAll('[data-paned-identity-status]');
    if (!statusNodes.length) {
      return;
    }

    var loggedIn = hasBrowserKeypair() && isRegistered();
    var label = hasBrowserKeypair() ? localDisplayName() : 'Guest';

    for (var index = 0; index < statusNodes.length; index += 1) {
      var statusNode = statusNodes[index];
      statusNode.setAttribute('data-identity-logged-in', loggedIn ? '1' : '0');
      var labelNode = statusNode.querySelector('[data-paned-identity-label]');
      if (labelNode) {
        labelNode.textContent = label;
      }
      statusNode.setAttribute('href', loggedIn ? '/forte/user/' + encodeURIComponent(label) : hasBrowserKeypair() ? '/account/key/' : '/forte/user/guest');
      statusNode.setAttribute('title', loggedIn ? 'Signed in as ' + label : 'Not signed in');
    }
  }

  syncIdentityIndicator();
  window.addEventListener('storage', syncIdentityIndicator);
  window.addEventListener('forum:identity-changed', syncIdentityIndicator);
})();
