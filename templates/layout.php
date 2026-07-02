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
        var allowed = <?= json_encode($explicitThemeNames, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        if (allowed.indexOf(theme) !== -1) {
          document.documentElement.setAttribute('data-theme', theme);
        }
      } catch (error) {
      }
    })();
  </script>
<?php if ($appVersionNotificationEnabled): ?>
  <meta name="app-version" content="<?= $e($appVersion) ?>">
  <meta name="app-version-endpoint" content="/api/version">
<?php endif; ?>
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="stylesheet" href="<?= $e($siteCssPath) ?>">
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
  <script src="<?= $e($composeDraftClearScriptPath) ?>" defer></script>
  <script src="<?= $e($themeToggleScriptPath) ?>" defer></script>
<?php if ($appVersionNotificationEnabled): ?>
  <script src="<?= $e($versionCheckScriptPath) ?>" defer></script>
<?php endif; ?>
</head>
<body>
<?php if ($appVersionNotificationEnabled): ?>
  <div class="app-version-banner" data-role="app-version-banner" hidden>
    <div class="app-version-banner__inner">
      <span class="app-version-banner__text">A new version is available.</span>
      <button type="button" class="app-version-banner__reload" data-action="reload-for-new-version">Reload</button>
    </div>
  </div>
<?php endif; ?>
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
