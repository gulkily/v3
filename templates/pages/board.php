<section class="stack">
  <article class="card">
    <h1>Board</h1>
    <div class="button-row button-row-natural button-row-split">
      <a class="nav-link" href="/tags/">Tags</a>
      <a class="nav-link" href="/compose/thread">New Post</a>
    </div>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
  <article class="card">
    <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h2>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
<?php if ($thread['thread_labels'] !== []): ?>
    <p class="meta">Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?></p>
<?php endif; ?>
    <p><?= $br($thread['body_preview']) ?></p>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
