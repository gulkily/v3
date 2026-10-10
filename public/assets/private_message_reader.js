(function () {
  "use strict";

  const privateKeyStorageKey = "forum_pki_private_key";
  const mailboxRequests = new Map();
  const cardMessages = new WeakMap();
  const cardRequests = new WeakMap();
  const cardCursors = new WeakMap();

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

    let openpgp, verificationKeys, decryptionKey, message, decrypted;
    try {
      openpgp = await openPgpApi();
    } catch (error) {
      return { kind: "load-failed", message: "Message reading support could not be loaded. Try again." };
    }
    try {
      verificationKeys = await Promise.all(senderPublicKeyArmors.map(function (armoredKey) {
        return openpgp.readKey({ armoredKey: String(armoredKey) });
      }));
    } catch (error) {
      return { kind: "bad-signature", message: "The sender's approved public keys could not be read, so this message cannot be verified." };
    }
    try {
      decryptionKey = await openpgp.readPrivateKey({ armoredKey: privateKeyArmor });
    } catch (error) {
      return { kind: "read-failed", message: "The saved private key could not be read. Check your browser identity, then retry." };
    }
    try {
      message = await openpgp.readMessage({ armoredMessage: String(options.encryptedEnvelope || "") });
      decrypted = await openpgp.decrypt({
        message: message,
        decryptionKeys: decryptionKey,
        verificationKeys: verificationKeys,
        format: "utf8",
      });
    } catch (error) {
      return { kind: "decryption-failed", message: "This encrypted message could not be decrypted." };
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

  function requestKey(mailbox, counterpartUsernameToken, cursor) {
    return mailbox === 'conversation' ? mailbox + ':' + counterpartUsernameToken + ':' + (cursor || '') : mailbox;
  }

  async function mailboxMessages(mailbox, counterpartUsernameToken, cursor) {
    const conversation = mailbox === "conversation";
    const key = requestKey(mailbox, counterpartUsernameToken, cursor);
    if (!mailboxRequests.has(key)) {
      mailboxRequests.set(key, (async function () {
        const path = conversation
          ? "/api/private_messages/conversation?username_token=" + encodeURIComponent(counterpartUsernameToken) + (cursor ? '&cursor=' + encodeURIComponent(cursor) : '')
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
      return await mailboxRequests.get(key);
    } catch (error) {
      mailboxRequests.delete(key);
      throw error;
    }
  }

  async function mailboxMessage(mailbox, messageId, counterpartUsernameToken, cursor) {
    const messages = await mailboxMessages(mailbox, counterpartUsernameToken, cursor);
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

  function presentState(card, state, message) {
    card.dataset.readerState = state;
    const details = card.querySelector('[data-role="private-message-unavailable-details"]');
    const explanation = card.querySelector('[data-role="private-message-unavailable-explanation"]');
    const retry = card.querySelector('[data-role="private-message-read-retry"]');
    const compact = state === 'unavailable' || state === 'decryption-failed';
    if (details && details.appendChild) {
      details.hidden = !compact;
      if (compact) {
        card.querySelector('[data-role="private-message-reader-error"]').hidden = true;
        explanation.textContent = message + (state === 'decryption-failed'
          ? ' This can happen if you added a new key to your account after the message was sent.' : '') +
          ' If you still have the private key you used at the time, restoring it in this browser may let you read the message.';
      }
      (compact ? details : card).appendChild(retry);
    }
    if (card.dispatchEvent) card.dispatchEvent(new CustomEvent('private-message-read-state-changed', { bubbles: true }));
  }

  async function readCardOnce(mailbox, card, counterpartUsernameToken, suppliedMessage) {
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
    const wasConnected = card.isConnected;
    if (card.dispatchEvent) card.dispatchEvent(new CustomEvent('private-message-before-read', { bubbles: true }));

    verification.hidden = true;
    verification.title = "Signature verified";
    if (verification.setAttribute) verification.setAttribute("aria-label", "Signature verified");
    plaintext.hidden = true;
    plaintext.textContent = "";
    error.hidden = false;
    error.textContent = "Decrypting and verifying message...";
    error.className = "meta";
    const retry = card.querySelector('[data-role="private-message-read-retry"]');
    if (retry && retry.addEventListener) {
      retry.hidden = true;
      retry.disabled = true;
      if (!retry.dataset.bound) {
        retry.dataset.bound = "1";
        retry.addEventListener("click", function () { readCard(mailbox, card, counterpartUsernameToken); });
      }
    }
    presentState(card, 'loading', '');

    let result;
    try {
      const message = suppliedMessage || cardMessages.get(card) || await mailboxMessage(mailbox, messageId, counterpartUsernameToken, cardCursors.get(card));
      if (String(message.message_id) !== messageId) throw new Error("Message identity mismatch.");
      cardMessages.set(card, message);
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
      if (!cardMessages.has(card)) mailboxRequests.delete(requestKey(mailbox, counterpartUsernameToken, cardCursors.get(card)));
      result = { kind: "load-failed", message: "This encrypted message or its sender keys could not be loaded. Try again." };
    }

    if (wasConnected && !card.isConnected) return result;
    setReaderResult(verification, error, plaintext, result);
    if (retry && retry.addEventListener) { retry.hidden = result.kind === "verified"; retry.disabled = false; }
    presentState(card, result.kind, result.message);
    return result;
  }

  function readCard(mailbox, card, counterpartUsernameToken, suppliedMessage) {
    if (!card) return Promise.resolve();
    if (cardRequests.has(card)) return cardRequests.get(card);
    const request = readCardOnce(mailbox, card, counterpartUsernameToken, suppliedMessage).finally(function () { cardRequests.delete(card); });
    cardRequests.set(card, request);
    return request;
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
    Promise.allSettled(Array.from(root.querySelectorAll("[data-private-message-id]")).map(function (card) {
      cardCursors.set(card, root.dataset.historyPageCursor || '');
      return readCard(mailbox, card, counterpartUsernameToken);
    })).then(function () {
      root.dataset.privateMessageReaderSettled = "1";
      if (root.dispatchEvent) root.dispatchEvent(new CustomEvent("private-message-reader-settled"));
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
