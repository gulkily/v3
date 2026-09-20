(function () {
  "use strict";

  var navigationInFlight = false;

  function safeDestination(value) {
    value = String(value || "");
    return /^\/(?![\\/\\\\])/.test(value) && !/[\u0000-\u001F\u007F\\\\]/.test(value) ? value : "";
  }

  function isRecoveryExempt(url) {
    return url.pathname === "/lobby" || url.pathname === "/lobby/"
      || url.pathname === "/account/key" || url.pathname === "/account/key/"
      || url.pathname.indexOf("/api") === 0 || url.pathname.indexOf("/downloads/") === 0;
  }

  function destinationForLink(link) {
    if (!link || link.target && link.target !== "_self" || link.hasAttribute("download")
      || link.hasAttribute("data-invite-navigation")) {
      return "";
    }

    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (error) {
      return "";
    }

    if (url.origin !== window.location.origin || isRecoveryExempt(url)) {
      return "";
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== "") {
      return "";
    }

    return safeDestination(url.pathname + url.search + url.hash);
  }

  async function sessionIsActive() {
    var response = await fetch("/api/auth_status", { credentials: "same-origin" });
    return response.ok;
  }

  function accountRecoveryDestination(destination) {
    return "/account/key/?return_to=" + encodeURIComponent(destination);
  }

  async function recoverAndNavigate(destination) {
    if (await sessionIsActive()) {
      window.location.assign(destination);
      return;
    }

    var authentication = window.PrivateSiteAuth;
    if (!authentication || typeof authentication.authenticate !== "function") {
      window.location.assign(accountRecoveryDestination(destination));
      return;
    }

    var result = await authentication.authenticate({ returnTo: destination });
    if (result && result.status === "not-configured") {
      window.location.assign(accountRecoveryDestination(destination));
    }
  }

  document.addEventListener("click", function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    var link = event.target && typeof event.target.closest === "function" ? event.target.closest("a[href]") : null;
    var destination = destinationForLink(link);
    if (!destination || navigationInFlight) {
      return;
    }

    event.preventDefault();
    navigationInFlight = true;
    recoverAndNavigate(destination)
      .catch(function () {
        window.location.assign(accountRecoveryDestination(destination));
      })
      .finally(function () {
        navigationInFlight = false;
      });
  });
})();
