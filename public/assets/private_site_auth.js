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

  function safeRelativeDestination(value) {
    value = String(value || "").trim();
    if (!/^\/(?![\\/\\\\])/.test(value) || /[\u0000-\u001F\u007F\\\\]/.test(value)) {
      return "";
    }

    return value;
  }

  function configuredReturnDestination() {
    var node = document.querySelector("[data-private-site-auth-state]");
    return safeRelativeDestination(node && node.dataset ? node.dataset.authReturnTo : "");
  }

  function approvedDestination(options) {
    var explicitDestination = safeRelativeDestination(options && options.returnTo);
    if (explicitDestination) {
      return explicitDestination;
    }

    var configuredDestination = configuredReturnDestination();
    if (configuredDestination) {
      return configuredDestination;
    }

    var value = "";
    try { value = sessionStorage.getItem("forum_invite_destination") || ""; sessionStorage.removeItem("forum_invite_destination"); } catch (error) {}
    return safeRelativeDestination(value) || "/";
  }

  function navigateAfterApproval(options) {
    var destination = approvedDestination(options);
    if ((configuredReturnDestination() !== "" || (options && options.replaceHistory === true))
      && window.location && typeof window.location.replace === "function") {
      window.location.replace(destination);
      return;
    }

    window.location.assign(destination);
  }

  function isExpiredChallengeError(error) {
    return error instanceof Error
      && error.message === "Authentication challenge is missing or expired.";
  }

  async function authenticateOnce(options) {
    var publicKey = stored("forum_pki_public_key");
    var privateKey = stored("forum_pki_private_key");
    var fingerprint = storedFingerprint();
    var loader = window.__forumOpenPgpLoader;
    var browserIdentity = window.__forumBrowserIdentity;

    if (!publicKey || !privateKey || !/^[a-f0-9]{40}$/.test(fingerprint)) {
      if (configuredReturnDestination() !== "") {
        setStatus("This browser key is unavailable. Check your browser key to continue.", "error");
      }
      return { status: "not-configured" };
    }

    var identityId = "openpgp:" + fingerprint;
    if (authenticatedIdentityId() === identityId) {
      setStatus("", "ok");
      if (configuredReturnDestination() !== "") {
        navigateAfterApproval(options);
      }
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
      navigateAfterApproval(options);
      return { status: "approved", identityId: identityId };
    }

    setStatus("Identity verified. Approval is still pending.", "ok");
    var returnTo = configuredReturnDestination();
    if (returnTo !== "") {
      window.location.replace("/lobby/?return_to=" + encodeURIComponent(returnTo));
      return { status: "pending", identityId: identityId };
    }
    // Re-render once so the server can expose the session-bound pending
    // identity. The rendered identity marker prevents a reload loop.
    window.location.reload();
    return { status: "pending", identityId: identityId };
  }

  function authenticate(options) {
    if (authenticationInFlight) {
      return authenticationInFlight;
    }

    authenticationInFlight = authenticateOnce(options)
      .catch(function (error) {
        if (!isExpiredChallengeError(error)) {
          throw error;
        }

        setStatus("Authentication challenge expired. Retrying...", "info");
        return authenticateOnce(options);
      })
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
