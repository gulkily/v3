<section class="stack">
  <article class="card">
    <h1>All Tags</h1>
    <div class="nav board-controls-nav">
      <a class="nav-link is-active" href="/tags/">Tags</a>
<?php foreach ($viewOptions as $option): ?>
      <a class="nav-link" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
<?php foreach ($sortOptions as $option): ?>
      <a class="nav-link" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
      <a class="nav-link" href="/compose/thread">New Post</a>
    </div>
  </article>

  <article class="card tags-section-card">
<?php if ($tagGroups === []): ?>
    <p class="meta">No tags yet.</p>
<?php else: ?>
    <div class="tag-groups">
<?php foreach ($tagGroups as $group): ?>
      <section class="tag-group">
        <div class="tag-group-header">
          <h2><a href="<?= $e($group['href']) ?>">#<?= $e($group['tag']) ?></a></h2>
          <p class="meta"><?= (int) $group['count'] ?> <?= (int) $group['count'] === 1 ? 'thread' : 'threads' ?></p>
        </div>
        <ul class="tag-thread-list" data-tag-preview-for="<?= $e($group['tag']) ?>">
<?php foreach ($group['preview_threads'] as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
          <li data-tag-preview-item="<?= $e($group['tag']) ?>">
            <a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a>
            <span class="meta">by <?= $author($thread) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
        <p class="meta tag-group-footer"><a href="<?= $e($group['href']) ?>">View all <?= $e($group['tag']) ?></a><?php if ($group['has_more']): ?> <span>showing 5 newest</span><?php endif; ?></p>
      </section>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </article>
</section>
