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
<?= $partial('partials/nav.php') ?>
    </header>
    <main class="main">
<?= $content ?>
    </main>
  </div>
</body>
</html>
