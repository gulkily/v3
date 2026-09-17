(function () {
  "use strict";
  function post(endpoint, values) {
    return fetch(endpoint, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: new URLSearchParams(values).toString() }).then(function (response) { return response.json(); });
  }
  async function ensureRedemptionIdentity(root, status) {
    const identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureReadyIdentity !== "function") {
      throw new Error("Identity setup is unavailable. Reload the page and try again.");
    }
    feedback(status, "Preparing your browser identity…", "ok");
    await identity.ensureReadyIdentity(root, status, { verifyPublishedIdentity: false });
    const fingerprint = String(localStorage.getItem("forum_pki_fingerprint") || "")
      .replace(/^openpgp:/, "")
      .toLowerCase();
    if (!/^[a-f0-9]{40}$/.test(fingerprint)) {
      throw new Error("Unable to prepare a browser key for this invitation.");
    }
    return fingerprint;
  }
  function feedback(node, message, kind) { node.hidden = false; node.textContent = message; node.dataset.status = kind || "ok"; }
  document.addEventListener("DOMContentLoaded", function () {
    const match = window.location.hash.match(/^#invite=([a-f0-9]{64})$/);
    const root = document.querySelector("[data-invitation-redemption]");
    if (!match || !root) return;
    root.hidden = false;
    const token = match[1];
    const status = root.querySelector("[data-role=invitation-redemption-feedback]");
    root.querySelector("[data-action=redeem-invitation]").addEventListener("click", async function () {
      try {
        const fingerprint = await ensureRedemptionIdentity(root, status);
        const prepared = await post("/api/prepare_invitation_redemption", { identity_id: "openpgp:" + fingerprint, invite_token: token });
        if (!prepared || prepared.status !== "ok") throw new Error(prepared && prepared.error || "Unable to redeem invitation.");
        const signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: "openpgp:" + fingerprint, canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!finalized || finalized.status !== "ok") throw new Error(finalized && finalized.error || "Unable to redeem invitation.");
        if (/^\/(?!\/)/.test(String(prepared.destination || ""))) sessionStorage.setItem("forum_invite_destination", String(prepared.destination));
        history.replaceState(null, "", window.location.pathname);
        feedback(status, "Invitation redeemed. Verifying member access…", "ok");
        window.location.reload();
      } catch (error) { feedback(status, error && error.message || "Unable to redeem invitation.", "error"); }
    });
  });
})();
