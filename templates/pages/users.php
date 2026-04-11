<section class="stack">
  <article class="card">
    <h1>Users</h1>
  </article>
<?php if ($showPendingLink): ?>
  <article class="card">
    <p><a href="/users/pending/">View users awaiting approval</a></p>
  </article>
<?php endif; ?>
<?php if ($users === []): ?>
  <article class="card">
    <p>No visible users yet.</p>
    <p class="meta">Approved users appear here after someone has visible threads or replies.</p>
  </article>
<?php else: ?>
<?php foreach ($users as $user): ?>
  <article class="card">
    <h2><a href="/user/<?= $e($user['username_token']) ?>"><?= $e($user['username']) ?></a></h2>
    <p class="meta"><?= (int) $user['thread_count'] ?> threads, <?= (int) $user['post_count'] ?> posts</p>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
