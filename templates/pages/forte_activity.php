<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array{key: string, label: string, count: int}> $viewCounts
 * @var string $selectedView
 * @var string $selectedItemId
 */
$selectedView ??= 'all';
$selectedItemId ??= '';
$selectedViewCount = 0;
foreach ($viewCounts as $viewCount) {
    if ($viewCount['key'] === $selectedView) {
        $selectedViewCount = $viewCount['count'];
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
  ]), 1) ?>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_activity_filter_list.php', ['viewCounts' => $viewCounts, 'selectedView' => $selectedView]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_activity_item_list.php', ['items' => $items, 'selectedView' => $selectedView, 'selectedItemId' => $selectedItemId]), 3) ?>
<?= $indent($partial('partials/paned_activity_detail_pane.php', ['items' => $items, 'selectedItemId' => $selectedItemId]), 3) ?>
    </div>
  </div>
  <div class="paned-statusbar">
    <span data-paned-activity-status-count><?= $selectedViewCount ?> item<?= $selectedViewCount === 1 ? '' : 's' ?></span>
  </div>
</div>
