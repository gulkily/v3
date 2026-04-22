<section class="stack">
  <article class="card">
    <h1>Board</h1>
    <div class="nav board-controls-nav">
      <a class="nav-link" href="/tags/">Tags</a>
<?php foreach ($viewOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
<?php foreach ($sortOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
      <a class="nav-link" href="/compose/thread">New Post</a>
    </div>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
  <article class="card">
    <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h2>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
    <p><?= $br($thread['body_preview']) ?></p>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
