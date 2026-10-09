(function () {
  "use strict";

  const privateKeyStorageKey = "forum_pki_private_key";

  function armoredPrivateKey() {
    try {
      return String(window.localStorage.getItem(privateKeyStorageKey) || "");
    } catch (error) {
      return "";
    }
  }

  async function openPgpApi() {
    const identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureOpenPgpApi !== "function") {
      throw new Error("OpenPGP support is unavailable.");
    }

    return identity.ensureOpenPgpApi(["readKey", "readPrivateKey", "readMessage", "decrypt"]);
  }

  async function decryptEnvelope(input) {
    const options = input || {};
    const privateKeyArmor = armoredPrivateKey();
    if (!privateKeyArmor) {
      return { kind: "unavailable", message: "This browser has no private key for this message." };
    }

    const senderPublicKeyArmors = Array.isArray(options.senderPublicKeyArmors) ? options.senderPublicKeyArmors : [];
    if (senderPublicKeyArmors.length === 0) {
      return { kind: "bad-signature", message: "The sender's approved public keys are unavailable, so this message cannot be verified." };
    }

    let decrypted;
    try {
      const openpgp = await openPgpApi();
      const verificationKeys = await Promise.all(senderPublicKeyArmors.map(function (armoredKey) {
        return openpgp.readKey({ armoredKey: String(armoredKey) });
      }));
      const decryptionKey = await openpgp.readPrivateKey({ armoredKey: privateKeyArmor });
      const message = await openpgp.readMessage({ armoredMessage: String(options.encryptedEnvelope || "") });
      decrypted = await openpgp.decrypt({
        message: message,
        decryptionKeys: decryptionKey,
        verificationKeys: verificationKeys,
        format: "utf8",
      });
    } catch (error) {
      return { kind: "decryption-failed", message: "This encrypted message could not be decrypted with the saved private key." };
    }

    if (!Array.isArray(decrypted.signatures) || decrypted.signatures.length === 0) {
      return { kind: "bad-signature", message: "This message has no verifiable sender signature." };
    }

    try {
      await Promise.all(decrypted.signatures.map(function (signature) {
        return signature.verified;
      }));
    } catch (error) {
      return { kind: "bad-signature", message: "The sender signature could not be verified." };
    }

    return { kind: "verified", plaintext: String(decrypted.data || "") };
  }

  async function mailboxMessage(mailbox, messageId) {
    const response = await fetch("/api/private_messages/" + encodeURIComponent(mailbox), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok || !payload || payload.status !== "ok" || !Array.isArray(payload.messages)) {
      throw new Error("Unable to load the encrypted message.");
    }

    const message = payload.messages.find(function (candidate) {
      return String(candidate && candidate.message_id || "") === messageId;
    });
    if (!message) {
      throw new Error("This encrypted message is no longer available in your mailbox.");
    }

    return message;
  }

  function setFeedback(node, plaintextNode, result) {
    node.textContent = result.message || "";
    node.className = "meta feedback feedback-" + result.kind;
    node.hidden = false;
    if (result.kind !== "verified") {
      plaintextNode.textContent = "";
      plaintextNode.hidden = true;
      return;
    }

    node.textContent = "Signature verified.";
    plaintextNode.textContent = result.plaintext;
    plaintextNode.hidden = false;
  }

  function bindMailbox(root) {
    if (!root || !root.dataset || root.dataset.privateMessageReaderBound === "1") {
      return false;
    }

    const mailbox = String(root.dataset.mailbox || "");
    if (mailbox !== "inbox" && mailbox !== "sent") {
      return false;
    }

    root.dataset.privateMessageReaderBound = "1";
    root.addEventListener("click", async function (event) {
      const button = event.target instanceof Element
        ? event.target.closest('[data-action="read-private-message"]')
        : null;
      if (!button) {
        return;
      }

      const card = button.closest("[data-private-message-id]");
      const feedback = card && card.querySelector('[data-role="private-message-reader-feedback"]');
      const plaintext = card && card.querySelector('[data-role="private-message-plaintext"]');
      if (!card || !feedback || !plaintext) {
        return;
      }

      const messageId = String(card.dataset.privateMessageId || "");
      if (!messageId) {
        return;
      }

      button.disabled = true;
      setFeedback(feedback, plaintext, { kind: "loading", message: "Loading encrypted message..." });
      try {
        const message = await mailboxMessage(mailbox, messageId);
        const messaging = window.ForumPrivateMessages;
        if (!messaging || typeof messaging.recipientKeys !== "function") {
          throw new Error("Sender key lookup is unavailable.");
        }
        const senderPublicKeyArmors = await messaging.recipientKeys(String(message.sender_username_token || ""));
        setFeedback(feedback, plaintext, await decryptEnvelope({
          encryptedEnvelope: message.encrypted_envelope,
          senderPublicKeyArmors: senderPublicKeyArmors,
        }));
      } catch (error) {
        setFeedback(feedback, plaintext, { kind: "decryption-failed", message: "This encrypted message could not be loaded or decrypted." });
      } finally {
        button.disabled = false;
      }
    });

    return true;
  }

  window.ForumPrivateMessageReader = {
    decryptEnvelope: decryptEnvelope,
    bindMailbox: bindMailbox,
  };

  document.addEventListener("DOMContentLoaded", function () {
    Array.from(document.querySelectorAll("[data-private-message-mailbox]")).forEach(bindMailbox);
  });
})();
