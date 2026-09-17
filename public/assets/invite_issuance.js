(function () {
  "use strict";

  function setFeedback(node, message, kind) {
    node.hidden = false;
    node.textContent = message;
    node.dataset.status = kind || "ok";
  }

  function randomToken() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, function (value) { return value.toString(16).padStart(2, "0"); }).join("");
  }

  async function verificationHash(token) {
    const bytes = new TextEncoder().encode(token);
    const digest = await crypto.subtle.digest("SHA-256", bytes);
    return "sha256:" + Array.from(new Uint8Array(digest), function (value) { return value.toString(16).padStart(2, "0"); }).join("");
  }

  async function post(endpoint, values) {
    const response = await fetch(endpoint, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams(values).toString(),
    });
    return response.json();
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-invitation-page]");
    const form = root && root.querySelector("[data-invitation-issue-form]");
    if (!form) return;
    const feedback = root.querySelector("[data-role=invitation-feedback]");
    const result = root.querySelector("[data-role=invitation-result]");
    const link = root.querySelector("[data-role=invitation-link]");
    const hash = root.querySelector("[data-role=invitation-hash]");
    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      try {
        const token = randomToken();
        const prepared = await post("/api/prepare_invitation", {
          action: "issue",
          verification_hash: await verificationHash(token),
          expires_at: new Date(Date.now() + 7 * 86400 * 1000).toISOString().replace(/\.\d{3}Z$/, "Z"),
          destination: String(form.elements.destination.value || ""),
        });
        if (!prepared || prepared.status !== "ok") throw new Error(prepared && prepared.error || "Unable to prepare invitation.");
        if (!window.ForumBrowserSigning || !window.ForumBrowserSigning.signCanonicalRecord) throw new Error("Browser signing is unavailable.");
        const signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: (prepared.canonical_record.match(/^Author-Identity-ID: (.+)$/m) || ["", ""])[1],
          canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!finalized || finalized.status !== "ok") throw new Error(finalized && finalized.error || "Unable to create invitation.");
        link.value = window.location.origin + "/lobby/#invite=" + token;
        hash.textContent = "Verification hash: " + finalized.verification_hash;
        result.hidden = false;
        setFeedback(feedback, "Invitation created. Copy the link now; the secret is not stored by the site.", "ok");
      } catch (error) {
        setFeedback(feedback, error && error.message || "Unable to create invitation.", "error");
      }
    });

    const revokeForm = root.querySelector("[data-invitation-revoke-form]");
    if (revokeForm) revokeForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      try {
        const prepared = await post("/api/prepare_invitation", {
          action: "revoke", invitation_id: String(revokeForm.elements.invitation_id.value || ""),
          verification_hash: String(revokeForm.elements.verification_hash.value || ""),
        });
        if (!prepared || prepared.status !== "ok") throw new Error(prepared && prepared.error || "Unable to prepare revocation.");
        const signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: (prepared.canonical_record.match(/^Author-Identity-ID: (.+)$/m) || ["", ""])[1],
          canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!finalized || finalized.status !== "ok") throw new Error(finalized && finalized.error || "Unable to revoke invitation.");
        setFeedback(feedback, "Invitation revoked.", "ok");
      } catch (error) {
        setFeedback(feedback, error && error.message || "Unable to revoke invitation.", "error");
      }
    });
  });
})();
