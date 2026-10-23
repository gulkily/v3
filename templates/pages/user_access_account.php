<section class="stack user-access-account">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
    <h1>Access: <?= $e($usernameToken) ?></h1>
    <p><a href="/user/<?= $e($usernameToken) ?>">Member profile</a></p>
  </article>
  <article class="card">
    <h2>Account keys</h2>
    <ul>
<?php foreach ($profiles as $profile): ?>
      <li><a href="/tools/user-access/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($shortenProfileSlug($profile['profile_slug'])) ?></a> — <?= (int) ($profile['is_revoked'] ?? 0) === 1 ? 'Revoked' : ((int) $profile['is_approved'] === 1 ? 'Approved' : 'Pending') ?>
<?php if ($canRevoke && (int) $profile['is_approved'] === 1 && !in_array($profile['identity_id'], $rootIdentityIds, true)): ?>
        <button type="button" class="key-revocation-danger" data-revoke-key="<?= $e($profile['identity_id']) ?>" aria-label="Revoke key <?= $e($shortenProfileSlug($profile['profile_slug'])) ?>">Revoke key…</button>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </article>
<?php if ($canRevoke): ?>
<?= $partial('partials/key_revocation_review.php') ?>
<?php endif; ?>
<?= $partial('partials/approval_history.php', ['approvalHistory' => $approvalHistory, 'historyAccountBase' => '/tools/user-access/accounts/', 'historyProfileBase' => '/tools/user-access/profiles/']) ?>
</section>
