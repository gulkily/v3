(function () {
  var storageKey = "zenmemes-thread-density";

  function readStoredDensity() {
    try {
      return localStorage.getItem(storageKey) === "compact" ? "compact" : "comfortable";
    } catch (error) {
      return "comfortable";
    }
  }

  function applyDensity(density) {
    if (density === "compact") {
      document.documentElement.setAttribute("data-thread-density", "compact");
    } else {
      document.documentElement.removeAttribute("data-thread-density");
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    var menu = document.querySelector("[data-role='thread-density-menu']");
    var trigger = menu ? menu.querySelector("[data-role='thread-density-toggle']") : null;
    var popover = menu ? menu.querySelector(".thread-density-menu__popover") : null;
    var options = popover
      ? Array.prototype.slice.call(popover.querySelectorAll("[data-thread-density-option]"))
      : [];

    if (!menu || !trigger || !popover || options.length === 0) {
      return;
    }

    var currentDensity = readStoredDensity();

    function syncOptions(density) {
      options.forEach(function (option) {
        var isCurrent = option.getAttribute("data-thread-density-option") === density;
        option.setAttribute("aria-checked", isCurrent ? "true" : "false");
      });
    }

    function openMenu() {
      popover.hidden = false;
      trigger.setAttribute("aria-expanded", "true");
      var checkedOption = options.filter(function (option) {
        return option.getAttribute("aria-checked") === "true";
      })[0];
      (checkedOption || options[0]).focus();
    }

    function closeMenu(refocus) {
      popover.hidden = true;
      trigger.setAttribute("aria-expanded", "false");
      if (refocus) {
        trigger.focus();
      }
    }

    function selectDensity(density) {
      currentDensity = density;
      applyDensity(currentDensity);
      syncOptions(currentDensity);

      try {
        localStorage.setItem(storageKey, currentDensity);
      } catch (error) {
      }
    }

    // The <html> data-thread-density attribute is already applied by the
    // inline layout script before first paint, so only the (hidden until
    // opened) popover state needs syncing here.
    syncOptions(currentDensity);

    trigger.addEventListener("click", function () {
      if (popover.hidden) {
        openMenu();
      } else {
        closeMenu(false);
      }
    });

    options.forEach(function (option) {
      option.addEventListener("click", function () {
        selectDensity(option.getAttribute("data-thread-density-option"));
        closeMenu(true);
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
  });
})();
