<section class="recent">
  <h1>Recent</h1>
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($items === []): ?>
  <p>No posts<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?>.</p>
<?php endif; ?>
  <div class="recent-list">
<?php foreach ($items as $item): ?>
<?= $partial('partials/recent_entry.php', ['item' => $item]) ?>
<?php endforeach; ?>
  </div>
<?= $partial('partials/page_navigation.php', ['pagination' => $pagination]) ?>
</section>
