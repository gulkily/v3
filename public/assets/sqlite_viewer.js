(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-sqlite-viewer]");
    if (!root) {
      return;
    }

    var loadButton = root.querySelector('[data-action="load-sqlite"]');
    var status = root.querySelector('[data-role="sqlite-status"]');
    var databaseUrl = "/downloads/read_model.sqlite3";

    function setStatus(message, state) {
      if (status) {
        status.textContent = message;
        if (state) {
          status.dataset.state = state;
        } else {
          delete status.dataset.state;
        }
      }
    }

    function setLoading(isLoading) {
      if (loadButton) {
        loadButton.disabled = isLoading;
        loadButton.textContent = isLoading ? "Loading..." : "Load Database";
      }
    }

    async function loadDatabase() {
      setLoading(true);
      setStatus("Downloading the published database...", "loading");

      try {
        if (typeof window.initSqlJs !== "function") {
          throw new Error("The browser SQLite runtime is unavailable.");
        }

        var response = await window.fetch(databaseUrl, { credentials: "same-origin" });
        if (!response.ok) {
          throw new Error("The published database could not be downloaded.");
        }

        var bytes = new Uint8Array(await response.arrayBuffer());
        var SQL = await window.initSqlJs({
          locateFile: function (fileName) {
            return "/assets/" + fileName;
          }
        });
        new SQL.Database(bytes);
        setStatus("Database loaded. Browsing and queries will run locally in this page.", "ok");
        if (loadButton) {
          loadButton.textContent = "Reload Database";
        }
      } catch (error) {
        setStatus(error && error.message ? error.message : "The database could not be loaded.", "error");
      } finally {
        if (loadButton) {
          loadButton.disabled = false;
        }
      }
    }

    if (loadButton) {
      loadButton.addEventListener("click", loadDatabase);
    }
  });
})();
