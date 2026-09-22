<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}> $flagsByToken
 * @var array<int, array<string, mixed>> $pendingUsers
 * @var array<int, array{key: string, label: string, count: int}> $categoryCounts
 * @var string $selectedCategory
 * @var string $selectedUserToken
 */
$selectedCategory ??= 'all';
$selectedUserToken ??= '';
$selectedCategoryInfo = ['key' => 'all', 'label' => 'All Users', 'count' => count($users)];
foreach ($categoryCounts as $category) {
    if ($category['key'] === $selectedCategory) {
        $selectedCategoryInfo = $category;
        break;
    }
}
?>
<div class="paned-window">
  <div class="paned-menubar">
    <span>File</span><span>Edit</span><span>View</span><span>Folder</span><span>Navigate</span><span>Help</span>
  </div>
<?= $indent($partial('partials/paned_toolbar.php', [
    'activeView' => 'users',
    'boardControlsEnabled' => false,
    'replyEnabled' => false,
  ]), 1) ?>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_users_filter_list.php', ['categoryCounts' => $categoryCounts, 'selectedCategory' => $selectedCategory]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_user_list.php', ['users' => $users, 'flagsByToken' => $flagsByToken, 'pendingUsers' => $pendingUsers, 'selectedCategory' => $selectedCategory, 'selectedUserToken' => $selectedUserToken]), 3) ?>
      <div class="paned-content-pane" data-paned-user-detail-pane>
        <article class="paned-content-post" data-paned-user-detail-placeholder>
          <div class="paned-content-head">
            <div class="paned-content-subject">No user selected</div>
          </div>
          <div class="body">Select a user from the list to preview their profile here.</div>
        </article>
      </div>
    </div>
  </div>
  <div class="paned-statusbar">
    <span data-paned-users-status-count data-paned-users-total-count="<?= count($users) ?>"><?php if ($selectedCategory === 'all'): ?><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?><?php elseif ($selectedCategory === 'not-approved'): ?><?= (int) $selectedCategoryInfo['count'] ?> pending user<?= $selectedCategoryInfo['count'] === 1 ? '' : 's' ?><?php else: ?>Showing <?= (int) $selectedCategoryInfo['count'] ?> of <?= count($users) ?> users (<?= $e($selectedCategoryInfo['label']) ?>)<?php endif; ?></span>
  </div>
</div>
