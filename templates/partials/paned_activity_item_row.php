<?php
/**
 * @var array<string, mixed> $item
 * @var bool $isSelected
 * @var bool $isTabStop
 * @var bool $visible
 */
$itemId = (string) $item['id'];
?>
<div
  class="paned-list-row<?= $isSelected ? ' paned-list-row--selected' : '' ?>"
  data-paned-activity-id="<?= $e($itemId) ?>"
  data-paned-activity-view-all="<?= $item['view_all'] ? '1' : '0' ?>"
  data-paned-activity-view-content="<?= $item['view_content'] ? '1' : '0' ?>"
  data-paned-activity-view-identity="<?= $item['view_identity'] ? '1' : '0' ?>"
  data-paned-activity-view-bootstrap="<?= $item['view_bootstrap'] ? '1' : '0' ?>"
  data-paned-activity-view-approval="<?= $item['view_approval'] ? '1' : '0' ?>"
  role="option"
  aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
  tabindex="<?= $isTabStop ? '0' : '-1' ?>"
  <?= $visible ? '' : 'hidden' ?>
>
  <span class="paned-list-from"><?= $e((string) $item['kind']) ?></span>
  <span class="paned-list-subject"><?= $e((string) ($item['label'] ?? '')) ?></span>
  <span class="paned-list-date"><?= $relativeTimestamp((string) ($item['created_at'] ?? '')) ?></span>
</div>
