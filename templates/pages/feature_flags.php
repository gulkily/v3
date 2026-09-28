<?php
$siteError = $flags[0]->siteError ?? null;

$flagGroups = [];
foreach ($flags as $flag) {
    $flagGroups[$flag->definition->groupKey()][] = $flag;
}
?>
<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Feature Flags</h1>
    <p class="feature-flags-notice meta">These settings apply to the whole site for all users as soon as you change them.</p>
<?php if ($siteError !== null): ?>
    <div class="feedback feedback-error"><?= $e($siteError) ?></div>
<?php endif; ?>
<?php foreach ($flagGroups as $groupKey => $groupFlags): ?>
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading"><?= $e($registry->groupLabel($groupKey)) ?></h2>
      <div class="feature-flag-list">
<?php foreach ($groupFlags as $flag): ?>
<?php
  $definition = $flag->definition;
  $parentDefinition = $definition->requiresEnabledFlag !== null ? $registry->get($definition->requiresEnabledFlag) : null;
  $rowClass = 'feature-flag-row' . ($flag->isBlockedByDependency() ? ' is-blocked' : '');
  $canEditFlag = $flag->canChangeFromSite() && $canManageFeatureFlags;
  $isReadOnlyForViewer = $flag->canChangeFromSite() && !$canManageFeatureFlags;
?>
        <div class="<?= $e($rowClass) ?>" data-feature-flag-row data-flag-key="<?= $e($definition->key) ?>" data-flag-default="<?= $definition->defaultValue ? 'true' : 'false' ?>">
          <div class="feature-flag-info">
            <div class="feature-flag-name">
              <?= $e($definition->label) ?>
<?php if (!$flag->isDefault()): ?>
              <span class="badge badge-overridden" title="Default is <?= $definition->defaultValue ? 'enabled' : 'disabled' ?>">overridden</span>
<?php endif; ?>
<?php if ($flag->isLocked()): ?>
              <span class="badge badge-locked" title="<?= $e($flag->lockReason()) ?>">locked</span>
<?php endif; ?>
            </div>
            <p class="feature-flag-description meta"><?= $e($definition->description) ?></p>
            <div class="feature-flag-meta meta">
              <code class="feature-flag-key"><?= $e($definition->key) ?></code>
<?php if ($flag->isLocked()): ?>
              <span class="feature-flag-info-icon" tabindex="0" title="<?= $e($flag->lockReason()) ?>" aria-label="<?= $e($flag->lockReason()) ?>">&#9432;</span>
<?php endif; ?>
<?php if ($parentDefinition !== null): ?>
              <span class="feature-flag-dependency<?= $flag->isBlockedByDependency() ? ' is-blocked' : '' ?>">
<?php if ($flag->isBlockedByDependency()): ?>
                &#9888; inactive &mdash; requires <?= $e($parentDefinition->label) ?>
<?php else: ?>
                requires <?= $e($parentDefinition->label) ?>
<?php endif; ?>
              </span>
<?php endif; ?>
<?php if ($isReadOnlyForViewer): ?>
              <span class="feature-flag-info-icon" tabindex="0" title="Read-only &mdash; requires a root-approved identity to change." aria-label="Read-only, requires a root-approved identity to change.">&#9432;</span>
<?php endif; ?>
<?php if (!$flag->isDefault() && $canEditFlag): ?>
              <form method="post" action="/tools/feature-flags/" class="inline-form feature-flag-reset-form" data-feature-flag-form>
                <input type="hidden" name="key" value="<?= $e($definition->key) ?>">
                <input type="hidden" name="value" value="<?= $definition->defaultValue ? 'true' : 'false' ?>">
                <button type="submit" class="link-button">Reset to default</button>
              </form>
<?php endif; ?>
              <span class="sr-only" data-role="feature-flag-source"><?= $e($flag->source) ?></span>
            </div>
          </div>
          <div class="feature-flag-control">
<?php if ($canEditFlag): ?>
            <form method="post" action="/tools/feature-flags/" class="inline-form" data-feature-flag-form data-feature-flag-toggle>
              <input type="hidden" name="key" value="<?= $e($definition->key) ?>">
              <input type="hidden" name="value" value="<?= $flag->effectiveValue ? 'false' : 'true' ?>">
              <button
                type="submit"
                role="switch"
                aria-checked="<?= $flag->effectiveValue ? 'true' : 'false' ?>"
                aria-label="<?= $e($definition->label) ?>"
                class="switch"
              ></button>
              <div class="meta" data-role="feature-flag-status"></div>
            </form>
<?php else: ?>
            <button
              type="button"
              role="switch"
              aria-checked="<?= $flag->effectiveValue ? 'true' : 'false' ?>"
              aria-label="<?= $e($definition->label) ?>"
              class="switch"
              disabled
            ></button>
<?php endif; ?>
            <span class="feature-flag-state<?= $flag->effectiveValue ? ' is-on' : '' ?>" data-role="feature-flag-effective"><?= $flag->effectiveValue ? 'enabled' : 'disabled' ?></span>
          </div>
        </div>
<?php endforeach; ?>
      </div>
    </section>
<?php endforeach; ?>
  </article>
</section>
