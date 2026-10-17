<section class="stack">
  <article class="card">
    <h1>Root approval</h1>
    <p>This seed records root approval for the key below.</p>
    <p class="root-approval-key"><?php if ($profile !== null): ?><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($keySlug) ?></a><?php else: ?><?= $e($keySlug) ?><?php endif; ?></p>
<?php if ($profile !== null): ?>
    <p>Current account: <a href="/user/<?= $e($profile['username_token']) ?>#account-keys"><?= $e($profile['username']) ?></a></p>
<?php endif; ?>
    <p><strong>Recorded reason:</strong> <?= $e($seed->seedReason) ?></p>
    <p class="meta">Authorizer: not recorded. Acceptance time: not recorded.</p>
<?php if (trim($seed->body) !== ''): ?>
    <div class="body"><?= nl2br($e($seed->body)) ?></div>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Source evidence</h2>
    <p><a href="<?= $e($sourceHref) ?>">Raw seed record</a></p>
  </article>
</section>
