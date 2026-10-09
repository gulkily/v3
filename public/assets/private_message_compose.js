(function () {
  "use strict";

  const draftPrefix = "forum_private_message_draft:";

  function storage() {
    try {
      return window.localStorage;
    } catch (error) {
      return null;
    }
  }

  function recipientToken(root) {
    const token = String(root && root.dataset && root.dataset.recipientUsernameToken || "").trim().toLowerCase();
    if (!/^[a-z0-9][a-z0-9._-]{0,63}$/.test(token)) {
      throw new Error("This profile cannot receive private messages.");
    }

    return token;
  }

  function senderToken(root) {
    const token = String(root && root.dataset && root.dataset.senderUsernameToken || "").trim().toLowerCase();
    if (!/^[a-z0-9][a-z0-9._-]{0,63}$/.test(token)) {
      throw new Error("Your approved identity is unavailable. Refresh and try again.");
    }

    return token;
  }

  function draftKey(root) {
    return draftPrefix + recipientToken(root);
  }

  function newMessageId() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return "private-" + window.crypto.randomUUID();
    }

    return "private-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 14);
  }

  function loadDraft(root) {
    const local = storage();
    if (!local) {
      return null;
    }

    try {
      const draft = JSON.parse(local.getItem(draftKey(root)) || "");
      if (!draft || typeof draft !== "object" || typeof draft.plaintext !== "string") {
        return null;
      }

      return {
        messageId: /^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/.test(String(draft.message_id || ""))
          ? String(draft.message_id)
          : newMessageId(),
        plaintext: draft.plaintext,
      };
    } catch (error) {
      return null;
    }
  }

  function saveDraft(root, messageId, plaintext) {
    const local = storage();
    if (!local) {
      return;
    }

    try {
      local.setItem(draftKey(root), JSON.stringify({ message_id: messageId, plaintext: String(plaintext || "") }));
    } catch (error) {
    }
  }

  function clearDraft(root) {
    const local = storage();
    if (!local) {
      return;
    }

    try {
      local.removeItem(draftKey(root));
    } catch (error) {
    }
  }

  function setFeedback(node, message, kind) {
    if (!node) {
      return;
    }

    node.textContent = message;
    node.className = "meta feedback feedback-" + kind;
    node.hidden = false;
  }

  async function ensureIdentity() {
    const identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureActionIdentity !== "function") {
      throw new Error("Browser identity support is unavailable. Refresh and try again.");
    }

    await identity.ensureActionIdentity(null, null, { verifyPublishedIdentity: true });
  }

  async function submit(root, messageId, plaintext) {
    const messaging = window.ForumPrivateMessages;
    if (!messaging || typeof messaging.prepareEnvelope !== "function") {
      throw new Error("Private-message encryption is unavailable. Refresh and try again.");
    }

    const recipientUsernameToken = recipientToken(root);
    const senderUsernameToken = senderToken(root);
    await ensureIdentity();
    const prepared = await messaging.prepareEnvelope({
      plaintext: plaintext,
      senderUsernameToken: senderUsernameToken,
      recipientUsernameToken: recipientUsernameToken,
    });
    const response = await fetch("/api/private_messages", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        message_id: messageId,
        recipient_username_token: recipientUsernameToken,
        encrypted_envelope: prepared.encryptedEnvelope,
      }),
    });
    const payload = await response.json();
    if (!response.ok || !payload || payload.status !== "ok") {
      throw new Error(String(payload && payload.error || "Unable to send the private message."));
    }

    return payload.message || {};
  }

  function bind(root) {
    if (!root || !root.dataset || root.dataset.privateMessageComposerBound === "1") {
      return false;
    }

    const form = root.querySelector("[data-private-message-form]");
    const plaintextField = form && form.querySelector('[name="plaintext"]');
    const submitButton = form && form.querySelector('button[type="submit"]');
    const feedback = root.querySelector('[data-role="private-message-feedback"]');
    if (!form || !plaintextField) {
      return false;
    }

    root.dataset.privateMessageComposerBound = "1";
    const priorDraft = loadDraft(root);
    let messageId = priorDraft ? priorDraft.messageId : newMessageId();
    if (priorDraft && plaintextField.value === "") {
      plaintextField.value = priorDraft.plaintext;
    }
    let submitting = false;

    plaintextField.addEventListener("input", function () {
      saveDraft(root, messageId, plaintextField.value);
    });

    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (submitting) {
        return;
      }

      const plaintext = String(plaintextField.value || "");
      if (plaintext.trim() === "") {
        setFeedback(feedback, "A private message cannot be empty.", "error");
        return;
      }

      submitting = true;
      if (submitButton) {
        submitButton.disabled = true;
      }
      saveDraft(root, messageId, plaintext);
      setFeedback(feedback, "Encrypting and sending private message...", "ok");

      try {
        await submit(root, messageId, plaintext);
        clearDraft(root);
        plaintextField.value = "";
        messageId = newMessageId();
        setFeedback(feedback, "Private message sent.", "ok");
      } catch (error) {
        setFeedback(feedback, error instanceof Error ? error.message : "Unable to send the private message.", "error");
      } finally {
        submitting = false;
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });

    return true;
  }

  window.ForumPrivateMessageComposer = {
    bind: bind,
    submit: submit,
  };

  document.addEventListener("DOMContentLoaded", function () {
    const roots = document.querySelectorAll("[data-private-message-composer]");
    Array.from(roots).forEach(bind);
  });
})();
