<?php $memberCount = count($users); ?>
<section class="stack users-page" data-users-directory>
  <h1 class="users-page__title">Users</h1>
<?php if (($pendingCount ?? null) !== null): ?>
<?= $indent($partial('partials/users_tabs.php', ['usersTab' => 'members', 'pendingCount' => $pendingCount]), 1) ?>
<?php endif; ?>
<?php if ($users === []): ?>
  <article class="card">
    <p>No members yet.</p>
  </article>
<?php else: ?>
  <div class="users-tools">
    <input type="search" data-users-search placeholder="Search <?= $e($memberCount) ?> members" aria-label="Search members" autocomplete="off" autocapitalize="none" spellcheck="false">
    <select data-users-sort aria-label="Sort members">
      <option value="activity">Activity</option>
      <option value="name">Name</option>
    </select>
  </div>
  <div class="account-group" data-users-list>
<?php foreach ($users as $index => $user): ?>
<?php $threads = (int) $user['thread_count']; $posts = (int) $user['post_count']; ?>
    <a class="account-row account-row--link" data-tone="neutral" href="/user/<?= $e($user['username_token']) ?>" data-user-name="<?= $e(strtolower((string) $user['username'])) ?>" data-user-rank="<?= $e($index) ?>"><span class="account-row__title"><?= $e($user['username']) ?><?php if ((string) ($user['username'] ?? '') === 'reply-agent'): ?> <span class="chip agent-label">Agent</span><?php endif; ?></span><span class="account-row__value"><?= $e($threads) ?> <?= $threads === 1 ? 'thread' : 'threads' ?> · <?= $e($posts) ?> <?= $posts === 1 ? 'post' : 'posts' ?></span></a>
<?php endforeach; ?>
  </div>
  <p class="meta" data-users-none hidden>No members match.</p>
  <script>
    // Search and sort the list already on the page; without JavaScript the full list shows in activity order.
    (function (root) {
      var list = root.querySelector('[data-users-list]');
      var search = root.querySelector('[data-users-search]');
      var sort = root.querySelector('[data-users-sort]');
      var none = root.querySelector('[data-users-none]');
      var rows = Array.prototype.slice.call(list.children);
      function apply() {
        var query = search.value.trim().toLowerCase();
        var shown = 0;
        rows.slice().sort(function (a, b) {
          return sort.value === 'name'
            ? a.dataset.userName.localeCompare(b.dataset.userName)
            : Number(a.dataset.userRank) - Number(b.dataset.userRank);
        }).forEach(function (row) {
          row.hidden = query !== '' && row.dataset.userName.indexOf(query) === -1;
          shown += row.hidden ? 0 : 1;
          list.appendChild(row);
        });
        none.hidden = shown > 0;
      }
      search.addEventListener('input', apply);
      sort.addEventListener('change', apply);
    })(document.currentScript.closest('[data-users-directory]'));
  </script>
<?php endif; ?>
</section>
