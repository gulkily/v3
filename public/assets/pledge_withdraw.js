// The "Withdraw pledge" button on the pledger's own pledge. A withdrawal is an ordinary reply
// to the pledge whose body is the line "withdraw", so this script only signs and sends that
// reply through the same prepare and finalize calls the Outbox uses. On failure the button
// comes back with a message; pressing again is safe, because a second withdrawal changes
// nothing. Without JavaScript the form posts as any reply form does.
(function () {
  "use strict";

  function feedback(form, message) {
    var node = form.querySelector('[data-role="pledge-withdraw-feedback"]');
    if (!node) return;
    node.textContent = message;
    node.hidden = message === "";
  }

  function value(form, name) {
    var field = form.querySelector('input[name="' + name + '"]');
    return field ? field.value : "";
  }

  function send(form) {
    var signing = window.ForumBrowserSigning || null;
    if (!signing || typeof signing.prepareOutboxPost !== "function" || typeof signing.finalizeOutboxPost !== "function") {
      return Promise.resolve({ ok: false, error: "Signing unavailable. Refresh and try again." });
    }
    return signing.prepareOutboxPost("reply", {
      threadId: value(form, "thread_id"),
      parentId: value(form, "parent_id"),
      boardTags: value(form, "board_tags"),
      body: value(form, "body"),
    }).then(function (result) {
      if (!result || !result.ok) return { ok: false, error: (result && result.error) || "" };
      return signing.finalizeOutboxPost(result.fields, result.prepared);
    });
  }

  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('[data-role="pledge-withdraw"]')) return;
    event.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    if (button && button.disabled) return;
    if (button) button.disabled = true;
    feedback(form, "");
    send(form).catch(function (error) {
      return { ok: false, error: error && error.message ? error.message : "" };
    }).then(function (result) {
      if (result && result.ok) {
        window.location.reload();
        return;
      }
      if (button) button.disabled = false;
      feedback(form, "Withdrawal failed. Try again." + (result && result.error ? " " + result.error : ""));
    });
  });
})();
