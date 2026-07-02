(function () {
  var storageKey = "zenmemes-theme";

  function systemTheme() {
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  document.addEventListener("DOMContentLoaded", function () {
    var menu = document.querySelector("[data-role='theme-menu']");
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

    function isExplicitTheme(theme) {
      return theme !== "auto" && themes.indexOf(theme) !== -1;
    }

    function readStoredTheme() {
      try {
        var storedTheme = localStorage.getItem(storageKey);
        return themes.indexOf(storedTheme) === -1 ? "auto" : storedTheme;
      } catch (error) {
        return "auto";
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

    function syncButton(theme) {
      button.setAttribute("data-theme", resolvedTheme(theme));
      button.setAttribute("data-theme-mode", theme);
      button.setAttribute(
        "aria-label",
        "Theme: " + labels[theme] + ". Activate to choose a theme."
      );
      button.setAttribute(
        "title",
        "Theme: " + labels[theme] + ". Click to choose a theme."
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

    if (!menu || !button || !popover || options.length === 0) {
      return;
    }

    var currentTheme = readStoredTheme();

    applyTheme(currentTheme);
    syncButton(currentTheme);
    syncOptions(currentTheme);

    function selectTheme(theme) {
      currentTheme = theme;
      applyTheme(currentTheme);
      syncButton(currentTheme);
      syncOptions(currentTheme);

      try {
        localStorage.setItem(storageKey, currentTheme);
      } catch (error) {
      }

      closeMenu(true);
    }

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
          syncButton(currentTheme);
        }
      });
    }
  });
})();
