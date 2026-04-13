<section class="stack">
  <article class="card">
    <h1>Activity</h1>
    <p class="meta">View: <?= $e($view) ?></p>
    <div class="nav">
<?php foreach ($viewOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
<?php foreach ($items as $item): ?>
  <article class="card">
    <p class="meta"><?= $e($item['kind']) ?></p>
    <p><a href="/posts/<?= $e($item['post_id']) ?>"><?= $e($item['post_id']) ?></a></p>
    <p><?= $e($item['label']) ?></p>
    <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
  </article>
<?php endforeach; ?>
</section>
