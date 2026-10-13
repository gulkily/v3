<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title><?= $e($title) ?></title>
  <link rel="icon" href="<?= $e($faviconPath) ?>" sizes="32x32">
  <script>
    (function () {
      var modes = <?= json_encode($themeModes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR) ?>;
      var theme = null;
      try {
        theme = localStorage.getItem(<?= json_encode($themeStorageKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>);
      } catch (error) {}
      if (!Object.prototype.hasOwnProperty.call(modes, theme)) {
        theme = <?= json_encode($defaultTheme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
      }
      var scheme = Object.prototype.hasOwnProperty.call(modes, theme)
        ? modes[theme]
        : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-forte-scheme', scheme === 'dark' ? 'dark' : 'light');
    })();
  </script>
  <script>
    window.__forumAssetPaths = <?= json_encode($browserRuntimeAssetPaths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
  </script>
  <link rel="stylesheet" href="<?= $e($siteCssPath) ?>">
<?php foreach ($additionalCssPaths as $additionalCssPath): ?>
  <link rel="stylesheet" href="<?= $e($additionalCssPath) ?>">
<?php endforeach; ?>
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="<?= $e($bodyClass) ?>">
<?= $content ?>
</body>
</html>
