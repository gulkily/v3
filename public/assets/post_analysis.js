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

  async function generateAgentReply(postId, responseMode) {
    const response = await fetch("/api/generate_agent_reply", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "Accept": "application/json",
      },
      body: new URLSearchParams({ post_id: postId, response_mode: responseMode || "" }).toString(),
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

  function agentReplySubject(result) {
    return result && result.response_mode_label ? result.response_mode_label : "Agent reply";
  }

  function applyGenerationResult(node, result, analysis) {
    if (!result || result.status !== "ok") {
      return false;
    }

    if (result.generation_status === "generated" && result.agent_post_id) {
      setFeedbackLink(
        node,
        agentReplySubject(result) + " added below this post.",
        agentReplyAnchorUrl(result.agent_post_id),
        "View agent reply."
      );
      return true;
    }

    if (result.generation_status === "already_posted" && result.agent_post_id) {
      setFeedbackLink(
        node,
        agentReplySubject(result) + " already exists below this post.",
        agentReplyAnchorUrl(result.agent_post_id),
        "View agent reply."
      );
      return true;
    }

    if (result.generation_status === "requested") {
      setFeedback(node, agentReplySubject(result) + " requested.");
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
      setFeedback(node, agentReplySubject(result) + " request in progress.");
      return true;
    }

    if (result.generation_status === "failed") {
      setFeedback(node, agentReplySubject(result) + " request failed.", "");
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

  let agentResponseModeMenu = null;
  let agentResponseModeTrigger = null;

  function agentResponseModes() {
    const catalog = document.querySelector('[data-agent-response-mode-catalog]');
    if (!catalog) {
      return [];
    }

    try {
      const modes = JSON.parse(catalog.textContent || "[]");
      return Array.isArray(modes) ? modes.filter(function (mode) {
        return mode && typeof mode.type === "string" && typeof mode.label === "string";
      }) : [];
    } catch (error) {
      return [];
    }
  }

  function closeAgentResponseModeMenu(restoreFocus) {
    if (!agentResponseModeMenu || agentResponseModeMenu.hidden) {
      return;
    }
    const trigger = agentResponseModeTrigger;
    agentResponseModeMenu.hidden = true;
    if (trigger) {
      trigger.setAttribute("aria-expanded", "false");
      if (restoreFocus) {
        trigger.focus();
      }
    }
    agentResponseModeTrigger = null;
  }

  function ensureAgentResponseModeMenu() {
    if (agentResponseModeMenu) {
      return agentResponseModeMenu;
    }

    const menu = document.createElement("div");
    menu.className = "agent-response-mode-menu";
    menu.id = "agent-response-mode-menu";
    menu.setAttribute("role", "menu");
    menu.setAttribute("aria-label", "Choose an agent response");
    menu.hidden = true;

    const choices = document.createElement("div");
    choices.className = "agent-response-mode-choices";
    agentResponseModes().forEach(function (mode) {
      const choice = document.createElement("button");
      choice.type = "button";
      choice.className = "agent-response-mode-choice";
      choice.setAttribute("role", "menuitem");
      choice.setAttribute("data-response-mode", mode.type);
      const label = document.createElement("strong");
      label.textContent = mode.label;
      choice.appendChild(label);
      choice.addEventListener("click", function () {
        const trigger = agentResponseModeTrigger;
        closeAgentResponseModeMenu(false);
        if (trigger) {
          requestAgentReply(trigger, mode);
        }
      });
      choices.appendChild(choice);
    });
    menu.appendChild(choices);

    const notice = document.createElement("p");
    notice.className = "meta agent-response-mode-notice";
    notice.textContent = "Facts analysis uses this post and supplied thread context only; it is not independently verified research.";
    menu.appendChild(notice);
    document.body.appendChild(menu);
    agentResponseModeMenu = menu;
    document.addEventListener("pointerdown", function (event) {
      if (!agentResponseModeMenu || agentResponseModeMenu.hidden) {
        return;
      }
      if (!agentResponseModeMenu.contains(event.target)
        && (!agentResponseModeTrigger || !agentResponseModeTrigger.contains(event.target))) {
        closeAgentResponseModeMenu(false);
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && agentResponseModeMenu && !agentResponseModeMenu.hidden) {
        event.preventDefault();
        closeAgentResponseModeMenu(true);
      }
    });
    return menu;
  }

  function openAgentResponseModeMenu(button) {
    const modes = agentResponseModes();
    const postId = button.getAttribute("data-post-id") || "";
    if (postId === "" || modes.length === 0) {
      setFeedback(feedbackForPost(postId), "Agent response modes are unavailable.");
      return;
    }

    const menu = ensureAgentResponseModeMenu();
    closeAgentResponseModeMenu(false);
    agentResponseModeTrigger = button;
    button.setAttribute("aria-expanded", "true");
    const bounds = button.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(bounds.left, window.innerWidth - 328)) + "px";
    menu.style.top = Math.min(bounds.bottom + 6, window.innerHeight - 120) + "px";
    menu.hidden = false;
    const firstChoice = menu.querySelector('[data-response-mode]');
    if (firstChoice) {
      firstChoice.focus();
    }
  }

  async function requestAgentReply(button, mode) {
    const postId = button.getAttribute("data-post-id") || "";
    if (postId === "" || !markGenerationStarted(postId)) {
      return;
    }

    const originalText = button.textContent;
    const feedback = feedbackForPost(postId);
    button.disabled = true;
    button.textContent = "Requesting...";
    setFeedback(feedback, "Requesting " + mode.label + "...");

    try {
      const result = await generateAgentReply(postId, mode.type);
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
    setFeedback(feedback, mode.label + " request failed.");
  }

  function bindAgentReplyRequestButtons() {
    document.querySelectorAll('[data-action="request-agent-reply"]').forEach(function (button) {
      if (button.getAttribute("data-agent-reply-request-bound") === "1") {
        return;
      }

      button.setAttribute("data-agent-reply-request-bound", "1");
      button.setAttribute("aria-haspopup", "menu");
      button.setAttribute("aria-controls", "agent-response-mode-menu");
      button.setAttribute("aria-expanded", "false");
      button.addEventListener("click", function () {
        openAgentResponseModeMenu(button);
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
