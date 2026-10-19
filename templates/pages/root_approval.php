<section class="stack">
  <article class="card">
    <h1>Root approval</h1>
    <p class="root-approval-key"><?php if ($profile !== null): ?><a href="/profiles/<?= $e($profile['profile_slug']) ?>" title="<?= $e($keySlug) ?>" aria-label="<?= $e($keySlug) ?>"><?= $e($shortenProfileSlug($keySlug)) ?></a><?php else: ?><span title="<?= $e($keySlug) ?>"><?= $e($shortenProfileSlug($keySlug)) ?></span><?php endif; ?></p>
<?php if ($profile !== null): ?>
    <p>Current account: <a href="/user/<?= $e($profile['username_token']) ?>#account-keys"><?= $e($profile['username']) ?></a></p>
<?php endif; ?>
    <p><strong>Recorded reason:</strong> <?= $e($seed->seedReason) ?></p>
<?php if (trim($seed->body) !== ''): ?>
    <div class="body"><?= nl2br($e($seed->body)) ?></div>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Source evidence</h2>
    <p><a href="<?= $e($sourceHref) ?>">Raw seed record</a></p>
<?php if ($matchingCommit !== ''): ?>
<?= $partial('partials/source_metadata.php', ['source_path' => $sourcePath, 'source_path_href' => $committedSourceHref,
    'source_commit_sha' => $matchingCommit, 'source_commit_href' => $commitHref]) ?>
<?php elseif ($currentVersionDiffers): ?>
    <p class="meta">The current seed differs from the latest repository version. The raw seed record above shows the current contents.</p>
<?php elseif ($repositoryHistory !== []): ?>
    <p class="meta">A committed version of the current seed could not be verified. The raw seed record above shows the current contents.</p>
<?php endif; ?>
    <h3>Repository history</h3>
    <p class="meta">Authorizer: not recorded. Acceptance time: not recorded. Repository commit dates describe file history.</p>
<?php if ($repositoryHistory === []): ?>
    <p class="meta">Repository history is unavailable for this seed.</p>
<?php else: ?>
    <ul>
<?php foreach ($repositoryHistory as $commit): ?>
      <li><a href="<?= $e($commit['href']) ?>" title="<?= $e($commit['sha']) ?>"><?= $e(substr($commit['sha'], 0, 12)) ?></a> · <?= $timestamp($commit['committed_at']) ?> · <?= $e($commit['subject']) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>
</section>
