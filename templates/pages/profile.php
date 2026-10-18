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
<?php if ($isOwnProfile): ?>
  <article class="card">
    <p><strong>This is your profile.</strong></p>
    <p class="meta">Your current browser identity matches this profile.</p>
  </article>
<?php endif; ?>
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
    <p><strong>Approved by:</strong> <?= $e($profile['approved_by_label']) ?></p>
<?php endif; ?>
    <p><strong>Threads:</strong> <?= (int) $profile['thread_count'] ?></p>
    <p><strong>Posts:</strong> <?= (int) $profile['post_count'] ?></p>
    <p><a href="/user/<?= $e($profile['username_token']) ?>">Member activity</a></p>
    <p id="account-keys"><a href="/tools/user-access/profiles/<?= $e($profile['profile_slug']) ?>#account-keys">User Access</a></p>
  </article>
</section>
