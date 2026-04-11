<section class="stack">
  <article class="card">
    <h1>Profile <?= $e($profile['profile_slug']) ?></h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
  </article>
<?php if ($self): ?>
  <article class="card">
    <p class="meta">Self profile mode</p>
    <p>This route can show account-aware bootstrap context. Current cookie hint: <?= $e($identityHint !== '' ? $identityHint : 'none') ?></p>
  </article>
<?php endif; ?>
  <article class="card">
    <p><strong>Identity ID:</strong> <?= $e($profile['identity_id']) ?></p>
    <p><strong>Visible username:</strong> <?= $e($profile['username']) ?></p>
    <p><strong>Fallback label:</strong> <?= $e($profile['fallback_label']) ?></p>
    <p><strong>Approved:</strong> <?= ((int) $profile['is_approved']) === 1 ? 'yes' : 'no' ?></p>
<?php if (((int) $profile['is_approved']) === 1 && ((string) ($profile['approved_by_label'] ?? '')) !== ''): ?>
    <p><strong>Approved by:</strong>
<?php if ((string) ($profile['approved_by_profile_slug'] ?? '') !== ''): ?>
      <a href="/profiles/<?= $e($profile['approved_by_profile_slug']) ?>"><?= $e($profile['approved_by_label']) ?></a>
<?php else: ?>
      <?= $e($profile['approved_by_label']) ?>
<?php endif; ?>
    </p>
<?php endif; ?>
    <p><strong>Bootstrap post:</strong> <a href="/posts/<?= $e($profile['bootstrap_post_id']) ?>"><?= $e($profile['bootstrap_post_id']) ?></a></p>
    <p><strong>Bootstrap thread:</strong> <a href="/threads/<?= $e($profile['bootstrap_thread_id']) ?>"><?= $e($profile['bootstrap_thread_id']) ?></a></p>
    <p><strong>Threads:</strong> <?= (int) $profile['thread_count'] ?></p>
    <p><strong>Posts:</strong> <?= (int) $profile['post_count'] ?></p>
    <p><strong>Username route:</strong> <a href="/user/<?= $e($profile['username_token']) ?>">/user/<?= $e($profile['username_token']) ?></a></p>
<?php if ($canApprove): ?>
    <form method="post" action="/profiles/<?= $e($profile['profile_slug']) ?>/approve">
      <button type="submit">Approve user</button>
    </form>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Public key</h2>
    <pre>
<?= $e($profile['public_key']) ?>
    </pre>
  </article>
</section>
