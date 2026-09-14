(function () {
  "use strict";

  var authenticationInFlight = null;

  function stored(name) {
    try {
      return window.localStorage.getItem(name) || "";
    } catch (error) {
      return "";
    }
  }

  function storedFingerprint() {
    var value = stored("forum_pki_fingerprint").trim().toLowerCase();
    if (value.indexOf("openpgp:") === 0) {
      value = value.slice("openpgp:".length);
    }
    return value;
  }

  function authenticatedIdentityId() {
    var node = document.querySelector("[data-private-site-auth-state]");
    if (!node || !node.dataset) {
      return "";
    }

    return String(node.dataset.authenticatedIdentityId || "").trim().toLowerCase();
  }

  function statusNode() {
    return document.querySelector('[data-role="private-site-auth-status"]');
  }

  function setStatus(message, kind) {
    var node = statusNode();
    if (!node) {
      return;
    }

    node.hidden = message === "";
    node.textContent = message;
    node.dataset.kind = kind || "info";
  }

  function responseError(text, fallback) {
    var match = String(text || "").match(/^error=(.+)$/m);
    if (match && match[1].trim() !== "") {
      return match[1].trim();
    }

    return fallback;
  }

  function reportFailure(error) {
    var message = error instanceof Error ? error.message : "Unable to authenticate this browser identity.";
    setStatus(message, "error");
    if (typeof console !== "undefined" && typeof console.error === "function") {
      console.error("[private site authentication]", error);
    }
  }

  async function authenticateOnce() {
    var publicKey = stored("forum_pki_public_key");
    var privateKey = stored("forum_pki_private_key");
    var fingerprint = storedFingerprint();
    var loader = window.__forumOpenPgpLoader;
    var browserIdentity = window.__forumBrowserIdentity;

    if (!publicKey || !privateKey || !/^[a-f0-9]{40}$/.test(fingerprint)) {
      return { status: "not-configured" };
    }

    var identityId = "openpgp:" + fingerprint;
    if (authenticatedIdentityId() === identityId) {
      setStatus("", "ok");
      return { status: "authenticated", identityId: identityId };
    }

    if (!loader) {
      throw new Error("OpenPGP support did not load. Reload the page and try again.");
    }

    setStatus("Verifying this browser identity...", "info");
    await loader.ready;
    if (browserIdentity && typeof browserIdentity.ensureReadyIdentity === "function") {
      await browserIdentity.ensureReadyIdentity(null, null, { verifyPublishedIdentity: true });
      publicKey = stored("forum_pki_public_key");
      privateKey = stored("forum_pki_private_key");
      fingerprint = storedFingerprint();
    }
    var openpgp = window.openpgp;
    var challengeResponse = await fetch("/api/auth_challenge", { credentials: "same-origin" });
    var challengeText = await challengeResponse.text();
    if (!challengeResponse.ok) {
      throw new Error(responseError(challengeText, "Unable to request an authentication challenge."));
    }

    var challengeMatch = challengeText.match(/^challenge=([a-f0-9]+)$/m);
    if (!challengeMatch) {
      throw new Error("The authentication challenge response was invalid.");
    }

    var challenge = challengeMatch[1];
    var publicKeyObject = await openpgp.readKey({ armoredKey: publicKey });
    var privateKeyObject = await openpgp.readPrivateKey({ armoredKey: privateKey });
    var publicFingerprint = String(publicKeyObject.getFingerprint()).trim().toLowerCase();
    var privateFingerprint = String(privateKeyObject.getFingerprint()).trim().toLowerCase();
    if (!/^[a-f0-9]{40}$/.test(publicFingerprint) || publicFingerprint !== privateFingerprint) {
      throw new Error("The saved public and private keys do not belong to the same identity.");
    }

    // The public key is authoritative; the stored fingerprint may be stale.
    fingerprint = publicFingerprint;
    var message = await openpgp.createMessage({ text: challenge });
    var signature = await openpgp.sign({
      message: message,
      signingKeys: privateKeyObject,
      detached: true,
      format: "armored"
    });
    var body = new URLSearchParams();
    identityId = "openpgp:" + fingerprint;
    body.set("identity_id", identityId);
    body.set("challenge", challenge);
    body.set("detached_signature", signature);
    var authenticationResponse = await fetch("/api/authenticate_identity", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    });

    var authenticationResult = await authenticationResponse.text();
    if (!authenticationResponse.ok) {
      throw new Error(responseError(authenticationResult, "Unable to authenticate this browser identity."));
    }

    if (/^approved=1$/m.test(authenticationResult)) {
      setStatus("Identity verified. Entering the site...", "ok");
      window.location.assign("/");
      return { status: "approved", identityId: identityId };
    }

    setStatus("Identity verified. Approval is still pending.", "ok");
    // Re-render once so the server can expose the session-bound pending
    // identity. The rendered identity marker prevents a reload loop.
    window.location.reload();
    return { status: "pending", identityId: identityId };
  }

  function authenticate() {
    if (authenticationInFlight) {
      return authenticationInFlight;
    }

    authenticationInFlight = authenticateOnce()
      .catch(function (error) {
        reportFailure(error);
        throw error;
      })
      .finally(function () {
        authenticationInFlight = null;
      });

    return authenticationInFlight;
  }

  window.PrivateSiteAuth = { authenticate: authenticate };
  document.addEventListener("DOMContentLoaded", function () {
    authenticate().catch(function () {});
  });
})();
