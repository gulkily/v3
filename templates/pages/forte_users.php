<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var array<int, array{letter: string, count: int}> $letterGroups
 * @var string $selectedLetter
 * @var string $selectedUserToken
 */
$selectedLetter ??= '';
$selectedUserToken ??= '';
$visibleUserCount = count($users);
if ($selectedLetter !== '') {
    foreach ($letterGroups as $group) {
        if ($group['letter'] === $selectedLetter) {
            $visibleUserCount = $group['count'];
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
    'activeView' => 'users',
    'boardControlsEnabled' => false,
    'replyEnabled' => false,
  ]), 1) ?>
  <div class="paned-board-layout">
<?= $indent($partial('partials/paned_users_filter_list.php', ['letterGroups' => $letterGroups, 'totalUserCount' => count($users), 'selectedLetter' => $selectedLetter]), 2) ?>
    <div class="paned-board-main paned-panes-stack">
<?= $indent($partial('partials/paned_user_list.php', ['users' => $users, 'selectedLetter' => $selectedLetter, 'selectedUserToken' => $selectedUserToken]), 3) ?>
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
    <span data-paned-users-status-count data-paned-users-total-count="<?= count($users) ?>"><?php if ($selectedLetter === ''): ?><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?><?php else: ?>Showing <?= $visibleUserCount ?> of <?= count($users) ?> users (<?= $e($selectedLetter) ?>)<?php endif; ?></span>
  </div>
</div>
