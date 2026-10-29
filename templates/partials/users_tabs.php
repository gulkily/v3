<?php
// Members | Waiting (N), in the board's filter control: the directory and the people waiting for approval.
// On the shared Users page the tabs are member-only, shown before paint for a
// browser remembered as approved; the Waiting page itself is approved-only.
$usersTab = (string) ($usersTab ?? 'members');
$pendingCount = (int) ($pendingCount ?? 0);
?>
<nav class="board-filter" aria-label="Users"<?= !empty($memberOnly) ? ' data-member-only' : '' ?>>
  <a class="nav-link<?= $usersTab === 'members' ? ' is-active' : '' ?>" href="/users/"<?= $usersTab === 'members' ? ' aria-current="page"' : '' ?>>Members</a>
  <a class="nav-link<?= $usersTab === 'waiting' ? ' is-active' : '' ?>" href="/users/pending"<?= $usersTab === 'waiting' ? ' aria-current="page"' : '' ?>>Waiting (<?= $e($pendingCount) ?>)</a>
</nav>
