<!doctype html>
<html lang="en" data-default-theme="<?= $e($defaultTheme) ?>"<?php if ($pageTheme !== null): ?> data-page-theme="<?= $e($pageTheme) ?>" data-page-path="<?= $e($pagePath) ?>"<?php endif; ?> data-theme-hint-cookie="<?= $e($themeHintCookieName) ?>" data-approved-members-only="<?= $approvedMembersOnlyEnabled ? '1' : '0' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title) ?></title>
  <link id="theme-stylesheet" rel="stylesheet" href="<?= $e($initialThemeStylesheetPath) ?>" fetchpriority="high">
  <script>
    (function () {
      var allowed = <?= json_encode($explicitThemeNames, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
      var themeStylesheetPaths = <?= json_encode($themeStylesheetPaths, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
      window.forumThemeScriptPaths = <?= json_encode($themeScriptPaths, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR) ?>;
      var themeHintCookieName = <?= json_encode($themeHintCookieName, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
      var themeLegacyAliases = <?= json_encode($themeLegacyAliases, JSON_HEX_TAG | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR) ?>;
      var browserRuntime = <?= json_encode($browserRuntime, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
      var themePalettes = <?= json_encode(array_map(static fn (array $entry): array => array_keys($entry['palettes']), $themePalettes), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
      try {
        // Same rule as member_approval.js: approval remembered for this identity.
        var memberFingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
        if (/^[a-f0-9]{40}$/.test(memberFingerprint)
          && localStorage.getItem('forum_pki_public_key') && localStorage.getItem('forum_pki_private_key')
          && localStorage.getItem('forum_pki_approved_fingerprint') === memberFingerprint) {
          document.documentElement.setAttribute('data-member', 'approved');
        }
      } catch (error) {}
      try {
        // First-visit intro (board.php): hidden before paint for any browser with a
        // saved identity (same keys as above) or that chose to hide it.
        if ((/^[a-f0-9]{40}$/.test((localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase())
            && localStorage.getItem('forum_pki_public_key') && localStorage.getItem('forum_pki_private_key'))
          || localStorage.getItem('forum_visitor_intro_hidden') === '1') {
          document.documentElement.setAttribute('data-visitor', 'known');
        }
      } catch (error) {}
      try {
        // Post table text size (table_zoom.js), restored before paint.
        var tableZoomSaved = localStorage.getItem('table-zoom');
        var tableZoom = parseFloat(tableZoomSaved);
        if (tableZoomSaved === 'page') {
          document.documentElement.setAttribute('data-table-size', 'page');
        } else if (tableZoom >= 0.5 && tableZoom <= 8) {
          document.documentElement.style.setProperty('--table-zoom', String(tableZoom));
        }
      } catch (error) {}
<?php if ($navSlotCount > 0): ?>
      try {
        // Personal phone tabs, chosen by nav_slots.js and read here before paint.
        var navSlotCount = <?= (int) $navSlotCount ?>;
        var navSlotSections = <?= json_encode($navSlotSections, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        window.__forumNavSlots = { count: navSlotCount, sections: navSlotSections };
        var navSlotState = JSON.parse(localStorage.getItem('forum_nav_slots') || 'null');
        var navSlots = navSlotState && Array.isArray(navSlotState.slots) ? navSlotState.slots : [];
        var navMember = document.documentElement.getAttribute('data-member') === 'approved';
        navSlots = navSlots.filter(function (section) {
          return navSlotSections.indexOf(section) !== -1 && ((section !== 'messages' && section !== 'inbox') || navMember);
        }).slice(0, navSlotCount);
        if (navSlots.length) document.documentElement.setAttribute('data-nav-slots', navSlots.join(' '));
      } catch (error) {}
<?php endif; ?>
      // Each palette theme's saved colour, or its first palette.
      Object.keys(themePalettes).forEach(function (paletteTheme) {
        var choices = themePalettes[paletteTheme];
        var palette = choices[0];
        try {
          var savedPalette = localStorage.getItem(browserRuntime.themeStorageKey + '-' + paletteTheme + '-palette');
          if (choices.indexOf(savedPalette) !== -1) palette = savedPalette;
        } catch (error) {}
        document.documentElement.setAttribute('data-' + paletteTheme + '-palette', palette);
      });
      var theme = null;
      window.forumBrowserRuntime = browserRuntime;

      function cookieValue(name) {
        var prefix = name + '=';
        var entries = document.cookie ? document.cookie.split(';') : [];
        for (var index = 0; index < entries.length; index++) {
          var entry = entries[index].trim();
          if (entry.indexOf(prefix) === 0) {
            return decodeURIComponent(entry.slice(prefix.length));
          }
        }
        return null;
      }

      function updateThemeHint(resolvedTheme) {
        if (cookieValue(themeHintCookieName) === resolvedTheme) {
          return;
        }
        document.cookie = themeHintCookieName + '=' + encodeURIComponent(resolvedTheme)
          + '; Path=/; Max-Age=31536000; SameSite=Lax'
          + (location.protocol === 'https:' ? '; Secure' : '');
      }

      try {
        theme = localStorage.getItem(browserRuntime.themeStorageKey);
        if (theme === null && browserRuntime.namespace === 'zenmemes' && browserRuntime.themeStorageKey !== 'zenmemes-theme') {
          theme = localStorage.getItem('zenmemes-theme');
          if (theme !== null) {
            localStorage.setItem(browserRuntime.themeStorageKey, theme);
          }
        }
      } catch (error) {
      }

      if (theme !== null && Object.prototype.hasOwnProperty.call(themeLegacyAliases, theme)) {
        theme = themeLegacyAliases[theme];
        try {
          localStorage.setItem(browserRuntime.themeStorageKey, theme);
        } catch (error) {
        }
      }
      window.forumThemeLegacyAliases = themeLegacyAliases;

      if (allowed.indexOf(theme) === -1) {
        theme = document.documentElement.getAttribute('data-default-theme');
      }

      // A page theme shows on its own page unless this browser left it there.
      var pageTheme = document.documentElement.getAttribute('data-page-theme');
      if (pageTheme && allowed.indexOf(pageTheme) !== -1) {
        var pageThemeExited = false;
        try {
          pageThemeExited = localStorage.getItem(browserRuntime.themeStorageKey + '-page-exit:'
            + document.documentElement.getAttribute('data-page-path')) === '1';
        } catch (error) {
        }
        if (!pageThemeExited) theme = pageTheme;
      }

      if (allowed.indexOf(theme) !== -1) {
        document.documentElement.setAttribute('data-theme', theme);
      }

      var resolvedTheme = allowed.indexOf(theme) !== -1
        ? theme
        : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var themeStylesheet = document.getElementById('theme-stylesheet');
      if (themeStylesheet && themeStylesheetPaths[resolvedTheme]) {
        themeStylesheet.setAttribute('href', themeStylesheetPaths[resolvedTheme]);
        themeStylesheet.setAttribute('data-theme-name', resolvedTheme);
      }
      document.documentElement.setAttribute('data-resolved-theme', resolvedTheme);
      window.forumThemeStylesheetPaths = themeStylesheetPaths;
      window.forumUpdateThemeHint = updateThemeHint;
      updateThemeHint(resolvedTheme);

<?php if ($showThreadDensityToggle): ?>
      try {
        var densityStorageKey = browserRuntime.threadDensityStorageKey;
        var densityParam = new URLSearchParams(location.search).get('density');
        var density;
        if (densityParam === 'compact' || densityParam === 'comfortable') {
          density = densityParam;
          localStorage.setItem(densityStorageKey, density);
        } else {
          density = localStorage.getItem(densityStorageKey);
        }
        if (density === 'compact') {
          document.documentElement.setAttribute('data-thread-density', 'compact');
        }
      } catch (error) {
      }
<?php endif; ?>
    })();
  </script>
<?php if ($appVersionNotificationEnabled): ?>
  <meta name="app-version" content="<?= $e($appVersion) ?>">
  <meta name="app-version-endpoint" content="<?= $e($versionEndpoint) ?>">
<?php if ($versionStorageKey !== 'forum_pending_app_version'): ?>
  <meta name="app-version-storage-key" content="<?= $e($versionStorageKey) ?>">
<?php endif; ?>
<?php endif; ?>
  <link rel="icon" href="<?= $e($faviconPath) ?>" sizes="32x32">
<?php if (!$approvedMembersOnlyEnabled): ?>
  <link rel="manifest" href="/manifest.webmanifest">
<?php endif; ?>
  <link rel="alternate" type="text/plain" href="/llms.txt" title="Agent guide">
  <script>
    window.__forumAssetPaths = <?= json_encode($browserRuntimeAssetPaths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
    window.forumBrowserIdentityOptions = {
      automaticGuestKeypairEnabled: <?= $automaticGuestKeypairEnabled ? 'true' : 'false' ?>,
      usernameChangesEnabled: <?= ($usernameChangesEnabled ?? false) ? 'true' : 'false' ?>
    };
  </script>
  <style data-role="critical-css"><?= $criticalCss ?></style>
  <link rel="preload" href="<?= $e($siteCssPath) ?>" as="style" fetchpriority="high">
  <link rel="stylesheet" href="<?= $e($siteCssPath) ?>" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="<?= $e($siteCssPath) ?>"></noscript>
  <link rel="stylesheet" href="<?= $e($navIconsCssPath) ?>">
<?php foreach ($pageStylesheetPaths as $pageStylesheetPath): ?>
  <link rel="stylesheet" href="<?= $e($pageStylesheetPath) ?>">
<?php endforeach; ?>
  <script src="<?= $e($themeToggleScriptPath) ?>" defer></script>
  <script src="<?= $e($memberApprovalScriptPath) ?>" defer></script>
<?php if ($showThreadDensityToggle): ?>
  <script src="<?= $e($threadDensityToggleScriptPath) ?>" defer></script>
<?php endif; ?>
<?php foreach ($scriptPaths as $scriptPath): ?>
  <script src="<?= $e($scriptPath) ?>" defer></script>
<?php endforeach; ?>
  <script src="<?= $e($composeDraftClearScriptPath) ?>" defer></script>
  <script src="<?= $e($replyViewportScriptPath) ?>" defer></script>
  <script src="<?= $e($composeDisclosureScriptPath) ?>" defer></script>
<?php if (($eventWhenScriptPath ?? null) !== null): ?>
  <script src="<?= $e($eventWhenScriptPath) ?>" defer></script>
<?php endif; ?>
  <script src="<?= $e($markdownDetectScriptPath) ?>" defer></script>
  <script src="<?= $e($tableZoomScriptPath) ?>" defer></script>
  <script src="<?= $e($inviteNavigationScriptPath) ?>" defer></script>
<?php if (($inboxUnreadScriptPath ?? null) !== null): ?>
  <script src="<?= $e($inboxUnreadScriptPath) ?>" defer></script>
<?php endif; ?>
<?php if ($navigationSlot === 'app' && ($deployPromptScriptPath ?? null) !== null): ?>
  <script src="<?= $e($deployPromptScriptPath) ?>" defer></script>
<?php endif; ?>
<?php if ($navigationSlot === 'app'): ?>
  <script src="<?= $e($accountMenuScriptPath) ?>" defer></script>
  <script src="<?= $e($tabBarScrollScriptPath) ?>" defer></script>
  <script src="<?= $e($navSlotsScriptPath) ?>" defer></script>
<?php endif; ?>
<?php if ($appVersionNotificationEnabled): ?>
  <script src="<?= $e($versionCheckScriptPath) ?>" defer></script>
<?php endif; ?>
<?php /* After the version checker, which reads its __v reload token first. */ ?>
  <script src="<?= $e($urlHintsScriptPath) ?>" defer></script>
<?php if (!$approvedMembersOnlyEnabled): ?>
  <script src="<?= $e($pwaRegistrationScriptPath) ?>" defer></script>
<?php endif; ?>
</head>
<body data-backup-state="<?= ($textBackupEnabled ?? true) ? '1' : '0' ?><?= ($imageBackupEnabled ?? true) ? '1' : '0' ?>" data-image-content-state="<?= ($imageContentEnabled ?? false) ? '1' : '0' ?>"<?= $publicAuthenticationResume ? ' data-private-site-auth-state data-public-auth-resume="true" data-authenticated-identity-id=""' : '' ?>>
<?php if ($appVersionNotificationEnabled): ?>
  <div class="app-version-banner" data-role="app-version-banner" hidden>
    <div class="app-version-banner__inner">
      <span class="app-version-banner__text">A new version is available.</span>
      <button type="button" class="app-version-banner__reload" data-action="reload-for-new-version">Reload</button>
    </div>
  </div>
<?php endif; ?>
  <!-- route-source: <?= $e($routeSource) ?> -->
  <div class="shell"<?= $navigationSlot === 'app' ? ' data-shell="app"' : '' ?>>
<?php if ($navigationSlot === 'app'): ?>
    <div class="shell-sky" aria-hidden="true"></div>
<?= $indent($partial('partials/app_header.php'), 2) ?>
<?php else: ?>
    <header class="site-header">
      <p class="eyebrow"><?= $e($siteName) ?></p>
      <div class="site-header-actions">
<?= $indent($partial('partials/nav.php'), 4) ?>
<?php if ($showThreadDensityToggle): ?>
<?= $indent($partial('partials/thread_density_toggle.php'), 4) ?>
<?php endif; ?>
<?= $indent($partial('partials/theme_menu.php'), 4) ?>
      </div>
    </header>
<?php endif; ?>
    <main class="main">
<?= $indent($content, 3) ?>
    </main>
    <footer class="site-status-bar" aria-hidden="true">
      <span class="site-status-bar__panel">Page 1</span>
      <span class="site-status-bar__panel">Sec 1</span>
      <span class="site-status-bar__panel">1/1</span>
      <span class="site-status-bar__panel site-status-bar__panel--wide">At 1&quot;&nbsp;&nbsp;Ln 1&nbsp;&nbsp;Col 1</span>
      <span class="site-status-bar__panel site-status-bar__panel--dim">REC</span>
      <span class="site-status-bar__panel site-status-bar__panel--dim">TRK</span>
      <span class="site-status-bar__panel site-status-bar__panel--dim">EXT</span>
      <span class="site-status-bar__panel site-status-bar__panel--dim">OVR</span>
    </footer>
  </div>
</body>
</html>
