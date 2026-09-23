(function () {
  var storageKey = "zenmemes-theme";

  function systemTheme() {
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  document.addEventListener("DOMContentLoaded", function () {
    var menu = document.querySelector("[data-role='theme-menu']");
    var cycleButton = menu ? menu.querySelector("[data-action='theme-cycle']") : null;
    var button = menu ? menu.querySelector("[data-action='theme-toggle']") : null;
    var popover = menu ? menu.querySelector(".theme-menu__popover") : null;
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
    var pendingTheme = null;

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

    function ensureThemeStylesheet(theme, highPriority) {
      var path = themeStylesheetPaths[theme];
      if (!path) {
        return { ready: true, promise: Promise.resolve() };
      }
      if (themeLoadStates[theme]) {
        if (highPriority) {
          themeLoadStates[theme].link.setAttribute("fetchpriority", "high");
        }
        return themeLoadStates[theme];
      }

      var link = themeStylesheet && themeStylesheet.getAttribute("href") === path
        ? themeStylesheet
        : document.createElement("link");
      if (link !== themeStylesheet) {
        link.setAttribute("rel", "stylesheet");
        link.setAttribute("href", path);
        link.setAttribute("data-theme-name", theme);
        document.head.appendChild(link);
      }
      link.setAttribute("fetchpriority", highPriority ? "high" : "low");

      var state = { link: link, ready: Boolean(link.sheet), promise: null };
      state.promise = state.ready
        ? Promise.resolve()
        : new Promise(function (resolve) {
            link.addEventListener("load", function () {
              state.ready = true;
              resolve();
            }, { once: true });
            link.addEventListener("error", function () {
              state.ready = true;
              resolve();
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

      return themes[(currentIndex + 1) % themes.length];
    }

    function syncButton(theme) {
      var next = nextTheme(theme);

      cycleButton.setAttribute("data-theme", resolvedTheme(theme));
      cycleButton.setAttribute("data-theme-mode", theme);
      cycleButton.setAttribute(
        "aria-label",
        "Theme: " + labels[theme] + ". Activate to switch to " + labels[next] + "."
      );
      cycleButton.setAttribute(
        "title",
        "Theme: " + labels[theme] + ". Click to switch to " + labels[next] + "."
      );
    }

    function syncOptions(theme) {
      options.forEach(function (option) {
        var isCurrent = option.getAttribute("data-theme-option") === theme;
        option.setAttribute("aria-checked", isCurrent ? "true" : "false");
      });
    }

    function openMenu() {
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

    var currentTheme = readStoredTheme();

    applyTheme(currentTheme);
    syncResolvedTheme(currentTheme);
    ensureThemeStylesheet(resolvedTheme(currentTheme), true);
    syncButton(currentTheme);
    syncOptions(currentTheme);
    scheduleThemeWarmup();

    function applySelection(theme) {
      pendingTheme = theme;
      var state = ensureThemeStylesheet(resolvedTheme(theme), true);
      if (!state.ready) {
        document.documentElement.setAttribute("data-theme-loading", "true");
        state.promise.then(function () {
          if (pendingTheme !== theme) {
            return;
          }
          document.documentElement.removeAttribute("data-theme-loading");
          commitSelection(theme);
        });
        return;
      }

      commitSelection(theme);
    }

    function commitSelection(theme) {
      document.documentElement.removeAttribute("data-theme-loading");
      currentTheme = theme;
      applyTheme(currentTheme);
      syncResolvedTheme(currentTheme);
      syncButton(currentTheme);
      syncOptions(currentTheme);

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
      applySelection(nextTheme(currentTheme));
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
