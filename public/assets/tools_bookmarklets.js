(function () {
  function composeUrl(baseOrigin, kind) {
    var selection = "";
    if (window.getSelection) {
      selection = window.getSelection().toString().trim();
    }
    var title = document.title || "";
    var currentUrl = window.location.href || "";
    var subject = title;
    var body = "";

    if (kind === "url") {
      body = currentUrl;
    } else if (kind === "selection") {
      body = selection;
    } else {
      body = selection;
      if (title) {
        body += (body ? "\n\n" : "") + title;
      }
      if (currentUrl) {
        body += (body ? "\n" : "") + currentUrl;
      }
    }

    return (
      baseOrigin +
      "/compose/thread?board_tags=general&subject=" +
      encodeURIComponent(subject) +
      "&body=" +
      encodeURIComponent(body)
    );
  }

  function bookmarkletSource(baseOrigin, kind, mode) {
    if (mode === "new-window") {
      return (
        "javascript:(function(){var u=(" +
        composeUrl.toString() +
        ")(" +
        JSON.stringify(baseOrigin) +
        "," +
        JSON.stringify(kind) +
        ");window.open(u,\"_blank\",\"noopener\");})()"
      );
    }

    return (
      "javascript:(function(){window.location=(" +
      composeUrl.toString() +
      ")(" +
      JSON.stringify(baseOrigin) +
      "," +
      JSON.stringify(kind) +
      ");})()"
    );
  }

  document.addEventListener("DOMContentLoaded", function () {
    var baseOrigin = window.location.origin;

    document.querySelectorAll("[data-bookmarklet='pending']").forEach(function (link) {
      var kind = link.getAttribute("data-bookmarklet-kind") || "clip";
      var mode = link.getAttribute("data-bookmarklet-mode") || "same-window";
      link.setAttribute("href", bookmarkletSource(baseOrigin, kind, mode));
    });
  });
})();
