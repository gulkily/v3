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
  <article class="card">
    <h1>Lobby</h1>
<?php if ($viewerProfile === null): ?>
    <p>This site is for approved members. Set up or select your browser key to continue.</p>
<?php elseif ($hasMembersOnlyAccess): ?>
    <p>Your account is approved. <a href="/">Enter the site</a>.</p>
<?php elseif (((int) ($viewerProfile['is_approved'] ?? 0)) === 1): ?>
    <p>Your identity is recognized in the lobby, but member access is cleared.</p>
    <p>Use <a href="/account/key/">Account</a> to authenticate again, or view <a href="/profiles/<?= $e((string) ($viewerProfile['profile_slug'] ?? '')) ?>">your profile</a>.</p>
<?php else: ?>
    <p>Your identity is set up, but approval is still pending.</p>
    <p>Use <a href="/account/key/">Account</a> to review your identity and profile.</p>
<?php endif; ?>
    <p class="meta" data-role="private-site-auth-status" hidden></p>
  </article>
</section>
