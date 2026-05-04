(function () {
  function hasReplyBody(details) {
    const body = details.querySelector('textarea[name="body"]');
    return body !== null && body.value.trim() !== "";
  }

  function bindInlineReply(details) {
    const trigger = details.querySelector("[data-inline-reply-trigger]");
    const body = details.querySelector('textarea[name="body"]');

    function openComposer(focusBody) {
      details.open = true;
      if (focusBody && body) {
        window.setTimeout(function () {
          body.focus();
        }, 0);
      }
    }

    if (hasReplyBody(details)) {
      openComposer(false);
    }

    if (trigger) {
      trigger.addEventListener("click", function () {
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
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-inline-reply-details]").forEach(bindInlineReply);
  });
})();
