<?php
$viewerProfile = $viewerProfile ?? null;
$viewerProfileHref = '';
$viewerProfileLabel = '';
if (is_array($viewerProfile)) {
    $viewerProfileHref = '/profiles/' . rawurlencode((string) ($viewerProfile['profile_slug'] ?? ''));
    $viewerProfileLabel = 'View profile';
}
$authenticatedIdentityId = is_array($viewerProfile)
    && (($viewerProfile['_authenticated_identity'] ?? true) === true)
    ? strtolower(trim((string) ($viewerProfile['identity_id'] ?? '')))
    : '';
?>
<section class="stack" data-account-key-root data-private-site-auth-state data-authenticated-identity-id="<?= $e($authenticatedIdentityId) ?>" data-profile-link-authorized="<?= $viewerProfileHref !== '' ? '1' : '0' ?>">
  <article class="card">
    <header class="account-key-header">
      <h1>Account Key</h1>
    </header>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <div class="stack">
      <div class="account-key-simple-surface account-key-status-card stack">
        <p class="account-key-simple-state">
          <span class="account-key-status-dot" data-role="simple-status-dot" aria-hidden="true"></span>
          <span class="account-key-status-badge" data-role="simple-status-badge">Not set up yet</span>
        </p>
        <div>
          <p class="account-key-label">Signed in as</p>
          <p class="account-key-username" data-role="username-field">guest</p>
        </div>
        <div class="account-key-fingerprint">
          <p class="account-key-label">Fingerprint</p>
          <p class="account-key-fingerprint-row">
            <code class="account-key-fingerprint-value" data-role="fingerprint-field">No key yet</code>
            <button type="button" class="account-key-viewer-copy" data-action="copy-fingerprint" hidden>Copy</button>
          </p>
        </div>
        <div>
          <p class="meta account-key-profile-link" data-role="profile-link-wrap"<?= $viewerProfileHref === '' ? ' hidden' : '' ?>><a data-role="profile-link" href="<?= $e($viewerProfileHref !== '' ? $viewerProfileHref : '/account/key/') ?>"><?= $e($viewerProfileLabel !== '' ? $viewerProfileLabel : 'View profile') ?></a></p>
        </div>
        <div class="account-key-simple-actions">
          <button type="button" class="account-key-primary-button" data-action="generate-browser-key">Set up this browser</button>
          <button type="button" class="account-key-secondary-button" data-action="clear-browser-identity" hidden>Clear identity</button>
        </div>
        <p class="meta account-key-simple-status" id="simple-status">Choose a name to set up this browser.</p>
        <p class="meta" data-role="private-site-auth-status" hidden></p>
      </div>
      <details class="account-key-advanced" id="key-backup" data-key-backup>
        <summary>Back up or restore</summary>
        <div class="stack">
          <div class="account-key-tabs" role="tablist" aria-label="Key backup and restore">
            <button type="button" role="tab" id="key-backup-tab" aria-controls="key-backup-panel" aria-selected="false" tabindex="-1" data-backup-tab="backup">Backup</button>
            <button type="button" role="tab" id="key-restore-tab" aria-controls="key-restore-panel" aria-selected="true" data-backup-tab="restore">Restore</button>
          </div>
          <div class="stack" id="key-backup-panel" role="tabpanel" aria-labelledby="key-backup-tab" data-backup-panel="backup" hidden>
            <p>Keep access to your identity and private messages if you clear browser data or change devices. Backups are encrypted here in your browser.</p>
            <p class="meta">Use a strong, unique passphrase and keep it somewhere safe. A forgotten passphrase cannot be recovered.</p>
            <label>Backup passphrase <input type="password" data-backup-password autocomplete="new-password"></label>
            <label>Confirm passphrase <input type="password" data-backup-confirm autocomplete="new-password"></label>
            <div class="button-row">
              <button type="button" data-backup-download="text">Download text backup</button>
              <button type="button" data-backup-download="png">Download Starfield PNG</button>
            </div>
            <p class="meta">Keep the original PNG file. Lossless PNG recompression is supported, but screenshots, resizing, and JPEG conversion can destroy the backup. Text is also available.</p>
          </div>
          <div class="stack" id="key-restore-panel" role="tabpanel" aria-labelledby="key-restore-tab" data-backup-panel="restore">
            <p>Choose your encrypted text backup or Starfield PNG and enter its passphrase to restore your identity in this browser.</p>
            <label>Restore from backup <input type="file" data-backup-file accept=".txt,.png,text/plain,image/png"></label>
            <label>Restore passphrase <input type="password" data-restore-password autocomplete="current-password"></label>
            <button type="button" data-backup-restore>Restore backup</button>
          </div>
          <p class="meta" data-backup-status role="status" aria-live="polite"></p>
        </div>
      </details>
      <details class="account-key-advanced" id="key-keys">
        <summary>Keys</summary>
        <div class="stack">
          <div class="stack">
            <div class="account-key-viewer-header">
              <label class="account-key-viewer-label" for="saved-public-key-viewer">Public key <span class="meta">(safe to share)</span></label>
              <button type="button" class="account-key-viewer-copy" data-copy-source="public">Copy</button>
            </div>
            <pre id="saved-public-key-viewer" data-role="public-key-viewer">
No browser public key saved yet.
            </pre>
          </div>
          <details class="account-key-reveal">
            <summary>Private key</summary>
            <div class="stack">
              <p class="account-key-warning"><strong>Never share this.</strong> Anyone with it can post as you.</p>
              <div class="account-key-viewer-header">
                <label class="account-key-viewer-label" for="saved-private-key-viewer">Saved private key</label>
                <button type="button" class="account-key-viewer-copy" data-copy-source="private">Copy</button>
              </div>
              <pre id="saved-private-key-viewer" data-role="private-key-viewer">
No browser private key saved yet.
              </pre>
              <div class="button-row account-key-advanced-actions">
                <button type="button" data-action="restore-private-key">Restore Private Key</button>
              </div>
            </div>
          </details>
          <div class="button-row account-key-advanced-actions">
            <button type="button" data-action="load-browser-key">Load Saved Public Key</button>
          </div>
          <form method="post" class="stack">
            <label>Link a public key<textarea name="public_key" rows="6" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----" data-role="public-key-field"></textarea></label>
            <button type="submit">Link identity</button>
          </form>
          <div class="button-row" hidden>
            <button type="button" data-action="copy-public-key">Copy Public Key</button>
            <button type="button" data-action="copy-private-key">Copy Private Key</button>
          </div>
        </div>
      </details>
      <details class="account-key-advanced" id="key-technical">
        <summary>Storage and privacy</summary>
        <div class="stack">
          <ul class="meta account-key-where">
            <li><strong>This browser:</strong> private key, public key, username, and fingerprint (local storage). Backup passphrases never leave it.</li>
            <li><strong>Server:</strong> your public key once published, at your first signed action or immediately if enabled below, plus your signed posts.</li>
            <li><strong>Cookie:</strong> an identity hint, kept 30 days, so the server can show which profile this browser last used.</li>
          </ul>
          <p class="meta" data-role="browser-key-status">
            <span data-role="browser-key-status-message">Ready.</span>
            <a href="#" data-action="undo-clear-browser-key" hidden>Undo</a>
          </p>
          <label><input type="checkbox" data-role="automatic-guest-publication"> Publish this browser's automatically prepared guest public key immediately</label>
          <p class="meta">Off by default: the public key stays on this browser until your first signed action. This setting applies only to this browser.</p>
          <p class="meta">
            <strong>Identity hint:</strong> <?= $e($identityHint !== '' ? $identityHint : 'none') ?>
            · <strong>Saved browser identity:</strong> <span data-role="identity-id-field">none</span>
          </p>
          <div class="button-row account-key-advanced-actions">
            <button type="button" data-action="clear-browser-key">Clear Saved Keypair</button>
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

    function storedFingerprint() {
      return readStorageValue('forum_pki_fingerprint').trim().toLowerCase();
    }

    function friendlyStatusMessage(rawMessage, username, ready) {
      if (!rawMessage || rawMessage === 'Ready.') {
        return ready ? 'All set! Posting as ' + username + '.' : 'Choose a name to set up this browser.';
      }

      if (rawMessage.indexOf('Browser keypair ready for ') === 0) {
        return 'All set! Posting as ' + username + '.';
      }

      if (rawMessage.indexOf('Restored browser keypair for ') === 0) {
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
      var clearIdentityButton = root.querySelector('[data-action="clear-browser-identity"]');
      var simpleStatus = root.querySelector('#simple-status');
      var fingerprintField = root.querySelector('[data-role="fingerprint-field"]');
      var copyFingerprint = root.querySelector('[data-action="copy-fingerprint"]');
      var fingerprint = storedFingerprint();

      if (fingerprintField) {
        fingerprintField.textContent = fingerprint ? 'openpgp-' + fingerprint : 'No key yet';
      }

      if (copyFingerprint) {
        copyFingerprint.hidden = fingerprint === '';
      }

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

      if (clearIdentityButton) {
        clearIdentityButton.hidden = !ready;
      }

      if (simpleStatus && !simpleStatus.dataset.kind) {
        simpleStatus.textContent = ready ? 'All set! Posting as ' + username + '.' : 'Choose a name to set up this browser.';
      }

      syncProfileLinks(root);
    }

    function syncProfileLinks(root) {
      var fingerprint = storedFingerprint();
      var links = root.querySelectorAll('[data-role="profile-link"]');
      var wraps = root.querySelectorAll('[data-role="profile-link-wrap"]');
      var privateSiteEnabled = document.documentElement
        && document.documentElement.dataset.approvedMembersOnly === '1';
      var serverAllowsProfileLink = root.dataset.profileLinkAuthorized === '1';
      var preserveServerProfileLink = privateSiteEnabled && serverAllowsProfileLink;

      links.forEach(function (link) {
        if (!preserveServerProfileLink) {
          link.href = fingerprint ? '/profiles/openpgp-' + fingerprint : '/account/key/';
          link.textContent = fingerprint ? 'View profile' : 'Open profile';
        }
      });

      wraps.forEach(function (wrap) {
        wrap.hidden = privateSiteEnabled ? !serverAllowsProfileLink : fingerprint === '';
      });
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

    function bindFingerprintCopy() {
      var root = accountKeyRoot();
      var button = root && root.querySelector('[data-action="copy-fingerprint"]');
      if (!button) {
        return;
      }

      button.addEventListener('click', function () {
        var value = storedFingerprint();
        if (value === '') {
          return;
        }
        value = 'openpgp-' + value;

        function done() {
          button.textContent = 'Copied';
          window.setTimeout(function () { button.textContent = 'Copy'; }, 1500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(value).then(done, function () {});
        }
      });
    }

    function initAccountKeySimpleUi() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      var statusNode = root.querySelector('[data-role="browser-key-status"]');

      bindViewerCopyButtons();
      bindFingerprintCopy();
      syncSimpleUI();
      mirrorAdvancedStatus();
      window.addEventListener('forum:identity-changed', syncSimpleUI);
      window.addEventListener('forum:identity-changed', mirrorAdvancedStatus);
      window.addEventListener('storage', syncSimpleUI);
      window.addEventListener('storage', mirrorAdvancedStatus);
      window.addEventListener('pageshow', syncSimpleUI);
      window.addEventListener('pageshow', mirrorAdvancedStatus);

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
