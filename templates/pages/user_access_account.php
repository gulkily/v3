<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
    <h1>Access: <?= $e($usernameToken) ?></h1>
    <p><a href="/user/<?= $e($usernameToken) ?>">Member profile</a></p>
  </article>
  <article class="card">
    <h2>Account keys</h2>
    <ul>
<?php foreach ($profiles as $profile): ?>
      <li><a href="/tools/user-access/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($shortenProfileSlug($profile['profile_slug'])) ?></a> — <?= (int) $profile['is_approved'] === 1 ? 'Approved' : 'Pending' ?></li>
<?php endforeach; ?>
    </ul>
  </article>
<?= $partial('partials/approval_history.php', ['approvalHistory' => $approvalHistory, 'historyAccountBase' => '/tools/user-access/accounts/', 'historyProfileBase' => '/tools/user-access/profiles/']) ?>
</section>
