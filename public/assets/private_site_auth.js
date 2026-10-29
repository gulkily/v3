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

  function reportFailure(error, options) {
    var message = error instanceof Error ? error.message : "Unable to authenticate this browser identity.";
    if (!(options && options.quiet)) setStatus(message, "error");
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

  function requestedReturnDestination(options) {
    return safeRelativeDestination(options && options.returnTo) || configuredReturnDestination();
  }

  function isPublicAuthenticationResume() {
    var node = document.querySelector("[data-private-site-auth-state]");
    return Boolean(node && node.dataset && node.dataset.publicAuthResume === "true");
  }

  function isApprovedMembersOnly() {
    return Boolean(
      document.documentElement
      && document.documentElement.dataset
      && document.documentElement.dataset.approvedMembersOnly === "1"
    );
  }

  function approvedDestination(options) {
    var requestedDestination = requestedReturnDestination(options);
    if (requestedDestination) {
      return requestedDestination;
    }

    var configuredDestination = configuredReturnDestination();
    if (configuredDestination) {
      return configuredDestination;
    }

    var value = "";
    try { value = sessionStorage.getItem("forum_invite_destination") || ""; sessionStorage.removeItem("forum_invite_destination"); } catch (error) {}
    return safeRelativeDestination(value);
  }

  function navigateAfterApproval(options) {
    var destination = approvedDestination(options);
    if (destination === "") {
      window.location.reload();
      return;
    }

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

  async function authenticateOnce(options, identity) {
    assertCurrentIdentity(identity);
    var publicKey = stored("forum_pki_public_key");
    var privateKey = stored("forum_pki_private_key");
    var fingerprint = storedFingerprint();
    var loader = window.__forumOpenPgpLoader;
    var browserIdentity = window.__forumBrowserIdentity;

    if (!publicKey || !privateKey || !/^[a-f0-9]{40}$/.test(fingerprint)) {
      return { status: "not-configured" };
    }

    // On public sites a key stays in the browser until its first signed action registers it.
    if (!isApprovedMembersOnly() && stored("forum_pki_published_fingerprint").trim().toLowerCase() !== fingerprint) {
      return { status: "not-configured" };
    }

    var identityId = "openpgp:" + fingerprint;
    if (!(options && options.force === true) && authenticatedIdentityId() === identityId) {
      return { status: "authenticated", identityId: identityId, existingSession: true };
    }

    if (!loader) {
      throw new Error("OpenPGP support did not load. Reload the page and try again.");
    }

    if (!(options && options.quiet)) setStatus("Verifying this browser identity...", "info");
    if (typeof loader.load === "function") {
      await loader.load();
    } else {
      await loader.ready;
    }
    if (browserIdentity && typeof browserIdentity.ensureReadyIdentity === "function") {
      const ready = await browserIdentity.ensureReadyIdentity(null, null, {
        verifyPublishedIdentity: true,
        existingIdentityOnly: Boolean(options && options.existingIdentityOnly)
      });
      if (ready === false) return { status: "not-configured" };
      publicKey = stored("forum_pki_public_key");
      privateKey = stored("forum_pki_private_key");
      fingerprint = storedFingerprint();
    }
    assertCurrentIdentity(identity);
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
    assertCurrentIdentity(identity);
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

    assertCurrentIdentity(identity);
    var approved = /^approved=1$/m.test(authenticationResult);
    if (window.ForumMemberApproval) window.ForumMemberApproval.record(fingerprint, approved);
    return {
      status: approved ? "approved" : isApprovedMembersOnly() ? "pending" : "authenticated",
      identityId: identityId
    };
  }

  function identitySnapshot() {
    return JSON.stringify([stored("forum_pki_public_key"), stored("forum_pki_private_key"), storedFingerprint()]);
  }

  function assertCurrentIdentity(identity) {
    if (identitySnapshot() !== identity) {
      throw new Error("Browser identity changed. Try signing in again with the current key.");
    }
  }

  function finishAuthentication(result, options, flight) {
    if (result.status === "not-configured") {
      var destination = requestedReturnDestination(options);
      if (options.navigate !== false && !isPublicAuthenticationResume() && destination
        && (!stored("forum_pki_public_key") || !stored("forum_pki_private_key") || !/^[a-f0-9]{40}$/.test(storedFingerprint()))) {
        if (!flight.navigated) window.location.replace("/lobby/?return_to=" + encodeURIComponent(destination));
        flight.navigated = true;
      }
      return result;
    }
    if (!options.quiet) setStatus("", "ok");
    if (options.navigate === false || flight.navigated) return result;
    if (result.existingSession && requestedReturnDestination(options) === "") return result;
    flight.navigated = true;
    if (result.status === "pending") {
      setStatus("Identity verified. Approval is still pending.", "ok");
      var returnTo = requestedReturnDestination(options);
      if (returnTo) window.location.replace("/lobby/?return_to=" + encodeURIComponent(returnTo));
      else window.location.reload();
    } else {
      setStatus(result.status === "approved" ? "Identity verified. Entering the site..." : "Identity verified.", "ok");
      navigateAfterApproval(options);
    }
    return result;
  }

  function authenticate(options) {
    options = Object.assign({}, options || {});
    var identity = identitySnapshot();
    var flight = authenticationInFlight;
    // PHP sessions hold one challenge. Queue fresh/identity-restricted work;
    // compatible callers share signing, but each retains its own continuation.
    if (flight && (options.force === true || flight.identity !== identity
      || Boolean(options.existingIdentityOnly) !== flight.existingIdentityOnly)) {
      return flight.promise.catch(function () {}).then(function () {
        try { assertCurrentIdentity(identity); } catch (error) {
          reportFailure(error, options);
          throw error;
        }
        return authenticate(options);
      });
    }
    if (!flight) {
      flight = { identity: identity, existingIdentityOnly: Boolean(options.existingIdentityOnly), navigated: false };
      authenticationInFlight = flight;
      flight.promise = authenticateOnce(options, identity).catch(function (error) {
        if (!isExpiredChallengeError(error)) throw error;
        assertCurrentIdentity(identity);
        if (!options.quiet) setStatus("Authentication challenge expired. Retrying...", "info");
        return authenticateOnce(options, identity);
      }).finally(function () {
        if (authenticationInFlight === flight) authenticationInFlight = null;
      });
    }
    return flight.promise.then(function (result) {
      assertCurrentIdentity(identity);
      return finishAuthentication(result, options, flight);
    }).catch(function (error) {
      reportFailure(error, options);
      throw error;
    });
  }

  function authenticateInPlace(state) {
    authenticate({ navigate: false, quiet: true }).then(function (result) {
      if (!result || !result.identityId || result.existingSession) return;
      state.dataset.authenticatedIdentityId = result.identityId;
      window.dispatchEvent(new CustomEvent("forum:authenticated-in-place", {
        detail: { identityId: result.identityId, approved: result.status === "approved" }
      }));
    }).catch(function () {});
  }

  window.PrivateSiteAuth = { authenticate: authenticate };
  document.addEventListener("DOMContentLoaded", function () {
    // Invite authenticates when an action starts, without redirecting away from its form.
    if (document.querySelector("[data-invitation-page]")) return;
    if (window.location && /^#invite=/.test(window.location.hash || "")) return;
    var state = document.querySelector("[data-private-site-auth-state]");
    if (!state) {
      return;
    }

    if (state.dataset && state.dataset.publicAuthResume === "true") {
      var sharedPage = Boolean(document.querySelector('meta[name="v3-generated-at"]'));
      if (sharedPage) {
        // Public shared HTML cannot change after authentication. Live actions and
        // protected pages resume/verify identity when needed; do not reload or
        // perform a redundant sign-in on every cached navigation.
        delete state.dataset.publicAuthResume;
        return;
      }
      authenticateInPlace(state);
      return;
    }

    if (state.hasAttribute("data-auth-in-place") && !isApprovedMembersOnly()) {
      authenticateInPlace(state);
      return;
    }

    authenticate().catch(function () {});
  });
})();
