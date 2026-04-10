(function () {
  var storageKey = "zenmemes-theme";
  var themes = ["auto", "light", "dark", "console", "lcd", "chicago", "vapor"];

  function isExplicitTheme(theme) {
    return (
      theme === "light" ||
      theme === "dark" ||
      theme === "console" ||
      theme === "lcd" ||
      theme === "chicago" ||
      theme === "vapor"
    );
  }

  function systemTheme() {
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  function readStoredTheme() {
    try {
      var storedTheme = localStorage.getItem(storageKey);
      return themes.indexOf(storedTheme) === -1 ? "auto" : storedTheme;
    } catch (error) {
      return "auto";
    }
  }

  function nextTheme(currentTheme) {
    var currentIndex = themes.indexOf(currentTheme);
    if (currentIndex === -1) {
      return themes[0];
    }

    return themes[(currentIndex + 1) % themes.length];
  }

  function labelForTheme(theme) {
    if (theme === "auto") {
      return "Auto/System";
    }

    if (theme === "light") {
      return "Light";
    }

    if (theme === "dark") {
      return "Dark";
    }

    if (theme === "chicago") {
      return "Chicago";
    }

    if (theme === "console") {
      return "Console";
    }

    if (theme === "lcd") {
      return "LCD";
    }

    return "Vapor";
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

  function syncButton(button, theme) {
    var next = nextTheme(theme);
    var effectiveTheme = resolvedTheme(theme);

    button.setAttribute("data-theme", effectiveTheme);
    button.setAttribute("data-theme-mode", theme);
    button.setAttribute(
      "aria-label",
      "Theme: " + labelForTheme(theme) + ". Activate to switch to " + labelForTheme(next) + "."
    );
    button.setAttribute(
      "title",
      "Theme: " + labelForTheme(theme) + ". Click to switch to " + labelForTheme(next) + "."
    );
  }

  document.addEventListener("DOMContentLoaded", function () {
    var button = document.querySelector("[data-action='theme-toggle']");
    var mediaQuery = window.matchMedia
      ? window.matchMedia("(prefers-color-scheme: dark)")
      : null;
    var currentTheme = readStoredTheme();

    applyTheme(currentTheme);

    if (!button) {
      return;
    }

    syncButton(button, currentTheme);

    button.addEventListener("click", function () {
      currentTheme = nextTheme(currentTheme);
      applyTheme(currentTheme);
      syncButton(button, currentTheme);

      try {
        localStorage.setItem(storageKey, currentTheme);
      } catch (error) {
      }
    });

    if (mediaQuery && typeof mediaQuery.addEventListener === "function") {
      mediaQuery.addEventListener("change", function () {
        if (currentTheme === "auto") {
          syncButton(button, currentTheme);
        }
      });
    }
  });
})();
