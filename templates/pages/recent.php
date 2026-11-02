<section class="stack">
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($items === []): ?>
  <div class="board-empty">
    <h2>No posts<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?></h2>
  </div>
<?php else: ?>
  <div class="thread-rows">
<?php foreach ($items as $item): ?>
<?= $indent($partial('partials/recent_entry.php', ['item' => $item]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination]), 1) ?>
</section>
