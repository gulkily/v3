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

  window.forumOutboxCompose = { replyItem: replyItem };

  document.addEventListener("DOMContentLoaded", function () {
    if (!window.forumOutbox || !window.forumOutboxStorage) return;
    Array.from(document.querySelectorAll('[data-compose-form][data-compose-kind="reply"]')).forEach(function (form) {
      var actions = form.querySelector(".compose-form-actions");
      if (!actions) return;
      var button = document.createElement("button");
      button.type = "button";
      button.textContent = "Save reply to Outbox";
      var status = document.createElement("p");
      status.className = "meta";
      status.setAttribute("data-role", "outbox-compose-status");
      status.hidden = true;
      button.addEventListener("click", function () {
        var item;
        try {
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
      actions.appendChild(button);
      form.appendChild(status);
    });
  });
})();
