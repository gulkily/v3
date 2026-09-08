<?php
/**
 * @var array<int, array{tag: string, count: int, threads: array}> $tagGroups
 * @var int $totalThreadCount
 */
?>
<div class="paned-folder-tree" data-paned-folder-tree>
  <div class="paned-folder-item paned-folder-item--selected" data-paned-folder="">
    <span>All Threads</span><span class="paned-folder-count"><?= (int) $totalThreadCount ?></span>
  </div>
<?php foreach ($tagGroups as $group): ?>
  <div class="paned-folder-item" data-paned-folder="<?= $e($group['tag']) ?>">
    <span>#<?= $e($group['tag']) ?></span><span class="paned-folder-count"><?= (int) $group['count'] ?></span>
  </div>
<?php endforeach; ?>
</div>
