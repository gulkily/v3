(function () {
  "use strict";
  // The account menu and the board's sort menu are native <details>, so they
  // already open and close without this script. This adds the expected menu
  // behavior: Escape and outside clicks close them. Nested popovers (theme,
  // LCD palette) handle their own Escape first and mark the event as handled.
  function menus() {
    return document.querySelectorAll("[data-role='account-menu'][open], [data-role='board-sort'][open]");
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
