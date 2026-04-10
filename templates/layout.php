<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title) ?></title>
  <link rel="stylesheet" href="/assets/site.css">
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
</head>
<body>
  <!-- route-source: <?= $e($routeSource) ?> -->
  <div class="shell">
    <header class="site-header">
      <p class="eyebrow">PHP Forum Rewrite</p>
      <nav class="nav">
<?php foreach ($navItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
        <a class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?></a>
<?php endforeach; ?>
      </nav>
    </header>
    <main class="main">
<?= $content ?>
    </main>
  </div>
</body>
</html>
