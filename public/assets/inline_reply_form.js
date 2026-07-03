(function () {
  function hasReplyBody(details) {
    const body = details.querySelector('textarea[name="body"]');
    return body !== null && body.value.trim() !== "";
  }

  function requestFrame(callback) {
    if (typeof window.requestAnimationFrame === "function") {
      window.requestAnimationFrame(callback);
      return;
    }

    window.setTimeout(callback, 0);
  }

  function scrollFullyIntoView(node) {
    if (!node || typeof node.getBoundingClientRect !== "function") {
      return;
    }

    const rect = node.getBoundingClientRect();
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    if (viewportHeight <= 0 || viewportWidth <= 0) {
      return;
    }

    const clippedBelow = rect.bottom > viewportHeight;
    const clippedAbove = rect.top < 0;
    const clippedRight = rect.right > viewportWidth;
    const clippedLeft = rect.left < 0;
    if (!clippedBelow && !clippedAbove && !clippedRight && !clippedLeft) {
      return;
    }

    node.scrollIntoView({
      behavior: "smooth",
      block: rect.height <= viewportHeight ? "nearest" : "start",
      inline: "nearest",
    });
  }

  function bindInlineReply(details) {
    const trigger = details.querySelector("[data-inline-reply-trigger]");
    const summary = details.querySelector(".inline-reply-summary");
    const body = details.querySelector('textarea[name="body"]');
    const composer = details.closest("[data-compose-root]") || details;

    function syncOpenState() {
      const expanded = details.open;
      details.dataset.inlineReplyExpanded = expanded ? "1" : "";
      if (summary) {
        summary.hidden = expanded;
      }
    }

    function openComposer(focusBody) {
      details.open = true;
      syncOpenState();
      if (focusBody && body) {
        window.setTimeout(function () {
          body.focus();
          requestFrame(function () {
            scrollFullyIntoView(composer);
          });
        }, 0);
        return;
      }

      requestFrame(function () {
        scrollFullyIntoView(composer);
      });
    }

    function maybeCollapseComposer() {
      if (!details.open || hasReplyBody(details)) {
        return;
      }

      const active = document.activeElement;
      if (active && details.contains(active)) {
        return;
      }

      details.open = false;
      syncOpenState();
    }

    if (hasReplyBody(details)) {
      openComposer(false);
    }

    if (trigger) {
      trigger.addEventListener("click", function (event) {
        event.preventDefault();
        openComposer(true);
      });

      trigger.addEventListener("focus", function () {
        openComposer(true);
      });
    }

    details.addEventListener("focusin", function (event) {
      if (event.target && event.target.tagName === "SUMMARY") {
        return;
      }

      openComposer(false);
    });

    details.addEventListener("focusout", function () {
      window.setTimeout(maybeCollapseComposer, 0);
    });

    details.addEventListener("toggle", syncOpenState);
    syncOpenState();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-inline-reply-details]").forEach(bindInlineReply);
  });
})();
