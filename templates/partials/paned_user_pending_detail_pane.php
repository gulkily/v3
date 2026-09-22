<?php
/**
 * @var string $usernameToken
 * @var int $pendingProfileCount
 * @var int $pendingThreadCount
 * @var int $pendingPostCount
 */
?>
<article class="paned-content-post" data-paned-user-detail-token="<?= $e($usernameToken) ?>">
  <div class="paned-content-head">
    <div class="paned-content-subject"><?= $e($usernameToken) ?></div>
    <div class="paned-content-meta">
      <span>Pending approval</span>
    </div>
  </div>
  <div class="paned-user-detail-section">
    <p class="meta">This username has not been approved yet.</p>
    <p>
      <?= (int) $pendingProfileCount ?> pending profile<?= $pendingProfileCount === 1 ? '' : 's' ?>,
      <?= (int) $pendingThreadCount ?> thread<?= $pendingThreadCount === 1 ? '' : 's' ?>,
      <?= (int) $pendingPostCount ?> post<?= $pendingPostCount === 1 ? '' : 's' ?> submitted so far.
    </p>
  </div>
</article>
