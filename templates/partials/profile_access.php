<?php $isReplyAgentProfile = (string) ($profile['username'] ?? '') === 'reply-agent'; ?>
  <article class="card">
    <p><strong>Visible username:</strong> <?= $e($profile['username']) ?></p>
<?php if ($isReplyAgentProfile): ?>
    <p><strong>Account type:</strong> automated reply agent</p>
<?php endif; ?>
    <?php if ((int) ($profile['is_revoked'] ?? 0) === 1): ?>
    <p><strong>Revoked</strong></p>
<?php endif; ?>
    <p><strong>Approved:</strong> <?= ((int) $profile['is_approved']) === 1 ? 'yes' : 'no' ?></p>
<?php if (((int) $profile['is_approved']) === 1 && ((string) ($profile['approved_by_label'] ?? '')) !== ''): ?>
    <p><strong>Approved by:</strong>
<?php if ((string) ($profile['approved_by_profile_slug'] ?? '') !== ''): ?>
      <a href="/profiles/<?= $e($profile['approved_by_profile_slug']) ?>"><?= $e($profile['approved_by_label']) ?></a>
<?php else: ?>
      <?= $e($profile['approved_by_label']) ?>
<?php endif; ?>
    </p>
<?php endif; ?>
    <p><strong>Threads:</strong> <?= (int) $profile['thread_count'] ?></p>
    <p><strong>Posts:</strong> <?= (int) $profile['post_count'] ?></p>
    <p><strong>Username route:</strong> <a href="/user/<?= $e($profile['username_token']) ?>">/user/<?= $e($profile['username_token']) ?></a></p>
<?php if ($canApprove): ?>
    <form method="post" action="/profiles/<?= $e($profile['profile_slug']) ?>/approve" data-signed-approval-form<?= !empty($accessManagement) ? ' data-approval-return="tools"' : '' ?> data-profile-slug="<?= $e($profile['profile_slug']) ?>">
      <button type="submit"><?= $e($approvalReview['label'] ?? 'Review account key approval') ?></button>
<?php if ($approvalReview !== null): ?>
      <p><?= $e($approvalReview['consequence']) ?></p>
<?php endif; ?>
    </form>
    <p class="meta" data-role="signed-approval-feedback" hidden></p>
<?php endif; ?>
    <details class="account-key-advanced">
      <summary>Advanced / technical details</summary>
      <div class="stack">
        <div>
          <p class="account-key-label">Profile slug</p>
          <p class="meta"><?= $e($profile['profile_slug']) ?></p>
        </div>
        <div>
          <p class="account-key-label">Identity ID</p>
          <p class="meta"><?= $e($profile['identity_id']) ?></p>
        </div>
        <div>
          <p class="account-key-label">Fallback label</p>
          <p class="meta"><?= $e($profile['fallback_label']) ?></p>
        </div>
        <div>
          <p class="account-key-label">Bootstrap post</p>
          <p class="meta">Internal setup artifact; excluded from normal feeds and counts.</p>
          <p><a href="/posts/<?= $e($profile['bootstrap_post_id']) ?>"><?= $e($profile['bootstrap_post_id']) ?></a></p>
        </div>
        <div>
          <p class="account-key-label">Bootstrap thread</p>
          <p><a href="/threads/<?= $e($profile['bootstrap_thread_id']) ?>"><?= $e($profile['bootstrap_thread_id']) ?></a></p>
        </div>
        <div>
          <p class="account-key-label">Public key</p>
          <pre>
<?= $e($profile['public_key']) ?>
          </pre>
        </div>
      </div>
    </details>
  </article>
<?= $partial('partials/approval_history.php', ['approvalHistory' => $approvalHistory, 'historyAccountBase' => $historyAccountBase ?? '/user/', 'historyProfileBase' => $historyProfileBase ?? '/profiles/']) ?>
