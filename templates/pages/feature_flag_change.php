<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Feature flag change</h1>
    <p class="feature-flags-notice meta">One recorded change to a site-wide feature flag. <a href="/tools/feature-flags/">Back to Feature Flags</a></p>
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading">Change</h2>
      <p><strong>Flag:</strong> <?= $e($flagLabel) ?> <code class="feature-flag-key"><?= $e($record->flagKey) ?></code></p>
      <p><strong>Set to:</strong> <?= $record->value ? 'enabled' : 'disabled' ?></p>
      <p><strong>Changed at:</strong> <?= $timestamp($record->createdAt) ?></p>
      <p><strong>Operator:</strong>
<?php if ($operator !== null): ?>
        <a href="/profiles/<?= $e($operator['profile_slug']) ?>"><?= $e($operator['username'] ?? $operator['profile_slug']) ?></a>
<?php else: ?>
        <span title="<?= $e($record->operatorIdentityId) ?>">unknown identity</span>
<?php endif; ?>
      </p>
    </section>
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading">Source evidence</h2>
<?= $indent($partial('partials/source_metadata.php', $sourceItem), 3) ?>
      <p class="meta">This page shows only what the record and repository history state; it does not re-verify the signature.</p>
    </section>
  </article>
</section>
