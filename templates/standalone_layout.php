<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= $e($title) ?></title>
  <link rel="icon" href="<?= $e($faviconPath) ?>" sizes="32x32">
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
