(function () {
  let actionTimingSequence = 0;

  function browserPerformance() {
    return typeof window !== "undefined" && window.performance && typeof window.performance.mark === "function"
      ? window.performance
      : null;
  }

  function startActionTiming(action) {
    const timing = {
      action: action,
      id: `${action}:${Date.now()}:${++actionTimingSequence}`,
      firstFeedbackMarked: false,
      points: {},
      serverTiming: {},
      errorKind: "",
    };
    markActionTiming(timing, "forum_action_start");
    return timing;
  }

  function markActionTiming(timing, name, extraDetail) {
    const perf = browserPerformance();
    if (!perf || !timing) {
      return;
    }

    if (typeof perf.now === "function") {
      timing.points[name] = perf.now();
    }

    const detail = Object.assign({
      action: timing.action,
      id: timing.id,
    }, extraDetail || {});

    try {
      perf.mark(name, { detail: detail });
    } catch (error) {
      try {
        perf.mark(name);
      } catch (ignored) {
      }
    }
  }

  function markFirstFeedback(timing) {
    if (!timing || timing.firstFeedbackMarked) {
      return;
    }

    timing.firstFeedbackMarked = true;
    markActionTiming(timing, "forum_first_feedback");
  }

  function completeActionTiming(timing, status) {
    markActionTiming(timing, "forum_action_complete", { status: status });
    emitActionTimingDebug(timing, status);
  }

  function timingDelta(timing, startName, endName) {
    if (!timing || !timing.points || typeof timing.points[startName] !== "number" || typeof timing.points[endName] !== "number") {
      return null;
    }

    return Math.max(0, Math.round((timing.points[endName] - timing.points[startName]) * 10) / 10);
  }

  function debugTimingEnabled() {
    try {
      if (typeof window !== "undefined" && window.location && window.location.search) {
        const params = new URLSearchParams(window.location.search);
        if (params.get("debug_timing") === "1") {
          return true;
        }
      }
    } catch (error) {
    }

    try {
      return typeof window !== "undefined"
        && window.localStorage
        && window.localStorage.getItem("forum_debug_timing") === "1";
    } catch (error) {
      return false;
    }
  }

  function emitActionTimingDebug(timing, status) {
    if (!debugTimingEnabled() || typeof console === "undefined" || typeof console.info !== "function") {
      return;
    }

    console.info("[forum timing]", {
      action: timing.action,
      status: status,
      duration_ms: timingDelta(timing, "forum_action_start", "forum_action_complete"),
      identity_ms: timingDelta(timing, "forum_identity_start", "forum_identity_ready"),
      network_ms: timingDelta(timing, "forum_fetch_start", "forum_response_received"),
      server_timing: timing.serverTiming || {},
      error_kind: timing.errorKind || "",
    });
  }

  function parseServerTimingHeader(value) {
    const metrics = {};
    String(value || "").split(",").forEach(function (entry) {
      const parts = entry.trim().split(";");
      const name = (parts.shift() || "").trim();
      if (!/^[a-z_][a-z0-9_]*$/.test(name)) {
        return;
      }

      const durationPart = parts.find(function (part) {
        return part.trim().startsWith("dur=");
      });
      if (!durationPart) {
        return;
      }

      const duration = Number(durationPart.trim().slice(4));
      if (!Number.isFinite(duration)) {
        return;
      }

      metrics[name] = duration;
    });

    return metrics;
  }

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

  function captureButtonState(button) {
    return {
      disabled: button.disabled,
      textContent: button.textContent,
      ariaPressed: button.getAttribute("aria-pressed"),
    };
  }

  function restoreButtonState(button, state) {
    button.disabled = state.disabled;
    button.textContent = state.textContent;
    if (state.ariaPressed === null || state.ariaPressed === undefined) {
      if (typeof button.removeAttribute === "function") {
        button.removeAttribute("aria-pressed");
      }
    } else {
      button.setAttribute("aria-pressed", state.ariaPressed);
    }
  }

  function setPendingReactionButton(button, appliedLabel) {
    button.disabled = true;
    button.textContent = appliedLabel;
    button.setAttribute("aria-pressed", "true");
  }

  function setConfirmedReactionButton(button, appliedLabel) {
    button.textContent = appliedLabel;
    button.setAttribute("aria-pressed", "true");
  }

  function parsedThreadScore(scoreNode) {
    if (!scoreNode) {
      return null;
    }

    const match = String(scoreNode.textContent || "").match(/^Score:\s*(-?\d+)$/);
    return match ? Number(match[1]) : null;
  }

  function setThreadScore(scoreNode, scoreTotal) {
    if (scoreNode && scoreTotal !== "") {
      scoreNode.textContent = `Score: ${scoreTotal}`;
    }
  }

  function setOptimisticThreadReactionState(button, scoreNode, tag, appliedLabel, previousState) {
    setPendingReactionButton(button, appliedLabel);

    if (tag !== "like" || previousState.button.ariaPressed === "true") {
      return;
    }

    const score = parsedThreadScore(scoreNode);
    if (score !== null && scoreNode) {
      scoreNode.textContent = `Score: ${score + 1}`;
    }
  }

  function captureThreadReactionState(button, scoreNode) {
    return {
      button: captureButtonState(button),
      scoreText: scoreNode ? scoreNode.textContent : null,
    };
  }

  function restoreThreadReactionState(button, scoreNode, state) {
    restoreButtonState(button, state.button);
    if (scoreNode && state.scoreText !== null) {
      scoreNode.textContent = state.scoreText;
    }
  }

  function capturePostReactionState(root, button) {
    return {
      button: captureButtonState(button),
      hidden: root.hidden,
    };
  }

  function setOptimisticPostReactionState(button, appliedLabel) {
    setPendingReactionButton(button, appliedLabel);
  }

  function restorePostReactionState(root, button, state) {
    restoreButtonState(button, state.button);
    root.hidden = state.hidden;
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

    const text = await response.text();
    return {
      text: text,
      serverTiming: parseServerTimingHeader(response.headers && typeof response.headers.get === "function"
        ? response.headers.get("Server-Timing")
        : ""),
    };
  }

  async function applyPostTag(postId, tag) {
    const response = await fetch("/api/apply_post_tag", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({ post_id: postId, tag }).toString(),
    });

    const text = await response.text();
    return {
      text: text,
      serverTiming: parseServerTimingHeader(response.headers && typeof response.headers.get === "function"
        ? response.headers.get("Server-Timing")
        : ""),
    };
  }

  async function ensureReactionIdentity(root, feedbackNode, timing) {
    const helper = window.__forumBrowserIdentity;
    if (!helper || typeof helper.ensureReadyIdentity !== "function") {
      throw new Error("Identity setup is unavailable. Reload the page and try again.");
    }

    markActionTiming(timing, "forum_identity_start");
    setFeedback(feedbackNode, "Preparing identity...", "ok");
    markFirstFeedback(timing);
    await helper.ensureReadyIdentity(root, feedbackNode);
    markActionTiming(timing, "forum_identity_ready");
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

  function postReactionMessage(appliedLabel, wroteRecord) {
    const label = String(appliedLabel || "Applied").trim() || "Applied";
    const lowerLabel = label.toLowerCase();
    return wroteRecord ? `${label}.` : `Already ${lowerLabel}.`;
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
      const timing = startActionTiming("apply_thread_tag");
      const previousState = captureThreadReactionState(button, scoreNode);

      button.disabled = true;

      try {
        await ensureReactionIdentity(root, feedbackNode, timing);
        setOptimisticThreadReactionState(button, scoreNode, tag, appliedLabel, previousState);
        setFeedback(feedbackNode, "Saving tag...", "ok");
        markFirstFeedback(timing);
        markActionTiming(timing, "forum_fetch_start");
        const result = await applyThreadTag(threadId, tag);
        markActionTiming(timing, "forum_response_received");
        const text = result.text;
        timing.serverTiming = result.serverTiming;
        if (!text.includes("status=ok")) {
          const errorMessage = parseResponseValue(text, "error") || "Unable to apply tag.";
          throw new Error(errorMessage);
        }

        const scoreTotal = parseResponseValue(text, "score_total");
        const wroteRecord = parseResponseValue(text, "wrote_record") === "yes";
        const viewerIsApproved = parseResponseValue(text, "viewer_is_approved") === "yes";

        setThreadScore(scoreNode, scoreTotal);

        setConfirmedReactionButton(button, appliedLabel);

        markActionTiming(timing, "forum_reconcile_complete");
        completeActionTiming(timing, "ok");
        if (viewerIsApproved) {
          setFeedback(feedbackNode, wroteRecord ? "Liked." : "Already liked.", "ok");
          return;
        }

        setFeedback(feedbackNode, wroteRecord ? "Liked." : "Already liked.", "ok");
      } catch (error) {
        restoreThreadReactionState(button, scoreNode, previousState);
        timing.errorKind = error instanceof Error && error.name ? error.name : "error";
        const feedback = feedbackFromError(error, "Unable to apply tag.");
        setFeedback(
          feedbackNode,
          feedback.message,
          "error",
          { technicalDetails: feedback.technicalDetails }
        );
        completeActionTiming(timing, "error");
      }
    });
  }

  function bindPostReactions(root) {
    const postId = root.getAttribute("data-post-id") || "";
    const feedbackNode = root.querySelector('[data-role="post-reaction-feedback"]');

    root.addEventListener("click", async (event) => {
      const button = event.target instanceof Element
        ? event.target.closest('[data-action="apply-post-tag"]')
        : null;
      if (!button || !(button instanceof HTMLButtonElement)) {
        return;
      }

      event.preventDefault();
      if (button.disabled || postId === "") {
        return;
      }

      const tag = button.getAttribute("data-tag") || "";
      const appliedLabel = button.getAttribute("data-applied-label") || "Applied";
      const timing = startActionTiming("apply_post_tag");
      const previousState = capturePostReactionState(root, button);

      button.disabled = true;

      try {
        await ensureReactionIdentity(root, feedbackNode, timing);
        setOptimisticPostReactionState(button, appliedLabel);
        setFeedback(feedbackNode, "Saving tag...", "ok");
        markFirstFeedback(timing);
        markActionTiming(timing, "forum_fetch_start");
        const result = await applyPostTag(postId, tag);
        markActionTiming(timing, "forum_response_received");
        const text = result.text;
        timing.serverTiming = result.serverTiming;
        if (!text.includes("status=ok")) {
          const errorMessage = parseResponseValue(text, "error") || "Unable to apply tag.";
          throw new Error(errorMessage);
        }

        const wroteRecord = parseResponseValue(text, "wrote_record") === "yes";
        const isHidden = parseResponseValue(text, "is_hidden") === "yes";

        setConfirmedReactionButton(button, appliedLabel);

        if (isHidden) {
          root.hidden = true;
          markActionTiming(timing, "forum_reconcile_complete");
          completeActionTiming(timing, "ok");
          return;
        }

        setFeedback(feedbackNode, postReactionMessage(appliedLabel, wroteRecord), "ok");
        markActionTiming(timing, "forum_reconcile_complete");
        completeActionTiming(timing, "ok");
      } catch (error) {
        restorePostReactionState(root, button, previousState);
        timing.errorKind = error instanceof Error && error.name ? error.name : "error";
        const feedback = feedbackFromError(error, "Unable to apply tag.");
        setFeedback(
          feedbackNode,
          feedback.message,
          "error",
          { technicalDetails: feedback.technicalDetails }
        );
        completeActionTiming(timing, "error");
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-thread-reactions-root]");
    if (root) {
      bindThreadReactions(root);
    }

    if (typeof document.querySelectorAll === "function") {
      document.querySelectorAll(".post-card[data-post-id]").forEach((postRoot) => {
        bindPostReactions(postRoot);
      });
    }
  });
})();
