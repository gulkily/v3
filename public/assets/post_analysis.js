(function () {
  function requestIdle(callback) {
    if (typeof window.requestIdleCallback === "function") {
      window.requestIdleCallback(callback, { timeout: 2000 });
      return;
    }

    window.setTimeout(callback, 0);
  }

  const generationStartedPostIds = window.__forumAgentReplyGenerationStartedPostIds instanceof Set
    ? window.__forumAgentReplyGenerationStartedPostIds
    : new Set();
  window.__forumAgentReplyGenerationStartedPostIds = generationStartedPostIds;

  const codexHandoffStartedPostIds = window.__forumCodexHandoffStartedPostIds instanceof Set
    ? window.__forumCodexHandoffStartedPostIds
    : new Set();
  window.__forumCodexHandoffStartedPostIds = codexHandoffStartedPostIds;

  function markGenerationStarted(postId) {
    if (generationStartedPostIds.has(postId)) {
      return false;
    }

    generationStartedPostIds.add(postId);
    return true;
  }

  function markCodexHandoffStarted(postId) {
    if (codexHandoffStartedPostIds.has(postId)) {
      return false;
    }

    codexHandoffStartedPostIds.add(postId);
    return true;
  }

  async function analyzePost(postId) {
    const response = await fetch("/api/analyze_post", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json",
      },
      body: new URLSearchParams({ post_id: postId }).toString(),
    });

    return response.json();
  }

  async function generateAgentReply(postId) {
    const response = await fetch("/api/generate_agent_reply", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json",
      },
      body: new URLSearchParams({ post_id: postId }).toString(),
    });

    return response.json();
  }

  async function requestCodexHandoff(postId) {
    const response = await fetch("/api/codex_handoff", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json",
      },
      body: new URLSearchParams({ post_id: postId }).toString(),
    });

    return response.json();
  }

  async function decideCodexHandoff(handoffId, decision) {
    const response = await fetch("/api/codex_handoff_approval", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json",
      },
      body: new URLSearchParams({ handoff_id: handoffId, decision: decision }).toString(),
    });

    return response.json();
  }

  function selectorEscape(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }

    return value.replace(/["\\]/g, "\\$&");
  }

  function feedbackForPost(postId) {
    const card = document.querySelector('[data-post-id="' + selectorEscape(postId) + '"]');
    if (!card) {
      return null;
    }

    return card.querySelector('[data-role="agent-reply-feedback"]');
  }

  function codexFeedbackForPost(postId) {
    const card = document.querySelector('[data-post-id="' + selectorEscape(postId) + '"]');
    if (!card) {
      return null;
    }

    return card.querySelector('[data-role="codex-handoff-feedback"]');
  }

  function cardForPost(postId) {
    return document.querySelector('[data-post-id="' + selectorEscape(postId) + '"]');
  }

  function setFeedback(node, text) {
    if (!node) {
      return;
    }

    node.hidden = false;
    node.textContent = "";
    node.textContent = text;
  }

  function setFeedbackLink(node, text, href, label) {
    if (!node) {
      return;
    }

    const link = document.createElement("a");
    link.href = href;
    link.textContent = label;

    node.hidden = false;
    node.textContent = "";
    node.appendChild(document.createTextNode(text + " "));
    node.appendChild(link);
  }

  function appendTextElement(parent, tagName, label, value) {
    if (!value) {
      return;
    }

    const element = document.createElement(tagName);
    if (label) {
      const strong = document.createElement("strong");
      strong.textContent = label + ":";
      element.appendChild(strong);
      element.appendChild(document.createTextNode(" "));
    }
    element.appendChild(document.createTextNode(value));
    parent.appendChild(element);
  }

  function agentReplyAnchorUrl(agentPostId) {
    const url = new URL(window.location.href);
    url.searchParams.set("created_post_id", agentPostId);
    url.searchParams.delete("__v");
    url.hash = "post-" + agentPostId;
    return url.pathname + url.search + url.hash;
  }

  function skippedReason(result, analysis) {
    if (result.reason && analysis && analysis.viewer_can_see_analysis) {
      return ": " + result.reason.replace(/_/g, " ");
    }

    if (result.failure_code && analysis && analysis.viewer_can_see_analysis) {
      return ": " + result.failure_code.replace(/_/g, " ");
    }

    return "";
  }

  function agentReplyResultFromAnalysis(analysis) {
    if (!analysis || analysis.status !== "ok" || !Object.prototype.hasOwnProperty.call(analysis, "agent_reply_generation_status")) {
      return null;
    }

    return {
      status: "ok",
      generation_status: analysis.agent_reply_generation_status,
      posted: analysis.agent_reply_posted,
      agent_post_id: analysis.agent_reply_post_id,
      agent_post_url: analysis.agent_reply_post_url,
      reason: analysis.agent_reply_reason,
      failure_code: analysis.agent_reply_failure_code,
    };
  }

  function applyGenerationResult(node, result, analysis) {
    if (!result || result.status !== "ok") {
      return false;
    }

    if (result.generation_status === "generated" && result.agent_post_id) {
      setFeedbackLink(
        node,
        "Agent analysis and reply added below this post.",
        agentReplyAnchorUrl(result.agent_post_id),
        "View agent reply."
      );
      return true;
    }

    if (result.generation_status === "already_posted" && result.agent_post_id) {
      setFeedbackLink(
        node,
        "Agent analysis and reply already exists below this post.",
        agentReplyAnchorUrl(result.agent_post_id),
        "View agent reply."
      );
      return true;
    }

    if (result.generation_status === "requested") {
      setFeedback(node, "Agent reply requested.");
      return true;
    }

    if (result.generation_status === "not_recommended") {
      if (result.reason === "config_disabled") {
        return false;
      }

      setFeedback(node, "Agent reply skipped" + skippedReason(result, analysis) + ".", "");
      return true;
    }

    if (result.generation_status === "analysis_required") {
      setFeedback(node, "Agent reply skipped: analysis required.", "");
      return true;
    }

    if (result.generation_status === "in_progress") {
      setFeedback(node, "Agent reply request in progress.");
      return true;
    }

    if (result.generation_status === "failed") {
      setFeedback(node, "Agent reply request failed.", "");
      return true;
    }

    setFeedback(node, "Agent reply skipped.", "");
    return true;
  }

  function codexHandoffLabel(status) {
    if (status === "draft_ready") {
      return "Ready for approval";
    }
    if (status === "approved") {
      return "Approved";
    }
    if (status === "rejected") {
      return "Rejected";
    }
    if (status === "running") {
      return "Running";
    }
    if (status === "completed") {
      return "Completed";
    }
    if (status === "failed") {
      return "Failed";
    }
    return "Requested";
  }

  function codexHandoffFeedback(status) {
    if (status === "requested") {
      return "Codex handoff requested.";
    }
    if (status === "draft_ready") {
      return "Codex handoff ready for approval.";
    }
    if (status === "approved") {
      return "Codex handoff approved.";
    }
    if (status === "rejected") {
      return "Codex handoff rejected.";
    }
    if (status === "running") {
      return "Codex handoff running.";
    }
    if (status === "completed") {
      return "Codex handoff completed.";
    }
    if (status === "failed") {
      return "Codex handoff failed.";
    }
    return "Codex handoff updated.";
  }

  function createCodexHandoffPreview(result) {
    const details = document.createElement("details");
    details.className = "codex-handoff-preview";
    details.setAttribute("data-role", "codex-handoff-preview");
    details.setAttribute("data-handoff-id", result.handoff_id || "");
    details.setAttribute("data-handoff-status", result.handoff_status || "");
    if (result.handoff_status === "draft_ready") {
      details.open = true;
    }

    const summary = document.createElement("summary");
    summary.textContent = "Codex handoff: " + codexHandoffLabel(result.handoff_status || "");
    details.appendChild(summary);

    const stack = document.createElement("div");
    stack.className = "stack";
    appendTextElement(stack, "p", "User story", result.user_story || "");
    appendTextElement(stack, "p", "Confidence", result.confidence_summary || "");
    if (result.fdp_step1) {
      const pre = document.createElement("pre");
      pre.className = "codex-handoff-draft";
      pre.textContent = result.fdp_step1;
      stack.appendChild(pre);
    }

    if (result.handoff_status === "draft_ready") {
      const actions = document.createElement("div");
      actions.className = "button-row button-row-natural codex-handoff-actions";

      const approve = document.createElement("button");
      approve.type = "button";
      approve.className = "thread-reaction-button";
      approve.setAttribute("data-action", "approve-codex-handoff");
      approve.setAttribute("data-handoff-id", result.handoff_id || "");
      approve.textContent = "Approve handoff";
      actions.appendChild(approve);

      const reject = document.createElement("button");
      reject.type = "button";
      reject.className = "thread-reaction-button";
      reject.setAttribute("data-action", "reject-codex-handoff");
      reject.setAttribute("data-handoff-id", result.handoff_id || "");
      reject.textContent = "Reject handoff";
      actions.appendChild(reject);

      stack.appendChild(actions);
    }

    details.appendChild(stack);
    return details;
  }

  function applyCodexHandoffResult(card, result) {
    if (!card || !result || result.status !== "ok") {
      return false;
    }

    const status = result.handoff_status || "";
    const feedback = card.querySelector('[data-role="codex-handoff-feedback"]');
    setFeedback(feedback, codexHandoffFeedback(status));

    const requestButton = card.querySelector('[data-action="request-codex-handoff"]');
    if (requestButton) {
      requestButton.hidden = true;
    }

    const existingPreview = card.querySelector('[data-role="codex-handoff-preview"]');
    if (existingPreview) {
      existingPreview.remove();
    }

    const actions = card.querySelector(".post-card-actions");
    if (actions) {
      actions.appendChild(createCodexHandoffPreview(result));
      bindCodexHandoffButtons();
    }

    return true;
  }

  function bindCodexHandoffButtons() {
    document.querySelectorAll('[data-action="request-codex-handoff"]').forEach(function (button) {
      if (button.getAttribute("data-codex-handoff-bound") === "1") {
        return;
      }

      button.setAttribute("data-codex-handoff-bound", "1");
      button.addEventListener("click", async function () {
        const postId = button.getAttribute("data-post-id") || "";
        if (postId === "" || !markCodexHandoffStarted(postId)) {
          return;
        }

        const originalText = button.textContent;
        const card = cardForPost(postId);
        const feedback = codexFeedbackForPost(postId);
        button.disabled = true;
        button.textContent = "Preparing...";
        setFeedback(feedback, "Preparing Codex handoff...");

        try {
          const result = await requestCodexHandoff(postId);
          if (applyCodexHandoffResult(card, result)) {
            return;
          }
        } catch (error) {
        }

        codexHandoffStartedPostIds.delete(postId);
        button.disabled = false;
        button.textContent = originalText;
        setFeedback(feedback, "Codex handoff request failed.");
      });
    });

    document.querySelectorAll('[data-action="approve-codex-handoff"], [data-action="reject-codex-handoff"]').forEach(function (button) {
      if (button.getAttribute("data-codex-handoff-decision-bound") === "1") {
        return;
      }

      button.setAttribute("data-codex-handoff-decision-bound", "1");
      button.addEventListener("click", async function () {
        const handoffId = button.getAttribute("data-handoff-id") || "";
        const decision = button.getAttribute("data-action") === "approve-codex-handoff" ? "approve" : "reject";
        const preview = button.closest('[data-role="codex-handoff-preview"]');
        const card = button.closest("[data-post-id]");
        const feedback = card ? card.querySelector('[data-role="codex-handoff-feedback"]') : null;
        if (handoffId === "" || !preview || !card) {
          return;
        }

        preview.querySelectorAll("button").forEach(function (actionButton) {
          actionButton.disabled = true;
        });
        setFeedback(feedback, decision === "approve" ? "Approving Codex handoff..." : "Rejecting Codex handoff...");

        try {
          const result = await decideCodexHandoff(handoffId, decision);
          if (applyCodexHandoffResult(card, result)) {
            return;
          }
        } catch (error) {
        }

        preview.querySelectorAll("button").forEach(function (actionButton) {
          actionButton.disabled = false;
        });
        setFeedback(feedback, "Codex handoff update failed.");
      });
    });
  }

  function bindAgentReplyRequestButtons() {
    document.querySelectorAll('[data-action="request-agent-reply"]').forEach(function (button) {
      if (button.getAttribute("data-agent-reply-request-bound") === "1") {
        return;
      }

      button.setAttribute("data-agent-reply-request-bound", "1");
      button.addEventListener("click", async function () {
        const postId = button.getAttribute("data-post-id") || "";
        if (postId === "" || !markGenerationStarted(postId)) {
          return;
        }

        const originalText = button.textContent;
        const feedback = feedbackForPost(postId);
        button.disabled = true;
        button.textContent = "Requesting...";
        setFeedback(feedback, "Requesting agent reply...");

        try {
          const result = await generateAgentReply(postId);
          const handled = applyGenerationResult(feedback, result, null);
          if (handled && result && result.status === "ok") {
            button.hidden = true;
            return;
          }
        } catch (error) {
        }

        generationStartedPostIds.delete(postId);
        button.disabled = false;
        button.textContent = originalText;
        setFeedback(feedback, "Agent reply request failed.");
      });
    });
  }

  function boot() {
    bindAgentReplyRequestButtons();
    bindCodexHandoffButtons();

    const root = document.querySelector("[data-created-post-id]");
    if (!root) {
      return;
    }

    const postId = root.getAttribute("data-created-post-id") || "";
    if (postId === "") {
      return;
    }

    const card = cardForPost(postId);
    if (!card) {
      return;
    }

    const work = card.getAttribute("data-agent-reply-work") || "none";
    if (work !== "analyze" && work !== "publish") {
      return;
    }

    const feedback = feedbackForPost(postId);
    if (!markGenerationStarted(postId)) {
      return;
    }

    requestIdle(async function () {
      try {
        let analysis = null;
        if (work === "analyze") {
          analysis = await analyzePost(postId);
          if (!analysis || analysis.status !== "ok") {
            return;
          }

          const result = agentReplyResultFromAnalysis(analysis);
          applyGenerationResult(feedback, result, analysis);
          return;
        }

        const result = await generateAgentReply(postId);
        applyGenerationResult(feedback, result, analysis);
      } catch (error) {
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
    return;
  }

  boot();
})();
