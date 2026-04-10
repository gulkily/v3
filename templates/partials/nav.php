<nav class="nav">
<?php foreach ($navItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
  <a class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?></a>
<?php endforeach; ?>
</nav>
