<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Operations</h1>
    <div class="feature-flag-list">
      <div class="feature-flag-row"<?= $canManageOperations ? ' data-high-traffic-control' : '' ?>>
        <div class="feature-flag-info">
          <div class="feature-flag-name">High-traffic mode</div>
          <p class="feature-flag-description meta">Public pages may be about two minutes old.</p>
<?php if ($canManageOperations): ?>
          <p class="meta" role="status" aria-live="polite" data-high-traffic-status<?= $highTrafficError === null || $highTrafficError === '' ? ' hidden' : '' ?>><?= $e($highTrafficError ?? '') ?></p>
<?php else: ?>
          <div class="feature-flag-meta meta">
            <span class="feature-flag-info-icon" tabindex="0" title="Read-only &mdash; requires a root-approved identity to change." aria-label="Read-only, requires a root-approved identity to change.">&#9432;</span>
          </div>
<?php endif; ?>
        </div>
<?php if ($canManageOperations): ?>
        <form class="feature-flag-control">
          <button type="submit" role="switch" aria-checked="<?= $highTrafficEnabled ? 'true' : 'false' ?>" aria-label="High-traffic mode" class="switch" data-enabled="<?= $highTrafficEnabled ? 'true' : 'false' ?>"></button>
          <span class="feature-flag-state<?= $highTrafficEnabled ? ' is-on' : '' ?>" data-high-traffic-state><?= $highTrafficEnabled ? 'enabled' : 'disabled' ?></span>
        </form>
<?php else: ?>
        <div class="feature-flag-control">
          <button type="button" role="switch" aria-checked="<?= $highTrafficEnabled ? 'true' : 'false' ?>" aria-label="High-traffic mode" class="switch" disabled></button>
          <span class="feature-flag-state<?= $highTrafficEnabled ? ' is-on' : '' ?>"><?= $highTrafficEnabled ? 'enabled' : 'disabled' ?></span>
        </div>
<?php endif; ?>
      </div>
    </div>
  </article>
</section>
