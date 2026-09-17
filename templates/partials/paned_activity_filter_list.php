<?php
/**
 * @var array<int, array{key: string, label: string, count: int}> $viewCounts
 * @var string $selectedView
 */
$selectedView ??= 'all';
?>
<div class="paned-folder-tree" data-paned-activity-filter-tree role="listbox" aria-label="Activity views">
<?php foreach ($viewCounts as $view): ?>
  <div
    class="paned-folder-item<?= $selectedView === $view['key'] ? ' paned-folder-item--selected' : '' ?>"
    data-paned-activity-view="<?= $e($view['key']) ?>"
    role="option"
    aria-selected="<?= $selectedView === $view['key'] ? 'true' : 'false' ?>"
    tabindex="<?= $selectedView === $view['key'] ? '0' : '-1' ?>"
  >
    <span><?= $e($view['label']) ?></span><span class="paned-folder-count"><?= (int) $view['count'] ?></span>
  </div>
<?php endforeach; ?>
</div>
