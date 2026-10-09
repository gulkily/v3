(function () {
  "use strict";

  const storageKeys = {
    username: "forum_pki_username",
    publicKey: "forum_pki_public_key",
    privateKey: "forum_pki_private_key",
  };

  function usernameToken(value) {
    const token = String(value || "").trim().toLowerCase();
    if (!/^[a-z0-9][a-z0-9._-]{0,63}$/.test(token)) {
      throw new Error("A valid username is required to prepare a private message.");
    }

    return token;
  }

  function armoredKey(value) {
    const key = String(value || "");
    if (!key.includes("-----BEGIN PGP PUBLIC KEY BLOCK-----")) {
      throw new Error("A required recipient public key is unavailable.");
    }

    return key;
  }

  async function openPgpApi(methods) {
    const identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureOpenPgpApi !== "function") {
      throw new Error("OpenPGP support is unavailable. Reload and try again.");
    }

    return identity.ensureOpenPgpApi(methods);
  }

  async function recipientKeys(token) {
    const response = await fetch(`/api/private_messages/recipient_keys?username_token=${encodeURIComponent(usernameToken(token))}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok || !payload || payload.status !== "ok" || !Array.isArray(payload.keys)) {
      throw new Error(String((payload && payload.error) || "Unable to load recipient keys."));
    }

    return payload.keys.map(function (row) {
      return armoredKey(row && row.public_key);
    });
  }

  function uniqueKeys(keys) {
    return Array.from(new Set(keys.map(armoredKey)));
  }

  async function prepareEnvelope(input) {
    const options = input || {};
    const plaintext = String(options.plaintext || "");
    if (plaintext === "") {
      throw new Error("A private message cannot be empty.");
    }

    const senderUsernameToken = usernameToken(options.senderUsernameToken || localStorage.getItem(storageKeys.username));
    const recipientUsernameToken = usernameToken(options.recipientUsernameToken);
    const localPublicKey = armoredKey(localStorage.getItem(storageKeys.publicKey));
    const localPrivateKey = String(localStorage.getItem(storageKeys.privateKey) || "");
    if (!localPrivateKey) {
      throw new Error("No browser private key is available to sign this private message.");
    }

    const [senderKeys, recipientKeysForUser, openpgp] = await Promise.all([
      recipientKeys(senderUsernameToken),
      recipientKeys(recipientUsernameToken),
      openPgpApi(["readKey", "readPrivateKey", "createMessage", "encrypt"]),
    ]);
    const publicKeyArmors = uniqueKeys([localPublicKey].concat(senderKeys, recipientKeysForUser));
    const publicKeys = await Promise.all(publicKeyArmors.map(function (key) {
      return openpgp.readKey({ armoredKey: key });
    }));
    const signingKey = await openpgp.readPrivateKey({ armoredKey: localPrivateKey });
    const localKey = await openpgp.readKey({ armoredKey: localPublicKey });
    const publicFingerprint = String(localKey.getFingerprint ? localKey.getFingerprint() : "").trim().toUpperCase();
    const privateFingerprint = String(signingKey.getFingerprint ? signingKey.getFingerprint() : "").trim().toUpperCase();
    if (publicFingerprint === "" || publicFingerprint !== privateFingerprint) {
      throw new Error("Saved browser keys do not match. Restore a matching keypair before sending.");
    }

    const message = await openpgp.createMessage({ text: plaintext });
    const encryptedEnvelope = await openpgp.encrypt({
      message: message,
      encryptionKeys: publicKeys,
      signingKeys: signingKey,
      format: "armored",
    });

    return {
      encryptedEnvelope: String(encryptedEnvelope || ""),
      senderUsernameToken: senderUsernameToken,
      recipientUsernameToken: recipientUsernameToken,
      senderKeyCount: uniqueKeys(senderKeys).length,
      recipientKeyCount: uniqueKeys(recipientKeysForUser).length,
    };
  }

  window.ForumPrivateMessages = {
    recipientKeys: recipientKeys,
    prepareEnvelope: prepareEnvelope,
  };
})();
