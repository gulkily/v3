<?php
/**
 * @var array<int, array{letter: string, count: int}> $letterGroups
 * @var int $totalUserCount
 * @var string $selectedLetter
 */
$selectedLetter ??= '';
?>
<div class="paned-folder-tree" data-paned-users-filter-list role="listbox" aria-label="Users">
  <div
    class="paned-folder-item<?= $selectedLetter === '' ? ' paned-folder-item--selected' : '' ?>"
    data-paned-user-letter=""
    role="option"
    aria-selected="<?= $selectedLetter === '' ? 'true' : 'false' ?>"
    tabindex="<?= $selectedLetter === '' ? '0' : '-1' ?>"
  >
    <span>All Users</span><span class="paned-folder-count"><?= (int) $totalUserCount ?></span>
  </div>
<?php foreach ($letterGroups as $group): ?>
  <div
    class="paned-folder-item<?= $selectedLetter === $group['letter'] ? ' paned-folder-item--selected' : '' ?>"
    data-paned-user-letter="<?= $e($group['letter']) ?>"
    role="option"
    aria-selected="<?= $selectedLetter === $group['letter'] ? 'true' : 'false' ?>"
    tabindex="<?= $selectedLetter === $group['letter'] ? '0' : '-1' ?>"
  >
    <span><?= $e($group['letter']) ?></span><span class="paned-folder-count"><?= (int) $group['count'] ?></span>
  </div>
<?php endforeach; ?>
</div>
