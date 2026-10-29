<?php
// Members | Waiting (N), in the board's filter control: the directory and the people waiting for approval.
// Rendered only for approved members, the only ones who can open Waiting.
$usersTab = (string) ($usersTab ?? 'members');
$pendingCount = (int) ($pendingCount ?? 0);
?>
<nav class="board-filter" aria-label="Users">
  <a class="nav-link<?= $usersTab === 'members' ? ' is-active' : '' ?>" href="/users/"<?= $usersTab === 'members' ? ' aria-current="page"' : '' ?>>Members</a>
  <a class="nav-link<?= $usersTab === 'waiting' ? ' is-active' : '' ?>" href="/users/pending"<?= $usersTab === 'waiting' ? ' aria-current="page"' : '' ?>>Waiting (<?= $e($pendingCount) ?>)</a>
</nav>
