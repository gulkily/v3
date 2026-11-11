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

  window.forumOutboxCompose = { replyItem: replyItem, threadItem: threadItem };

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
          var image = form.querySelector('[name="image"]');
          if ((window.ForumImageUpload && window.ForumImageUpload.has(form)) || (image && image.files && image.files.length)) throw new Error("Images cannot be saved to Outbox. Keep this draft open and publish online, or remove the image.");
          item = replyItem({
            threadId: form.querySelector('[name="thread_id"]').value,
            parentId: form.querySelector('[name="parent_id"]').value,
            body: form.querySelector('[name="body"]').value,
            bodyFormat: bodyFormatOf(form),
            theme: themeOf(form)
          });
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
          var image = form.querySelector('[name="image"]');
          if ((window.ForumImageUpload && window.ForumImageUpload.has(form)) || (image && image.files && image.files.length)) throw new Error("Images cannot be saved to Outbox. Keep this draft open and publish online, or remove the image.");
          item = threadItem({
            subject: form.querySelector('[name="subject"]').value,
            body: form.querySelector('[name="body"]').value,
            boardTags: form.querySelector('[name="board_tags"]').value,
            bodyFormat: bodyFormatOf(form),
            theme: themeOf(form)
          });
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
        }).catch(function (error) {
          button.disabled = false;
          status.textContent = error && error.message ? error.message : "Unable to save the thread draft.";
          status.hidden = false;
        });
      });
      form.appendChild(status);
    });
  }

  window.forumOutboxCompose.bind = bindComposeForms;
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindComposeForms);
  } else {
    bindComposeForms();
  }
})();
