<section class="stack">
  <article class="card">
    <h1><?= $e($title) ?></h1>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
    <p class="meta">Score: <?= (int) $thread['score_total'] ?></p>
<?php if ($thread['thread_labels'] !== []): ?>
    <p class="meta">Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?></p>
<?php endif; ?>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
  </article>
<?php foreach ($posts as $post): ?>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
<?php endforeach; ?>
</section>
