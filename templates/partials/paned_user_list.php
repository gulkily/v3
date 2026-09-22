<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var string $selectedLetter
 * @var string $selectedUserToken
 */
$selectedLetter ??= '';
$selectedUserToken ??= '';
$userLetter = static function (string $token): string {
    $letter = $token === '' ? '#' : strtoupper($token[0]);

    return ctype_alpha($letter) ? $letter : '#';
};
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
$letter = $userLetter($token);
$visible = $selectedLetter === '' || $selectedLetter === $letter;
$isSelected = $selectedUserToken !== '' && $token === $selectedUserToken;
$isTabStop = $selectedUserToken !== '' ? $isSelected : ($visible && !$tabStopAssigned);
if ($isTabStop) {
    $tabStopAssigned = true;
}
?>
    <div
      class="paned-list-row<?= $isSelected ? ' paned-list-row--selected' : '' ?>"
      data-paned-user-token="<?= $e($token) ?>"
      data-paned-user-row-letter="<?= $e($letter) ?>"
      role="option"
      aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
      tabindex="<?= $isTabStop ? '0' : '-1' ?>"
      <?= $visible ? '' : 'hidden' ?>
    >
      <span class="paned-list-subject"><?= $e($user['username']) ?></span>
      <span class="paned-list-from"><?= (int) $user['thread_count'] ?></span>
      <span class="paned-list-date"><?= (int) $user['post_count'] ?></span>
    </div>
<?php endforeach; ?>
  </div>
</div>
