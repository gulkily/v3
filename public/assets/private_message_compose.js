(function () {
  "use strict";
  const draftPrefix = "forum_private_message_draft:";
  const attempts = new WeakMap();

  function storage() {
    try { return window.localStorage; } catch (error) { return null; }
  }
  function token(root, name) {
    const value = String(root && root.dataset && root.dataset[name] || "").trim().toLowerCase();
    if (!/^[a-z0-9][a-z0-9._-]{0,63}$/.test(value)) throw new Error("An approved sender and recipient are required.");
    return value;
  }
  function draftKey(root) {
    return draftPrefix + token(root, "senderUsernameToken") + ":" + token(root, "recipientUsernameToken");
  }
  function senderKey() {
    try { return String(storage().getItem("forum_pki_public_key") || ""); } catch (error) { return ""; }
  }
  function newMessageId() {
    return "private-" + (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now().toString(36) + "-" + Math.random().toString(36).slice(2));
  }
  function loadDraft(root) {
    try {
      const local = storage();
      const state = JSON.parse(local.getItem(draftKey(root)) || "null");
      if (state && typeof state.plaintext === "string" && state.senderKey === senderKey()) {
        if (state.attempt && (!/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/.test(state.attempt.messageId) || typeof state.attempt.encryptedEnvelope !== "string")) state.attempt = null;
        return state;
      }
      // Legacy drafts have no sender scope. Only adopt when the saved identity agrees.
      if (local.getItem("forum_pki_username") === token(root, "senderUsernameToken")) {
        const legacy = JSON.parse(local.getItem(draftPrefix + token(root, "recipientUsernameToken")) || "null");
        if (legacy && typeof legacy.plaintext === "string") return { plaintext: legacy.plaintext, version: newMessageId(), legacy: true };
      }
    } catch (error) { /* Storage may be unavailable; the live draft remains usable. */ }
    return { plaintext: "", version: newMessageId() };
  }
  function setFeedback(node, message, kind) {
    if (!node) return;
    node.textContent = message;
    node.className = "meta feedback feedback-" + kind;
    node.hidden = false;
  }
  async function ensureIdentity() {
    const identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureActionIdentity !== "function") throw new Error("Browser identity support is unavailable. Refresh and try again.");
    await identity.ensureActionIdentity(null, null, { verifyPublishedIdentity: true });
  }
  async function submit(root, messageId, plaintext, onPrepared) {
    const initialKey = senderKey();
    await ensureIdentity();
    if (initialKey !== senderKey()) throw new Error("Your identity changed. Restore it before sending.");
    let attempt = attempts.get(root);
    if (!attempt || attempt.messageId !== messageId) {
      const messaging = window.ForumPrivateMessages;
      if (!messaging || !messaging.prepareEnvelope) throw new Error("Private-message encryption is unavailable. Refresh and try again.");
      const prepared = await messaging.prepareEnvelope({ plaintext: plaintext,
        senderUsernameToken: token(root, "senderUsernameToken"), recipientUsernameToken: token(root, "recipientUsernameToken") });
      if (initialKey !== senderKey()) throw new Error("Your identity changed. Restore it before sending.");
      attempt = { messageId: messageId, encryptedEnvelope: prepared.encryptedEnvelope, senderKey: senderKey() };
      attempts.set(root, attempt);
      if (onPrepared) onPrepared(attempt);
    }
    if (attempt.senderKey !== senderKey()) throw new Error("Restore the original sending identity to check this attempt.");
    const response = await fetch("/api/private_messages", {
      method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ message_id: attempt.messageId, recipient_username_token: token(root, "recipientUsernameToken"), encrypted_envelope: attempt.encryptedEnvelope }),
    });
    const payload = await response.json();
    if (!response.ok || !payload || payload.status !== "ok") throw new Error(String(payload && payload.error || "Unable to confirm the private message."));
    const message = payload.message;
    if (!message || message.message_id !== attempt.messageId || message.sender_username_token !== token(root, "senderUsernameToken") || message.recipient_username_token !== token(root, "recipientUsernameToken") || !message.created_at || Number.isNaN(new Date(message.created_at).getTime())) {
      throw new Error("The delivery confirmation did not match this attempt.");
    }
    return message;
  }
  function bind(root) {
    if (!root || !root.dataset || root.dataset.privateMessageComposerBound === "1") return false;
    const form = root.querySelector("[data-private-message-form]");
    const field = form && form.querySelector('[name="plaintext"]');
    const button = form && form.querySelector('button[type="submit"]');
    const feedback = root.querySelector('[data-role="private-message-feedback"]');
    const retry = root.querySelector('[data-role="private-message-send-retry"]');
    if (!form || !field) return false;
    root.dataset.privateMessageComposerBound = "1";
    const state = loadDraft(root);
    const originalKey = senderKey();
    if (field.value === "") field.value = state.plaintext;
    let version = state.version || newMessageId();
    let pending = state.attempt || null;
    let submitting = false;
    let saved = true;
    if (pending) attempts.set(root, pending);
    function persist() {
      try {
        const local = storage();
        if (!local) throw new Error("Storage unavailable");
        if (field.value === "" && !pending) local.removeItem(draftKey(root));
        else local.setItem(draftKey(root), JSON.stringify({ plaintext: field.value, version: version, senderKey: originalKey, attempt: pending }));
        if (state.legacy) local.removeItem(draftPrefix + token(root, "recipientUsernameToken"));
        saved = true;
      } catch (error) { saved = false; }
      if (!saved) setFeedback(feedback, "Draft recovery is unavailable in this browser. Keep this page open until delivery is confirmed.", "error");
    }
    function recovery() {
      if (retry) { retry.hidden = !pending; retry.disabled = submitting; }
    }
    field.addEventListener("input", function () { version = newMessageId(); persist(); });
    if (state.legacy) setFeedback(feedback, "An older draft was restored. Check the conversation before sending: its previous delivery cannot be confirmed automatically.", "error");
    if (pending) setFeedback(feedback, "A previous send is unconfirmed. Check it before sending another message; your draft is retained.", "error");
    recovery();
    async function send(event, retryOnly) {
      event.preventDefault();
      if (submitting) return;
      if (senderKey() !== originalKey) { setFeedback(feedback, "Your identity changed. Restore it or reload before sending.", "error"); return; }
      if (pending && !retryOnly && pending.snapshotVersion !== version) {
        setFeedback(feedback, "Check the previous send before sending your edited draft.", "error"); return;
      }
      const plaintext = String(field.value || "");
      if (!pending && plaintext.trim() === "") { setFeedback(feedback, "A private message cannot be empty.", "error"); return; }
      const snapshotVersion = pending ? pending.snapshotVersion : version;
      const messageId = pending ? pending.messageId : newMessageId();
      submitting = true;
      if (button) button.disabled = true;
      recovery();
      persist();
      setFeedback(feedback, pending ? "Checking previous delivery..." : "Encrypting and sending private message...", "ok");
      try {
        const message = await submit(root, messageId, plaintext, function (attempt) {
          pending = Object.assign(attempt, { snapshotVersion: snapshotVersion });
          persist();
          recovery();
        });
        const encryptedEnvelope = pending.encryptedEnvelope;
        pending = null;
        attempts.delete(root);
        if (version === snapshotVersion) { field.value = ""; version = newMessageId(); }
        persist();
        setFeedback(feedback, saved ? "Private message sent." : "Private message sent. Your newer draft cannot be saved; keep this page open.", saved ? "ok" : "error");
        const successUrl = String(root.dataset.privateMessageSuccessUrl || "");
        const handled = root.dispatchEvent && !root.dispatchEvent(new CustomEvent('private-message-sent', {
          bubbles: true, cancelable: true, detail: { message: message, encryptedEnvelope: encryptedEnvelope },
        }));
        if (!handled && successUrl && saved && window.location && window.location.assign) window.location.assign(successUrl);
      } catch (error) {
        setFeedback(feedback, (error instanceof Error ? error.message : "Unable to confirm delivery.") + (pending ? " Delivery is unconfirmed; check the previous send before sending another." : "") + (!saved ? " Recovery cannot be saved; keep this page open." : ""), "error");
      } finally {
        submitting = false;
        if (button) button.disabled = false;
        recovery();
      }
    }
    form.addEventListener("submit", function (event) { return send(event, false); });
    if (retry) retry.addEventListener("click", function (event) { return send(event, true); });
    return true;
  }
  window.ForumPrivateMessageComposer = { bind: bind, submit: submit };
  document.addEventListener("DOMContentLoaded", function () { Array.from(document.querySelectorAll("[data-private-message-composer]")).forEach(bind); });
})();
