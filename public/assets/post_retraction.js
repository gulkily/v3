(function () {
  function setFeedback(node, message, kind) {
    if (!node) {
      return;
    }

    node.textContent = message;
    node.className = `meta retraction-feedback feedback-${kind}`;
    node.hidden = false;
  }

  function feedbackNodeFor(button) {
    const row = button.closest('[data-role="retraction-control"]') || button.parentElement;
    return row ? row.querySelector('[data-role="retraction-feedback"]') : null;
  }

  async function retractPost(button) {
    const signing = window.ForumBrowserSigning || null;
    const feedback = feedbackNodeFor(button);
    if (!signing || typeof signing.submitSignedRetraction !== "function") {
      setFeedback(feedback, "Browser signing is unavailable. Refresh this page and try again.", "error");
      return;
    }

    button.setAttribute("disabled", "disabled");
    setFeedback(feedback, "Signing and submitting your deletion...", "ok");

    try {
      const result = await signing.submitSignedRetraction(button.getAttribute("data-post-id") || "");
      if (!result || !result.ok) {
        setFeedback(feedback, result && result.error ? result.error : "Unable to delete this post.", "error");
        button.removeAttribute("disabled");
        return;
      }

      if (result.retractionStatus === "partial") {
        setFeedback(
          feedback,
          "Your deletion was recorded, but updating the site did not finish (" + (result.failedStep || "unknown step") + "). "
            + "Retry to finish removing it.",
          "error"
        );
        button.textContent = "Retry deletion";
        button.removeAttribute("disabled");
        return;
      }

      setFeedback(feedback, button.getAttribute("data-done") || "Post deleted.", "ok");
      window.setTimeout(function () {
        window.location.reload();
      }, 1500);
    } catch (error) {
      setFeedback(feedback, error instanceof Error ? error.message : "Unable to delete this post.", "error");
      button.removeAttribute("disabled");
    }
  }

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape" || !(event.target instanceof Element)) return;
    const menu = event.target.closest("details.post-more-actions");
    if (!menu || !menu.open) return;
    event.preventDefault();
    menu.open = false;
    menu.querySelector("summary").focus();
  });

  document.addEventListener("click", function (event) {
    const button = event.target instanceof Element ? event.target.closest('[data-action="retract-post"]') : null;
    if (!button || button.hasAttribute("disabled")) {
      return;
    }

    event.preventDefault();
    const confirmText = button.getAttribute("data-confirm") || "Delete this post?";
    if (!window.confirm(confirmText)) {
      return;
    }

    retractPost(button);
  });
})();
