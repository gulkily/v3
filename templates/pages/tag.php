<section class="stack thread-list">
  <article class="card">
    <h1><?= $e($jobCategory['title'] ?? '#' . $group['tag']) ?></h1>
<?php if (isset($jobCategory)): ?>
    <p><?= $e($jobCategory['description']) ?></p>
<?php if ($group['threads'] === []): ?>
    <p>No listings on this page.</p>
<?php endif; ?>
<?php endif; ?>
  </article>

<?php if ($paginationError !== null): ?>
  <p class="meta"><?= $e($paginationError) ?></p>
<?php endif; ?>
<?php foreach ($group['threads'] as $thread): ?>
<?= $indent($partial('partials/thread_card.php', [
    'thread' => $thread,
    'showLabels' => true,
]), 1) ?>
<?php endforeach; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination]), 1) ?>
  <p class="meta"><a href="/tags/">Back to Tags</a> <span>|</span> <a href="/">Back to Board</a></p>
</section>
