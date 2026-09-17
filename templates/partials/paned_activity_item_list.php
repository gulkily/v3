<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var string $selectedView
 * @var string $selectedItemId
 * @var array<string, array{has_more: bool, next_cursor: array{created_at: string, post_id: ?string, id: int}|null}> $viewPagination
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
$viewPagination ??= [];
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
<?= $indent($partial('partials/paned_activity_item_row.php', [
    'item' => $item,
    'isSelected' => $isSelected,
    'isTabStop' => $isTabStop,
    'visible' => $visible,
]), 2) ?>
<?php endforeach; ?>
  </div>
  <div class="paned-list-load-more-group" data-paned-activity-load-more-group>
<?php foreach ($viewPagination as $viewKey => $pagination): ?>
<?php
$hasMore = (bool) ($pagination['has_more'] ?? false);
$nextCursor = $pagination['next_cursor'] ?? null;
$cursorJson = $nextCursor !== null ? json_encode($nextCursor) : '';
?>
    <button
      type="button"
      class="paned-list-load-more-button"
      data-paned-activity-load-more
      data-paned-activity-view="<?= $e($viewKey) ?>"
      data-paned-activity-cursor="<?= $e($cursorJson) ?>"
      data-paned-activity-has-more="<?= $hasMore ? '1' : '0' ?>"
      <?= ($viewKey === $selectedView && $hasMore) ? '' : 'hidden' ?>
    >Load more</button>
<?php endforeach; ?>
  </div>
</div>
