<?php
// Members | Waiting (N): the directory and the people waiting for approval, side by side.
// Rendered only for approved members, the only ones who can open Waiting.
$usersTab = (string) ($usersTab ?? 'members');
$pendingCount = (int) ($pendingCount ?? 0);
?>
<nav class="users-tabs" aria-label="Users">
  <a href="/users/"<?= $usersTab === 'members' ? ' aria-current="page"' : '' ?>>Members</a>
  <a href="/users/pending"<?= $usersTab === 'waiting' ? ' aria-current="page"' : '' ?>>Waiting (<?= $e($pendingCount) ?>)</a>
</nav>
