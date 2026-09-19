<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var array<int, array<string, mixed>> $contentThreads
 * @var array<int, array{tag: string, count: int, threads: array}> $tagGroups
 * @var string $selectedTag
 * @var string $selectedThreadId
 */
$selectedTag ??= '';
$selectedThreadId ??= '';
$contentThreads ??= $threads;
$visibleThreadCount = count($threads);
if ($selectedTag !== '') {
    foreach ($tagGroups as $group) {
        if ($group['tag'] === $selectedTag) {
            $visibleThreadCount = $group['count'];
            break;
        }
    }
}
?>
<div class="paned-window">
  <div class="paned-menubar">
    <span>File</span><span>Edit</span><span>View</span><span>Folder</span><span>Navigate</span><span>Help</span>
  </div>
<?= $indent($partial('partials/paned_toolbar.php', [
    'activeView' => 'board',
    'boardControlsEnabled' => true,
    'replyEnabled' => $selectedThreadId !== '',
  ]), 1) ?>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_folder_tree.php', ['tagGroups' => $tagGroups, 'totalThreadCount' => count($threads), 'selectedTag' => $selectedTag]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_board_thread_list.php', ['threads' => $threads, 'selectedTag' => $selectedTag, 'sortColumn' => $sortColumn, 'sortDir' => $sortDir]), 3) ?>
<?= $indent($partial('partials/paned_board_content_pane.php', ['threads' => $contentThreads]), 3) ?>
    </div>
  </div>
  <div class="paned-statusbar">
    <span data-paned-board-status-count data-paned-board-total-count="<?= count($threads) ?>" data-paned-board-tag-count="<?= count($tagGroups) ?>"><?php if ($selectedTag === ''): ?><?= count($threads) ?> thread<?= count($threads) === 1 ? '' : 's' ?> · <?= count($tagGroups) ?> tags<?php else: ?>Showing <?= $visibleThreadCount ?> of <?= count($threads) ?> threads (#<?= $e($selectedTag) ?>)<?php endif; ?></span>
  </div>
<?= $indent($partial('partials/paned_board_new_thread_dialog.php', ['selectedTag' => $selectedTag]), 1) ?>
<?= $indent($partial('partials/paned_profile_summary_dialog.php'), 1) ?>
</div>
