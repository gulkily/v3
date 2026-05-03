(function () {
  function hasReplyBody(details) {
    const body = details.querySelector('textarea[name="body"]');
    return body !== null && body.value.trim() !== "";
  }

  function bindInlineReply(details) {
    if (hasReplyBody(details)) {
      details.open = true;
    }

    details.addEventListener("focusin", function (event) {
      if (event.target && event.target.tagName === "SUMMARY") {
        return;
      }

      details.open = true;
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-inline-reply-details]").forEach(bindInlineReply);
  });
})();
