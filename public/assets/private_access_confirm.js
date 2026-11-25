(function () {
  "use strict";
  // Confirm on a waiting key, through ForumBrowserSigning. A failed submit keeps the signed request
  // so Retry repeats it exactly (the server treats a repeat of an accepted confirm as success).
  function bind(row) {
    const button = row.querySelector('[data-action="confirm-private-access"]');
    const feedback = row.querySelector('[data-role="private-access-feedback"]');
    if (!button) return;
    let signed = null;
    function say(message) { feedback.textContent = message; feedback.hidden = false; }
    button.addEventListener("click", async function () {
      button.disabled = true;
      try {
        const signing = window.ForumBrowserSigning;
        if (!signing || typeof signing.submitSignedPrivateAccessConfirmation !== "function") {
          throw new Error("Browser signing is unavailable. Refresh and retry.");
        }
        const result = await signing.submitSignedPrivateAccessConfirmation(row.dataset.targetIdentityId, { signed: signed });
        signed = result.signed || null;
        if (!result.ok) throw new Error(result.error);
        if (!result.hasAccess) throw new Error("The confirm was saved but this key still can't read messages. Ask an operator.");
        button.hidden = true;
        const notMe = row.querySelector('[data-role="not-me"]');
        if (notMe) notMe.hidden = true;
        say("Confirmed");
      } catch (error) {
        say(error.message || "Couldn't confirm this key.");
        button.textContent = signed ? "Retry" : "Confirm";
      } finally { button.disabled = false; }
    });
  }
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-private-access-row]").forEach(bind);
  });
})();
