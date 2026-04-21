<section class="stack">
  <article class="card">
    <p class="eyebrow">Tag</p>
    <h1>#<?= $e($group['tag']) ?></h1>
    <p class="meta"><?= (int) $group['count'] ?> <?= (int) $group['count'] === 1 ? 'thread' : 'threads' ?></p>
    <p class="meta"><a href="/tags/">Back to Tags</a> <span>|</span> <a href="/">Back to Board</a></p>
  </article>

<?php foreach ($group['threads'] as $thread): ?>
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
