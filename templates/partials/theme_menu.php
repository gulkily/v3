<div class="theme-menu" data-role="theme-menu">

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
  <div class="theme-menu__popover" id="theme-menu-popover" role="menu" aria-label="Theme" hidden>
<?php foreach ($themes as $theme): ?>
    <button
      type="button"
      class="theme-menu__option"
      role="menuitemradio"
      aria-checked="false"
      data-theme-option="<?= $e($theme['name']) ?>"
      data-theme-option-mode="<?= $e($theme['mode']) ?>"
    >
<?php if ($theme['name'] === 'auto'): ?>
      <span class="theme-swatch theme-menu__swatch" data-theme-mode="auto" aria-hidden="true"></span>
<?php else: ?>
      <span class="theme-swatch theme-menu__swatch" data-theme="<?= $e($theme['name']) ?>" aria-hidden="true"></span>
<?php endif; ?>
      <span class="theme-menu__label"><?= $e($theme['label']) ?></span>
    </button>
<?php endforeach; ?>
  </div>
<?php if (in_array('lcd', array_column($themes, 'name'), true)): ?>
  <div class="lcd-palette" data-role="lcd-palette">
    <button type="button" class="lcd-palette__trigger" data-action="lcd-palette-toggle"
      aria-label="Choose LCD palette" title="Choose LCD palette" aria-haspopup="menu"
      aria-expanded="false" aria-controls="lcd-palette-popover"><span class="lcd-palette__chip" aria-hidden="true"></span></button>
    <div class="theme-menu__popover" id="lcd-palette-popover" role="menu" aria-label="LCD palette" hidden>
<?php foreach ($lcdPalettes as $palette => $label): ?>
      <button type="button" class="theme-menu__option lcd-palette__option" role="menuitemradio"
        aria-checked="false" data-lcd-palette-option="<?= $e($palette) ?>">
        <span class="lcd-palette__chip" data-palette-chip="<?= $e($palette) ?>" aria-hidden="true"></span><?= $e($label) ?>
      </button>
<?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
</div>
