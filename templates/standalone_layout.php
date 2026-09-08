<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title) ?></title>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="stylesheet" href="<?= $e($siteCssPath) ?>">
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="<?= $e($bodyClass) ?>">
<?= $content ?>
</body>
</html>
