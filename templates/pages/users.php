<section class="stack">
  <h1>Users</h1>
<?php if ($showPendingLink): ?>
  <article class="card">
    <p><a href="/users/pending/">View users awaiting approval</a></p>
  </article>
<?php endif; ?>
<?php if ($profiles === []): ?>
  <article class="card">
    <p>No visible users yet.</p>
    <p class="meta">Approved profiles appear here after someone has visible threads or replies.</p>
  </article>
<?php else: ?>
<?php foreach ($profiles as $profile): ?>
  <article class="card">
    <h2><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['username']) ?></a></h2>
    <p><strong>Profile:</strong> <a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></p>
    <p><strong>Username route:</strong> <a href="/user/<?= $e($profile['username_token']) ?>">/user/<?= $e($profile['username_token']) ?></a></p>
    <p class="meta"><?= (int) $profile['thread_count'] ?> threads, <?= (int) $profile['post_count'] ?> posts</p>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
