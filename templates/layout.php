<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title) ?></title>
  <script>
    (function () {
      try {
        var theme = localStorage.getItem('zenmemes-theme');
        if (
          theme === 'light' ||
          theme === 'dark' ||
          theme === 'console' ||
          theme === 'lcd' ||
          theme === 'chicago' ||
          theme === 'vapor'
        ) {
          document.documentElement.setAttribute('data-theme', theme);
        }
      } catch (error) {
      }
    })();
  </script>
  <link rel="stylesheet" href="/assets/site.css">
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
  <script src="/assets/theme_toggle.js" defer></script>
</head>
<body>
  <!-- route-source: <?= $e($routeSource) ?> -->
  <div class="shell">
    <header class="site-header">
      <p class="eyebrow"><?= $e($siteName) ?></p>
      <div class="site-header-actions">
<?= $indent($partial('partials/nav.php'), 4) ?>
        <button
          type="button"
          class="theme-toggle"
          data-action="theme-toggle"
          aria-label="Cycle theme"
          title="Cycle theme"
        ></button>
      </div>
    </header>
    <main class="main">
<?= $indent($content, 3) ?>
    </main>
  </div>
</body>
</html>
