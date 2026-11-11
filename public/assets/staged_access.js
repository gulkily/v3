(function () {
  "use strict";
  // Staged for a browser holding a key but without a session: sign in where we are, then
  // reload once into the viewer's staged posts. A refused sign-in shows Retry, never a loop.
  var reloadKey = "forum_staged_access_reload";
  function storage() { try { return window.sessionStorage; } catch (error) { return null; } }
  function recentlyReloaded() {
    try { return Date.now() - Number(storage().getItem(reloadKey) || 0) < 15000; } catch (error) { return false; }
  }
  function bind(root) {
    if (!root || root.dataset.accessBound === "1") return;
    root.dataset.accessBound = "1";
    var guest = root.querySelector('[data-access-for="guest"]');
    var failed = root.querySelector('[data-access-for="failed"]');
    function show(state) {
      root.dataset.accessState = state;
      guest.hidden = state !== "guest";
      failed.hidden = state !== "failed";
    }
    function signIn() {
      if (!window.PrivateSiteAuth) { show("failed"); return; }
      show("checking");
      window.PrivateSiteAuth.authenticate({ navigate: false, quiet: true, force: true }).then(function (result) {
        var status = result && result.status;
        if ((status === "approved" || status === "authenticated" || status === "pending") && !recentlyReloaded()) {
          try { storage().setItem(reloadKey, String(Date.now())); } catch (error) { /* Without storage, reload once anyway. */ }
          window.location.reload();
        } else if (status === "not-configured" || status === "approved" || status === "authenticated" || status === "pending") {
          show("guest");
        } else {
          show("failed");
        }
      }).catch(function () { show("failed"); });
    }
    root.querySelector('[data-action="access-retry"]').addEventListener("click", function () {
      try { storage().removeItem(reloadKey); } catch (error) { /* Nothing to clear. */ }
      signIn();
    });
    if (root.dataset.accessState === "checking") signIn();
  }
  document.addEventListener("DOMContentLoaded", function () { bind(document.querySelector("[data-staged-access]")); });
})();
