<section class="stack thread-list">
  <article class="card">
    <p class="eyebrow">Tag</p>
    <h1>#<?= $e($group['tag']) ?></h1>
    <p class="meta"><?= (int) $group['count'] ?> <?= (int) $group['count'] === 1 ? 'thread' : 'threads' ?></p>
    <p class="meta"><a href="/tags/">Back to Tags</a> <span>|</span> <a href="/">Back to Board</a></p>
  </article>

<?php foreach ($group['threads'] as $thread): ?>
<?= $indent($partial('partials/thread_card.php', [
    'thread' => $thread,
    'showLabels' => true,
]), 1) ?>
<?php endforeach; ?>
</section>
