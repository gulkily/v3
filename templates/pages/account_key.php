<?php
$viewerProfile = $viewerProfile ?? null;
$viewerProfileHref = '';
$viewerProfileLabel = '';
if (is_array($viewerProfile)) {
    $viewerProfileHref = '/profiles/' . rawurlencode((string) ($viewerProfile['profile_slug'] ?? ''));
    $viewerProfileLabel = 'This key’s profile';
}
$authenticatedIdentityId = is_array($viewerProfile)
    && (($viewerProfile['_authenticated_identity'] ?? true) === true)
    ? strtolower(trim((string) ($viewerProfile['identity_id'] ?? '')))
    : '';
// The server's view of this browser's key; the page script trusts it only when it matches the stored fingerprint.
$viewerIdentityId = is_array($viewerProfile) ? strtolower(trim((string) ($viewerProfile['identity_id'] ?? ''))) : '';
$viewerApproved = is_array($viewerProfile) && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1;
$usernameChangesEnabled = $usernameChangesEnabled ?? false;
?>
<section class="stack account-page" data-account-key-root data-account-state="guest" data-sky="account" data-username-changes-enabled="<?= $usernameChangesEnabled ? '1' : '0' ?>" data-authenticated-username="<?= $e($authenticatedIdentityId !== '' ? (string) ($viewerProfile['username'] ?? '') : '') ?>" data-private-site-auth-state data-authenticated-identity-id="<?= $e($authenticatedIdentityId) ?>" data-viewer-identity-id="<?= $e($viewerIdentityId) ?>" data-viewer-approved="<?= $viewerApproved ? '1' : '0' ?>" data-profile-link-authorized="<?= $viewerProfileHref !== '' ? '1' : '0' ?>">
  <header class="account-key-header">
    <h1>Account</h1>
  </header>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  <div class="account-card" data-account-card="guest" data-tone="neutral">
    <span class="account-avatar" aria-hidden="true">?</span>
    <h2>You’re posting as guest</h2>
    <p class="meta">Pick a name if you like. No email or password.</p>
    <button type="button" class="account-key-primary-button" data-action="generate-browser-key">Choose a name</button>
  </div>
  <div class="account-card account-card--member night" data-account-card="member" data-tone="neutral">
    <div class="account-starfield" data-starfield-slot aria-hidden="true"></div>
    <div class="account-id">
      <span class="account-avatar" aria-hidden="true" data-role="account-initial"></span>
      <div class="account-id__text">
        <p class="account-name" data-role="username-field">guest</p>
        <p class="account-key-line"><span>This browser’s key</span> <span class="chip" data-role="simple-status-badge" hidden></span></p>
      </div>
    </div>
    <p class="meta" data-account-when="waiting">You can post now. Posts from this key show as pending until a member approves it.</p>
    <p class="account-user-link" data-account-when="approved"><a data-role="user-link" href="/users/">All of <span data-role="user-link-name">your</span> posts</a></p>
<?php if (($testingSelfApprovalToken ?? null) !== null): ?>
    <p><button type="button" class="account-key-secondary-button" data-test-self-approval="<?= $e($testingSelfApprovalToken) ?>">Approve current account</button></p>
<?php endif; ?>
  </div>
  <p class="meta account-key-simple-status" id="simple-status" role="status" hidden></p>
  <p class="meta" data-role="private-site-auth-status" hidden></p>
  <div class="account-group" data-account-when="member">
    <div class="account-row" data-tone="neutral">
      <span class="account-row__title">Name</span>
      <span class="account-row__value" data-role="name-row-value">guest</span>
      <button type="button" class="account-row__action" data-action="rename-profile"<?= $usernameChangesEnabled ? '' : ' hidden' ?>>Change</button>
    </div>
    <div class="account-row" data-tone="neutral">
      <span class="account-row__title">Fingerprint</span>
      <code class="account-row__value account-key-fingerprint-value" data-role="fingerprint-field">No key yet</code>
      <button type="button" class="account-row__action account-key-viewer-copy" data-action="copy-fingerprint" hidden>Copy</button>
    </div>
  </div>
  <div class="account-group">
    <a class="account-row account-row--link" data-tone="neutral" href="#key-backup" data-backup-open="backup" data-account-when="member">Save a copy of this key</a>
    <a class="account-row account-row--link" data-tone="neutral" href="#key-backup" data-backup-open="restore">Use a key from another device</a>
  </div>
  <details class="account-key-advanced" id="key-backup" data-key-backup>
    <summary>Save or use a copy</summary>
    <div class="stack">
      <div class="account-key-tabs" role="tablist" aria-label="Save or use a copy of a key">
        <button type="button" role="tab" id="key-backup-tab" aria-controls="key-backup-panel" aria-selected="false" tabindex="-1" data-backup-tab="backup">Backup</button>
        <button type="button" role="tab" id="key-restore-tab" aria-controls="key-restore-panel" aria-selected="true" data-backup-tab="restore">Restore</button>
      </div>
      <div class="stack" id="key-backup-panel" role="tabpanel" aria-labelledby="key-backup-tab" data-backup-panel="backup" hidden>
        <p class="meta">Use it on another device or after clearing this browser, with no new approval and your messages intact. Forgotten passphrases cannot be recovered.</p>
        <label>Backup passphrase <input type="password" data-backup-password autocomplete="new-password"></label>
        <label>Confirm passphrase <input type="password" data-backup-confirm autocomplete="new-password"></label>
        <div class="button-row">
          <button type="button" data-backup-download="text">Download text backup</button>
          <button type="button" data-backup-download="png">Download Starfield PNG</button>
        </div>
        <p class="meta">Keep the original PNG file. Screenshots or edited images may not restore.</p>
      </div>
      <div class="stack" id="key-restore-panel" role="tabpanel" aria-labelledby="key-restore-tab" data-backup-panel="restore">
        <label>Restore from backup <input type="file" data-backup-file accept=".txt,.png,text/plain,image/png"></label>
        <label>Restore passphrase <input type="password" data-restore-password autocomplete="current-password"></label>
        <button type="button" data-backup-restore>Restore backup</button>
      </div>
      <p class="meta" data-backup-status role="status" aria-live="polite"></p>
    </div>
  </details>
  <details class="account-key-advanced" id="key-advanced">
    <summary>Advanced <span class="meta">Keys, linking, storage</span></summary>
    <div class="stack">
      <p class="meta account-key-profile-link" data-role="profile-link-wrap"<?= $viewerProfileHref === '' ? ' hidden' : '' ?>><a data-role="profile-link" href="<?= $e($viewerProfileHref !== '' ? $viewerProfileHref : '/account/key/') ?>"><?= $e($viewerProfileLabel !== '' ? $viewerProfileLabel : 'This key’s profile') ?></a></p>
      <details class="account-key-reveal" id="key-keys">
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
          <div class="button-row" hidden>
            <button type="button" data-action="copy-public-key">Copy Public Key</button>
            <button type="button" data-action="copy-private-key">Copy Private Key</button>
          </div>
        </div>
      </details>
      <details class="account-key-reveal" id="key-link">
        <summary>Link a public key</summary>
        <form method="post" class="stack">
          <label>Public key<textarea name="public_key" rows="6" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----" data-role="public-key-field"></textarea></label>
          <button type="submit">Link identity</button>
        </form>
      </details>
      <details class="account-key-reveal" id="key-technical">
        <summary>Storage and privacy</summary>
        <div class="stack">
          <ul class="meta account-key-where">
            <li><strong>This browser:</strong> private key, public key, username, and fingerprint (local storage). Backup passphrases never leave it.</li>
            <li><strong>Server:</strong> your published public key and signed posts.</li>
            <li><strong>Cookie:</strong> an identity hint, kept 30 days, so the server can show which profile this browser last used.</li>
          </ul>
          <p class="meta" data-role="browser-key-status">
            <span data-role="browser-key-status-message">Ready.</span>
            <a href="#" data-action="undo-clear-browser-key" hidden>Undo</a>
          </p>
          <label><input type="checkbox" data-role="automatic-guest-publication"> Publish guest key immediately</label>
          <p class="meta">Otherwise, this browser publishes its public key at your first signed action.</p>
          <p class="meta">
            <strong>Identity hint:</strong> <?= $e(((string) ($identityHint ?? '')) !== '' ? (string) $identityHint : 'none') ?>
            · <strong>Saved browser identity:</strong> <span data-role="identity-id-field">none</span>
          </p>
          <div class="button-row account-key-advanced-actions">
            <button type="button" data-action="clear-browser-key">Clear Saved Keypair</button>
          </div>
        </div>
      </details>
    </div>
  </details>
  <div class="account-group" data-account-when="member">
    <button type="button" class="account-row account-row--danger" data-tone="danger" data-action="clear-browser-identity" hidden>Remove this key</button>
  </div>
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

    function shortFingerprint(fingerprint) {
      var groups = fingerprint.toUpperCase().match(/.{1,4}/g) || [];
      return groups.length <= 4 ? groups.join(' ') : groups.slice(0, 2).join(' ') + ' … ' + groups.slice(-1).join(' ');
    }

    // Matches the read model's `username_token` (saved usernames are already normalized).
    function usernameToken(username) {
      return username.toLowerCase();
    }

    function friendlyStatusMessage(rawMessage) {
      if (!rawMessage || rawMessage === 'Ready.') {
        return '';
      }

      if (rawMessage.indexOf('Browser keypair ready for ') === 0) {
        return '';
      }

      if (rawMessage.indexOf('Restored browser keypair for ') === 0) {
        return '';
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
        return 'Removed this key from this browser.';
      }

      if (rawMessage === 'Restored the recently cleared browser keypair.') {
        return 'This key is back in this browser.';
      }

      return rawMessage;
    }

    // Guest: no saved key, or the automatic `guest` key that was never named.
    // Otherwise waiting or approved; the server's approval counts only for this
    // browser's own key, and an unknown approval is looked up after paint.
    function accountState(root) {
      var fingerprint = storedFingerprint();
      var username = readStorageValue('forum_pki_username') || 'guest';
      var serverKnowsThisKey = fingerprint !== '' && root.dataset.viewerIdentityId === 'openpgp:' + fingerprint;
      var approval = serverKnowsThisKey ? (root.dataset.viewerApproved === '1' ? 'approved' : 'waiting') : (root.dataset.lookedUpApproval || '');
      if (!hasBrowserKeypair() || (username === 'guest' && approval !== 'approved')) {
        return 'guest';
      }
      return approval || 'member';
    }

    function lookUpApproval(root) {
      var fingerprint = storedFingerprint();
      if (fingerprint === '' || root.dataset.lookupFingerprint === fingerprint || !window.fetch) {
        return;
      }
      root.dataset.lookupFingerprint = fingerprint;
      fetch('/api/get_profile?profile_slug=openpgp-' + encodeURIComponent(fingerprint), { credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.text() : ''; })
        .then(function (text) {
          if (storedFingerprint() !== fingerprint) {
            return;
          }
          root.dataset.lookedUpApproval = /^Approved: yes$/m.test(text) ? 'approved' : 'waiting';
          syncSimpleUI();
        })
        .catch(function () {});
    }

    function syncSimpleUI() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      var username = readStorageValue('forum_pki_username') || 'guest';
      var ready = hasBrowserKeypair();
      var fingerprint = storedFingerprint();
      var state = accountState(root);
      var badge = root.querySelector('[data-role="simple-status-badge"]');
      var clearIdentityButton = root.querySelector('[data-action="clear-browser-identity"]');
      var simpleStatus = root.querySelector('#simple-status');
      var fingerprintField = root.querySelector('[data-role="fingerprint-field"]');
      var copyFingerprint = root.querySelector('[data-action="copy-fingerprint"]');
      var userLink = root.querySelector('[data-role="user-link"]');

      root.dataset.accountState = state;
      root.querySelectorAll('[data-role="username-field"], [data-role="name-row-value"]').forEach(function (node) {
        node.textContent = username;
      });
      root.querySelectorAll('[data-role="account-initial"]').forEach(function (node) {
        node.textContent = username.charAt(0);
      });

      if (fingerprintField) {
        fingerprintField.textContent = fingerprint ? shortFingerprint(fingerprint) : 'No key yet';
        fingerprintField.title = fingerprint ? 'openpgp-' + fingerprint : '';
      }

      if (copyFingerprint) {
        copyFingerprint.hidden = fingerprint === '';
      }

      if (badge) {
        badge.hidden = state !== 'waiting' && state !== 'approved';
        badge.textContent = state === 'approved' ? 'Approved' : 'Waiting for approval';
        badge.className = 'chip ' + (state === 'approved' ? 'chip--approved' : 'chip--pending');
      }

      if (userLink) {
        userLink.href = '/user/' + encodeURIComponent(usernameToken(username));
        var name = userLink.querySelector('[data-role="user-link-name"]');
        if (name) {
          name.textContent = username + '’s';
        }
      }

      if (clearIdentityButton) {
        clearIdentityButton.hidden = !ready;
      }

      if (simpleStatus && !simpleStatus.dataset.kind) {
        simpleStatus.textContent = '';
        simpleStatus.hidden = true;
      }

      syncProfileLinks(root);
      if (state === 'member') {
        lookUpApproval(root);
      }
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

      if (!statusNode || !simpleStatus) {
        return;
      }

      simpleStatus.textContent = friendlyStatusMessage(
        statusMessage ? statusMessage.textContent.trim() : statusNode.textContent.trim()
      );
      simpleStatus.hidden = simpleStatus.textContent === '';
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

    // The Save and Use rows open the existing backup panel on the matching tab.
    function bindBackupRows() {
      var root = accountKeyRoot();
      if (!root) {
        return;
      }

      root.querySelectorAll('[data-backup-open]').forEach(function (row) {
        row.addEventListener('click', function (event) {
          var panel = root.querySelector('#key-backup');
          var tab = root.querySelector('[data-backup-tab="' + row.getAttribute('data-backup-open') + '"]');
          if (!panel) {
            return;
          }
          event.preventDefault();
          panel.open = true;
          if (tab) {
            tab.click();
          }
          panel.scrollIntoView({ block: 'start' });
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
      bindFingerprintCopy();
      bindBackupRows();
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

    // Runs before first paint: this script follows the page markup, so the
    // right state card shows from the start.
    syncSimpleUI();
    document.addEventListener('DOMContentLoaded', function () {
      window.setTimeout(initAccountKeySimpleUi, 0);
    });
  })();
</script>
