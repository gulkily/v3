(function () {
  'use strict';
  // /tools/preferences/: saves changes made on the page. preferences.php shows
  // the saved values (window.forumPreferences.render) before first paint; each
  // change here writes the same key the feature's own script reads, then shows
  // the new values. Identity keys, drafts, the outbox and message caches are
  // never touched.
  var prefs = window.forumPreferences;
  var page = document.querySelector('[data-preferences]');
  if (!prefs || !page) return;
  var keys = prefs.keys;
  var root = document.documentElement;

  function remove(key) {
    try { if (key) localStorage.removeItem(key); } catch (error) {}
  }

  var resets = {
    'reading-margins': function () {
      if (typeof window.forumResetReadingMargins === 'function') {
        window.forumResetReadingMargins();
      } else {
        remove(keys.margins);
        root.removeAttribute('data-reading-margins');
        root.style.removeProperty('--reading-left');
        root.style.removeProperty('--reading-right');
      }
    },
    'table-size': function () {
      keys.tableSizes.forEach(remove);
    },
    'gallery-size': function () {
      remove(keys.gallerySize);
      root.style.removeProperty('--gallery-zoom');
    },
    'phone-tabs': function () {
      remove(keys.navSlots);
      var defaults = (window.__forumNavSlots && window.__forumNavSlots.defaults) || [];
      if (defaults.length) root.setAttribute('data-nav-slots', defaults.join(' '));
      else root.removeAttribute('data-nav-slots');
    },
    'page-themes': function () {
      prefs.pageExits().forEach(remove);
    },
    'welcome-note': function () {
      remove(keys.welcome);
    }
  };

  // Everything this page lists, plus each theme's colour choice; the usage log
  // keeps its entries (Clear on the Usage log page removes them). The page
  // reloads so every setting shows its default from first paint.
  function resetAll() {
    var themeKey = keys.themeKey;
    prefs.storedKeys().forEach(function (key) {
      if (!key || !themeKey) return;
      if (key === themeKey || key.indexOf(keys.pageExitPrefix) === 0
        || (key.indexOf(themeKey + '-') === 0 && key.slice(-keys.paletteSuffix.length) === keys.paletteSuffix)) {
        remove(key);
      }
    });
    [keys.density, keys.margins, keys.gallerySize, keys.navSlots, keys.welcome].forEach(remove);
    keys.tableSizes.forEach(remove);
    window.location.reload();
  }

  page.addEventListener('change', function (event) {
    var control = event.target;
    var name = control.getAttribute && control.getAttribute('data-preference');
    if (name === 'theme' && typeof window.forumSelectTheme === 'function') {
      window.forumSelectTheme(control.value);
    } else if (name === 'thread-view') {
      try { localStorage.setItem(keys.density, control.value); } catch (error) {}
      if (control.value === 'compact') root.setAttribute('data-thread-density', 'compact');
      else root.removeAttribute('data-thread-density');
    }
  });

  // A theme chosen in the header's theme menu shows in the Theme row too.
  if (window.MutationObserver) {
    new MutationObserver(function () { prefs.render(); })
      .observe(root, { attributes: true, attributeFilter: ['data-resolved-theme'] });
  }

  page.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-preference-reset]');
    if (!button) return;
    var name = button.getAttribute('data-preference-reset');
    if (name === 'all') {
      resetAll();
      return;
    }
    if (resets[name]) resets[name]();
    prefs.render();
  });
})();
