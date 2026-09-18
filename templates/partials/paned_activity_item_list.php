<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var string $selectedView
 * @var string $selectedItemId
 * @var array<string, array{has_more: bool, next_cursor: array{sort_value: string, id: int}|null}> $viewPagination
 * @var array<string, array{ariaSort: string, href: string}> $sortHeaderLinks
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
$viewPagination ??= [];
$sortHeaderLinks ??= [];
$tabStopAssigned = false;
?>
<div class="paned-list-pane">
  <div class="paned-list-head" data-paned-sort-head>
    <span class="paned-list-from-head" aria-sort="<?= $e($sortHeaderLinks['kind']['ariaSort'] ?? 'none') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="kind" data-paned-sort-href="<?= $e($sortHeaderLinks['kind']['href'] ?? '') ?>">Kind</button></span>
    <span class="paned-list-subject-head" aria-sort="<?= $e($sortHeaderLinks['label']['ariaSort'] ?? 'none') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="label" data-paned-sort-href="<?= $e($sortHeaderLinks['label']['href'] ?? '') ?>">Label</button></span>
    <span class="paned-list-date-head" aria-sort="<?= $e($sortHeaderLinks['date']['ariaSort'] ?? 'none') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="date" data-paned-sort-href="<?= $e($sortHeaderLinks['date']['href'] ?? '') ?>">Date</button></span>
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
    <div class="paned-list-load-more-group" data-paned-activity-load-more-group role="presentation">
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
</div>
