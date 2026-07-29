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
    var button = document.querySelector("[data-role='thread-density-toggle']");
    if (!button) {
      return;
    }

    var currentDensity = readStoredDensity();

    function syncButton(density) {
      var isCompact = density === "compact";
      var label = isCompact ? "Switch to comfortable thread view" : "Switch to compact thread view";

      button.setAttribute("aria-pressed", isCompact ? "true" : "false");
      button.textContent = isCompact ? "Comfortable" : "Compact";
      button.setAttribute("title", label);
      button.setAttribute("aria-label", label);
    }

    applyDensity(currentDensity);
    syncButton(currentDensity);

    button.addEventListener("click", function () {
      currentDensity = currentDensity === "compact" ? "comfortable" : "compact";
      applyDensity(currentDensity);
      syncButton(currentDensity);

      try {
        localStorage.setItem(storageKey, currentDensity);
      } catch (error) {
      }
    });
  });
})();
