<?php $memberCount = count($users); ?>
<section class="stack users-page" data-users-directory>
  <h1 class="users-page__title">Users</h1>
  <div class="board-toolbar">
<?= $indent($partial('partials/users_tabs.php', ['usersTab' => 'members', 'pendingCount' => $pendingCount, 'memberOnly' => true]), 2) ?>
<?php if ($users !== []): ?>
<?= $indent($partial('partials/board_sort.php', ['sortOptions' => $sortOptions, 'sortLabel' => $sortLabel]), 2) ?>
    <input type="search" class="board-toolbar__search" data-users-search placeholder="Search <?= $e($memberCount) ?> members" aria-label="Search members" autocomplete="off" autocapitalize="none" spellcheck="false">
<?php endif; ?>
  </div>
<?php if ($users === []): ?>
  <article class="card">
    <p>No members yet.</p>
  </article>
<?php else: ?>
  <div class="thread-rows" data-users-list>
<?php foreach ($users as $user): ?>
<?php $threads = (int) $user['thread_count']; $posts = (int) $user['post_count']; ?>
    <article class="thread-row" data-user-name="<?= $e(strtolower((string) $user['username'])) ?>" data-user-active="<?= $e((string) ($user['active_at'] ?? '')) ?>">
      <h3 class="thread-row__title"><a href="/user/<?= $e($user['username_token']) ?>"><?= $e($user['username']) ?></a><?php if ((string) ($user['username'] ?? '') === 'reply-agent'): ?> <span class="chip agent-label">Agent</span><?php endif; ?></h3>
      <p class="meta thread-row__meta"><?= $e($threads) ?> <?= $threads === 1 ? 'thread' : 'threads' ?> · <?= $e($posts) ?> <?= $posts === 1 ? 'post' : 'posts' ?><?php if (($user['active_at'] ?? '') !== ''): ?> · Active <?= $shortDate((string) $user['active_at']) ?><?php endif; ?></p>
    </article>
<?php endforeach; ?>
  </div>
  <p class="meta" data-users-none hidden>No members match.</p>
  <script>
    // Search the list already on the page; without JavaScript the full list shows in the chosen sort order.
    (function (root) {
      var rows = Array.prototype.slice.call(root.querySelector('[data-users-list]').children);
      var search = root.querySelector('[data-users-search]');
      var none = root.querySelector('[data-users-none]');
      search.addEventListener('input', function () {
        var query = search.value.trim().toLowerCase();
        var shown = 0;
        rows.forEach(function (row) {
          row.hidden = query !== '' && row.dataset.userName.indexOf(query) === -1;
          shown += row.hidden ? 0 : 1;
        });
        none.hidden = shown > 0;
      });
    })(document.currentScript.closest('[data-users-directory]'));
  </script>
<?php endif; ?>
</section>
