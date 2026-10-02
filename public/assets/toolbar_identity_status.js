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

  function localDisplayName() {
    return readStorageValue('forum_pki_username') || 'guest';
  }

  function syncIdentityIndicator() {
    var statusNodes = document.querySelectorAll('[data-paned-identity-status]');
    if (!statusNodes.length) {
      return;
    }

    var loggedIn = hasBrowserKeypair();
    var label = loggedIn ? localDisplayName() : 'Guest';

    for (var index = 0; index < statusNodes.length; index += 1) {
      var statusNode = statusNodes[index];
      statusNode.setAttribute('data-identity-logged-in', loggedIn ? '1' : '0');
      var labelNode = statusNode.querySelector('[data-paned-identity-label]');
      if (labelNode) {
        labelNode.textContent = label;
      }
    }
  }

  syncIdentityIndicator();
})();
