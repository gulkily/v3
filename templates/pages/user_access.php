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
<?php if ($canSecurityReview || ($canResumeHistory ?? false)): ?>
  <article class="card" id="access-history">
    <div class="access-history-heading">
      <h2>Access history</h2>
<?php if ($canSecurityReview && $historyHasMore): ?>
      <a href="/activity/?view=security">View all</a>
<?php endif; ?>
    </div>
<?php if (!$canSecurityReview): ?>
<?= $partial('partials/access_history_auth.php', ['canSignIn' => true, 'authReturnTo' => '/tools/user-access/#access-history']) ?>
<?php elseif ($historyItems === []): ?>
    <p class="meta">No access changes recorded yet.</p>
<?php else: ?>
<?php foreach ($historyItems as $item): ?>
<?= $partial('partials/access_history_event.php', ['item' => $item]) ?>
<?php endforeach; ?>
<?php endif; ?>
  </article>
<?php endif; ?>
</section>
