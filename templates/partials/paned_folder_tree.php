<?php
/**
 * @var array<int, array{tag: string, count: int, threads: array}> $tagGroups
 * @var int $totalThreadCount
 * @var string $selectedTag
 */
$selectedTag ??= '';
?>
<div class="paned-folder-tree" data-paned-folder-tree role="listbox" aria-label="Tags">
  <div
    class="paned-folder-item<?= $selectedTag === '' ? ' paned-folder-item--selected' : '' ?>"
    data-paned-folder=""
    role="option"
    aria-selected="<?= $selectedTag === '' ? 'true' : 'false' ?>"
    tabindex="<?= $selectedTag === '' ? '0' : '-1' ?>"
  >
    <span>All Threads</span><span class="paned-folder-count"><?= (int) $totalThreadCount ?></span>
  </div>
<?php foreach ($tagGroups as $group): ?>
  <div
    class="paned-folder-item<?= $selectedTag === $group['tag'] ? ' paned-folder-item--selected' : '' ?>"
    data-paned-folder="<?= $e($group['tag']) ?>"
    role="option"
    aria-selected="<?= $selectedTag === $group['tag'] ? 'true' : 'false' ?>"
    tabindex="<?= $selectedTag === $group['tag'] ? '0' : '-1' ?>"
  >
    <span>#<?= $e($group['tag']) ?></span><span class="paned-folder-count"><?= (int) $group['count'] ?></span>
  </div>
<?php endforeach; ?>
</div>
