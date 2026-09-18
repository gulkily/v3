<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $commitItems
 * @var string $selectedView
 * @var string $selectedItemId
 * @var array<string, array{has_more: bool, next_cursor: array{sort_value: string, id: int}|null}> $viewPagination
 * @var array<string, array{ariaSort: string, href: string}> $sortHeaderLinks
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
$commitItems ??= [];
$viewPagination ??= [];
$sortHeaderLinks ??= [];
$tabStopAssigned = false;
// The Commits view's rows are shas/subjects, not kind/label activity
// records - the first two columns are relabeled to match what's actually
// shown there. Kind/Label still sort no differently while browsing Commits
// (resolveCommitSort() only recognizes date - a deliberate Step 3 scope
// decision), this only fixes the displayed column names.
$kindHeaderLabel = $selectedView === 'commits' ? 'Hash' : 'Kind';
$labelHeaderLabel = $selectedView === 'commits' ? 'Subject' : 'Label';
?>
<div class="paned-list-pane">
  <div class="paned-list-head" data-paned-sort-head>
    <span class="paned-list-from-head" aria-sort="<?= $e($sortHeaderLinks['kind']['ariaSort'] ?? 'none') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="kind" data-paned-sort-href="<?= $e($sortHeaderLinks['kind']['href'] ?? '') ?>"><?= $e($kindHeaderLabel) ?></button></span>
    <span class="paned-list-subject-head" aria-sort="<?= $e($sortHeaderLinks['label']['ariaSort'] ?? 'none') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="label" data-paned-sort-href="<?= $e($sortHeaderLinks['label']['href'] ?? '') ?>"><?= $e($labelHeaderLabel) ?></button></span>
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
<?php foreach ($commitItems as $item): ?>
<?php
$itemId = 'commit-' . (string) $item['sha'];
$visible = $selectedView === 'commits';
$isSelected = $selectedItemId !== '' && $itemId === $selectedItemId;
$isTabStop = $selectedItemId !== '' ? $isSelected : ($visible && !$tabStopAssigned);
if ($isTabStop) {
    $tabStopAssigned = true;
}
?>
<?= $indent($partial('partials/paned_activity_commit_row.php', [
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
