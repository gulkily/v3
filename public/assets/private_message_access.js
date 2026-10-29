(function () {
  "use strict";
  // Messages for a registered key without a usable session: sign in where we are, then
  // reload into Messages if approved, or say the key is waiting. One reload at most, so a
  // refused session can't loop; a failure offers Retry.
  const reloadKey = "forum_private_message_access_reload";
  function storage() { try { return window.sessionStorage; } catch (error) { return null; } }
  function recentlyReloaded() {
    try { return Date.now() - Number(storage().getItem(reloadKey) || 0) < 15000; } catch (error) { return false; }
  }
  function bind(root) {
    if (!root || root.dataset.accessBound === "1") return;
    root.dataset.accessBound = "1";
    const retry = root.querySelector('[data-action="access-retry"]');
    function show(state) { root.dataset.accessState = state; }
    function signIn() {
      if (!window.PrivateSiteAuth) { show("failed"); return; }
      show("checking");
      // A fresh sign-in (force) reports approval; a remembered session would not.
      window.PrivateSiteAuth.authenticate({ navigate: false, quiet: true, force: true }).then(function (result) {
        const status = result && result.status;
        if (status === "approved" && !recentlyReloaded()) {
          try { storage().setItem(reloadKey, String(Date.now())); } catch (error) { /* Without storage, reload once anyway. */ }
          window.location.reload();
        } else if (status === "authenticated" || status === "pending") show("waiting");
        else if (status === "not-configured") show("guest");
        else show("failed");
      }).catch(function () { show("failed"); });
    }
    if (retry) retry.addEventListener("click", function () {
      try { storage().removeItem(reloadKey); } catch (error) { /* Nothing to clear. */ }
      signIn();
    });
    if (root.dataset.accessState === "checking") signIn();
  }
  document.addEventListener("DOMContentLoaded", function () { bind(document.querySelector("[data-private-message-access]")); });
})();
