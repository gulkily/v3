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
      setFeedback(feedback, "Signing unavailable. Refresh and try again.", "error");
      return;
    }

    const control = button.closest('[data-role="retraction-control"]');
    const retry = control ? control.querySelector('[data-role="retraction-retry"]') : null;
    const menu = control ? control.querySelector('details.post-more-actions') : null;
    if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
    const buttons = control && control.querySelectorAll ? Array.from(control.querySelectorAll('[data-action="retract-post"]')) : [button];
    buttons.forEach(function (action) { action.setAttribute("disabled", "disabled"); });
    if (feedback) feedback.hidden = true;
    const progressTimer = window.setTimeout(function () {
      setFeedback(feedback, "Retracting…", "ok");
    }, 300);
    function stopProgress() { window.clearTimeout(progressTimer); }
    function reportFailure(detail) {
      setFeedback(feedback, "Retraction failed. Try again.", "error");
      const details = control ? control.querySelector('[data-role="retraction-details"]') : null;
      if (details) {
        details.querySelector('p').textContent = detail || "Unable to retract this post.";
        details.hidden = false;
      }
    }
    const details = control ? control.querySelector('[data-role="retraction-details"]') : null;
    if (details) { details.hidden = true; details.open = false; }

    try {
      const result = await signing.submitSignedRetraction(button.getAttribute("data-post-id") || "");
      stopProgress();
      if (!result || !result.ok) {
        reportFailure(result && result.error);
        buttons.forEach(function (action) { action.removeAttribute("disabled"); });
        return;
      }

      if (result.retractionStatus === "partial") {
        setFeedback(
          feedback,
          "Retracted. Site update incomplete.",
          "error"
        );
        if (retry) retry.hidden = false;
        else button.textContent = "Retry retraction";
        buttons.forEach(function (action) { action.removeAttribute("disabled"); });
        return;
      }

      if (result.cleanupStatus === "failed") {
        setFeedback(feedback, "Retracted. Cleanup could not be queued.", "error");
        if (retry) retry.hidden = false;
        else button.textContent = "Retry retraction";
        buttons.forEach(function (action) { action.removeAttribute("disabled"); });
        return;
      }

      if (retry) retry.hidden = true;
      if (result.cleanupStatus === "pending") {
        try { window.sessionStorage.setItem("forum-retraction-cleanup", button.getAttribute("data-post-id") || ""); } catch (_) {}
      }
      if (result.labelCleanupStatus === "unresolved") {
        try { window.sessionStorage.setItem("forum-retraction-label-review", button.getAttribute("data-post-id") || ""); } catch (_) {}
      }
      window.location.reload();
    } catch (error) {
      stopProgress();
      reportFailure(error instanceof Error ? error.message : null);
      buttons.forEach(function (action) { action.removeAttribute("disabled"); });
    }
  }

  // One-time acknowledgment survives the reload that removes the retracted prose.
  // It describes the accepted queue request, not current cleanup completion.
  try {
    const postId = window.sessionStorage.getItem("forum-retraction-cleanup");
    if (postId && document.querySelectorAll) {
      for (const notice of document.querySelectorAll("[data-retracted-post-id]")) {
        if (notice.getAttribute("data-retracted-post-id") !== postId) continue;
        const status = document.createElement("p");
        status.className = "meta";
        status.setAttribute("role", "status");
        status.textContent = "Local cleanup queued.";
        notice.appendChild(status);
        window.sessionStorage.removeItem("forum-retraction-cleanup");
        break;
      }
    }
  } catch (_) {}

  try {
    const reviewId = window.sessionStorage.getItem("forum-retraction-label-review");
    if (reviewId && document.querySelectorAll) {
      for (const notice of document.querySelectorAll("[data-retracted-post-id]")) {
        if (notice.getAttribute("data-retracted-post-id") !== reviewId) continue;
        const warning = document.createElement("p");
        warning.className = "meta";
        warning.setAttribute("role", "status");
        warning.textContent = "Some labels need operator review. Ask an operator to inspect this post's label ownership.";
        notice.appendChild(warning);
        window.sessionStorage.removeItem("forum-retraction-label-review");
        break;
      }
    }
  } catch (_) {}

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
    const confirmText = button.getAttribute("data-confirm") || "Retract this post?";
    if (!window.confirm(confirmText)) {
      return;
    }

    retractPost(button);
  });
})();
