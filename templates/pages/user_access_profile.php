<?php $isReplyAgentProfile = (string) ($profile['username'] ?? '') === 'reply-agent'; ?>
<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
    <h1>Access: <?= $e($profile['username']) ?></h1>
    <p class="meta"><?= $e($profile['profile_slug']) ?></p>
    <p><a href="/tools/user-access/accounts/<?= $e($profile['username_token']) ?>">Account keys</a></p>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  </article>
<?= $partial('partials/profile_access.php', ['historyAccountBase' => '/tools/user-access/accounts/', 'historyProfileBase' => '/tools/user-access/profiles/']) ?>
</section>
