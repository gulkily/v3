(function () {
  "use strict";
  document.addEventListener("click", function (event) {
    const link = event.target && event.target.closest ? event.target.closest("[data-invite-navigation]") : null;
    if (!link) return;
    event.preventDefault();
    window.location.assign("/invites/?destination=" + encodeURIComponent(window.location.pathname));
  });
})();
