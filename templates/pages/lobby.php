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
    <h2>Board invitation</h2>
    <p>Invitations with a destination are accepted automatically.</p>
    <button type="button" data-action="redeem-invitation">Redeem invitation</button>
    <p class="meta" data-role="invitation-redemption-feedback" role="status" hidden></p>
    <p><a href="/account/key/">Review or restore your account</a> · <a href="/">Go to the board</a></p>
  </article>
</section>
