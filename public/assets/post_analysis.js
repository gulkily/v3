(function () {
  function requestIdle(callback) {
    if (typeof window.requestIdleCallback === "function") {
      window.requestIdleCallback(callback, { timeout: 2000 });
      return;
    }

    window.setTimeout(callback, 0);
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

  function setFeedback(node, text, href) {
    if (!node) {
      return;
    }

    node.hidden = false;
    node.textContent = "";
    if (!href) {
      node.textContent = text;
      return;
    }

    node.append(document.createTextNode(text + " "));
    const link = document.createElement("a");
    link.href = href;
    link.textContent = "View reply";
    node.append(link);
  }

  function existingAgentReplyUrl(postId) {
    const card = document.querySelector('[data-post-id="' + selectorEscape(postId) + '"]');
    if (!card) {
      return "";
    }

    const postedId = card.getAttribute("data-agent-reply-posted-id") || "";
    return postedId ? "/posts/" + encodeURIComponent(postedId) : "";
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

  function applyGenerationResult(node, result, analysis) {
    if (!result || result.status !== "ok") {
      setFeedback(node, "Agent reply failed.", "");
      return;
    }

    if (result.generation_status === "generated" && result.agent_post_url) {
      setFeedback(node, "Agent reply posted", result.agent_post_url);
      return;
    }

    if (result.generation_status === "already_posted" && result.agent_post_url) {
      setFeedback(node, "Agent reply posted", result.agent_post_url);
      return;
    }

    if (result.generation_status === "not_recommended") {
      setFeedback(node, "Agent reply skipped" + skippedReason(result, analysis) + ".", "");
      return;
    }

    if (result.generation_status === "analysis_required") {
      setFeedback(node, "Agent reply skipped: analysis required.", "");
      return;
    }

    if (result.generation_status === "failed") {
      setFeedback(node, "Agent reply failed" + skippedReason(result, analysis) + ".", "");
      return;
    }

    setFeedback(node, "Agent reply skipped.", "");
  }

  function boot() {
    const root = document.querySelector("[data-created-post-id]");
    if (!root) {
      return;
    }

    const postId = root.getAttribute("data-created-post-id") || "";
    if (postId === "") {
      return;
    }

    const feedback = feedbackForPost(postId);
    const existingUrl = existingAgentReplyUrl(postId);
    if (existingUrl) {
      setFeedback(feedback, "Agent reply posted", existingUrl);
      return;
    }

    requestIdle(async function () {
      try {
        const analysis = await analyzePost(postId);
        if (!analysis || analysis.status !== "ok") {
          return;
        }

        if (analysis.agent_reply_generation_allowed !== true) {
          if (analysis.viewer_can_see_analysis && analysis.analysis_status !== "complete") {
            setFeedback(feedback, "Agent reply skipped: analysis required.", "");
          }
          return;
        }

        setFeedback(feedback, "Generating agent reply...", "");
        const result = await generateAgentReply(postId);
        applyGenerationResult(feedback, result, analysis);
      } catch (error) {
        setFeedback(feedback, "Agent reply failed.", "");
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
    return;
  }

  boot();
})();
