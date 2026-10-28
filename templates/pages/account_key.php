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
<section class="stack account-page" data-account-key-root data-account-state="guest" data-sky="account" data-username-changes-enabled="<?= $usernameChangesEnabled ? '1' : '0' ?>" data-authenticated-username="<?= $e($authenticatedIdentityId !== '' ? (string) ($viewerProfile['username'] ?? '') : '') ?>" data-private-site-auth-state data-auth-in-place<?= ($testingSelfApprovalEnabled ?? false) && ($testingSelfApprovalToken ?? null) === null ? ' data-testing-self-approval-pending' : '' ?> data-authenticated-identity-id="<?= $e($authenticatedIdentityId) ?>" data-viewer-identity-id="<?= $e($viewerIdentityId) ?>" data-viewer-approved="<?= $viewerApproved ? '1' : '0' ?>" data-profile-link-authorized="<?= $viewerProfileHref !== '' ? '1' : '0' ?>">
  <header class="account-key-header">
    <h1>Account</h1>
  </header>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  <div class="account-card" data-account-card="guest" data-tone="neutral">
    <span class="account-avatar" aria-hidden="true">?</span>
    <h2>You’re posting as <span data-role="guest-name">guest</span></h2>
    <p class="meta">Pick a name if you like. No email or password.</p>
    <label class="account-name-field">Name <span class="meta">(optional)</span><input type="text" data-role="account-name-input" autocomplete="nickname" autocapitalize="none" spellcheck="false" maxlength="64"></label>
    <button type="button" class="account-key-primary-button" data-action="generate-browser-key">Continue</button>
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
    <p class="meta" data-role="join-hint" hidden>Approved members already use <span data-role="join-hint-name"></span>. Posts from this key join <span data-role="join-hint-name"></span> once a member approves it.</p>
    <p class="account-user-link" data-account-when="approved"><a data-role="user-link" href="/users/">All of <span data-role="user-link-name">your</span> posts</a></p>
<?php if (($testingSelfApprovalToken ?? null) !== null): ?>
    <p><button type="button" class="account-key-secondary-button" data-test-self-approval="<?= $e($testingSelfApprovalToken) ?>">Approve current account</button></p>
<?php endif; ?>
  </div>
  <p class="meta account-key-simple-status" id="simple-status" role="status" hidden></p>
  <p class="account-retry"><button type="button" class="account-key-secondary-button" data-action="retry-account-action" hidden>Retry</button><button type="button" class="account-key-secondary-button" data-action="undo-remove" hidden>Undo</button></p>
  <p class="meta" data-role="private-site-auth-status" hidden></p>
  <div class="account-group" data-account-when="member">
    <div class="account-row" data-tone="neutral">
      <span class="account-row__title">Name</span>
      <span class="account-row__value" data-role="name-row-value">guest</span>
      <button type="button" class="account-row__action" data-action="rename-profile"<?= $usernameChangesEnabled ? '' : ' hidden' ?>>Change</button>
    </div>
    <form class="account-name-editor" data-role="name-editor" hidden>
      <label>Name <input type="text" data-role="name-editor-input" autocomplete="nickname" autocapitalize="none" spellcheck="false" maxlength="64" required></label>
      <p class="meta">Posts from this key move to the new name.</p>
      <div class="button-row">
        <button type="button" class="account-key-secondary-button" data-action="cancel-name">Cancel</button>
        <button type="submit">Save</button>
      </div>
    </form>
    <div class="account-row" data-tone="neutral">
      <span class="account-row__title">Fingerprint</span>
      <code class="account-row__value account-key-fingerprint-value" data-role="fingerprint-field">No key yet</code>
      <button type="button" class="account-row__action account-key-viewer-copy" data-action="copy-fingerprint" hidden>Copy</button>
    </div>
  </div>
  <div class="account-group">
    <a class="account-row account-row--link" data-tone="neutral" href="#key-backup" data-backup-open="backup" data-account-when="member"><span class="account-row__title">Save a copy of this key</span><span class="account-row__value" data-role="saved-copy-date"></span></a>
    <a class="account-row account-row--link" data-tone="neutral" href="#key-backup" data-backup-open="restore">Use a key from another device</a>
  </div>
  <dialog class="account-sheet" id="key-backup" data-key-backup aria-labelledby="key-backup-title">
    <form method="dialog" class="stack">
      <div class="stack" data-backup-panel="backup" hidden>
        <h2 id="key-backup-title">Save a copy of this key</h2>
        <p class="meta">Use it on another device or after clearing this browser, with no new approval and your messages intact. The passphrase can’t be recovered if you forget it.</p>
        <label>Passphrase <input type="password" data-backup-password autocomplete="new-password"></label>
        <p class="account-strength meta" data-role="passphrase-strength" aria-live="polite"><meter min="0" max="4" value="0" data-role="passphrase-meter"></meter> <span data-role="passphrase-strength-label"></span></p>
        <label>Confirm passphrase <input type="password" data-backup-confirm autocomplete="new-password"></label>
        <fieldset class="account-format">
          <legend>Format</legend>
          <label><input type="radio" name="backup-format" value="text" data-backup-format checked> Text file</label>
          <label><input type="radio" name="backup-format" value="png" data-backup-format> Starfield image</label>
        </fieldset>
        <p class="meta" data-role="format-note">A small text file.</p>
        <button type="button" data-backup-download>Download copy</button>
      </div>
      <div class="stack" data-backup-panel="restore" hidden>
        <h2 id="key-restore-title">Use a key from another device</h2>
        <p class="meta">Choose a saved copy of a key and enter its passphrase.</p>
        <label>Saved copy <input type="file" data-backup-file accept=".txt,.png,text/plain,image/png"></label>
        <label>Passphrase <input type="password" data-restore-password autocomplete="current-password"></label>
        <button type="button" data-backup-restore>Use this key</button>
      </div>
      <p class="meta" data-backup-status role="status" aria-live="polite"></p>
      <button type="submit" class="account-key-secondary-button" value="close" data-action="close-sheet">Close</button>
    </form>
  </dialog>
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
          <p class="meta">
            <strong>Identity hint:</strong> <?= $e(((string) ($identityHint ?? '')) !== '' ? (string) $identityHint : 'none') ?>
            · <strong>Saved browser identity:</strong> <span data-role="identity-id-field">none</span>
          </p>
        </div>
      </details>
    </div>
  </details>
  <div class="account-group" data-account-when="member">
    <button type="button" class="account-row account-row--danger" data-tone="danger" data-action="remove-key" hidden>Remove this key</button>
  </div>
  <dialog class="account-sheet" id="key-remove" data-role="remove-dialog" aria-labelledby="key-remove-title" aria-describedby="key-remove-text">
    <form method="dialog" class="stack">
      <h2 id="key-remove-title">Remove this key from this browser?</h2>
      <p id="key-remove-text" data-role="remove-consequence">Your posts stay under your name.</p>
      <button type="button" data-backup-open="backup" data-role="remove-save-first">Save a copy first</button>
      <button type="submit" class="account-danger-button" value="remove" data-action="clear-browser-identity">Remove</button>
      <button type="submit" class="account-key-secondary-button" value="cancel">Cancel</button>
    </form>
  </dialog>
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

    // A key is registered once the server has it; until then it stays in this browser.
    function isRegistered(root) {
      var fingerprint = storedFingerprint();
      return fingerprint !== '' && (readStorageValue('forum_pki_published_fingerprint').trim().toLowerCase() === fingerprint
        || root.dataset.viewerIdentityId === 'openpgp:' + fingerprint);
    }

    // Guest: no saved key, a key not yet registered (shown under its name), or
    // the automatic `guest` key that was never named.
    // Otherwise waiting or approved; the server's approval counts only for this
    // browser's own key, and an unknown approval is looked up after paint.
    function accountState(root) {
      var fingerprint = storedFingerprint();
      var username = readStorageValue('forum_pki_username') || 'guest';
      var serverKnowsThisKey = fingerprint !== '' && root.dataset.viewerIdentityId === 'openpgp:' + fingerprint;
      var approval = serverKnowsThisKey ? (root.dataset.viewerApproved === '1' ? 'approved' : 'waiting') : (root.dataset.lookedUpApproval || '');
      if (!hasBrowserKeypair() || !isRegistered(root) || (username === 'guest' && approval !== 'approved')) {
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
      var clearIdentityButton = root.querySelector('[data-action="remove-key"]');
      var simpleStatus = root.querySelector('#simple-status');
      var fingerprintField = root.querySelector('[data-role="fingerprint-field"]');
      var copyFingerprint = root.querySelector('[data-action="copy-fingerprint"]');
      var userLink = root.querySelector('[data-role="user-link"]');

      root.dataset.accountState = state;
      root.querySelectorAll('[data-role="username-field"], [data-role="name-row-value"], [data-role="guest-name"]').forEach(function (node) {
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
      syncJoinHint(root, state, username);
      syncSaveCopy(root, fingerprint);
    }

    // The Save row shows when this browser last saved a copy of this key.
    function syncSaveCopy(root, fingerprint) {
      var value = root.querySelector('[data-role="saved-copy-date"]');
      if (!value) {
        return;
      }
      var saved = fingerprint ? readStorageValue('forum_pki_backup_verified_' + fingerprint) : '';
      var date = /^\d{4}-\d{2}-\d{2}$/.test(saved) ? new Date(saved + 'T00:00:00') : null;
      value.textContent = saved === '' ? '' : 'Saved' + (date ? ' ' + date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '');
    }

    // A waiting key whose name approved members already use joins that user once approved.
    function syncJoinHint(root, state, username) {
      var hint = root.querySelector('[data-role="join-hint"]');
      if (!hint) {
        return;
      }
      var token = usernameToken(username);
      if (state !== 'waiting' || token === 'guest') {
        hint.hidden = true;
        return;
      }
      if (root.dataset.joinHintFor === token) {
        hint.hidden = root.dataset.joinHintShown !== '1';
        return;
      }
      root.dataset.joinHintFor = token;
      root.dataset.joinHintShown = '0';
      hint.hidden = true;
      if (!window.fetch) {
        return;
      }
      fetch('/api/get_username?username=' + encodeURIComponent(token), { credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.text() : ''; })
        .then(function (text) {
          var match = /^Approved-Keys: (\d+)$/m.exec(text);
          if (root.dataset.joinHintFor !== token || !match || Number(match[1]) < 1) {
            return;
          }
          root.dataset.joinHintShown = '1';
          hint.querySelectorAll('[data-role="join-hint-name"]').forEach(function (node) {
            node.textContent = username;
          });
          hint.hidden = root.dataset.accountState !== 'waiting';
        })
        .catch(function () {});
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
        wrap.hidden = privateSiteEnabled ? !serverAllowsProfileLink : !isRegistered(root);
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
      var undoLink = statusNode.querySelector('[data-action="undo-clear-browser-key"]');
      var undo = root.querySelector('[data-action="undo-remove"]');
      if (undo && undoLink) {
        undo.hidden = undoLink.hidden;
      }
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

    // Remove this key asks first, saying what stays and what a new key would need.
    function bindRemoveDialog() {
      var root = accountKeyRoot();
      var open = root && root.querySelector('[data-action="remove-key"]');
      var dialog = root && root.querySelector('[data-role="remove-dialog"]');
      if (!open || !dialog) {
        return;
      }
      var saveFirst = dialog.querySelector('[data-role="remove-save-first"]');

      open.addEventListener('click', function () {
        var name = readStorageValue('forum_pki_username') || 'guest';
        var approved = root.dataset.accountState === 'approved';
        var saved = readStorageValue('forum_pki_backup_verified_' + storedFingerprint()) !== '';
        dialog.querySelector('[data-role="remove-consequence"]').textContent = approved
          ? 'Your posts stay under ' + name + '. To post as ' + name + ' again, a new key will wait for a member’s approval' + (saved ? '' : ', and messages sent to this key can’t be read') + '.'
          : 'Your posts stay under ' + name + '. A new key with this name will also wait for a member’s approval.';
        saveFirst.hidden = !approved || saved;
        if (typeof dialog.showModal === 'function') {
          dialog.showModal();
        } else {
          dialog.setAttribute('open', '');
        }
      });
      // Save a copy first closes this dialog before the copy sheet opens.
      saveFirst.addEventListener('click', function () {
        dialog.close();
      }, true);
    }

    // Undo is shown beside the removal message rather than inside Advanced.
    function bindUndo() {
      var root = accountKeyRoot();
      var undo = root && root.querySelector('[data-action="undo-remove"]');
      var link = root && root.querySelector('[data-action="undo-clear-browser-key"]');
      if (!undo || !link) {
        return;
      }
      undo.addEventListener('click', function () {
        link.click();
      });
    }

    // Change opens the name editor in place; Cancel and Escape close it.
    function bindNameEditor() {
      var root = accountKeyRoot();
      var editor = root && root.querySelector('[data-role="name-editor"]');
      var change = root && root.querySelector('[data-action="rename-profile"]');
      if (!editor || !change) {
        return;
      }
      var input = editor.querySelector('[data-role="name-editor-input"]');

      function close() {
        editor.hidden = true;
        change.focus();
      }

      change.addEventListener('click', function () {
        editor.hidden = false;
        input.value = readStorageValue('forum_pki_username') || '';
        input.focus();
        input.select();
      });
      editor.querySelector('[data-action="cancel-name"]').addEventListener('click', close);
      editor.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          close();
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
      bindNameEditor();
      bindRemoveDialog();
      bindUndo();
      syncSimpleUI();
      mirrorAdvancedStatus();
      window.addEventListener('forum:identity-changed', syncSimpleUI);
      window.addEventListener('forum:identity-changed', mirrorAdvancedStatus);
      window.addEventListener('storage', syncSimpleUI);
      window.addEventListener('storage', mirrorAdvancedStatus);
      window.addEventListener('pageshow', syncSimpleUI);
      window.addEventListener('forum:key-backup-recorded', syncSimpleUI);
      window.addEventListener('forum:authenticated-in-place', function (event) {
        // Testing deployments render "Approve current account" only for a signed-in session.
        if (root.hasAttribute('data-testing-self-approval-pending') && !event.detail.approved) {
          window.location.reload();
          return;
        }
        root.dataset.viewerIdentityId = event.detail.identityId;
        root.dataset.viewerApproved = event.detail.approved ? '1' : '0';
        syncSimpleUI();
      });
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
          attributeFilter: ['data-kind', 'hidden'],
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
