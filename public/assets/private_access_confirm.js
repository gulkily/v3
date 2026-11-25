(function () {
  "use strict";
  // Confirm on a waiting key, through ForumBrowserSigning. A failed submit keeps the signed request
  // so Retry repeats it exactly (the server treats a repeat of an accepted confirm as success).
  // A confirmed key leaves the card at once, as an approved user leaves Users > Waiting.

  // The same two headings as templates/partials/private_access_waiting.php.
  function heading(count, accountName) {
    return count === 1
      ? "A new key for " + accountName + " wants to read your messages"
      : count + " keys for " + accountName + " want to read your messages";
  }

  function settle(card, row) {
    const code = row.querySelector(".private-access-code");
    const details = row.closest("details");
    row.remove();
    if (!card) return;
    const status = card.querySelector('[data-role="private-access-card-feedback"]');
    if (status) {
      status.textContent = "Confirmed" + (code && code.textContent.trim() ? " " + code.textContent.trim() : "") + ".";
      status.hidden = false;
    }
    if (details) {
      const left = details.querySelectorAll("[data-private-access-row]").length;
      const summary = details.querySelector("summary");
      if (left === 0) details.remove();
      else if (summary) summary.textContent = left + " more";
    }
    const remaining = card.querySelectorAll("[data-private-access-row]").length;
    const title = card.querySelector('[data-role="private-access-heading"]');
    if (remaining === 0) {
      // Only the status line stays.
      Array.from(card.children).forEach(function (child) {
        if (child !== status) child.hidden = true;
      });
    } else if (title && card.dataset.accountName) {
      title.textContent = heading(remaining, card.dataset.accountName);
    }
  }

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
        settle(row.closest("[data-private-access-waiting]"), row);
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
