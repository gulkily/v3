<section class="stack" data-account-key-root>
  <article class="card">
    <h1>Account Key</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <div class="stack">
      <div class="account-key-simple-surface stack">
        <p class="account-key-simple-state">
          <span class="account-key-status-dot" data-role="simple-status-dot" aria-hidden="true"></span>
          <span class="account-key-status-badge" data-role="simple-status-badge">Not set up yet</span>
        </p>
        <div>
          <p class="account-key-label">Signed in as</p>
          <p class="account-key-username" data-role="username-field">guest</p>
        </div>
        <p>Your account lives in this browser. Use the same browser to keep your posts linked to this name.</p>
        <button type="button" class="account-key-primary-button" data-action="generate-browser-key">Set up this browser</button>
        <p class="meta account-key-simple-status" id="simple-status">Choose a name to set up this browser.</p>
      </div>
      <details class="account-key-advanced">
        <summary>Advanced / technical details</summary>
        <div class="stack">
          <div>
            <p class="account-key-label">Browser key status</p>
            <p class="meta" data-role="browser-key-status">
              <span data-role="browser-key-status-message">Ready.</span>
              <a href="#" data-action="undo-clear-browser-key" hidden>Undo</a>
            </p>
          </div>
          <div class="button-row account-key-advanced-actions">
            <button type="button" data-action="load-browser-key">Load Saved Public Key</button>
            <button type="button" data-action="copy-public-key">Copy Public Key</button>
            <button type="button" data-action="copy-private-key">Copy Private Key</button>
            <button type="button" data-action="clear-browser-key">Clear Saved Keypair</button>
          </div>
          <div class="stack">
            <p class="account-key-label">Identity hint cookie</p>
            <p class="meta"><?= $e($identityHint !== '' ? $identityHint : 'none') ?></p>
            <p class="meta">
              <strong>Saved browser identity:</strong>
              <span data-role="identity-id-field">none</span>
              <span data-role="profile-link-wrap" hidden>
                · <a data-role="profile-link" href="/account/key/">Open profile</a>
              </span>
            </p>
          </div>
          <form method="post" class="stack">
            <label>Public key<textarea name="public_key" rows="10" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----" data-role="public-key-field"></textarea></label>
            <button type="submit">Link identity</button>
          </form>
          <div class="stack">
            <div class="account-key-viewer-header">
              <label class="account-key-viewer-label" for="saved-public-key-viewer">Saved public key</label>
              <button type="button" class="account-key-viewer-copy" data-copy-source="public">Copy</button>
            </div>
            <pre id="saved-public-key-viewer" data-role="public-key-viewer">
No browser public key saved yet.
            </pre>
          </div>
          <div class="stack">
            <div class="account-key-viewer-header">
              <label class="account-key-viewer-label" for="saved-private-key-viewer">Saved private key</label>
              <button type="button" class="account-key-viewer-copy" data-copy-source="private">Copy</button>
            </div>
            <pre id="saved-private-key-viewer" data-role="private-key-viewer">
No browser private key saved yet.
            </pre>
          </div>
        </div>
      </details>
    </div>
  </article>
</section>
<script>
  (function () {
    function accountKeyRoot() {
      return document.querySelector('[data-account-key-root]');
    }

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

    function friendlyStatusMessage(rawMessage, username, ready) {
      if (!rawMessage || rawMessage === 'Ready.') {
        return ready ? 'All set! Posting as ' + username + '.' : 'Choose a name to set up this browser.';
      }

      if (rawMessage.indexOf('Browser keypair ready for ') === 0) {
        return 'All set! Posting as ' + username + '.';
      }

      if (rawMessage === 'Loaded the saved browser public key into the form.') {
        return 'Loaded your saved public key into the advanced form.';
      }

      if (rawMessage === 'Copied the saved public key.') {
        return 'Public key copied.';
      }

      if (rawMessage === 'Copied the saved private key.') {
        return 'Private key copied.';
      }

      if (rawMessage === 'Cleared the saved browser keypair from local storage.') {
        return 'Removed this browser keypair.';
      }

      if (rawMessage === 'Restored the recently cleared browser keypair.') {
        return 'Restored this browser keypair.';
      }

      return rawMessage;
    }

    function syncSimpleUI() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      var username = readStorageValue('forum_pki_username') || 'guest';
      var ready = hasBrowserKeypair();
      var usernameField = root.querySelector('[data-role="username-field"]');
      var statusDot = root.querySelector('[data-role="simple-status-dot"]');
      var statusBadge = root.querySelector('[data-role="simple-status-badge"]');
      var generateButton = root.querySelector('[data-action="generate-browser-key"]');
      var simpleStatus = root.querySelector('#simple-status');

      if (usernameField) {
        usernameField.textContent = username;
      }

      if (statusDot) {
        statusDot.dataset.state = ready ? 'ready' : 'pending';
      }

      if (statusBadge) {
        statusBadge.textContent = ready ? 'This browser is ready' : 'Not set up yet';
      }

      if (generateButton) {
        generateButton.textContent = ready ? 'Re-setup / change name' : 'Set up this browser';
      }

      if (simpleStatus && !simpleStatus.dataset.kind) {
        simpleStatus.textContent = ready ? 'All set! Posting as ' + username + '.' : 'Choose a name to set up this browser.';
      }
    }

    function mirrorAdvancedStatus() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      var statusNode = root.querySelector('[data-role="browser-key-status"]');
      var statusMessage = root.querySelector('[data-role="browser-key-status-message"]');
      var simpleStatus = root.querySelector('#simple-status');
      var username = readStorageValue('forum_pki_username') || 'guest';
      var ready = hasBrowserKeypair();

      if (!statusNode || !simpleStatus) {
        return;
      }

      simpleStatus.textContent = friendlyStatusMessage(
        statusMessage ? statusMessage.textContent.trim() : statusNode.textContent.trim(),
        username,
        ready
      );
      simpleStatus.dataset.kind = statusNode.dataset.kind || '';
    }

    function bindViewerCopyButtons() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      root.querySelectorAll('[data-copy-source]').forEach(function (button) {
        button.addEventListener('click', function () {
          var source = button.getAttribute('data-copy-source');
          var targetAction = source === 'private' ? '[data-action="copy-private-key"]' : '[data-action="copy-public-key"]';
          var targetButton = root.querySelector(targetAction);
          if (targetButton) {
            targetButton.click();
          }
        });
      });
    }

    function initAccountKeySimpleUi() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      var statusNode = root.querySelector('[data-role="browser-key-status"]');

      bindViewerCopyButtons();
      syncSimpleUI();
      mirrorAdvancedStatus();
      window.addEventListener('storage', syncSimpleUI);
      window.addEventListener('storage', mirrorAdvancedStatus);

      if (statusNode && typeof MutationObserver === 'function') {
        new MutationObserver(function () {
          syncSimpleUI();
          mirrorAdvancedStatus();
        }).observe(statusNode, {
          subtree: true,
          childList: true,
          characterData: true,
          attributes: true,
          attributeFilter: ['data-kind'],
        });
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      window.setTimeout(initAccountKeySimpleUi, 0);
    });
  })();
</script>
