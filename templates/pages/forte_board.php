<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var array<int, array{tag: string, count: int, threads: array}> $tagGroups
 * @var string $selectedTag
 */
$selectedTag ??= '';
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
  <div class="paned-toolbar">
    <button type="button" class="paned-toolbar-btn" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="2" width="12" height="12" fill="none" stroke="currentColor"/><line x1="8" y1="5" x2="8" y2="11" stroke="currentColor"/><line x1="5" y1="8" x2="11" y2="8" stroke="currentColor"/></svg>
      <span>New</span>
    </button>
    <span class="paned-toolbar-sep"></span>
    <button type="button" class="paned-toolbar-btn" data-paned-board-prev>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M11 3 L5 8 L11 13" fill="none" stroke="currentColor"/></svg>
      <span>Prev</span>
    </button>
    <button type="button" class="paned-toolbar-btn" data-paned-board-next>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 3 L11 8 L5 13" fill="none" stroke="currentColor"/></svg>
      <span>Next</span>
    </button>
    <span class="paned-toolbar-sep"></span>
    <button type="button" class="paned-toolbar-btn" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8 a5 5 0 1 1 1.6 3.6" fill="none" stroke="currentColor"/><path d="M3 11 v-3 h3" fill="none" stroke="currentColor"/></svg>
      <span>Refresh</span>
    </button>
  </div>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_folder_tree.php', ['tagGroups' => $tagGroups, 'totalThreadCount' => count($threads), 'selectedTag' => $selectedTag]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_board_thread_list.php', ['threads' => $threads, 'selectedTag' => $selectedTag, 'sortColumn' => $sortColumn, 'sortDir' => $sortDir]), 3) ?>
<?= $indent($partial('partials/paned_board_content_pane.php', ['threads' => $threads]), 3) ?>
    </div>
  </div>
  <div class="paned-statusbar">
    <span data-paned-board-status-count data-paned-board-total-count="<?= count($threads) ?>" data-paned-board-tag-count="<?= count($tagGroups) ?>"><?php if ($selectedTag === ''): ?><?= count($threads) ?> thread<?= count($threads) === 1 ? '' : 's' ?> · <?= count($tagGroups) ?> tags<?php else: ?>Showing <?= $visibleThreadCount ?> of <?= count($threads) ?> threads (#<?= $e($selectedTag) ?>)<?php endif; ?></span>
    <span>Forte reader</span>
  </div>
</div>
