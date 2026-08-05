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

  function markGenerationStarted(postId) {
    if (generationStartedPostIds.has(postId)) {
      return false;
    }

    generationStartedPostIds.add(postId);
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
