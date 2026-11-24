<?php
/**
 * /tools/preferences/: the settings this browser keeps, each with its current
 * value and a way to change or reset it. The server HTML is the same for every
 * visitor; the inline script below fills in this browser's values before first
 * paint, and preferences.js saves changes and calls it again.
 *
 * @var list<array{name: string, label: string, mode: string}> $preferenceThemes
 */
$tableZoomKeys = array_values(\ForumRewrite\View\ZoomTable::KINDS);
?>
<section class="stack preferences-page" data-preferences>
  <article class="card stack">
    <h1>Preferences</h1>
    <p class="meta">Saved in this browser only.</p>
    <div class="account-group">
      <div class="account-row" data-tone="neutral">
        <label class="account-row__title" for="preference-theme">Theme</label>
        <select id="preference-theme" class="preferences-page__select" data-preference="theme">
<?php foreach ($preferenceThemes as $theme): ?>
          <option value="<?= $e($theme['name']) ?>"><?= $e($theme['label']) ?></option>
<?php endforeach; ?>
        </select>
        <a class="account-row__action" href="/themes/">Browse</a>
      </div>
<?php if ($threadDensityEnabled): ?>
      <div class="account-row" data-tone="neutral">
        <label class="account-row__title" for="preference-thread-view">Thread view</label>
        <select id="preference-thread-view" class="preferences-page__select" data-preference="thread-view">
          <option value="comfortable">Comfortable</option>
          <option value="compact">Compact</option>
        </select>
      </div>
<?php endif; ?>
<?php if ($readingMarginsEnabled): ?>
      <div class="account-row" data-tone="neutral">
        <span class="account-row__title">Reading margins</span>
        <span class="account-row__value" data-preference-value="reading-margins">Site default</span>
        <button type="button" class="account-row__action" data-preference-reset="reading-margins" hidden>Reset</button>
      </div>
<?php endif; ?>
<?php if ($draggableWindowsEnabled ?? false): ?>
      <div class="account-row" data-tone="neutral">
        <label class="account-row__title" for="preference-window-controls">Window controls</label>
        <select id="preference-window-controls" class="preferences-page__select" data-preference="window-controls">
          <option value="on">On</option>
          <option value="off">Off</option>
        </select>
      </div>
<?php endif; ?>
<?php if ($openBesideEnabled ?? false): ?>
      <div class="account-row" data-tone="neutral">
        <label class="account-row__title" for="preference-open-beside">Open links beside</label>
        <select id="preference-open-beside" class="preferences-page__select" data-preference="open-beside">
          <option value="off">Off</option>
          <option value="on">On</option>
        </select>
      </div>
<?php endif; ?>
      <div class="account-row" data-tone="neutral">
        <span class="account-row__title">Table text size</span>
        <span class="account-row__value" data-preference-value="table-size">Site default</span>
        <button type="button" class="account-row__action" data-preference-reset="table-size" hidden>Reset</button>
      </div>
      <div class="account-row" data-tone="neutral">
        <span class="account-row__title">Gallery image size</span>
        <span class="account-row__value" data-preference-value="gallery-size">Site default</span>
        <button type="button" class="account-row__action" data-preference-reset="gallery-size" hidden>Reset</button>
      </div>
      <div class="account-row" data-tone="neutral" data-preference-row="phone-tabs" hidden>
        <span class="account-row__title">Phone tabs</span>
        <span class="account-row__value" data-preference-value="phone-tabs"></span>
        <button type="button" class="account-row__action" data-preference-reset="phone-tabs" hidden>Reset</button>
      </div>
      <div class="account-row" data-tone="neutral">
        <span class="account-row__title">Page themes</span>
        <span class="account-row__value" data-preference-value="page-themes">Shown</span>
        <button type="button" class="account-row__action" data-preference-reset="page-themes" hidden>Show again</button>
      </div>
      <div class="account-row" data-tone="neutral">
        <span class="account-row__title">Welcome note</span>
        <span class="account-row__value" data-preference-value="welcome-note">Shown</span>
        <button type="button" class="account-row__action" data-preference-reset="welcome-note" hidden>Show again</button>
      </div>
<?php if ($pushEnabled ?? false): ?>
      <div class="account-row" data-tone="neutral" data-preference-row="notifications" hidden>
        <span class="account-row__title">Notifications</span>
        <span class="account-row__value" data-preference-value="notifications" aria-live="polite">Off</span>
        <span class="preferences-page__actions">
          <button type="button" class="account-row__action" data-push-test hidden>Send a test</button>
          <button type="button" class="account-row__action" data-push-toggle>Turn on</button>
        </span>
      </div>
<?php endif; ?>
<?php if ($usageLogEnabled): ?>
      <a class="account-row account-row--link" data-tone="neutral" href="/tools/usage-log/"><span class="account-row__title">Usage log</span><span class="account-row__value" data-preference-value="usage-log">Off</span></a>
<?php endif; ?>
    </div>
    <div class="account-group">
      <button type="button" class="account-row account-row--danger" data-tone="neutral" data-preference-reset="all">Reset all preferences</button>
    </div>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
<script>
  // Shows this browser's saved values. Runs here, before first paint, and again
  // from preferences.js after every change. Keys are the ones the layout's head
  // script and each feature's own script read.
  window.forumPreferences = (function () {
    var root = document.documentElement;
    var page = document.querySelector('[data-preferences]');
    var runtime = window.forumBrowserRuntime || {};
    var themeKey = runtime.themeStorageKey || '';
    var keys = {
      themeKey: themeKey,
      pageExitPrefix: themeKey + '-page-exit:',
      paletteSuffix: '-palette',
      density: runtime.threadDensityStorageKey || '',
      margins: runtime.readingMarginsStorageKey || '',
      windowControls: runtime.windowControlsStorageKey || '',
      collapsedWindows: runtime.collapsedWindowsStorageKey || '',
      openBeside: runtime.openBesideStorageKey || '',
      openBesidePane: runtime.openBesidePaneStorageKey || '',
      tableSizes: <?= json_encode($tableZoomKeys, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>,
      gallerySize: 'gallery-zoom',
      navSlots: 'forum_nav_slots',
      welcome: 'forum_visitor_intro_hidden',
      usageLog: 'forum_usage_log',
      push: 'forum_push_state'
    };

    function get(key) {
      try { return key ? localStorage.getItem(key) : null; } catch (error) { return null; }
    }
    function storedKeys() {
      var all = [];
      try { for (var index = 0; index < localStorage.length; index++) all.push(localStorage.key(index)); } catch (error) {}
      return all;
    }
    function pageExits() {
      return storedKeys().filter(function (key) { return key && key.indexOf(keys.pageExitPrefix) === 0; });
    }
    function show(name, text, changed) {
      var value = page.querySelector('[data-preference-value="' + name + '"]');
      var reset = page.querySelector('[data-preference-reset="' + name + '"]');
      if (value) value.textContent = text;
      if (reset) reset.hidden = !changed;
    }
    function savedTheme(select) {
      var theme = get(keys.themeKey);
      var aliases = window.forumThemeLegacyAliases || {};
      if (theme !== null && Object.prototype.hasOwnProperty.call(aliases, theme)) theme = aliases[theme];
      var names = Array.prototype.map.call(select.options, function (option) { return option.value; });
      if (names.indexOf(theme) === -1) theme = root.getAttribute('data-default-theme');
      return names.indexOf(theme) === -1 ? 'auto' : theme;
    }
    function tabLabels() {
      return (root.getAttribute('data-nav-slots') || '').split(' ').filter(Boolean).map(function (section) {
        var link = document.querySelector('.site-header--app nav.nav > .nav-link[data-tab="' + section + '"]');
        return link ? link.textContent.trim() : section;
      }).join(', ');
    }

    function render() {
      var theme = page.querySelector('[data-preference="theme"]');
      if (theme) theme.value = savedTheme(theme);
      var density = page.querySelector('[data-preference="thread-view"]');
      if (density) density.value = get(keys.density) === 'compact' ? 'compact' : 'comfortable';
      var windowControls = page.querySelector('[data-preference="window-controls"]');
      if (windowControls) windowControls.value = get(keys.windowControls) === 'off' ? 'off' : 'on';
      var openBeside = page.querySelector('[data-preference="open-beside"]');
      if (openBeside) openBeside.value = get(keys.openBeside) === 'on' ? 'on' : 'off';
      var margins = root.hasAttribute('data-reading-margins');
      show('reading-margins', margins ? 'Custom' : 'Site default', margins);
      var tableSize = keys.tableSizes.some(function (key) { return get(key) !== null; });
      show('table-size', tableSize ? 'Custom' : 'Site default', tableSize);
      var gallerySize = get(keys.gallerySize) !== null;
      show('gallery-size', gallerySize ? 'Custom' : 'Site default', gallerySize);
      var tabsRow = page.querySelector('[data-preference-row="phone-tabs"]');
      if (tabsRow) {
        tabsRow.hidden = !window.__forumNavSlots;
        show('phone-tabs', tabLabels() || 'None', get(keys.navSlots) !== null);
      }
      var exits = pageExits().length;
      show('page-themes', exits ? 'Left on ' + exits + (exits === 1 ? ' page' : ' pages') : 'Shown', exits > 0);
      var welcomeHidden = get(keys.welcome) === '1';
      show('welcome-note', welcomeHidden ? 'Hidden' : 'Shown', welcomeHidden);
      var recording = false;
      try { recording = JSON.parse(get(keys.usageLog) || 'null').recording === true; } catch (error) {}
      show('usage-log', recording ? 'Recording' : 'Off', false);
      renderNotifications();
    }

    // Notifications (push_preferences.js): shown only to a browser with a saved key that
    // supports push. The last known state is drawn before paint; the script then checks it.
    function renderNotifications() {
      var row = page.querySelector('[data-preference-row="notifications"]');
      if (!row) return;
      var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
      var hasKey = !!(get('forum_pki_fingerprint') && get('forum_pki_private_key'));
      row.hidden = !(supported && hasKey);
      if (row.hidden) return;
      var state = Notification.permission === 'denied' ? 'blocked' : get(keys.push) === 'on' ? 'on' : 'off';
      showNotifications(state);
    }
    function showNotifications(state, note) {
      var row = page.querySelector('[data-preference-row="notifications"]');
      if (!row) return;
      var labels = { on: 'On', off: 'Off', blocked: 'Blocked', working: 'Working…' };
      show('notifications', note || labels[state] || 'Off', false);
      row.setAttribute('data-push-state', state);
      var toggle = row.querySelector('[data-push-toggle]');
      var test = row.querySelector('[data-push-test]');
      toggle.textContent = state === 'on' ? 'Turn off' : 'Turn on';
      toggle.disabled = state === 'working';
      test.hidden = state !== 'on';
    }

    render();
    return { render: render, keys: keys, pageExits: pageExits, storedKeys: storedKeys, showNotifications: showNotifications };
  })();
</script>
