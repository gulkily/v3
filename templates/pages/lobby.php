<?php
$viewerProfile = is_array($viewerProfile ?? null) ? $viewerProfile : null;
$authenticatedIdentityId = $viewerProfile !== null
    && (($viewerProfile['_authenticated_identity'] ?? true) === true)
    ? strtolower(trim((string) ($viewerProfile['identity_id'] ?? '')))
    : '';
$hasMembersOnlyAccess = $viewerProfile !== null
    && (($viewerProfile['_members_only_access'] ?? (((int) ($viewerProfile['is_approved'] ?? 0)) === 1)) === true);
?>
<section class="stack" data-private-site-auth-state data-authenticated-identity-id="<?= $e($authenticatedIdentityId) ?>">
  <article class="card" data-lobby-card>
    <h1>Lobby</h1>
<?php if ($viewerProfile === null): ?>
    <p>This site is for approved members. <a href="/account/key/">Sign in or set up your account</a>.</p>
<?php elseif ($hasMembersOnlyAccess): ?>
    <p><a href="/">Enter the site</a></p>
<?php elseif (((int) ($viewerProfile['is_approved'] ?? 0)) === 1): ?>
    <p><a href="/account/key/">Sign in again</a> to enter the site.</p>
<?php else: ?>
    <p>Approval pending.</p>
<?php endif; ?>
    <p class="meta" data-role="private-site-auth-status" hidden></p>
  </article>
  <article class="card" data-invitation-redemption hidden>
    <h1>Redeem invitation</h1>
    <form data-invitation-name-form>
      <div data-invitation-name-field hidden>
      <label>Username
        <input type="text" name="username" data-invitation-username autocomplete="nickname" autocapitalize="none" spellcheck="false" maxlength="80">
      </label>
      </div>
      <button type="submit" data-action="redeem-invitation">Redeem invitation</button>
    </form>
    <p class="meta" data-role="invitation-redemption-feedback" role="status" hidden></p>
  </article>
</section>

<script>
(function () {
  var root = document.querySelector('[data-invitation-redemption]');
  var match = window.location.hash.match(/^#invite=(.*)$/);
  if (!root || !match) return;
  root.hidden = false;
  // An invite visitor only needs the redemption form, so the lobby card goes away.
  var lobby = document.querySelector('[data-lobby-card]');
  if (lobby) lobby.hidden = true;
  // Decide the name control from this browser before paint; server HTML stays static.
  try {
    var name = localStorage.getItem('forum_pki_username') || 'guest';
    var needsName = name === 'guest' || !localStorage.getItem('forum_pki_private_key');
    root.querySelector('[data-invitation-name-field]').hidden = !needsName;
    root.querySelector('[data-invitation-username]').value = sessionStorage.getItem('forum_invite_name:' + match[1]) || '';
  } catch (error) {
    root.querySelector('[data-invitation-name-field]').hidden = false;
  }
})();
</script>
