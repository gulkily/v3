<?php
/**
 * @var array<string, mixed> $item
 * @var bool $isSelected
 * @var bool $isTabStop
 * @var bool $visible
 */
$sha = (string) $item['sha'];
// Prefixed and namespaced separately from data-paned-activity-commit-sha:
// activity items and commits are both autoincrement ids from separate
// tables, so their raw ids can collide - "commit-<sha>" can't collide with
// a purely-numeric activity item id.
$rowId = 'commit-' . $sha;
?>
<div
  class="paned-list-row<?= $isSelected ? ' paned-list-row--selected' : '' ?>"
  data-paned-activity-id="<?= $e($rowId) ?>"
  data-paned-activity-commit-sha="<?= $e($sha) ?>"
  data-paned-activity-view-commits="1"
  role="option"
  aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
  tabindex="<?= $isTabStop ? '0' : '-1' ?>"
  <?= $visible ? '' : 'hidden' ?>
>
  <span class="paned-list-from"><?= $e(substr($sha, 0, 12)) ?></span>
  <span class="paned-list-subject"><?= $e((string) ($item['subject'] ?? '')) ?></span>
  <span class="paned-list-date"><?= $relativeTimestamp((string) ($item['committed_at'] ?? '')) ?></span>
</div>
