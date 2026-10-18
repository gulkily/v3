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
<?php if ($self): ?>
  <article class="card">
    <p class="meta">Self profile mode</p>
    <p>This route can show account-aware bootstrap context. Current cookie hint: <?= $e($identityHint !== '' ? $identityHint : 'none') ?></p>
  </article>
<?php endif; ?>
<?= $partial('partials/profile_access.php') ?>
</section>
