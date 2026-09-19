<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $commitItems
 * @var array<int, array{key: string, label: string, count: int, loadedCount: int}> $viewCounts
 * @var string $selectedView
 * @var string $selectedItemId
 * @var array<string, array{has_more: bool, next_cursor: array{sort_value: string, id: int}|null}> $viewPagination
 * @var array<string, array{ariaSort: string, href: string}> $sortHeaderLinks
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
// The status bar tracks how many of the selected view's items are actually
// loaded/visible right now, not the view's full total (that's the left-pane
// folder count) - it grows via JS as more pages are loaded.
$selectedViewLoadedCount = 0;
foreach ($viewCounts as $viewCount) {
    if ($viewCount['key'] === $selectedView) {
        $selectedViewLoadedCount = $viewCount['loadedCount'];
        break;
    }
}
?>
<div class="paned-window">
  <div class="paned-menubar">
    <span>File</span><span>Edit</span><span>View</span><span>Folder</span><span>Navigate</span><span>Help</span>
  </div>
<?= $indent($partial('partials/paned_toolbar.php', [
    'activeView' => 'activity',
    'boardControlsEnabled' => false,
    'replyEnabled' => false,
    'prevNextEnabled' => true,
  ]), 1) ?>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_activity_filter_list.php', ['viewCounts' => $viewCounts, 'selectedView' => $selectedView]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_activity_item_list.php', ['items' => $items, 'commitItems' => $commitItems, 'selectedView' => $selectedView, 'selectedItemId' => $selectedItemId, 'viewPagination' => $viewPagination, 'sortHeaderLinks' => $sortHeaderLinks]), 3) ?>
<?= $indent($partial('partials/paned_activity_detail_pane.php', ['items' => $items, 'selectedItemId' => $selectedItemId]), 3) ?>
    </div>
  </div>
  <div class="paned-statusbar">
    <span data-paned-activity-status-count><?= $selectedViewLoadedCount ?> item<?= $selectedViewLoadedCount === 1 ? '' : 's' ?></span>
  </div>
<?= $indent($partial('partials/paned_content_summary_dialog.php'), 1) ?>
</div>
