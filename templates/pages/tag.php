<section class="stack thread-list">
  <article class="card">
    <h1>#<?= $e($group['tag']) ?></h1>
  </article>

<?php foreach ($group['threads'] as $thread): ?>
<?= $indent($partial('partials/thread_card.php', [
    'thread' => $thread,
    'showLabels' => true,
]), 1) ?>
<?php endforeach; ?>
  <p class="meta"><a href="/tags/">Back to Tags</a> <span>|</span> <a href="/">Back to Board</a></p>
</section>
