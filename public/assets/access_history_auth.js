(function () {
  "use strict";
  document.addEventListener("click", async function (event) {
    var button = event.target.closest("[data-access-history-sign-in]");
    if (!button || !window.PrivateSiteAuth) return;
    var state = button.closest("[data-private-site-auth-state]");
    var status = state.querySelector('[data-role="private-site-auth-status"]');
    button.disabled = true;
    try {
      var result = await window.PrivateSiteAuth.authenticate({ force: true, returnTo: state.dataset.authReturnTo });
      if (result.status === "not-configured") {
        status.hidden = false;
        status.textContent = "Restore or choose an operator account, then return here and try again.";
      }
    } catch (error) {
      // The canonical authentication helper renders the actionable failure.
    } finally {
      button.disabled = false;
    }
  });
})();
