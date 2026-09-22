<?php
/**
 * @var array<string, mixed> $user
 * @var array{new: bool, established: bool, no_threads: bool, recently_active: bool} $flags
 * @var bool $isSelected
 * @var bool $isTabStop
 * @var bool $visible
 */
$token = (string) $user['username_token'];
?>
<div
  class="paned-list-row<?= $isSelected ? ' paned-list-row--selected' : '' ?>"
  data-paned-user-token="<?= $e($token) ?>"
  data-paned-user-category-all="1"
  data-paned-user-category-new="<?= $flags['new'] ? '1' : '0' ?>"
  data-paned-user-category-established="<?= $flags['established'] ? '1' : '0' ?>"
  data-paned-user-category-no-threads="<?= $flags['no_threads'] ? '1' : '0' ?>"
  data-paned-user-category-recently-active="<?= $flags['recently_active'] ? '1' : '0' ?>"
  data-paned-sort-username="<?= $e(mb_strtolower($user['username'])) ?>"
  data-paned-sort-threads="<?= (int) $user['thread_count'] ?>"
  data-paned-sort-posts="<?= (int) $user['post_count'] ?>"
  data-paned-sort-active="<?= $e((string) ($user['active_at'] ?? '')) ?>"
  data-paned-sort-joined="<?= $e((string) ($user['joined_at'] ?? '')) ?>"
  role="option"
  aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
  tabindex="<?= $isTabStop ? '0' : '-1' ?>"
  <?= $visible ? '' : 'hidden' ?>
>
  <span class="paned-list-subject"><?= $e($user['username']) ?></span>
  <span class="paned-list-from"><?= (int) $user['thread_count'] ?></span>
  <span class="paned-list-date"><?= (int) $user['post_count'] ?></span>
  <span class="paned-list-active"><?= $relativeTimestamp((string) ($user['active_at'] ?? '')) ?></span>
  <span class="paned-list-joined"><?= $relativeTimestamp((string) ($user['joined_at'] ?? '')) ?></span>
</div>
