<div class="nav tools-nav" role="navigation" aria-label="Tools">
<?php foreach ($toolNavOptions as $option): ?>
<?php if (($textBackupEnabled ?? true) === false && ($imageBackupEnabled ?? true) === false && ($option['key'] ?? '') === 'backup') { continue; } ?>
<?php if (!empty($option['group_start'])): ?>
  <span class="tools-nav-divider" aria-hidden="true"></span>
<?php endif; ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
<?php $class .= !empty($option['standalone']) ? ' nav-link-standalone' : ''; ?>
  <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>" data-tone="<?= $e($option['tone'] ?? '') ?>"><?= $e($option['label']) ?><?php if (!empty($option['standalone'])): ?><span class="nav-link-standalone-marker" aria-hidden="true"> &#8599;</span><?php endif; ?></a>
<?php endforeach; ?>
</div>
