<div class="theme-menu" data-role="theme-menu">
<?php foreach ($themePalettes as $paletteTheme => $paletteEntry): ?>
<?php $paletteLabel = $paletteEntry['label']; ?>
  <div class="lcd-palette" data-role="<?= $e($paletteTheme) ?>-palette" data-palette-label="<?= $e($paletteLabel) ?>">
    <button type="button" class="lcd-palette__trigger" data-action="<?= $e($paletteTheme) ?>-palette-toggle"
      aria-label="Choose <?= $e($paletteLabel) ?> palette" title="Choose <?= $e($paletteLabel) ?> palette" aria-haspopup="menu"
      aria-expanded="false" aria-controls="<?= $e($paletteTheme) ?>-palette-popover"><span class="lcd-palette__chip" aria-hidden="true"></span></button>
    <div class="theme-menu__popover" id="<?= $e($paletteTheme) ?>-palette-popover" role="menu" aria-label="<?= $e($paletteLabel) ?> palette" hidden>
<?php foreach ($paletteEntry['palettes'] as $palette => $label): ?>
      <button type="button" class="theme-menu__option lcd-palette__option" role="menuitemradio"
        aria-checked="false" data-<?= $e($paletteTheme) ?>-palette-option="<?= $e($palette) ?>">
        <span class="lcd-palette__chip" data-palette-theme="<?= $e($paletteTheme) ?>" data-palette-chip="<?= $e($palette) ?>" aria-hidden="true"></span><?= $e($label) ?>
      </button>
<?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
  <button
    type="button"
    class="theme-toggle theme-swatch"
    data-action="theme-cycle"
    aria-label="Cycle theme"
    title="Cycle theme"
  ></button>
  <button
    type="button"
    class="theme-menu__trigger"
    data-action="theme-toggle"
    aria-label="Choose theme"
    title="Choose theme"
    aria-haspopup="menu"
    aria-expanded="false"
    aria-controls="theme-menu-popover"
  >&#9662;</button>
<?php $themeLayout = \ForumRewrite\View\ThemeRegistry::menuLayout($themes); ?>
<?php $themeOption = static function (array $theme, string $attributes = '') use ($e): string { ob_start(); ?>
    <button
      type="button"
      class="theme-menu__option"
      role="menuitemradio"
      aria-checked="false"
      data-theme-option="<?= $e($theme['name']) ?>"
      data-theme-option-mode="<?= $e($theme['mode']) ?>"<?php if (isset($theme['order'])): ?>
      data-theme-order="<?= $e((string) $theme['order']) ?>"<?php endif; ?><?= $attributes ?><?php if (($theme['cycle'] ?? true) === false): ?>
      data-theme-cycle="skip"<?php endif; ?>
    >
<?php if ($theme['name'] === 'auto'): ?>
      <span class="theme-swatch theme-menu__swatch" data-theme-mode="auto" aria-hidden="true"></span>
<?php else: ?>
      <span class="theme-swatch theme-menu__swatch" data-theme="<?= $e($theme['name']) ?>" aria-hidden="true"></span>
<?php endif; ?>
      <span class="theme-menu__label"><?= $e($theme['label']) ?></span>
    </button>
<?php return (string) ob_get_clean(); }; ?>
<?php if ($themeLayout['columns']): ?>
  <div class="theme-menu__popover theme-menu__popover--columns" id="theme-menu-popover" role="menu" aria-label="Theme" hidden>
<?php if ($themeLayout['auto'] !== null): ?>
<?= $themeOption($themeLayout['auto'], "\n      data-theme-column=\"both\" data-theme-row=\"auto\"") ?>
<?php endif; ?>
    <span class="theme-menu__heading" role="none" aria-hidden="true">Light</span>
    <span class="theme-menu__heading" role="none" aria-hidden="true">Dark</span>
<?php foreach (['rows' => 'p', 'unpaired' => 'u'] as $section => $prefix): ?>
<?php if ($section === 'unpaired' && $themeLayout['unpaired'] !== [] && $themeLayout['rows'] !== []): ?>
    <hr class="theme-menu__divider" role="none" aria-hidden="true">
<?php endif; ?>
<?php foreach ($themeLayout[$section] as $rowIndex => $row): ?>
<?php foreach (['light', 'dark'] as $column): ?>
<?php if ($row[$column] === null): ?>
    <span class="theme-menu__empty" role="none" aria-hidden="true"></span>
<?php else: ?>
<?= $themeOption($row[$column], "\n      data-theme-column=\"" . $column . "\" data-theme-row=\"" . $prefix . $rowIndex . "\"") ?>
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="theme-menu__popover" id="theme-menu-popover" role="menu" aria-label="Theme" hidden>
<?php foreach ($themes as $theme): ?>
<?= $themeOption($theme) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
