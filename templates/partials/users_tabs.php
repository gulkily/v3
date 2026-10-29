<?php
// Members | Waiting: the directory and the people waiting for approval, side by side. Only approved
// members can open Waiting, so its count shows there and not on the shared Members page.
$usersTab = (string) ($usersTab ?? 'members');
$pendingCount = isset($pendingCount) ? (int) $pendingCount : null;
?>
<nav class="users-tabs" aria-label="Users">
  <a href="/users/"<?= $usersTab === 'members' ? ' aria-current="page"' : '' ?>>Members</a>
  <a href="/users/pending"<?= $usersTab === 'waiting' ? ' aria-current="page"' : '' ?>>Waiting<?= $pendingCount !== null ? ' (' . $e($pendingCount) . ')' : '' ?></a>
</nav>
