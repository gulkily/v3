<?php
/**
 * @var array<int, array<string, mixed>> $users
 */
?>
<div class="paned-window paned-standalone-window">
  <div class="paned-dialog-titlebar">
    <span>Users</span>
  </div>
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
    <p class="paned-standalone-back"><a href="/forte">&larr; Back to board</a></p>
  </div>
</div>
