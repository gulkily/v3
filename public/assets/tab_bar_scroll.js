(function () {
  "use strict";
  // On phones the app shell's bottom tab bar slides away while the reader
  // scrolls down and comes back on scroll up, near either end of the page,
  // when focus enters it, and while the account menu is open. It also stays
  // away while someone types in a field: with the keyboard up it would cover a
  // composer's Post button. Without this script the bar simply stays visible.
  const shell = document.querySelector("[data-shell='app']");
  const bar = shell && shell.querySelector(".site-header--app .site-header-actions");
  if (!shell || !bar) return;

  const phone = window.matchMedia("(max-width: 639px)");
  // Ignore smaller movements (jitter, momentum tails); show within `EDGE` of
  // the top or bottom so iOS bounce at the ends never toggles the bar.
  const THRESHOLD = 8;
  const EDGE = 48;
  let last = window.scrollY;
  let frame = 0;

  function setHidden(hidden) {
    if (hidden) shell.dataset.tabbar = "hidden";
    else delete shell.dataset.tabbar;
  }

  function pinned() {
    return !phone.matches
      || bar.contains(document.activeElement)
      || bar.querySelector("[data-role='account-menu'][open]") !== null;
  }

  // A text field outside the bar has focus, so the on-screen keyboard is up.
  function typing() {
    const field = document.activeElement;
    if (!field || bar.contains(field)) return false;
    if (field.isContentEditable || field.matches("textarea, select")) return true;
    return field.matches("input") && !/^(button|submit|reset|checkbox|radio|range|color|file|image|hidden)$/i.test(field.type);
  }

  function update() {
    frame = 0;
    if (phone.matches && typing()) {
      setHidden(true);
      return;
    }
    const max = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0);
    const y = Math.min(Math.max(window.scrollY, 0), max);
    if (pinned() || y < EDGE || y > max - EDGE) {
      setHidden(false);
      last = y;
      return;
    }
    if (Math.abs(y - last) < THRESHOLD) return;
    setHidden(y > last);
    last = y;
  }

  function schedule() {
    if (!frame) frame = window.requestAnimationFrame(update);
  }

  window.addEventListener("scroll", schedule, { passive: true });
  window.addEventListener("resize", schedule);
  phone.addEventListener("change", update);
  bar.addEventListener("focusin", function () { setHidden(false); });
  // Moving between fields fires focusout then focusin; wait a frame before deciding.
  document.addEventListener("focusin", schedule);
  document.addEventListener("focusout", schedule);
  // <details> toggle events do not bubble, so listen in the capture phase.
  bar.addEventListener("toggle", update, true);
})();
