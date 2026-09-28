(function () {
  "use strict";

  function clearSnapshotNode(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  var snapshotPresentation = {
    metadataValue: function (database, key) {
      var result = database.exec("SELECT value FROM metadata WHERE key = ?", [key])[0];
      return result && result.values && result.values[0] ? String(result.values[0][0] || "") : "";
    },
    normalThreadUrl: function (threadId) {
      return "/threads/" + encodeURIComponent(threadId);
    },
    threadIdFromPathname: function (pathname) {
      var match = String(pathname || "").match(/^\/threads\/([^/]+)\/?$/);
      return match ? decodeURIComponent(match[1]) : "";
    },
    threadTitle: function (subject, preview) {
      var title = String(subject || "").trim();
      if (title) return title;
      var fallback = String(preview || "").replace(/\s+/g, " ").trim();
      return fallback || "Untitled thread";
    },
    renderThreadList: function (options) {
      var result = options.database.exec(
        "SELECT root_post_id, subject, body_preview, reply_count, last_activity_at, author_label "
        + "FROM threads ORDER BY last_activity_at DESC, root_post_id DESC"
      )[0];
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      var heading = document.createElement("h2");
      heading.textContent = options.heading || "Recent saved threads";
      options.content.appendChild(heading);
      var list = document.createElement("div");
      list.className = "stack";
      (result && result.values ? result.values : []).forEach(function (row) {
        var threadId = String(row[0] || "");
        var button = document.createElement("button");
        button.type = "button";
        button.className = "nav-link";
        button.setAttribute("data-offline-thread-id", threadId);
        button.textContent = snapshotPresentation.threadTitle(row[1], row[2]);
        button.addEventListener("click", function () { options.onSelect(threadId); });
        list.appendChild(button);
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = String(row[5] || "guest") + " · " + Number(row[3] || 0) + " replies · " + String(row[4] || "");
        list.appendChild(meta);
      });
      if (!list.firstChild) {
        var empty = document.createElement("p");
        empty.className = "meta";
        empty.textContent = options.emptyMessage || "No recent public threads were included in this snapshot.";
        options.content.appendChild(empty);
        return false;
      }
      options.content.appendChild(list);
      return true;
    },
    renderThreadDetail: function (options) {
      var result = options.database.exec(
        "SELECT post_id, parent_id, subject, body, author_label, created_at "
        + "FROM posts WHERE thread_id = ? ORDER BY sequence_number ASC, post_id ASC",
        [options.threadId]
      )[0];
      var rows = result && result.values ? result.values : [];
      if (!rows.length) {
        options.setStatus("That thread is not included in this offline snapshot.", "error");
        if (options.onMissing) options.onMissing();
        return false;
      }
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      var back = document.createElement("button");
      back.type = "button";
      back.className = "nav-link";
      back.textContent = options.backLabel || "Back to saved threads";
      back.addEventListener("click", options.onBack);
      options.content.appendChild(back);
      rows.forEach(function (row, index) {
        var post = document.createElement("article");
        post.className = "card";
        if (index === 0) {
          var heading = document.createElement("h2");
          heading.textContent = snapshotPresentation.threadTitle(row[2], row[3]);
          post.appendChild(heading);
        }
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = String(row[4] || "guest") + " · " + String(row[5] || "");
        post.appendChild(meta);
        var body = document.createElement("div");
        body.className = "body";
        body.textContent = String(row[3] || "");
        post.appendChild(body);
        options.content.appendChild(post);
      });
      var onlineOnly = document.createElement("p");
      onlineOnly.className = "meta";
      onlineOnly.textContent = "Reading from a saved snapshot. Posting, voting, and tagging require a connection.";
      options.content.appendChild(onlineOnly);
      return true;
    }
  };
  window.forumOfflineSnapshot = snapshotPresentation;

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-offline-reader]");
    if (!root) {
      return;
    }

    var status = root.querySelector('[data-role="offline-reader-status"]');
    var generation = root.querySelector('[data-role="offline-reader-generation"]');
    var content = root.querySelector('[data-role="offline-reader-content"]');
    var snapshotUrl = root.getAttribute("data-snapshot-url") || "/offline/snapshot.sqlite3";

    function setStatus(message, state) {
      if (!status) {
        return;
      }
      status.textContent = message;
      if (state) {
        status.dataset.state = state;
      } else {
        delete status.dataset.state;
      }
    }

    function metadataValue(database, key) {
      return snapshotPresentation.metadataValue(database, key);
    }

    function clearNode(node) {
      while (node && node.firstChild) {
        node.removeChild(node.firstChild);
      }
    }

    function threadTitle(subject, preview) {
      return snapshotPresentation.threadTitle(subject, preview);
    }

    function renderThreadList(database) {
      if (!content) return;
      snapshotPresentation.renderThreadList({
        content: content,
        database: database,
        onSelect: function (threadId) { selectThread(database, threadId); }
      });
    }

    function threadIdFromHash() {
      var match = String(window.location.hash || "").match(/^#thread=(.+)$/);
      return match ? decodeURIComponent(match[1]) : "";
    }

    function renderThreadDetail(database, threadId) {
      if (!content) return;
      snapshotPresentation.renderThreadDetail({
        content: content,
        database: database,
        onBack: function () {
          window.location.hash = "";
          renderThreadList(database);
        },
        onMissing: function () { renderThreadList(database); },
        setStatus: setStatus,
        threadId: threadId
      });
    }

    function selectThread(database, threadId) {
      if (window.location.hash !== "#thread=" + encodeURIComponent(threadId)) {
        window.location.hash = "thread=" + encodeURIComponent(threadId);
      }
      renderThreadDetail(database, threadId);
    }

    async function loadSnapshot() {
      setStatus("Loading the local reading snapshot...", "loading");
      try {
        if (typeof window.initSqlJs !== "function") {
          throw new Error("The browser SQLite runtime is unavailable.");
        }
        var response = await window.fetch(snapshotUrl, { credentials: "omit" });
        if (!response.ok) {
          throw new Error("No offline reading snapshot is available yet. Open the site while online and try again.");
        }
        var bytes = new Uint8Array(await response.arrayBuffer());
        var SQL = await window.initSqlJs({
          locateFile: function (fileName) {
            return "/assets/" + fileName;
          }
        });
        var database = new SQL.Database(bytes);
        var generatedAt = metadataValue(database, "generated_at");
        if (generation && generatedAt) {
          generation.textContent = "Snapshot saved " + generatedAt + ".";
          generation.hidden = false;
        }
        setStatus("Offline snapshot is ready.", "ok");
        root.dispatchEvent(new CustomEvent("forum-offline-reader-ready", {
          detail: { database: database, generatedAt: generatedAt }
        }));
      } catch (error) {
        setStatus(error && error.message ? error.message : "The offline reading snapshot could not be loaded.", "error");
      }
    }

    root.addEventListener("forum-offline-reader-ready", function (event) {
      var database = event.detail.database;
      var threadId = threadIdFromHash();
      if (threadId) {
        renderThreadDetail(database, threadId);
      } else {
        renderThreadList(database);
      }
      window.addEventListener("hashchange", function () {
        var selectedThreadId = threadIdFromHash();
        if (selectedThreadId) {
          renderThreadDetail(database, selectedThreadId);
        } else {
          renderThreadList(database);
        }
      });
    });

    loadSnapshot();
  });
})();
