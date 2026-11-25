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


  // On instagram.com: the post on screen (its own page, the open post, or the feed
  // post nearest the middle of the screen) and the largest image showing in it.
  // The composer gets both addresses (not the link, which stays out of the post)
  // and any selected text as the body, then asks the site to fetch the image.
  function instagramComposeUrl(baseOrigin) {
    if (!/(^|\.)instagram\.com$/.test(location.hostname)) {
      alert("Open an Instagram post first, then tap this bookmarklet.");
      return null;
    }
    function codeOf(href) {
      var m = String(href || "").match(/\/(p|reel|tv)\/([A-Za-z0-9_-]+)/);
      return m ? { kind: m[1] === "tv" ? "p" : m[1], code: m[2] } : null;
    }
    function isPostImage(img) {
      var src = img.currentSrc || img.src || "";
      return /^https:\/\/[^\/]*(cdninstagram\.com|fbcdn\.net)\//.test(src) && img.naturalWidth >= 150;
    }
    function bestSrc(img) {
      var best = img.currentSrc || img.src, bestWidth = img.naturalWidth || 0;
      String(img.getAttribute("srcset") || "").split(",").forEach(function (part) {
        var bits = part.trim().split(/\s+/), w = parseInt(bits[1], 10);
        if (bits[0] && w > bestWidth) { best = bits[0]; bestWidth = w; }
      });
      return best;
    }
    function shown(el) {
      var r = el.getBoundingClientRect();
      var w = Math.max(0, Math.min(r.right, innerWidth) - Math.max(r.left, 0));
      var h = Math.max(0, Math.min(r.bottom, innerHeight) - Math.max(r.top, 0));
      return w * h;
    }
    var scope = document.querySelector('div[role="dialog"]') || document;
    var imgs = [].slice.call(scope.querySelectorAll("img")).filter(isPostImage);
    imgs.sort(function (a, b) { return shown(b) - shown(a); });
    var img = imgs[0] && shown(imgs[0]) > 0 ? imgs[0] : null;

    var post = codeOf(location.pathname);
    if (!post && img) {
      for (var el = img; el && !post; el = el.parentElement) {
        var links = el.querySelectorAll ? el.querySelectorAll('a[href*="/p/"], a[href*="/reel/"]') : [];
        for (var i = 0; i < links.length && !post; i++) post = codeOf(links[i].getAttribute("href"));
        if (el.tagName === "ARTICLE") break;
      }
    }
    if (!post) {
      alert("No Instagram post found. Open the post itself, then tap this bookmarklet.");
      return null;
    }
    var link = "https://www.instagram.com/" + post.kind + "/" + post.code + "/";
    var selection = window.getSelection ? window.getSelection().toString().trim() : "";
    return (
      baseOrigin +
      "/compose/thread?board_tags=general&subject=&body=" +
      encodeURIComponent(selection) +
      "&instagram_post=" + encodeURIComponent(link) +
      (img ? "&instagram_image=" + encodeURIComponent(bestSrc(img)) : "")
    );
  }


  // Reads a Hacker News item page (a story, a comment's own page, or the
  // comment holding the selection) into a port file and sends it to /port.
  // Self-contained: its source text is the bookmarklet.
  function hnPortSubmit(baseOrigin, mode) {
    function fail(message) { alert(message); }
    if (location.hostname !== "news.ycombinator.com" || location.pathname !== "/item") {
      fail("Open a Hacker News thread first, then tap this bookmarklet.");
      return;
    }
    function textOf(el) {
      var out = "";
      (function walk(n) {
        if (n.nodeType === 3) { out += n.nodeValue; return; }
        if (n.nodeType !== 1) return;
        var tag = n.tagName;
        if (tag === "P") out += "\n\n";
        if (tag === "BR") { out += "\n"; return; }
        if (tag === "A" && n.getAttribute("href")) {
          var href = n.href, shown = n.textContent;
          out += href.indexOf(shown.replace(/\.\.\.$/, "")) === 0 || shown === "" ? href : shown + " (" + href + ")";
          return;
        }
        if (tag === "DIV" && /(^| )reply( |$)/.test(n.className)) return;
        for (var c = n.firstChild; c; c = c.nextSibling) walk(c);
        if (tag === "PRE") out += "\n";
      })(el);
      return out.replace(/ /g, " ").replace(/[ \t]+\n/g, "\n").replace(/\n{3,}/g, "\n\n").trim();
    }
    function itemOf(row) {
      var head = row.querySelector(".subline, span.comhead:not(.sitebit)") || (row.nextElementSibling && row.nextElementSibling.querySelector(".subline"));
      var user = head && head.querySelector('a[href^="user?id="]');
      var age = head && head.querySelector(".age");
      var when = age ? String(age.getAttribute("title") || "").split(" ")[0] : "";
      return {
        id: row.id,
        author: user ? user.textContent.trim() : "[deleted]",
        createdAt: /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(when) ? when + "Z" : when
      };
    }
    function block(fields, text) {
      var out = "%%\n";
      for (var i = 0; i < fields.length; i++) {
        if (fields[i][1] !== "" && fields[i][1] !== null) out += fields[i][0] + ": " + String(fields[i][1]).replace(/\s+/g, " ") + "\n";
      }
      return out + "\n" + text.replace(/^%/gm, "%%") + "\n";
    }

    var fat = document.querySelector("table.fatitem tr.athing");
    if (!fat) { fail("No Hacker News item found on this page."); return; }
    var isStory = /(^| )submission( |$)/.test(fat.className);
    var titleLink = document.querySelector("table.fatitem .titleline > a");
    var onStory = document.querySelector("table.fatitem .onstory a");
    var threadTitle = isStory ? (titleLink ? titleLink.textContent : "") : (onStory ? onStory.getAttribute("title") || onStory.textContent : "");
    var storyId = isStory ? fat.id : (onStory ? (onStory.getAttribute("href").match(/id=(\d+)/) || [])[1] : fat.id);

    var storyStats = isStory && fat.nextElementSibling && fat.nextElementSibling.querySelector(".subline");
    var rows = [].slice.call(document.querySelectorAll("table.comment-tree tr.athing.comtr"));
    var root = null;
    var sel = window.getSelection ? window.getSelection() : null;
    if (sel && sel.rangeCount && sel.toString().trim() !== "") {
      var node = sel.getRangeAt(0).commonAncestorContainer;
      if (node.nodeType !== 1) node = node.parentNode;
      var chosen = node && node.closest ? node.closest("tr.athing.comtr") : null;
      if (chosen) {
        var start = rows.indexOf(chosen), depth = +chosen.querySelector("td.ind").getAttribute("indent"), end = start + 1;
        while (end < rows.length && +rows[end].querySelector("td.ind").getAttribute("indent") > depth) end++;
        root = chosen;
        rows = rows.slice(start + 1, end);
        isStory = false;
      }
    }

    var rootItem = itemOf(root || fat);
    var rootText = root ? textOf(root.querySelector(".commtext") || root) : textOf(document.querySelector("table.fatitem " + (isStory ? ".toptext" : ".commtext")) || document.createElement("div"));
    var text = "Port-Format: hn-v1\nThread-Title: " + threadTitle.replace(/\s+/g, " ").trim()
      + "\nThread-Link: https://news.ycombinator.com/item?id=" + storyId + "\n";
    if (storyStats) {
      var score = storyStats.querySelector(".score");
      var points = score && score.textContent.trim().match(/^(\d+) points?$/);
      if (points) text += "Points: " + points[1] + "\n";
      var links = storyStats.querySelectorAll('a[href="item?id=' + storyId + '"]');
      for (var i = 0; i < links.length; i++) {
        var label = links[i].textContent.trim();
        var count = label.match(/^(\d+)\s+comments?$/);
        if (count || label === "discuss") {
          text += "Comment-Count: " + (count ? count[1] : "0") + "\n";
          break;
        }
      }
    }
    text += block([["Item", rootItem.id], ["Author", rootItem.author], ["Created-At", rootItem.createdAt],
      ["Title", isStory ? threadTitle : ""], ["Link", isStory && titleLink && /^https?:/.test(titleLink.href) && titleLink.href.indexOf("https://news.ycombinator.com/item?id=") !== 0 ? titleLink.href : ""]], rootText);

    var stack = [rootItem.id], base = rows.length ? +rows[0].querySelector("td.ind").getAttribute("indent") : 0;
    if (rows.length > 500) { fail("This thread has " + rows.length + " comments; ports take up to 500. Select text in one comment to port it and its replies."); return; }
    for (var r = 0; r < rows.length; r++) {
      var d = +rows[r].querySelector("td.ind").getAttribute("indent") - base;
      if (d < 0 || d > stack.length - 1) { fail("Couldn't read how comment " + rows[r].id + " is nested. Nothing was sent."); return; }
      stack.length = d + 1;
      var item = itemOf(rows[r]);
      var body = rows[r].querySelector(".commtext");
      text += block([["Item", item.id], ["Parent", stack[d]], ["Author", item.author], ["Created-At", item.createdAt || rootItem.createdAt]], body ? textOf(body) : "[deleted]");
      stack.push(item.id);
    }

    var form = document.createElement("form");
    form.method = "post";
    form.action = baseOrigin + "/port";
    form.acceptCharset = "UTF-8";
    if (mode === "new-window") form.target = "_blank";
    var field = document.createElement("textarea");
    field.name = "port";
    field.value = text;
    form.appendChild(field);
    form.style.display = "none";
    document.body.appendChild(form);
    form.submit();
  }

  function bookmarkletScript(baseOrigin, kind, mode) {
    if (kind === "hn-port") {
      return "(function(){(" + hnPortSubmit.toString() + ")(" + JSON.stringify(baseOrigin) + "," + JSON.stringify(mode) + ");})()";
    }
    var fn = kind === "tweet" ? tweetComposeUrl : kind === "instagram" ? instagramComposeUrl : composeUrl;

    // Instagram, new window: a new window when the browser allows one, otherwise
    // this window. No "noopener" feature, since with it window.open() never reports
    // success. The same-window version is the ordinary one below.
    if (kind === "instagram" && mode === "new-window") {
      return (
        "(function(){var u=(" +
        fn.toString() +
        ")(" +
        JSON.stringify(baseOrigin) +
        "," +
        JSON.stringify(kind) +
        ");if(u){var w=window.open(u,\"_blank\");if(w){w.opener=null;}else{window.location=u;}}})()"
      );
    }

    if (mode === "new-window") {
      return (
        "(function(){var u=(" +
        fn.toString() +
        ")(" +
        JSON.stringify(baseOrigin) +
        "," +
        JSON.stringify(kind) +
        ");if(u){window.open(u,\"_blank\",\"noopener\");}})()"
      );
    }

    return (
      "(function(){var u=(" +
      fn.toString() +
      ")(" +
      JSON.stringify(baseOrigin) +
      "," +
      JSON.stringify(kind) +
      ");if(u){window.location=u;}})()"
    );
  }

  // The server host leads the address as a comment (javascript:/*example.com*/...)
  // so a bookmark editor that shows only the first few characters shows where
  // the bookmarklet posts. It stays unencoded in the link so it reads as typed.
  function hostPrefix(baseOrigin) {
    var host = "";
    try {
      host = new URL(baseOrigin).host;
    } catch (error) {
      host = String(baseOrigin).replace(/^[a-z]+:\/\//i, "");
    }
    host = host.replace(/[^A-Za-z0-9.:\-\[\]]/g, "");
    return host ? "/*" + host + "*/" : "";
  }

  function bookmarkletSource(baseOrigin, kind, mode) {
    // Encode the URL instead of stripping whitespace from executable JavaScript.
    return "javascript:" + hostPrefix(baseOrigin) + encodeURIComponent(bookmarkletScript(baseOrigin, kind, mode));
  }

  // The copied form is plain, unencoded JavaScript on one physical line so it
  // also works pasted into qutebrowser's `:jseval` (where "javascript:" parses
  // as a label). Only newline-plus-indentation between tokens is collapsed;
  // the generated source has no line comments, and string content uses \n
  // escapes rather than literal line breaks.
  function bookmarkletCopySource(baseOrigin, kind, mode) {
    return "javascript:" + hostPrefix(baseOrigin) + bookmarkletScript(baseOrigin, kind, mode).replace(/\s*[\r\n]+\s*/g, " ");
  }

  async function copySource(source) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      try {
        await navigator.clipboard.writeText(source);
        return;
      } catch (error) {
        // Older browsers and restricted contexts may still allow a user-initiated copy.
      }
    }
    var field = document.createElement("textarea");
    field.value = source;
    field.readOnly = true;
    field.tabIndex = -1;
    field.setAttribute("aria-hidden", "true");
    field.style.cssText = "position:fixed;left:-9999px;top:0;opacity:0";
    document.body.appendChild(field);
    try {
      field.select();
      if (!document.execCommand || !document.execCommand("copy")) {
        throw new Error("Copy unavailable");
      }
    } finally {
      field.remove();
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    var baseOrigin = window.location.origin;

    document.querySelectorAll("[data-bookmarklet='pending']").forEach(function (link) {
      var kind = link.getAttribute("data-bookmarklet-kind") || "clip";
      var mode = link.getAttribute("data-bookmarklet-mode") || "same-window";
      link.setAttribute("href", bookmarkletSource(baseOrigin, kind, mode));
      var item = link.closest(".tool-launcher-item");
      var button = item.querySelector("[data-bookmarklet-copy]");
      var feedback = item.querySelector("[data-bookmarklet-feedback]");
      var copyText = bookmarkletCopySource(baseOrigin, kind, mode);
      var feedbackTimer;
      button.disabled = false;
      button.addEventListener("click", async function () {
        clearTimeout(feedbackTimer);
        feedback.textContent = "";
        button.disabled = true;
        try {
          await copySource(copyText);
          feedback.textContent = "Copied";
        } catch (error) {
          feedback.textContent = "Copy failed. Try again.";
        } finally {
          button.disabled = false;
          button.focus({ preventScroll: true });
          feedbackTimer = setTimeout(function () { feedback.textContent = ""; }, 4000);
        }
      });
    });
  });
})();
