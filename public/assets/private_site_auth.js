(function () {
  "use strict";

  function stored(name) {
    try {
      return window.localStorage.getItem(name) || "";
    } catch (error) {
      return "";
    }
  }

  async function authenticate() {
    var publicKey = stored("forum_pki_public_key");
    var privateKey = stored("forum_pki_private_key");
    var fingerprint = stored("forum_pki_fingerprint").trim().toLowerCase();
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
    var key = await openpgp.readPrivateKey({ armoredKey: privateKey });
    var message = await openpgp.createMessage({ text: challenge });
    var signature = await openpgp.sign({
      message: message,
      signingKeys: key,
      detached: true,
      format: "armored"
    });
    var body = new URLSearchParams();
    body.set("identity_id", "openpgp:" + fingerprint);
    body.set("challenge", challenge);
    body.set("detached_signature", signature);
    await fetch("/api/authenticate_identity", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    });
  }

  window.PrivateSiteAuth = { authenticate: authenticate };
  document.addEventListener("DOMContentLoaded", function () {
    authenticate().catch(function () {});
  });
})();
