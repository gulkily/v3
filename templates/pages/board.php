<section class="stack">
  <article class="card">
    <h1>Board</h1>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
  <article class="card">
    <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h2>
    <p><?= $br($thread['body_preview']) ?></p>
    <p class="meta"><?= (int) $thread['reply_count'] ?> replies</p>
  </article>
<?php endforeach; ?>
</section>
