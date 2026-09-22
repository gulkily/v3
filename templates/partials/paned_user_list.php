<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}> $flagsByToken
 * @var array<int, array<string, mixed>> $pendingUsers
 * @var string $selectedCategory
 * @var string $selectedUserToken
 * @var string $sortColumn
 * @var string $sortDir
 */
$selectedCategory ??= 'all';
$selectedUserToken ??= '';
$pendingUsers ??= [];
$sortColumn ??= '';
$sortDir ??= '';
$ariaSort = static function (string $column) use ($sortColumn, $sortDir): string {
    if ($column !== $sortColumn) {
        return 'none';
    }

    return $sortDir === 'desc' ? 'descending' : 'ascending';
};
$tabStopAssigned = false;
?>
<div class="paned-list-pane" data-paned-users-list-pane>
  <div class="paned-list-head" data-paned-sort-head>
    <span class="paned-list-subject-head" aria-sort="<?= $ariaSort('username') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="username">Username</button></span>
    <span class="paned-list-from-head" aria-sort="<?= $ariaSort('threads') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="threads">Threads</button></span>
    <span class="paned-list-date-head" aria-sort="<?= $ariaSort('posts') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="posts">Posts</button></span>
    <span class="paned-list-active-head" aria-sort="<?= $ariaSort('active') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="active">Active</button></span>
    <span class="paned-list-joined-head" aria-sort="<?= $ariaSort('joined') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="joined">Joined</button></span>
  </div>
  <div class="paned-list-body" data-paned-users-list-body role="listbox" aria-label="Users">
<?php foreach ($users as $user): ?>
<?php
$token = (string) $user['username_token'];
$flags = $flagsByToken[$token] ?? ['new' => false, 'established' => false, 'no_threads' => false, 'recently_active' => false];
$categoryKey = $selectedCategory === 'all' ? 'all' : str_replace('-', '_', $selectedCategory);
$visible = $selectedCategory === 'all' || ($flags[$categoryKey] ?? false);
$isSelected = $selectedUserToken !== '' && $token === $selectedUserToken;
$isTabStop = $selectedUserToken !== '' ? $isSelected : ($visible && !$tabStopAssigned);
if ($isTabStop) {
    $tabStopAssigned = true;
}
?>
<?= $indent($partial('partials/paned_user_row.php', [
    'user' => $user,
    'flags' => $flags,
    'isSelected' => $isSelected,
    'isTabStop' => $isTabStop,
    'visible' => $visible,
]), 2) ?>
<?php endforeach; ?>
<?php foreach ($pendingUsers as $pendingUser): ?>
<?php
$token = (string) $pendingUser['username_token'];
$visible = $selectedCategory === 'not-approved';
$isSelected = $selectedUserToken !== '' && $token === $selectedUserToken;
$isTabStop = $selectedUserToken !== '' ? $isSelected : ($visible && !$tabStopAssigned);
if ($isTabStop) {
    $tabStopAssigned = true;
}
?>
<?= $indent($partial('partials/paned_user_pending_row.php', [
    'pendingUser' => $pendingUser,
    'isSelected' => $isSelected,
    'isTabStop' => $isTabStop,
    'visible' => $visible,
]), 2) ?>
<?php endforeach; ?>
  </div>
</div>
