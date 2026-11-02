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

  // The sort menu opens under its trigger's left edge. When the trigger sits
  // near the right edge of a narrow screen (the Users toolbar on a phone), that
  // would push the menu past the viewport, so align it to the trigger's right
  // edge instead. Decided when the menu opens, so the closed page is unchanged.
  document.addEventListener("toggle", function (event) {
    var details = event.target;
    if (!details.matches || !details.matches("[data-role='board-sort']")) return;
    var menu = details.querySelector(".board-sort__menu");
    if (!menu) return;
    menu.classList.remove("board-sort__menu--end");
    if (details.open && menu.getBoundingClientRect().right > document.documentElement.clientWidth) {
      menu.classList.add("board-sort__menu--end");
    }
  }, true);

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape" || event.defaultPrevented) return;
    menus().forEach(function (menu) {
      menu.open = false;
      const summary = menu.querySelector("summary");
      if (summary && menu.contains(document.activeElement)) summary.focus();
    });
  });
})();
