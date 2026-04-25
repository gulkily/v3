(function () {
  function createTechnicalFeedbackToggle(node) {
    if (!node || typeof node.appendChild !== "function" || typeof document === "undefined" || typeof document.createElement !== "function") {
      return null;
    }

    const toggle = document.createElement("a");
    toggle.href = "#";
    toggle.textContent = "details";
    toggle.setAttribute("data-role", "thread-reaction-technical-toggle");
    toggle.hidden = true;

    const details = document.createElement("code");
    details.setAttribute("data-role", "thread-reaction-technical-details");
    details.hidden = true;
    details.style.whiteSpace = "pre-wrap";

    toggle.addEventListener("click", function (event) {
      event.preventDefault();
      const expanded = !details.hidden;
      details.hidden = expanded;
      toggle.textContent = expanded ? "details" : "hide";
    });

    node.appendChild(document.createTextNode(" "));
    node.appendChild(toggle);
    node.appendChild(document.createTextNode(" "));
    node.appendChild(details);

    return {
      toggle: toggle,
      details: details,
    };
  }

  function renderTechnicalFeedback(node, technicalDetails) {
    if (!node || typeof node.querySelector !== "function") {
      return;
    }

    let toggle = node.querySelector('[data-role="thread-reaction-technical-toggle"]');
    let details = node.querySelector('[data-role="thread-reaction-technical-details"]');
    if ((!toggle || !details) && technicalDetails !== "") {
      const created = createTechnicalFeedbackToggle(node);
      if (created) {
        toggle = created.toggle;
        details = created.details;
      }
    }

    if (!toggle || !details) {
      return;
    }

    if (technicalDetails === "") {
      toggle.hidden = true;
      toggle.textContent = "details";
      details.hidden = true;
      details.textContent = "";
      return;
    }

    toggle.hidden = false;
    toggle.textContent = "details";
    details.hidden = true;
    details.textContent = technicalDetails;
  }

  function setFeedback(node, message, kind) {
    const technicalDetails = arguments.length > 3 && arguments[3] && typeof arguments[3].technicalDetails === "string"
      ? arguments[3].technicalDetails.trim()
      : "";
    if (!node) {
      return;
    }

    node.textContent = message;
    node.setAttribute("data-kind", kind);
    node.hidden = false;
    renderTechnicalFeedback(node, technicalDetails);
  }

  function parseResponseValue(text, key) {
    const prefix = `${key}=`;
    const line = String(text)
      .split("\n")
      .find((item) => item.startsWith(prefix));

    return line ? line.slice(prefix.length) : "";
  }

  async function applyThreadTag(threadId, tag) {
    const response = await fetch("/api/apply_thread_tag", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({ thread_id: threadId, tag }).toString(),
    });

    return response.text();
  }

  async function ensureReactionIdentity(root, feedbackNode) {
    const helper = window.__forumBrowserIdentity;
    if (!helper || typeof helper.ensureReadyIdentity !== "function") {
      throw new Error("Identity setup is unavailable. Reload the page and try again.");
    }

    setFeedback(feedbackNode, "Preparing identity...", "ok");
    await helper.ensureReadyIdentity(root, feedbackNode);
  }

  function feedbackFromError(error, fallbackMessage) {
    if (error instanceof Error) {
      return {
        message: error.message,
        technicalDetails: typeof error.technicalDetails === "string" ? error.technicalDetails.trim() : "",
      };
    }

    return {
      message: fallbackMessage,
      technicalDetails: "",
    };
  }

  function bindThreadReactions(root) {
    const threadId = root.getAttribute("data-thread-id") || "";
    const scoreNode = root.querySelector('[data-role="thread-score"]');
    const feedbackNode = root.querySelector('[data-role="thread-reaction-feedback"]');

    root.addEventListener("click", async (event) => {
      const button = event.target instanceof Element
        ? event.target.closest('[data-action="apply-thread-tag"]')
        : null;
      if (!button || !(button instanceof HTMLButtonElement)) {
        return;
      }

      event.preventDefault();
      if (button.disabled || threadId === "") {
        return;
      }

      const tag = button.getAttribute("data-tag") || "";
      const appliedLabel = button.getAttribute("data-applied-label") || "Applied";

      button.disabled = true;

      try {
        await ensureReactionIdentity(root, feedbackNode);
        setFeedback(feedbackNode, "Saving tag...", "ok");
        const text = await applyThreadTag(threadId, tag);
        if (!text.includes("status=ok")) {
          const errorMessage = parseResponseValue(text, "error") || "Unable to apply tag.";
          throw new Error(errorMessage);
        }

        const scoreTotal = parseResponseValue(text, "score_total");
        const wroteRecord = parseResponseValue(text, "wrote_record") === "yes";
        const viewerIsApproved = parseResponseValue(text, "viewer_is_approved") === "yes";

        if (scoreNode && scoreTotal !== "") {
          scoreNode.textContent = `Score: ${scoreTotal}`;
        }

        button.textContent = appliedLabel;
        button.setAttribute("aria-pressed", "true");

        if (viewerIsApproved) {
          setFeedback(feedbackNode, wroteRecord ? "Liked." : "Already liked.", "ok");
          return;
        }

        setFeedback(feedbackNode, wroteRecord ? "Liked." : "Already liked.", "ok");
      } catch (error) {
        button.disabled = false;
        const feedback = feedbackFromError(error, "Unable to apply tag.");
        setFeedback(
          feedbackNode,
          feedback.message,
          "error",
          { technicalDetails: feedback.technicalDetails }
        );
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-thread-reactions-root]");
    if (root) {
      bindThreadReactions(root);
    }
  });
})();
