<?php
/**
 * @var string $activeView 'board' | 'activity' | 'users'
 * @var bool $boardControlsEnabled
 * @var bool $replyEnabled
 * @var bool $prevNextEnabled
 */
$activeView ??= 'board';
$boardControlsEnabled ??= false;
$replyEnabled ??= false;
$prevNextEnabled ??= $boardControlsEnabled;
?>
<div class="paned-toolbar">
  <button type="button" class="paned-toolbar-btn" data-paned-board-new<?= $boardControlsEnabled ? '' : ' disabled' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="2" width="12" height="12" fill="none" stroke="currentColor"/><line x1="8" y1="5" x2="8" y2="11" stroke="currentColor"/><line x1="5" y1="8" x2="11" y2="8" stroke="currentColor"/></svg>
    <span>New</span>
  </button>
  <button type="button" class="paned-toolbar-btn" data-paned-board-reply<?= ($boardControlsEnabled && $replyEnabled) ? '' : ' disabled' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M9 3 L3 8 L9 13" fill="none" stroke="currentColor"/><path d="M3 8 H13" fill="none" stroke="currentColor"/></svg>
    <span>Reply</span>
  </button>
  <span class="paned-toolbar-sep"></span>
  <button type="button" class="paned-toolbar-btn" data-paned-board-prev<?= $prevNextEnabled ? '' : ' disabled' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M11 3 L5 8 L11 13" fill="none" stroke="currentColor"/></svg>
    <span>Prev</span>
  </button>
  <button type="button" class="paned-toolbar-btn" data-paned-board-next<?= $prevNextEnabled ? '' : ' disabled' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 3 L11 8 L5 13" fill="none" stroke="currentColor"/></svg>
    <span>Next</span>
  </button>
  <span class="paned-toolbar-sep"></span>
  <button type="button" class="paned-toolbar-btn" disabled>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8 a5 5 0 1 1 1.6 3.6" fill="none" stroke="currentColor"/><path d="M3 11 v-3 h3" fill="none" stroke="currentColor"/></svg>
    <span>Refresh</span>
  </button>
  <span class="paned-toolbar-sep"></span>
  <a href="/forte" class="paned-toolbar-btn"<?= $activeView === 'board' ? ' aria-current="page"' : '' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="3" width="12" height="10" fill="none" stroke="currentColor"/><line x1="2" y1="6" x2="14" y2="6" stroke="currentColor"/></svg>
    <span>Board</span>
  </a>
  <a href="/forte/users/" class="paned-toolbar-btn"<?= $activeView === 'users' ? ' aria-current="page"' : '' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="6" cy="5" r="2.3" fill="none" stroke="currentColor"/><path d="M1.5 13 c0 -3 2 -4.5 4.5 -4.5 s4.5 1.5 4.5 4.5" fill="none" stroke="currentColor"/><circle cx="11.5" cy="6" r="1.8" fill="none" stroke="currentColor"/><path d="M9.7 8.7 c1 -0.5 2 -0.4 2.8 0.3 c0.9 0.8 1.5 2 1.5 4" fill="none" stroke="currentColor"/></svg>
    <span>Users</span>
  </a>
  <a href="/forte/activity/" class="paned-toolbar-btn"<?= $activeView === 'activity' ? ' aria-current="page"' : '' ?>>
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor"/><path d="M8 4.5 V8 L10.5 9.5" fill="none" stroke="currentColor"/></svg>
    <span>Activity</span>
  </a>
</div>
