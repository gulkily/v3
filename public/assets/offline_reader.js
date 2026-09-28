(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-offline-reader]");
    if (!root) {
      return;
    }

    var status = root.querySelector('[data-role="offline-reader-status"]');
    var generation = root.querySelector('[data-role="offline-reader-generation"]');
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

    loadSnapshot();
  });
})();
