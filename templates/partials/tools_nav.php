<div class="nav tools-nav">
<?php $sawStandaloneToolNavOption = false; ?>
<?php foreach ($toolNavOptions as $option): ?>
<?php if (!empty($option['standalone']) && !$sawStandaloneToolNavOption): $sawStandaloneToolNavOption = true; ?>
  <span class="tools-nav-divider" aria-hidden="true"></span>
<?php endif; ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
<?php $class .= !empty($option['standalone']) ? ' nav-link-standalone' : ''; ?>
  <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?><?php if (!empty($option['standalone'])): ?><span class="nav-link-standalone-marker" aria-hidden="true"> &#8599;</span><?php endif; ?></a>
<?php endforeach; ?>
</div>
