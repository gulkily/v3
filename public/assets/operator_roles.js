(function () {
  "use strict";
  function request(path, fields) {
    return window.fetch(path, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(fields).toString()
    }).then(function (response) {
      return response.json().then(function (result) {
        if (!response.ok || result.status !== "ok") {
          throw new Error(result.error || "Unable to change operator access.");
        }
        return result;
      });
    });
  }
  document.addEventListener("submit", function (event) {
    var form = event.target.closest("[data-operator-action-form]");
    if (!form) { return; }
    event.preventDefault();
    if (form.dataset.pending) { return; }
    var fields = Object.fromEntries(new FormData(form).entries());
    var feedback = form.querySelector("[data-operator-feedback]");
    var button = form.querySelector("button");
    form.dataset.pending = "1";
    button.disabled = true;
    feedback.textContent = "Preparing operator change…";
    Promise.resolve().then(function () {
      if (!window.ForumBrowserSigning) { throw new Error("Reload the page to enable browser signing."); }
      return window.ForumBrowserSigning.ensureActionIdentity(null, null);
    }).then(function () {
      return request("/api/prepare_operator_action", fields);
    }).then(function (prepared) {
      var message = prepared.action === "revoke"
        ? "Revoke this grant to " + prepared.account + "?\nAccounts losing operator access: "
          + (prepared.affected_accounts.length ? prepared.affected_accounts.join(", ") : "none; alternate paths preserve access")
          + ".\nUnrevoked downstream grants will resume if authority is restored."
        : "Make " + prepared.account + " an operator? All approved keys can manage this instance and appoint other operators.";
      if (!window.confirm(message)) {
        throw new Error("Cancelled. No operator access changed.");
      }
      return window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record).then(function (signature) {
        return request("/api/finalize_operator_action", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id,
          record_path: prepared.record_path, canonical_record: prepared.canonical_record,
          detached_signature: signature
        });
      });
    }).then(function () { window.location.reload(); }).catch(function (error) {
      feedback.textContent = error.message;
    }).finally(function () {
      delete form.dataset.pending;
      button.disabled = false;
    });
  });
})();
