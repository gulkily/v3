// /themes: a card's Use button picks that theme through the theme menu's own
// selection path, so it is saved and on a page theme's page leaves it as usual.
document.addEventListener("click", function (event) {
  var button = event.target.closest ? event.target.closest("[data-theme-catalog-select]") : null;
  if (button && typeof window.forumSelectTheme === "function") {
    window.forumSelectTheme(button.getAttribute("data-theme-catalog-select"));
  }
});
