<section class="stack">
  <article class="card">
    <h1>Activity</h1>
    <p class="meta">View: <?= $e($view) ?></p>
  </article>
<?php foreach ($items as $item): ?>
  <article class="card">
    <p class="meta"><?= $e($item['kind']) ?></p>
    <p><a href="/posts/<?= $e($item['post_id']) ?>"><?= $e($item['post_id']) ?></a></p>
    <p><?= $e($item['label']) ?></p>
  </article>
<?php endforeach; ?>
</section>
