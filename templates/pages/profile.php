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
    <p class="meta"><?= $e($profileSlug) ?></p>
<?php if ($isReplyAgentProfile): ?>
    <p class="meta"><span class="agent-label">Automated reply agent</span></p>
<?php endif; ?>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  </article>
<?php if ($canPrivateMessage): ?>
<?= $indent($partial('partials/private_message_composer.php', [
    'recipientUsernameToken' => (string) $profile['username_token'],
    'senderUsernameToken' => (string) $viewerProfile['username_token'],
    'recipientLabel' => $headingLabel,
]), 2) ?>
<?php endif; ?>
  <article class="card">
    <p><strong>Approved:</strong> <?= (int) $profile['is_approved'] === 1 ? 'yes' : 'no' ?></p>
<?php if ((int) $profile['is_approved'] === 1 && (string) ($profile['approved_by_label'] ?? '') !== ''): ?>
    <p><strong>Approved by:</strong>
<?php if ((string) ($profile['approved_by_profile_slug'] ?? '') !== ''): ?>
      <a href="/profiles/<?= $e($profile['approved_by_profile_slug']) ?>"><?= $e($profile['approved_by_label']) ?></a>
<?php else: ?>
      <?= $e($profile['approved_by_label']) ?>
<?php endif; ?>
    </p>
<?php endif; ?>
    <p><strong>Threads:</strong> <?= (int) $profileHistory['threads']['total'] ?></p>
    <p><strong>Posts:</strong> <?= (int) $profileHistory['posts']['total'] ?></p>
    <p><a href="/user/<?= $e($profile['username_token']) ?>">User <?= $e($profile['username_token']) ?></a></p>
    <p id="account-keys"><a href="/tools/user-access/profiles/<?= $e($profile['profile_slug']) ?>#account-keys">User Access</a></p>
<?= $indent($partial('partials/public_key.php', ['publicKey' => (string) $profile['public_key']]), 2) ?>
  </article>
<?php foreach (['threads' => 'Threads', 'posts' => 'Posts'] as $kind => $label): ?>
  <article class="card" id="<?= $e($kind) ?>">
    <h2><?= $e($label) ?></h2>
<?php $history = $profileHistory[$kind]; ?>
<?php if ($history['error'] !== null): ?>
    <p role="alert"><?= $e($history['error']) ?></p>
<?php elseif ($history['items'] === []): ?>
    <p>No visible <?= $e($kind) ?>.</p>
<?php else: ?>
<?php foreach ($history['items'] as $item): ?>
    <article class="card">
<?php if ($kind === 'threads'): ?>
      <h3><a href="/threads/<?= $e($item['post_id']) ?>"><?= $e($threadTitle($item)) ?></a></h3>
<?php else: ?>
      <p><a href="/posts/<?= $e($item['post_id']) ?>"><?= $e($item['post_id']) ?></a></p>
<?php endif; ?>
      <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
    </article>
<?php endforeach; ?>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $history['pagination'], 'paginationLabel' => $label . ' pagination']), 2) ?>
  </article>
<?php endforeach; ?>
</section>
