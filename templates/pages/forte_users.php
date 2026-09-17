<?php
/**
 * @var array<int, array<string, mixed>> $users
 */
?>
<div class="paned-window">
  <div class="paned-menubar">
    <span>File</span><span>Edit</span><span>View</span><span>Folder</span><span>Navigate</span><span>Help</span>
  </div>
<?= $indent($partial('partials/paned_toolbar.php', [
    'activeView' => 'users',
    'boardControlsEnabled' => false,
    'replyEnabled' => false,
  ]), 1) ?>
  <div class="paned-standalone-body">
<?php if ($users === []): ?>
    <p>No visible users yet.</p>
    <p class="meta">Approved users appear here after someone has visible threads or replies.</p>
<?php else: ?>
<?php foreach ($users as $user): ?>
    <p>
      <a href="/forte/user/<?= $e($user['username_token']) ?>"><?= $e($user['username']) ?></a>
      <span class="meta"><?= (int) $user['thread_count'] ?> threads, <?= (int) $user['post_count'] ?> posts</span>
    </p>
<?php endforeach; ?>
<?php endif; ?>
  </div>
</div>
