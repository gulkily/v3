<section class="stack">
  <article class="card">
    <h1>Board</h1>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
  <article class="card">
    <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h2>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', 'Started') ?></p>
    <p class="meta"><?= $timeMeta('Last activity', (string) $thread['last_activity_at']) ?></p>
    <p><?= $br($thread['body_preview']) ?></p>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
