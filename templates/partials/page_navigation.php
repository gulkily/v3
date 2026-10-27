<?php if ($pagination['previous_href'] !== null || $pagination['next_href'] !== null || $pagination['restart_href'] !== null): ?>
<nav class="nav pagination-nav" aria-label="<?= $e($paginationLabel ?? 'Pagination') ?>">
<?php if ($pagination['previous_href'] !== null): ?>
  <a class="nav-link" rel="prev" href="<?= $e($pagination['previous_href']) ?>">Previous</a>
<?php endif; ?>
<?php if ($pagination['next_href'] !== null): ?>
  <a class="nav-link" rel="next" href="<?= $e($pagination['next_href']) ?>">Next</a>
<?php endif; ?>
<?php if ($pagination['restart_href'] !== null): ?>
  <a class="nav-link" href="<?= $e($pagination['restart_href']) ?>">First page</a>
<?php endif; ?>
</nav>
<?php endif; ?>
