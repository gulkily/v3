(function () {
  "use strict";
  // Confirm on a waiting key: prepare a confirm record, sign it with this browser's key, submit.
  // A failed submit keeps the signed request so a retry repeats it exactly (the server treats a
  // repeat of an accepted confirm as success).
  async function post(path, payload) {
    const response = await fetch(path, { method: "POST", credentials: "same-origin", cache: "no-store",
      headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.status !== "ok") throw new Error(result.error || "Couldn't confirm this key.");
    return result;
  }
  function bind(row) {
    const button = row.querySelector('[data-action="confirm-private-access"]');
    const feedback = row.querySelector('[data-role="private-access-feedback"]');
    if (!button) return;
    let signed = null;
    function say(message) { feedback.textContent = message; feedback.hidden = false; }
    button.addEventListener("click", async function () {
      button.disabled = true;
      try {
        if (!signed) {
          if (!window.ForumBrowserSigning) throw new Error("Browser signing is unavailable. Refresh and retry.");
          const actor = "openpgp:" + (localStorage.getItem("forum_pki_fingerprint") || "").trim().toLowerCase();
          const prepared = await post("/api/prepare_private_access_confirmation", { actor_identity_id: actor, target_identity_id: row.dataset.targetIdentityId });
          signed = { prepare_token: prepared.prepare_token, canonical_record: prepared.canonical_record,
            detached_signature: await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record, actor) };
        }
        const result = await post("/api/confirm_private_access", signed);
        if (!result.has_access) throw new Error("The confirm was saved but this key still can't read messages. Ask an operator.");
        button.hidden = true;
        const notMe = row.querySelector('[data-role="not-me"]');
        if (notMe) notMe.hidden = true;
        say("Confirmed");
      } catch (error) {
        say(error.message || "Couldn't confirm this key.");
        button.textContent = signed ? "Retry" : "Confirm";
      } finally { button.disabled = false; }
    });
  }
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-private-access-row]").forEach(bind);
  });
})();
