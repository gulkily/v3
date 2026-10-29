(function () {
  "use strict";
  var button = document.querySelector("[data-test-self-approval]");
  if (!button) return;
  async function requestApproval() {
    var response = await fetch("/api/test_self_approval", {
      method: "POST", credentials: "same-origin",
      body: new URLSearchParams({ token: button.dataset.testSelfApproval })
    });
    var body = await response.json().catch(function () { return {}; });
    return { ok: response.ok, status: response.status, error: body.error };
  }

  button.addEventListener("click", async function () {
    button.disabled = true;
    var status = document.getElementById("simple-status");
    try {
      var auth = window.PrivateSiteAuth;
      var result = await requestApproval();
      // Approval is for the key this session is signed in with. If the session lost it (Remove,
      // a new key, or a replaced session), sign in again with the current key and retry once.
      if (result.status === 403 && auth && typeof auth.authenticateInPlace === "function") {
        await auth.authenticateInPlace({ force: true });
        result = await requestApproval();
      }
      if (!result.ok) throw new Error(result.error || "Approval failed. Try again.");
      window.location.reload();
    } catch (error) {
      status.hidden = false;
      status.textContent = error.message;
      button.disabled = false;
    }
  });
}());
