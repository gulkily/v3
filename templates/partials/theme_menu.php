<div class="theme-menu" data-role="theme-menu">
  <button
    type="button"
    class="theme-toggle theme-swatch"
    data-action="theme-toggle"
    aria-label="Choose theme"
    title="Choose theme"
    aria-haspopup="menu"
    aria-expanded="false"
    aria-controls="theme-menu-popover"
  ></button>
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
</div>
