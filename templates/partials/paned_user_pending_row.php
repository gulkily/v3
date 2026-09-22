<?php
/**
 * @var array<string, mixed> $pendingUser
 * @var bool $isSelected
 * @var bool $isTabStop
 * @var bool $visible
 */
$token = (string) $pendingUser['username_token'];
?>
<div
  class="paned-list-row paned-list-row--pending<?= $isSelected ? ' paned-list-row--selected' : '' ?>"
  data-paned-user-token="<?= $e($token) ?>"
  data-paned-user-category-not-approved="1"
  data-paned-sort-username="<?= $e(mb_strtolower($pendingUser['username'])) ?>"
  data-paned-sort-threads="<?= (int) $pendingUser['thread_count'] ?>"
  data-paned-sort-posts="<?= (int) $pendingUser['post_count'] ?>"
  data-paned-sort-active=""
  data-paned-sort-joined=""
  role="option"
  aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
  tabindex="<?= $isTabStop ? '0' : '-1' ?>"
  <?= $visible ? '' : 'hidden' ?>
>
  <span class="paned-list-subject"><?= $e($pendingUser['username']) ?></span>
  <span class="paned-list-from"><?= (int) $pendingUser['thread_count'] ?></span>
  <span class="paned-list-date"><?= (int) $pendingUser['post_count'] ?></span>
  <span class="paned-list-active"></span>
  <span class="paned-list-joined"></span>
</div>
