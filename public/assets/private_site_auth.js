(function () {
  "use strict";

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

  async function authenticate() {
    var publicKey = stored("forum_pki_public_key");
    var privateKey = stored("forum_pki_private_key");
    var fingerprint = storedFingerprint();
    var loader = window.__forumOpenPgpLoader;

    if (!publicKey || !privateKey || !/^[a-f0-9]{40}$/.test(fingerprint) || !loader) {
      return;
    }

    await loader.ready;
    var openpgp = window.openpgp;
    var challengeResponse = await fetch("/api/auth_challenge", { credentials: "same-origin" });
    if (!challengeResponse.ok) {
      return;
    }

    var challengeText = await challengeResponse.text();
    var challengeMatch = challengeText.match(/^challenge=([a-f0-9]+)$/m);
    if (!challengeMatch) {
      return;
    }

    var challenge = challengeMatch[1];
    var publicKeyObject = await openpgp.readKey({ armoredKey: publicKey });
    var privateKeyObject = await openpgp.readPrivateKey({ armoredKey: privateKey });
    var publicFingerprint = String(publicKeyObject.getFingerprint()).trim().toLowerCase();
    var privateFingerprint = String(privateKeyObject.getFingerprint()).trim().toLowerCase();
    if (!/^[a-f0-9]{40}$/.test(publicFingerprint) || publicFingerprint !== privateFingerprint) {
      return;
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
    body.set("identity_id", "openpgp:" + fingerprint);
    body.set("challenge", challenge);
    body.set("detached_signature", signature);
    var authenticationResponse = await fetch("/api/authenticate_identity", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    });

    if (!authenticationResponse.ok) {
      return;
    }

    var authenticationResult = await authenticationResponse.text();
    if (/^approved=1$/m.test(authenticationResult)) {
      window.location.assign("/");
      return;
    }

    // Re-render the lobby/account page so the server can expose the
    // session-bound viewer profile link, including for pending users.
    window.location.reload();
  }

  window.PrivateSiteAuth = { authenticate: authenticate };
  document.addEventListener("DOMContentLoaded", function () {
    authenticate().catch(function () {});
  });
})();
