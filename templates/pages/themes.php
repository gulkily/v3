<section class="stack theme-catalog">
  <h1>Themes</h1>
<?php
// The card for the theme showing now is marked before paint: layout.php sets
// data-resolved-theme in <head>, and theme_toggle.js keeps it current.
$currentSelectors = array_map(
    static fn (array $theme): string => ':root[data-resolved-theme="' . $theme['name'] . '"] [data-theme-card="' . $theme['name'] . '"]',
    $themes,
);
?>
<?php if ($currentSelectors !== []): ?>
  <style><?= implode(",\n", array_map(static fn (string $s): string => $s . ' .theme-catalog__use', $currentSelectors)) ?> { display: none; }
<?= implode(",\n", array_map(static fn (string $s): string => $s . ' .theme-catalog__current', $currentSelectors)) ?> { display: inline; }
<?= implode(",\n", $currentSelectors) ?> { outline: 2px solid var(--active-line); outline-offset: 2px; }</style>
<?php endif; ?>
  <ul class="theme-catalog__grid">
<?php foreach ($themes as $theme): ?>
    <li class="card theme-catalog__card" data-theme-card="<?= $e($theme['name']) ?>">
      <div class="theme-catalog__preview" style="<?= $e(\ForumRewrite\View\ThemeCatalog::style($theme['properties'])) ?>">
        <h2 class="theme-catalog__name"><?= $e($theme['label']) ?></h2>
        <span class="theme-catalog__sample-meta" aria-hidden="true">3 replies · 2h ago</span>
        <span class="theme-catalog__sample-row" aria-hidden="true">
          <span class="theme-catalog__sample-button">Reply</span>
          <span class="theme-catalog__sample-tag">#memes</span>
        </span>
      </div>
      <div class="theme-catalog__details">
        <code>theme: <?= $e($theme['name']) ?></code>
        <span class="theme-catalog__mode"><?= $e(ucfirst($theme['mode'])) ?><?php if ($theme['palettes'] !== []): ?> · <?= $e(implode(', ', $theme['palettes'])) ?><?php endif; ?></span>
        <button type="button" class="theme-catalog__use" data-theme-catalog-select="<?= $e($theme['name']) ?>">Use</button>
        <span class="theme-catalog__current">Current</span>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
</section>
