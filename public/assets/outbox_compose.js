(function () {
  "use strict";

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
      payload: { threadId: threadId, parentId: parentId, body: body }
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
      payload: { subject: subject, body: body, boardTags: boardTags }
    });
  }

  window.forumOutboxCompose = { replyItem: replyItem, threadItem: threadItem };

  document.addEventListener("DOMContentLoaded", function () {
    if (!window.forumOutbox || !window.forumOutboxStorage) return;
    Array.from(document.querySelectorAll('[data-compose-form][data-compose-kind="reply"]')).forEach(function (form) {
      var actions = form.querySelector(".compose-form-actions");
      if (!actions) return;
      var button = form.querySelector('[data-action="save-compose-outbox"]');
      if (!button) return;
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
            body: form.querySelector('[name="body"]').value
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
      if (!button) return;
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
            boardTags: form.querySelector('[name="board_tags"]').value
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
  });
})();
