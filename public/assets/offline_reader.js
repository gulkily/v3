(function () {
  "use strict";

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
      var result = database.exec("SELECT value FROM metadata WHERE key = ?", [key])[0];
      return result && result.values && result.values[0] ? String(result.values[0][0] || "") : "";
    }

    function clearNode(node) {
      while (node && node.firstChild) {
        node.removeChild(node.firstChild);
      }
    }

    function threadTitle(subject, preview) {
      var title = String(subject || "").trim();
      if (title) {
        return title;
      }
      var fallback = String(preview || "").replace(/\s+/g, " ").trim();
      return fallback || "Untitled thread";
    }

    function renderThreadList(database) {
      if (!content) {
        return;
      }
      var result = database.exec(
        "SELECT root_post_id, subject, body_preview, reply_count, last_activity_at, author_label "
        + "FROM threads ORDER BY last_activity_at DESC, root_post_id DESC"
      )[0];
      clearNode(content);
      content.hidden = false;

      var heading = document.createElement("h2");
      heading.textContent = "Recent saved threads";
      content.appendChild(heading);
      var list = document.createElement("div");
      list.className = "stack";
      (result && result.values ? result.values : []).forEach(function (row) {
        var threadId = String(row[0] || "");
        var button = document.createElement("button");
        button.type = "button";
        button.className = "nav-link";
        button.setAttribute("data-offline-thread-id", threadId);
        button.textContent = threadTitle(row[1], row[2]);
        button.addEventListener("click", function () {
          root.dispatchEvent(new CustomEvent("forum-offline-reader-thread-selected", {
            detail: { database: database, threadId: threadId }
          }));
        });
        list.appendChild(button);

        var meta = document.createElement("p");
        meta.className = "meta";
        var replyCount = Number(row[3] || 0);
        meta.textContent = String(row[5] || "guest") + " · " + replyCount + " replies · " + String(row[4] || "");
        list.appendChild(meta);
      });
      if (!list.firstChild) {
        var empty = document.createElement("p");
        empty.className = "meta";
        empty.textContent = "No recent public threads were included in this snapshot.";
        content.appendChild(empty);
        return;
      }
      content.appendChild(list);
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
      renderThreadList(event.detail.database);
    });

    loadSnapshot();
  });
})();
