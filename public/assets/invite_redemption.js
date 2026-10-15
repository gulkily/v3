(function () {
  "use strict";
  const invitationTokenPattern = /^(?:[a-f0-9]{32}|[a-f0-9]{64}|[A-Za-z0-9_-]{21}[AQgw])$/;
  async function post(endpoint, values) {
    const response = await fetch(endpoint, { method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: new URLSearchParams(values).toString() });
    const result = await response.json();
    if (!response.ok || !result || result.status !== "ok") throw new Error(result && result.error || "Unable to redeem invitation.");
    return result;
  }
  function destination(value) {
    value = String(value || "");
    let decoded;
    try { decoded = decodeURIComponent(value); } catch (error) { return ""; }
    return value.length <= 500 && /^\/(?!\/)/.test(decoded) && !/[\u0000-\u0020\u007f\\]/.test(value) && !/[\u0000-\u001f\u007f\\]/.test(decoded) ? value : "";
  }
  async function ensureRedemptionIdentity(root, status, targeted) {
    const identity = window.__forumBrowserIdentity;
    const readiness = identity && (identity.ensureActionIdentity || identity.ensureReadyIdentity);
    if (typeof readiness !== "function") throw new Error("Identity setup is unavailable. Reload the page and try again.");
    feedback(status, "Preparing your browser identity…", "ok");
    const options = { verifyPublishedIdentity: false };
    if (targeted) options.temporaryName = "reader-" + Array.from(crypto.getRandomValues(new Uint8Array(8)), byte => byte.toString(16).padStart(2, "0")).join("");
    await readiness.call(identity, root, status, options);
    const fingerprint = String(localStorage.getItem("forum_pki_fingerprint") || "").replace(/^openpgp:/, "").toLowerCase();
    if (!/^[a-f0-9]{40}$/.test(fingerprint)) throw new Error("Unable to prepare a browser key for this invitation.");
    return fingerprint;
  }
  function feedback(node, message, kind) { node.hidden = false; node.textContent = message; node.dataset.status = kind || "ok"; }
  document.addEventListener("DOMContentLoaded", function () {
    const match = window.location.hash.match(/^#invite=(.*)$/);
    const root = document.querySelector("[data-invitation-redemption]");
    if (!match || !root) return;
    root.hidden = false;
    const token = match[1];
    const status = root.querySelector("[data-role=invitation-redemption-feedback]");
    const button = root.querySelector("[data-action=redeem-invitation]");
    let inFlight = false;
    async function redeem(manual) {
      if (inFlight) return;
      inFlight = true;
      button.disabled = true;
      button.hidden = true;
      try {
        if (!invitationTokenPattern.test(token)) throw new Error("Invitation token is invalid. Ask the sender for a new invitation.");
        feedback(status, "Checking invitation…", "ok");
        const inspected = await post("/api/inspect_invitation", { invite_token: token });
        if (!inspected.targeted && !manual) {
          feedback(status, "Choose Redeem invitation to join.", "ok");
          button.hidden = false;
          return;
        }
        const fingerprint = await ensureRedemptionIdentity(root, status, inspected.targeted);
        const identityId = "openpgp:" + fingerprint;
        const prepared = await post("/api/prepare_invitation_redemption", { identity_id: identityId, invite_token: token });
        const signature = await window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: identityId, canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!window.PrivateSiteAuth) throw new Error("Access verification is unavailable. Reload to resume your invitation.");
        feedback(status, "Invitation accepted. Verifying member access…", "ok");
        const authenticated = await window.PrivateSiteAuth.authenticate({ navigate: false, force: true });
        if (authenticated.status !== "approved" || authenticated.identityId !== identityId) {
          throw new Error("Member access could not be verified. Retry or review your account.");
        }
        const target = destination(finalized.destination);
        if (inspected.targeted && !target) throw new Error("Invitation destination is unavailable. Your membership is saved; use the board link below.");
        // Keep the bearer in the current page until access succeeds, allowing reload/retry
        // after a lost response. Replace the Lobby entry so Back does not redeem again.
        try { sessionStorage.removeItem("forum_invite_destination"); } catch (error) {}
        window.location.replace(target || "/");
      } catch (error) {
        feedback(status, (error && error.message || "Unable to redeem invitation.") + " Retry here or ask the sender for help.", "error");
        button.textContent = "Retry invitation";
        button.hidden = false;
      } finally {
        inFlight = false;
        button.disabled = false;
      }
    }
    button.addEventListener("click", function () { void redeem(true); });
    void redeem(false);
  });
})();
