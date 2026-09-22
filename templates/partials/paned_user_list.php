<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}> $flagsByToken
 * @var array<int, array<string, mixed>> $pendingUsers
 * @var string $selectedCategory
 * @var string $selectedUserToken
 */
$selectedCategory ??= 'all';
$selectedUserToken ??= '';
$pendingUsers ??= [];
$tabStopAssigned = false;
?>
<div class="paned-list-pane" data-paned-users-list-pane>
  <div class="paned-list-head">
    <span class="paned-list-subject-head">Username</span>
    <span class="paned-list-from-head">Threads</span>
    <span class="paned-list-date-head">Posts</span>
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
