<?php
$profileSlug = (string) $profile['profile_slug'];
$headingLabel = trim((string) ($profile['username'] ?? ''));
if ($headingLabel === '') {
    $headingLabel = trim((string) ($profile['fallback_label'] ?? ''));
}
if ($headingLabel === '') {
    $headingLabel = $profileSlug;
}
$isReplyAgentProfile = (string) ($profile['username'] ?? '') === 'reply-agent';
?>
<section class="stack">
  <article class="card">
    <h1><?= $e($headingLabel) ?></h1>
    <p class="meta">Profile <?= $e($profileSlug) ?></p>
<?php if ($isReplyAgentProfile): ?>
    <p class="meta"><span class="agent-label">Automated reply agent</span></p>
<?php endif; ?>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  </article>
<?php if ($isOwnProfile): ?>
  <article class="card">
    <p><strong>This is your profile.</strong></p>
    <p class="meta">Your current browser identity matches this profile.</p>
  </article>
<?php endif; ?>
<?php if ($self): ?>
  <article class="card">
    <p class="meta">Self profile mode</p>
    <p>This route can show account-aware bootstrap context. Current cookie hint: <?= $e($identityHint !== '' ? $identityHint : 'none') ?></p>
  </article>
<?php endif; ?>
  <article class="card">
    <p><strong>Visible username:</strong> <?= $e($profile['username']) ?></p>
<?php if ($isReplyAgentProfile): ?>
    <p><strong>Account type:</strong> automated reply agent</p>
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
    <form method="post" action="/profiles/<?= $e($profile['profile_slug']) ?>/approve">
      <button type="submit">Approve user</button>
    </form>
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
</section>
