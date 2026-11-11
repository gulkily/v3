(function () {
  "use strict";

  // Offline, the worker serves the reply page as it renders with no target, so it has no quoted post.
  // Fill that in from the saved offline snapshot, the way the server renders it online.

  function shortDate(timestamp) {
    var date = new Date(String(timestamp || ""));
    if (isNaN(date.getTime())) return "";
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric", timeZone: "UTC" });
  }

  function threadTitle(subject, body) {
    var title = String(subject || "").trim();
    if (title) return title;
    var line = String(body || "").replace(/\s+/g, " ").trim();
    return line.length > 80 ? line.slice(0, 79) + "…" : (line || "Untitled thread");
  }

  function appendLines(node, text) {
    String(text || "").split(/\r\n|\r|\n/).forEach(function (line, index) {
      if (index) node.appendChild(document.createElement("br"));
      node.appendChild(document.createTextNode(line));
    });
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (typeof window.initSqlJs === "function") { resolve(); return; }
      var script = document.createElement("script");
      script.src = src;
      script.onload = function () { resolve(); };
      script.onerror = function () { reject(new Error("Unable to load " + src)); };
      document.head.appendChild(script);
    });
  }

  function openSnapshot() {
    var paths = window.__forumAssetPaths || {};
    if (!paths.sqlRuntime || !paths.sqlRuntimeBinary) return Promise.reject(new Error("The offline runtime is unavailable."));
    return loadScript(paths.sqlRuntime).then(function () {
      return Promise.all([
        window.initSqlJs({ locateFile: function () { return paths.sqlRuntimeBinary; } }),
        window.fetch("/offline/snapshot.sqlite3", { credentials: "omit" }).then(function (response) {
          if (!response.ok) throw new Error("No offline snapshot is saved on this device.");
          return response.arrayBuffer();
        })
      ]);
    }).then(function (parts) {
      return new parts[0].Database(new Uint8Array(parts[1]));
    });
  }

  function post(database, postId) {
    var result = database.exec("SELECT post_id, thread_id, subject, body, author_label, created_at FROM posts WHERE post_id = ?", [postId])[0];
    var row = result && result.values[0];
    return row ? { postId: row[0], threadId: row[1], subject: row[2], body: row[3], author: row[4], createdAt: row[5] } : null;
  }

  function renderParent(card, parent, root, threadId) {
    var heading = card.querySelector(".compose-page-title");
    var author = String(parent.author || "guest");
    if (heading) heading.textContent = "Reply to " + author;
    var quote = document.createElement("blockquote");
    quote.className = "reply-context";
    var meta = document.createElement("p");
    meta.className = "meta";
    meta.appendChild(document.createTextNode(author + " · "));
    var time = document.createElement("time");
    time.dateTime = String(parent.createdAt || "");
    time.textContent = shortDate(parent.createdAt);
    meta.appendChild(time);
    if (root) {
      meta.appendChild(document.createTextNode(" · in "));
      var link = document.createElement("a");
      link.href = "/threads/" + encodeURIComponent(threadId);
      link.textContent = threadTitle(root.subject, root.body);
      meta.appendChild(link);
    }
    var body = document.createElement("div");
    var text = String(parent.body || "");
    var isLong = text.length > 280 || (text.match(/\n/g) || []).length >= 4;
    body.className = "body reply-context-body" + (isLong ? " reply-context-body--clamped" : "");
    appendLines(body, text);
    quote.appendChild(meta);
    quote.appendChild(body);
    card.appendChild(quote);
  }

  function renderMissing(card) {
    var note = document.createElement("p");
    note.className = "reply-context reply-context--missing";
    note.textContent = "This thread isn't saved on this device. You can still write your reply and save it to send later.";
    card.appendChild(note);
  }

  function fillReplyContext() {
    var card = document.querySelector(".reply-context-card");
    var threadField = document.querySelector('[data-compose-form] [name="thread_id"]');
    var parentField = document.querySelector('[data-compose-form] [name="parent_id"]');
    // Only the copy the worker serves offline needs this; online, the server renders the quoted post.
    if (!document.querySelector("[data-offline-copy]")) return Promise.resolve();
    if (!card || card.querySelector(".reply-context") || !threadField || !threadField.value) return Promise.resolve();
    var threadId = threadField.value;
    var parentId = (parentField && parentField.value) || threadId;
    return openSnapshot().then(function (database) {
      try {
        var parent = post(database, parentId);
        if (!parent || String(parent.threadId) !== String(threadId)) { renderMissing(card); return; }
        renderParent(card, parent, post(database, threadId), threadId);
      } finally {
        database.close();
      }
    }).catch(function () {
      renderMissing(card);
    });
  }

  window.forumOfflineReplyContext = { fill: fillReplyContext };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fillReplyContext);
  } else {
    fillReplyContext();
  }
})();
