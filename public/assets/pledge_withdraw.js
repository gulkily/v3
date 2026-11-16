// The pledge buttons: "Withdraw pledge", "Mark delivered" and "Mark filled". Each is an ordinary
// reply to the pledge whose body is one line ("withdraw" or "filled"), so this script only signs
// and sends that reply through the same prepare and finalize calls the Outbox uses. On failure
// the button comes back with the form's message; pressing again is safe, because a second
// withdrawal changes nothing and a fill on a filled pledge does not count. Without JavaScript
// the form posts as any reply form does.
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
    if (!form || !form.matches || !form.matches('[data-role="pledge-withdraw"], [data-role="pledge-line"]')) return;
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
      feedback(form, (form.getAttribute("data-failure") || "Withdrawal failed. Try again.") + (result && result.error ? " " + result.error : ""));
    });
  });
})();
