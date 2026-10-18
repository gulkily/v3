<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
    <h1>User Access</h1>
    <p><a href="/tools/user-access/pending/">Pending approvals</a></p>
  </article>
  <article class="card">
    <h2>Accounts</h2>
<?php if ($users === []): ?>
    <p>No approved accounts.</p>
<?php else: ?>
    <ul>
<?php foreach ($users as $user): ?>
      <li><a href="/tools/user-access/accounts/<?= $e($user['username_token']) ?>"><?= $e($user['username']) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>
</section>
