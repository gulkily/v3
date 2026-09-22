<?php
/**
 * @var array<int, array{key: string, label: string, count: int}> $categoryCounts
 * @var string $selectedCategory
 */
$selectedCategory ??= 'all';
?>
<div class="paned-folder-tree" data-paned-users-filter-list role="listbox" aria-label="User categories">
<?php foreach ($categoryCounts as $category): ?>
  <div
    class="paned-folder-item<?= $selectedCategory === $category['key'] ? ' paned-folder-item--selected' : '' ?>"
    data-paned-user-category="<?= $e($category['key']) ?>"
    role="option"
    aria-selected="<?= $selectedCategory === $category['key'] ? 'true' : 'false' ?>"
    tabindex="<?= $selectedCategory === $category['key'] ? '0' : '-1' ?>"
  >
    <span><?= $e($category['label']) ?></span><span class="paned-folder-count"><?= (int) $category['count'] ?></span>
  </div>
<?php endforeach; ?>
</div>
