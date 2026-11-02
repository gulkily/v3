(function () {
  var runtime = window.forumBrowserRuntime || null;
  var storageKey = runtime && runtime.themeStorageKey;
  if (!storageKey) return;

  function systemTheme() {
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  document.addEventListener("DOMContentLoaded", function () {
    var menu = document.querySelector("[data-role='theme-menu']");
    var cycleButton = menu ? menu.querySelector("[data-action='theme-cycle']") : null;
    var button = menu ? menu.querySelector("[data-action='theme-toggle']") : null;
    var popover = menu ? menu.querySelector("#theme-menu-popover") : null;
    var options = popover
      ? Array.prototype.slice.call(popover.querySelectorAll("[data-theme-option]"))
      : [];
    var themes = options.map(function (option) {
      return option.getAttribute("data-theme-option");
    });
    var labels = {};
    options.forEach(function (option) {
      var labelElement = option.querySelector(".theme-menu__label");
      labels[option.getAttribute("data-theme-option")] = labelElement
        ? labelElement.textContent
        : option.getAttribute("data-theme-option");
    });
    var mediaQuery = window.matchMedia
      ? window.matchMedia("(prefers-color-scheme: dark)")
      : null;
    var themeStylesheet = document.getElementById("theme-stylesheet");
    var themeStylesheetPaths = window.forumThemeStylesheetPaths || {};
    var themeLoadStates = {};
    var pendingSelection = null;

    function isExplicitTheme(theme) {
      return theme !== "auto" && themes.indexOf(theme) !== -1;
    }

    function defaultTheme() {
      var attr = document.documentElement.getAttribute("data-default-theme");
      return attr && themes.indexOf(attr) !== -1 ? attr : "auto";
    }

    function readStoredTheme() {
      try {
        var storedTheme = localStorage.getItem(storageKey);
        var aliases = window.forumThemeLegacyAliases || {};
        if (Object.prototype.hasOwnProperty.call(aliases, storedTheme)) storedTheme = aliases[storedTheme];
        return themes.indexOf(storedTheme) === -1 ? defaultTheme() : storedTheme;
      } catch (error) {
        return defaultTheme();
      }
    }

    function applyTheme(theme) {
      if (isExplicitTheme(theme)) {
        document.documentElement.setAttribute("data-theme", theme);
        return;
      }

      document.documentElement.removeAttribute("data-theme");
    }

    function resolvedTheme(theme) {
      return isExplicitTheme(theme) ? theme : systemTheme();
    }

    function syncResolvedTheme(theme) {
      var resolved = resolvedTheme(theme);
      var stylesheetPath = themeStylesheetPaths[resolved];

      if (themeStylesheet && themeStylesheet.getAttribute("href") === stylesheetPath) {
        themeStylesheet.setAttribute("data-theme-name", resolved);
      }

      document.documentElement.setAttribute("data-resolved-theme", resolved);
      if (typeof window.forumUpdateThemeHint === "function") {
        window.forumUpdateThemeHint(resolved);
      }
    }

    function hasThemeStyles(link) {
      try {
        // A failed initial request can leave a non-null, unreadable sheet.
        return Boolean(link.sheet && link.sheet.cssRules.length);
      } catch (error) {
        return false;
      }
    }

    function ensureThemeStylesheet(theme, highPriority) {
      var path = themeStylesheetPaths[theme];
      if (!path) {
        return { ready: true, promise: Promise.resolve(true) };
      }
      if (themeLoadStates[theme]) {
        if (highPriority) {
          themeLoadStates[theme].link.setAttribute("fetchpriority", "high");
        }
        return themeLoadStates[theme];
      }

      var link = themeStylesheet && themeStylesheet.getAttribute("href") === path && hasThemeStyles(themeStylesheet)
        ? themeStylesheet
        : document.createElement("link");
      if (link !== themeStylesheet) {
        link.setAttribute("rel", "stylesheet");
        link.setAttribute("href", path);
        link.setAttribute("data-theme-name", theme);
        document.head.appendChild(link);
      }
      link.setAttribute("fetchpriority", highPriority ? "high" : "low");

      var state = { link: link, ready: hasThemeStyles(link), promise: null };
      state.promise = state.ready
        ? Promise.resolve(true)
        : new Promise(function (resolve) {
            link.addEventListener("load", function () {
              state.ready = true;
              resolve(true);
            }, { once: true });
            link.addEventListener("error", function () {
              // Keep the current theme usable and let the next selection retry.
              delete themeLoadStates[theme];
              link.remove();
              resolve(false);
            }, { once: true });
          });
      themeLoadStates[theme] = state;
      return state;
    }

    function warmAlternateThemes() {
      var activeTheme = resolvedTheme(currentTheme);
      themes.forEach(function (theme) {
        var resolved = resolvedTheme(theme);
        if (resolved !== activeTheme) {
          ensureThemeStylesheet(resolved, false);
        }
      });
    }

    function scheduleThemeWarmup() {
      if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(warmAlternateThemes, { timeout: 1000 });
        return;
      }
      window.setTimeout(warmAlternateThemes, 0);
    }

    function nextTheme(currentTheme) {
      var currentIndex = themes.indexOf(currentTheme);
      if (currentIndex === -1) {
        return themes[0];
      }

      var currentResolvedTheme = resolvedTheme(currentTheme);
      for (var offset = 1; offset < themes.length; offset += 1) {
        var candidate = themes[(currentIndex + offset) % themes.length];
        if (resolvedTheme(candidate) !== currentResolvedTheme) {
          return candidate;
        }
      }
      return currentTheme;
    }

    function nextAppearance(theme) {
      var index = paletteNames.indexOf(lcdControl.value());
      if (theme === 'lcd' && index >= 0 && index + 1 < paletteNames.length) {
        return { theme: theme, palette: paletteNames[index + 1] };
      }
      var next = nextTheme(theme);
      return { theme: next, palette: next === 'lcd' ? paletteNames[0] : null };
    }

    function appearanceLabel(theme, selectedPalette) {
      var option = paletteOptions[paletteNames.indexOf(selectedPalette)];
      return labels[theme] + (theme === 'lcd' && option ? ' ' + option.textContent.trim() : '');
    }

    function syncButton(theme) {
      var next = nextAppearance(theme);
      var currentLabel = appearanceLabel(theme, lcdControl.value());
      var nextLabel = appearanceLabel(next.theme, next.palette);

      cycleButton.setAttribute("data-theme", resolvedTheme(theme));
      cycleButton.setAttribute("data-theme-mode", theme);
      cycleButton.setAttribute(
        "aria-label",
        "Theme: " + currentLabel + ". Activate to switch to " + nextLabel + "."
      );
      cycleButton.setAttribute(
        "title",
        "Theme: " + currentLabel + ". Click to switch to " + nextLabel + "."
      );
    }

    function syncOptions(theme) {
      options.forEach(function (option) {
        var isCurrent = option.getAttribute("data-theme-option") === theme;
        option.setAttribute("aria-checked", isCurrent ? "true" : "false");
      });
    }

    function openMenu() {
      closePalette(false);
      popover.hidden = false;
      button.setAttribute("aria-expanded", "true");
      var checkedOption = options.filter(function (option) {
        return option.getAttribute("aria-checked") === "true";
      })[0];
      (checkedOption || options[0]).focus();
    }

    function closeMenu(refocus) {
      popover.hidden = true;
      button.setAttribute("aria-expanded", "false");
      if (refocus) {
        button.focus();
      }
    }

    if (!menu || !cycleButton || !button || !popover || options.length === 0) {
      return;
    }

    function createPaletteControl(paletteTheme, themeLabel) {
      var paletteControl = menu.querySelector('[data-role="' + paletteTheme + '-palette"]');
      var paletteButton = paletteControl && paletteControl.querySelector('[data-action="' + paletteTheme + '-palette-toggle"]');
      var palettePopover = paletteControl && paletteControl.querySelector('[role="menu"]');
      var paletteOptions = paletteControl ? Array.prototype.slice.call(paletteControl.querySelectorAll('[data-' + paletteTheme + '-palette-option]')) : [];
      var paletteNames = paletteOptions.map(function (option) { return option.getAttribute('data-' + paletteTheme + '-palette-option'); });
      var palette = document.documentElement.getAttribute('data-' + paletteTheme + '-palette') || 'green';

      function setPalette(value) {
        palette = value;
        document.documentElement.setAttribute('data-' + paletteTheme + '-palette', palette);
        try { localStorage.setItem(storageKey + '-' + paletteTheme + '-palette', palette); } catch (error) {}
      }

      function closePalette(refocus) {
        if (!palettePopover) return;
        palettePopover.hidden = true;
        paletteButton.setAttribute('aria-expanded', 'false');
        if (refocus) paletteButton.focus();
      }

      function syncPalette() {
        if (!paletteControl) return;
        if (currentTheme !== paletteTheme) {
          var hadFocus = paletteControl.contains(document.activeElement);
          closePalette(false);
          if (hadFocus) cycleButton.focus();
        }
        var selected = paletteOptions.filter(function (option) {
          var checked = option.getAttribute('data-' + paletteTheme + '-palette-option') === palette;
          option.setAttribute('aria-checked', String(checked));
          return checked;
        })[0];
        if (selected) paletteButton.setAttribute('aria-label', 'Choose ' + themeLabel + ' palette: ' + selected.textContent.trim());
      }

      if (paletteButton) {
        paletteButton.addEventListener('click', function () {
          if (!palettePopover.hidden) { closePalette(false); return; }
          closeMenu(false);
          palettePopover.hidden = false;
          paletteButton.setAttribute('aria-expanded', 'true');
          (paletteOptions.filter(function (option) { return option.getAttribute('aria-checked') === 'true'; })[0] || paletteOptions[0]).focus();
        });
        paletteOptions.forEach(function (option) {
          option.addEventListener('click', function () {
            setPalette(option.getAttribute('data-' + paletteTheme + '-palette-option'));
            syncButton(currentTheme);
            syncPalette();
            closePalette(true);
          });
        });
        paletteControl.addEventListener('keydown', function (event) {
          if (palettePopover.hidden) return;
          event.stopPropagation();
          if (event.key === 'Escape') { event.preventDefault(); closePalette(true); return; }
          var index = paletteOptions.indexOf(document.activeElement);
          if (event.key === 'Tab') { closePalette(true); return; }
          if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) === -1) return;
          event.preventDefault();
          index = event.key === 'Home' ? 0 : event.key === 'End' ? paletteOptions.length - 1
            : (index + (event.key === 'ArrowUp' ? -1 : 1) + paletteOptions.length) % paletteOptions.length;
          paletteOptions[index].focus();
        });
        paletteControl.addEventListener('focusout', function (event) {
          if (!paletteControl.contains(event.relatedTarget)) closePalette(false);
        });
        document.addEventListener('click', function (event) {
          if (!paletteControl.contains(event.target)) closePalette(false);
        });
      }

      return { close: closePalette, sync: syncPalette, set: setPalette, names: paletteNames, options: paletteOptions, value: function () { return palette; } };
    }
    var lcdControl = createPaletteControl('lcd', 'LCD');
    var consoleControl = createPaletteControl('console', 'Console');
    var paletteNames = lcdControl.names;
    var paletteOptions = lcdControl.options;
    function closePalette(refocus) { lcdControl.close(refocus); consoleControl.close(false); }
    function syncPalette() { lcdControl.sync(); consoleControl.sync(); }

    var currentTheme = readStoredTheme();

    applyTheme(currentTheme);
    syncResolvedTheme(currentTheme);
    ensureThemeStylesheet(resolvedTheme(currentTheme), true);
    syncButton(currentTheme);
    syncOptions(currentTheme);
    syncPalette();
    scheduleThemeWarmup();

    function applySelection(theme, selectedPalette) {
      var selection = { theme: theme, palette: selectedPalette };
      pendingSelection = selection;
      var state = ensureThemeStylesheet(resolvedTheme(theme), true);
      if (!state.ready) {
        document.documentElement.setAttribute("data-theme-loading", "true");
        state.promise.then(function (ready) {
          if (pendingSelection !== selection) {
            return;
          }
          document.documentElement.removeAttribute("data-theme-loading");
          if (ready) commitSelection(theme, selectedPalette);
        });
        return;
      }

      commitSelection(theme, selectedPalette);
    }

    function commitSelection(theme, selectedPalette) {
      document.documentElement.removeAttribute("data-theme-loading");
      currentTheme = theme;
      if (selectedPalette) lcdControl.set(selectedPalette);
      applyTheme(currentTheme);
      syncResolvedTheme(currentTheme);
      syncButton(currentTheme);
      syncOptions(currentTheme);
      syncPalette();

      try {
        localStorage.setItem(storageKey, currentTheme);
      } catch (error) {
      }
    }

    function selectTheme(theme) {
      applySelection(theme);
      closeMenu(true);
    }

    cycleButton.addEventListener("click", function () {
      var next = nextAppearance(currentTheme);
      applySelection(next.theme, next.palette);
    });

    button.addEventListener("click", function () {
      if (popover.hidden) {
        openMenu();
      } else {
        closeMenu(false);
      }
    });

    options.forEach(function (option) {
      option.addEventListener("click", function () {
        selectTheme(option.getAttribute("data-theme-option"));
      });
    });

    document.addEventListener("click", function (event) {
      if (!popover.hidden && !menu.contains(event.target)) {
        closeMenu(false);
      }
    });

    menu.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !popover.hidden) {
        event.preventDefault();
        closeMenu(true);
        return;
      }

      if (popover.hidden || (event.key !== "ArrowDown" && event.key !== "ArrowUp")) {
        return;
      }

      event.preventDefault();
      var focusedIndex = options.indexOf(document.activeElement);
      var step = event.key === "ArrowDown" ? 1 : -1;
      var nextIndex = focusedIndex === -1
        ? 0
        : (focusedIndex + step + options.length) % options.length;
      options[nextIndex].focus();
    });

    if (mediaQuery && typeof mediaQuery.addEventListener === "function") {
      mediaQuery.addEventListener("change", function () {
        if (currentTheme === "auto") {
          ensureThemeStylesheet(resolvedTheme(currentTheme), false);
          syncResolvedTheme(currentTheme);
          syncButton(currentTheme);
        }
      });
    }
  });
})();
