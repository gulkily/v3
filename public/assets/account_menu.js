(function () {
  "use strict";
  // The account menu is a native <details>, so it already opens and closes
  // without this script. This adds the expected menu behavior: Escape and
  // outside clicks close it. Nested popovers (theme, LCD palette) handle their
  // own Escape first and mark the event as handled.
  function menus() {
    return document.querySelectorAll("[data-role='account-menu'][open]");
  }

  document.addEventListener("click", function (event) {
    menus().forEach(function (menu) {
      if (!menu.contains(event.target)) menu.open = false;
    });
  });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape" || event.defaultPrevented) return;
    menus().forEach(function (menu) {
      menu.open = false;
      const summary = menu.querySelector("summary");
      if (summary && menu.contains(document.activeElement)) summary.focus();
    });
  });
})();
