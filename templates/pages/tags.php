<section class="stack">
<?= $indent($partial('partials/board_toolbar.php', [
    'viewOptions' => $viewOptions,
    'sortOptions' => $sortOptions,
    'sortLabel' => $sortLabel,
    'tagsCurrent' => true,
]), 1) ?>

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
<?php $subject = $threadTitle($thread); ?>
          <li data-tag-preview-item="<?= $e($group['tag']) ?>">
            <a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a>
            <span class="meta">by <?= $author($thread) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
<?php if ($group['has_more']): ?>
        <p class="meta tag-group-footer">showing 5 newest of <?= (int) $group['count'] ?></p>
<?php endif; ?>
      </section>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </article>
</section>
