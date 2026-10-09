(function () {
  "use strict";

  const privateKeyStorageKey = "forum_pki_private_key";
  const mailboxRequests = new Map();

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

  async function mailboxMessages(mailbox, counterpartUsernameToken) {
    const conversation = mailbox === "conversation";
    const requestKey = conversation ? mailbox + ":" + counterpartUsernameToken : mailbox;
    if (!mailboxRequests.has(requestKey)) {
      mailboxRequests.set(requestKey, (async function () {
        const path = conversation
          ? "/api/private_messages/conversation?username_token=" + encodeURIComponent(counterpartUsernameToken)
          : "/api/private_messages/" + encodeURIComponent(mailbox);
        const response = await fetch(path, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (!response.ok || !payload || payload.status !== "ok" || !Array.isArray(payload.messages)) {
          throw new Error("Unable to load the encrypted message.");
        }

        return payload.messages;
      })());
    }

    try {
      return await mailboxRequests.get(requestKey);
    } catch (error) {
      mailboxRequests.delete(requestKey);
      throw error;
    }
  }

  async function mailboxMessage(mailbox, messageId, counterpartUsernameToken) {
    const messages = await mailboxMessages(mailbox, counterpartUsernameToken);
    const message = messages.find(function (candidate) {
      return String(candidate && candidate.message_id || "") === messageId;
    });
    if (!message) {
      throw new Error("This encrypted message is no longer available in your mailbox.");
    }

    return message;
  }

  function setReaderResult(verificationNode, errorNode, plaintextNode, result) {
    verificationNode.hidden = true;
    errorNode.textContent = "";
    errorNode.hidden = true;
    plaintextNode.textContent = "";
    plaintextNode.hidden = true;

    if (result.kind !== "verified") {
      errorNode.textContent = result.message || "This encrypted message could not be loaded or decrypted.";
      errorNode.className = "feedback feedback-" + result.kind;
      errorNode.hidden = false;
      return;
    }

    verificationNode.textContent = "✓";
    verificationNode.hidden = false;
    plaintextNode.textContent = result.plaintext;
    plaintextNode.hidden = false;
  }

  async function readCard(mailbox, card, counterpartUsernameToken) {
    const verification = card && card.querySelector('[data-role="private-message-verification"]');
    const error = card && card.querySelector('[data-role="private-message-reader-error"]');
    const plaintext = card && card.querySelector('[data-role="private-message-plaintext"]');
    if (!card || !verification || !error || !plaintext) {
      return;
    }

    const messageId = String(card.dataset.privateMessageId || "");
    if (!messageId) {
      return;
    }

    let result;
    try {
      const message = await mailboxMessage(mailbox, messageId, counterpartUsernameToken);
      const messaging = window.ForumPrivateMessages;
      if (!messaging || typeof messaging.recipientKeys !== "function") {
        throw new Error("Sender key lookup is unavailable.");
      }
      const senderPublicKeyArmors = await messaging.recipientKeys(String(message.sender_username_token || ""));
      result = await decryptEnvelope({
        encryptedEnvelope: message.encrypted_envelope,
        senderPublicKeyArmors: senderPublicKeyArmors,
      });
    } catch (error) {
      result = { kind: "decryption-failed", message: "This encrypted message could not be loaded or decrypted." };
    }

    setReaderResult(verification, error, plaintext, result);
    return result;
  }

  function bindMailbox(root) {
    if (!root || !root.dataset || root.dataset.privateMessageReaderBound === "1") {
      return false;
    }

    const mailbox = String(root.dataset.mailbox || "");
    if (mailbox !== "inbox" && mailbox !== "sent" && mailbox !== "conversation") {
      return false;
    }

    const counterpartUsernameToken = String(root.dataset.counterpartUsernameToken || "");
    if (mailbox === "conversation" && counterpartUsernameToken === "") {
      return false;
    }

    root.dataset.privateMessageReaderBound = "1";
    Array.from(root.querySelectorAll("[data-private-message-id]")).forEach(function (card) {
      readCard(mailbox, card, counterpartUsernameToken);
    });

    return true;
  }

  window.ForumPrivateMessageReader = {
    decryptEnvelope: decryptEnvelope,
    bindMailbox: bindMailbox,
    readCard: readCard,
  };

  document.addEventListener("DOMContentLoaded", function () {
    Array.from(document.querySelectorAll("[data-private-message-mailbox]")).forEach(bindMailbox);
  });
})();
