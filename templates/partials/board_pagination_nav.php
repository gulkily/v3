<div class="nav pagination-nav">
<?php foreach ($pagination as $item): ?>
<?php if ($item['type'] === 'ellipsis'): ?>
  <span class="pagination-ellipsis">&hellip;</span>
<?php elseif ($item['type'] === 'page' && $item['is_active']): ?>
  <span class="nav-link is-active"><?= $e($item['label']) ?></span>
<?php else: ?>
  <a class="nav-link" href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?></a>
<?php endif; ?>
<?php endforeach; ?>
</div>
