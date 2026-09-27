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

  function tweetComposeUrl(baseOrigin, kind) {
    function textOf(el) {
      if (!el) return "";
      var out = "";
      (function walk(n) {
        if (n.nodeType === 3) { out += n.nodeValue; return; }
        if (n.nodeType !== 1) return;
        if (n.tagName === "IMG") { out += n.getAttribute("alt") || ""; return; }
        if (n.tagName === "BR") { out += "\n"; return; }
        for (var c = n.firstChild; c; c = c.nextSibling) walk(c);
      })(el);
      return out.replace(/…$/, "").trim();
    }

    var arts = [].slice.call(document.querySelectorAll('article[data-testid="tweet"]'));
    var sel = window.getSelection ? window.getSelection() : null;
    var selText = sel ? sel.toString().trim() : "";
    var art = null;

    if (sel && sel.rangeCount) {
      var node = sel.getRangeAt(0).commonAncestorContainer;
      if (node.nodeType !== 1) node = node.parentNode;
      art = node && node.closest ? node.closest('article[data-testid="tweet"]') : null;
    }
    var m = location.pathname.match(/\/status\/(\d+)/);
    if (!art && m) {
      art = arts.filter(function (a) {
        return [].some.call(a.querySelectorAll('a[href*="/status/"]'), function (l) {
          return l.querySelector("time") && l.getAttribute("href").indexOf("/status/" + m[1]) > -1;
        });
      })[0] || null;
    }
    if (!art) {
      art = arts.filter(function (a) { return a.getBoundingClientRect().bottom > 60; })[0] || arts[0];
    }
    if (!art) {
      alert("No tweet found on this page.");
      return null;
    }

    var text = selText || textOf(art.querySelector('[data-testid="tweetText"]'));
    var nameEl = art.querySelector('[data-testid="User-Name"]');
    var bits = nameEl ? nameEl.innerText.split("\n") : [];
    var name = bits[0] || "";
    var handle = (bits.filter(function (b) { return b.charAt(0) === "@"; })[0]) || "";
    var link = [].filter.call(art.querySelectorAll('a[href*="/status/"]'), function (l) {
      return l.querySelector("time");
    })[0];
    var url = link ? link.href.split("?")[0] : location.href.split("?")[0];

    var first = text.split("\n")[0];
    var subject = (handle ? handle + ": " : "") + (first.length > 90 ? first.slice(0, 87) + "..." : first);
    var body = text + "\n\n— " + name + (handle ? " (" + handle + ")" : "") + "\n" + url;

    return (
      baseOrigin +
      "/compose/thread?board_tags=general&subject=" +
      encodeURIComponent(subject) +
      "&body=" +
      encodeURIComponent(body)
    );
  }

  function bookmarkletSource(baseOrigin, kind, mode) {
    var fn = kind === "tweet" ? tweetComposeUrl : composeUrl;

    if (mode === "new-window") {
      return (
        "javascript:(function(){var u=(" +
        fn.toString() +
        ")(" +
        JSON.stringify(baseOrigin) +
        "," +
        JSON.stringify(kind) +
        ");if(u){window.open(u,\"_blank\",\"noopener\");}})()"
      );
    }

    return (
      "javascript:(function(){var u=(" +
      fn.toString() +
      ")(" +
      JSON.stringify(baseOrigin) +
      "," +
      JSON.stringify(kind) +
      ");if(u){window.location=u;}})()"
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
