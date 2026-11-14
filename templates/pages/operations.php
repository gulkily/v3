<?php
$highTrafficRows = [
    ['setting' => 'mode', 'name' => 'High-traffic mode', 'description' => 'Public pages may be about two minutes old.', 'on' => $highTrafficEnabled, 'note' => $highTrafficReason ?? null, 'unavailable' => null],
    ['setting' => 'turn-on', 'name' => 'Turn on automatically', 'description' => null, 'on' => $highTrafficTurnOn ?? false, 'note' => null, 'unavailable' => null],
    ['setting' => 'turn-off', 'name' => 'Turn off automatically', 'description' => null, 'on' => $highTrafficTurnOff ?? false, 'note' => $highTrafficTurnOffUnavailable ?? null,
        // An unavailable switch can still be turned off, never on.
        'unavailable' => ($highTrafficTurnOff ?? false) ? null : ($highTrafficTurnOffUnavailable ?? null)],
];
?>
<section class="stack">
  <article class="card">
    <h1>Operations</h1>
    <div class="feature-flag-list">
<?php foreach ($highTrafficRows as $row): ?>
<?php $control = $row['setting'] === 'mode' ? ' data-high-traffic-control' : ' data-high-traffic-setting="' . $e($row['setting']) . '"'; ?>
      <div class="feature-flag-row"<?= $canManageOperations ? $control : '' ?>>
        <div class="feature-flag-info">
          <div class="feature-flag-name"><?= $e($row['name']) ?></div>
<?php if ($row['description'] !== null): ?>
          <p class="feature-flag-description meta"><?= $e($row['description']) ?></p>
<?php endif; ?>
          <p class="feature-flag-description meta" data-high-traffic-note<?= $row['note'] === null ? ' hidden' : '' ?>><?= $e($row['note'] ?? '') ?></p>
<?php if ($canManageOperations): ?>
          <p class="meta" role="status" aria-live="polite" data-high-traffic-status<?= $row['setting'] !== 'mode' || $highTrafficError === null || $highTrafficError === '' ? ' hidden' : '' ?>><?= $row['setting'] === 'mode' ? $e($highTrafficError ?? '') : '' ?></p>
<?php else: ?>
          <div class="feature-flag-meta meta">
            <span class="feature-flag-info-icon" tabindex="0" title="Read-only &mdash; requires a root-approved identity to change." aria-label="Read-only, requires a root-approved identity to change.">&#9432;</span>
          </div>
<?php endif; ?>
        </div>
<?php if ($canManageOperations): ?>
        <form class="feature-flag-control">
          <button type="submit" role="switch" aria-checked="<?= $row['on'] ? 'true' : 'false' ?>" aria-label="<?= $e($row['name']) ?>" class="switch" data-enabled="<?= $row['on'] ? 'true' : 'false' ?>"<?= $row['unavailable'] !== null ? ' disabled' : '' ?>></button>
          <span class="feature-flag-state<?= $row['on'] ? ' is-on' : '' ?>" data-high-traffic-state><?= $row['on'] ? 'enabled' : 'disabled' ?></span>
        </form>
<?php else: ?>
        <div class="feature-flag-control">
          <button type="button" role="switch" aria-checked="<?= $row['on'] ? 'true' : 'false' ?>" aria-label="<?= $e($row['name']) ?>" class="switch" disabled></button>
          <span class="feature-flag-state<?= $row['on'] ? ' is-on' : '' ?>"><?= $row['on'] ? 'enabled' : 'disabled' ?></span>
        </div>
<?php endif; ?>
      </div>
<?php endforeach; ?>
    </div>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
