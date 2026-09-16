<?php
/**
 * @var array<string, mixed> $profile
 */
$headingLabel = trim((string) ($profile['username'] ?? ''));
if ($headingLabel === '') {
    $headingLabel = trim((string) ($profile['fallback_label'] ?? ''));
}
if ($headingLabel === '') {
    $headingLabel = (string) $profile['profile_slug'];
}
$isApproved = ((int) $profile['is_approved']) === 1;
?>
<div class="paned-window paned-standalone-window">
  <div class="paned-dialog-titlebar">
    <span>Profile: <?= $e($headingLabel) ?></span>
  </div>
  <div class="paned-standalone-body">
    <p><strong>Visible username:</strong> <?= $e($profile['username']) ?></p>
    <p><strong>Approved:</strong> <?= $isApproved ? 'yes' : 'no' ?></p>
<?php if ($isApproved && (string) ($profile['approved_by_label'] ?? '') !== ''): ?>
    <p><strong>Approved by:</strong>
<?php if ((string) ($profile['approved_by_profile_slug'] ?? '') !== ''): ?>
      <a href="/forte/profiles/<?= $e($profile['approved_by_profile_slug']) ?>"><?= $e($profile['approved_by_label']) ?></a>
<?php else: ?>
      <?= $e($profile['approved_by_label']) ?>
<?php endif; ?>
    </p>
<?php endif; ?>
    <p><strong>Threads:</strong> <?= (int) $profile['thread_count'] ?></p>
    <p><strong>Posts:</strong> <?= (int) $profile['post_count'] ?></p>
<?php if ($isApproved && (string) ($profile['username_token'] ?? '') !== ''): ?>
    <p><strong>Username route:</strong> <a href="/forte/user/<?= $e($profile['username_token']) ?>">/forte/user/<?= $e($profile['username_token']) ?></a></p>
<?php endif; ?>
    <details class="paned-standalone-advanced">
      <summary>Advanced / technical details</summary>
      <div class="paned-standalone-advanced-body">
        <p><strong>Profile slug:</strong> <?= $e($profile['profile_slug']) ?></p>
        <p><strong>Identity ID:</strong> <?= $e($profile['identity_id']) ?></p>
        <p><strong>Bootstrap thread:</strong> <a href="/forte?selected=<?= $e($profile['bootstrap_thread_id']) ?>"><?= $e($profile['bootstrap_thread_id']) ?></a></p>
        <p><strong>Public key</strong></p>
        <pre><?= $e($profile['public_key']) ?></pre>
      </div>
    </details>
    <p class="paned-standalone-back"><a href="/forte">&larr; Back to board</a></p>
  </div>
</div>
