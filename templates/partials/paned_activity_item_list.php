<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var string $selectedView
 * @var string $selectedItemId
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
$tabStopAssigned = false;
?>
<div class="paned-list-pane">
  <div class="paned-list-head">
    <span class="paned-list-from-head">Kind</span>
    <span class="paned-list-subject-head">Label</span>
    <span class="paned-list-date-head">Date</span>
  </div>
  <div class="paned-list-body" data-paned-activity-list-body role="listbox" aria-label="Activity items">
<?php foreach ($items as $item): ?>
<?php
$itemId = (string) $item['id'];
$visible = (bool) ($item['view_' . $selectedView] ?? false);
$isSelected = $selectedItemId !== '' && $itemId === $selectedItemId;
$isTabStop = $selectedItemId !== '' ? $isSelected : ($visible && !$tabStopAssigned);
if ($isTabStop) {
    $tabStopAssigned = true;
}
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
      <span class="paned-list-date"><?= $timestamp((string) ($item['created_at'] ?? '')) ?></span>
    </div>
<?php endforeach; ?>
  </div>
</div>
