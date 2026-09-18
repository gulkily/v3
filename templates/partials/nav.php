<nav class="nav">
<?php foreach ($navItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
  <a class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"<?= !empty($item['invite_action']) ? ' data-invite-navigation' : '' ?>><?= $e($item['label']) ?></a>
<?php endforeach; ?>
</nav>
