(function () {
  "use strict";

  // The composer's Markdown box travels with the draft; drafts without it leave the server default.
  function withFormat(payload, fields) {
    if (fields.bodyFormat === "plain" || fields.bodyFormat === "markdown") payload.bodyFormat = fields.bodyFormat;
    if (fields.theme) payload.theme = String(fields.theme);
    return payload;
  }

  function themeOf(form) {
    var choice = form.querySelector('[name="theme"]');
    return choice ? choice.value : "";
  }

  function bodyFormatOf(form) {
    var toggle = form.querySelector('input[type="checkbox"][name="body_format"]');
    if (!toggle) return "";
    return toggle.checked ? "markdown" : "plain";
  }

  function replyItem(fields) {
    var threadId = String(fields.threadId || "").trim();
    var parentId = String(fields.parentId || "").trim();
    var body = String(fields.body || "").trim();
    if (!threadId || !body) throw new Error("A reply needs a thread and body before it can be saved to the Outbox.");
    return window.forumOutbox.createItem({
      id: window.forumOutbox.createIntentId("reply"),
      action: "reply",
      state: "draft",
      target: { kind: "thread", id: threadId, parentId: parentId },
      summary: "Reply to thread " + threadId,
      payload: withFormat({ threadId: threadId, parentId: parentId, body: body }, fields)
    });
  }

  function threadItem(fields) {
    var subject = String(fields.subject || "").trim();
    var body = String(fields.body || "").trim();
    var boardTags = String(fields.boardTags || "general").trim() || "general";
    if (!body) throw new Error("A thread needs a body before it can be saved to the Outbox.");
    return window.forumOutbox.createItem({
      id: window.forumOutbox.createIntentId("thread"),
      action: "thread",
      state: "draft",
      target: { kind: "board" },
      summary: subject || "New post draft",
      payload: withFormat({ subject: subject, body: body, boardTags: boardTags }, fields)
    });
  }

  function hasImage(form) {
    var image = form.querySelector('[name="image"]');
    return Boolean((window.ForumImageUpload && window.ForumImageUpload.has(form)) || (image && image.files && image.files.length));
  }

  function fieldValue(form, name) {
    var field = form.querySelector('[name="' + name + '"]');
    return field ? field.value : "";
  }

  // The composer's current text as an Outbox draft item.
  function itemFromForm(form) {
    if (hasImage(form)) throw new Error("Images cannot be saved to Outbox. Keep this draft open and publish online, or remove the image.");
    if (form.dataset.composeKind === "reply") {
      return replyItem({
        threadId: fieldValue(form, "thread_id"),
        parentId: fieldValue(form, "parent_id"),
        body: fieldValue(form, "body"),
        bodyFormat: bodyFormatOf(form),
        theme: themeOf(form)
      });
    }
    return threadItem({
      subject: fieldValue(form, "subject"),
      body: fieldValue(form, "body"),
      boardTags: fieldValue(form, "board_tags"),
      bodyFormat: bodyFormatOf(form),
      theme: themeOf(form)
    });
  }

  // Post without a connection: keep the text in the Outbox, queued to send once the connection returns.
  function queueFromForm(form) {
    if (!window.forumOutbox || !window.forumOutboxStorage) {
      return Promise.reject(new Error("The Outbox is unavailable on this page."));
    }
    var item;
    try {
      item = window.forumOutbox.transition(itemFromForm(form), "queued");
    } catch (error) {
      return Promise.reject(error);
    }
    return window.forumOutboxStorage.save(item).then(function () { return item; });
  }

  window.forumOutboxCompose = { replyItem: replyItem, threadItem: threadItem, itemFromForm: itemFromForm, queueFromForm: queueFromForm };

  // A form is bound once, whether this script ran before or after the page finished parsing.
  function unbound(form) {
    if (form.dataset.outboxComposeBound === "true") return false;
    form.dataset.outboxComposeBound = "true";
    return true;
  }

  function bindComposeForms() {
    if (!window.forumOutbox || !window.forumOutboxStorage) return;
    Array.from(document.querySelectorAll('[data-compose-form][data-compose-kind="reply"]')).forEach(function (form) {
      var actions = form.querySelector(".compose-form-actions");
      if (!actions) return;
      var button = form.querySelector('[data-action="save-compose-outbox"]');
      if (!button || !unbound(form)) return;
      button.disabled = false;
      var status = document.createElement("p");
      status.className = "meta";
      status.setAttribute("data-role", "outbox-compose-status");
      status.hidden = true;
      button.addEventListener("click", function () {
        var item;
        try {
          item = itemFromForm(form);
        } catch (error) {
          status.textContent = error.message;
          status.hidden = false;
          return;
        }
        button.disabled = true;
        window.forumOutboxStorage.save(item).then(function () {
          status.textContent = "Reply draft saved to Tools → Outbox.";
          status.hidden = false;
          button.textContent = "Saved to Outbox";
          showUnsentReplies();
        }).catch(function (error) {
          button.disabled = false;
          status.textContent = error && error.message ? error.message : "Unable to save the reply draft.";
          status.hidden = false;
        });
      });
      form.appendChild(status);
    });
    Array.from(document.querySelectorAll('[data-compose-form][data-compose-kind="thread"]')).forEach(function (form) {
      var actions = form.querySelector(".compose-form-actions");
      if (!actions) return;
      var button = form.querySelector('[data-action="save-compose-outbox"]');
      if (!button || !unbound(form)) return;
      button.disabled = false;
      var status = document.createElement("p");
      status.className = "meta";
      status.setAttribute("data-role", "outbox-compose-status");
      status.hidden = true;
      button.addEventListener("click", function () {
        var item;
        try {
          item = itemFromForm(form);
        } catch (error) {
          status.textContent = error.message;
          status.hidden = false;
          return;
        }
        button.disabled = true;
        window.forumOutboxStorage.save(item).then(function () {
          status.textContent = "Thread draft saved to Tools → Outbox.";
          status.hidden = false;
          button.textContent = "Saved to Outbox";
          showUnsentReplies();
        }).catch(function (error) {
          button.disabled = false;
          status.textContent = error && error.message ? error.message : "Unable to save the thread draft.";
          status.hidden = false;
        });
      });
      form.appendChild(status);
    });
  }

  function isWaitingPost(item) {
    return (item.action === "reply" || item.action === "thread")
      && (item.state === "queued" || item.state === "waiting_for_connection" || item.state === "sending");
  }

  function textPreview(text) {
    var line = String(text || "").replace(/\s+/g, " ").trim();
    return line.length > 120 ? line.slice(0, 119) + "…" : line;
  }

  // A thread page lists its own replies that are still on this device, above its reply composer.
  function showUnsentReplies() {
    if (typeof document.querySelector !== "function") return Promise.resolve();
    var form = document.querySelector('[data-compose-form][data-compose-kind="reply"]');
    var threadField = form && form.querySelector('[name="thread_id"]');
    if (!threadField || !threadField.value || !window.forumOutboxStorage) return Promise.resolve();
    var threadId = threadField.value;
    var anchor = form.closest("[data-compose-root]") || form;
    return window.forumOutboxStorage.list().then(function (items) {
      var unsent = items.filter(function (item) {
        return item.action === "reply" && item.target && item.target.id === threadId
          && (isWaitingPost(item) || item.state === "draft" || item.state === "needs_attention" || item.state === "rejected" || item.state === "conflicted");
      }).reverse();
      var list = document.querySelector('[data-role="outbox-unsent-replies"]');
      if (!unsent.length) {
        if (list) list.remove();
        return;
      }
      if (!list) {
        list = document.createElement("section");
        list.className = "outbox-unsent";
        list.setAttribute("data-role", "outbox-unsent-replies");
        list.setAttribute("aria-label", "Replies saved on this device");
        anchor.parentNode.insertBefore(list, anchor);
      }
      list.textContent = "";
      unsent.forEach(function (item) {
        var entry = document.createElement("article");
        entry.className = "card outbox-unsent__item";
        var body = document.createElement("p");
        body.textContent = textPreview(item.payload && item.payload.body);
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = isWaitingPost(item)
          ? "Saved on this device, not sent. "
          : "Saved on this device as " + String(item.state).replace(/_/g, " ") + ". ";
        var link = document.createElement("a");
        link.href = "/tools/outbox/";
        link.textContent = "Outbox";
        meta.appendChild(link);
        entry.appendChild(body);
        entry.appendChild(meta);
        list.appendChild(entry);
      });
    }).catch(function () {});
  }

  function loadSigning() {
    if (window.ForumBrowserSigning && typeof window.ForumBrowserSigning.prepareOutboxPost === "function") return Promise.resolve();
    var lazy = window.ForumLazyComposeSigning;
    return lazy && typeof lazy.load === "function" ? lazy.load() : Promise.resolve();
  }

  // Once the connection is back, posts waiting in the Outbox go out from whatever page has a composer.
  // The sender's own lock keeps two tabs from sending the same item.
  function sendWaitingPosts() {
    if (typeof navigator !== "undefined" && navigator.onLine === false) return Promise.resolve();
    var sender = window.forumOutboxSender;
    if (!window.forumOutboxStorage || !sender || typeof sender.processQueuedOutbox !== "function") return Promise.resolve();
    return window.forumOutboxStorage.list().then(function (items) {
      if (!items.some(isWaitingPost)) return null;
      return loadSigning().then(function () { return sender.processQueuedOutbox(); });
    }).catch(function () {}).then(showUnsentReplies);
  }

  function start() {
    bindComposeForms();
    showUnsentReplies().then(sendWaitingPosts);
  }

  window.forumOutboxCompose.bind = bindComposeForms;
  window.forumOutboxCompose.showUnsentReplies = showUnsentReplies;
  window.forumOutboxCompose.sendWaitingPosts = sendWaitingPosts;
  if (typeof window.addEventListener === "function") window.addEventListener("online", sendWaitingPosts);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
