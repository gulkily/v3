(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-sqlite-viewer]");
    if (!root) {
      return;
    }

    var loadButton = root.querySelector('[data-action="load-sqlite"]');
    var status = root.querySelector('[data-role="sqlite-status"]');
    var explorer = root.querySelector('[data-role="sqlite-explorer"]');
    var tableSelect = root.querySelector('[data-role="sqlite-table-select"]');
    var tableDetails = root.querySelector('[data-role="sqlite-table-details"]');
    var tablePreview = root.querySelector('[data-role="sqlite-table-preview"]');
    var queryPanel = root.querySelector('[data-role="sqlite-query-panel"]');
    var querySelect = root.querySelector('[data-role="sqlite-query-select"]');
    var queryInput = root.querySelector('[data-role="sqlite-query-input"]');
    var queryButton = root.querySelector('[data-action="run-sqlite-query"]');
    var queryStatus = root.querySelector('[data-role="sqlite-query-status"]');
    var queryResults = root.querySelector('[data-role="sqlite-query-results"]');
    var databaseUrl = "/downloads/read_model.sqlite3";
    var database = null;
    var presetQueries = [
      {
        label: "Recent posts",
        description: "Show the ten newest indexed posts.",
        sql: "SELECT post_id, created_at, subject, author_label FROM posts ORDER BY created_at DESC LIMIT 10"
      },
      {
        label: "Threads by reply count",
        description: "Show the most active indexed threads.",
        sql: "SELECT root_post_id, subject, reply_count, last_activity_at FROM threads ORDER BY reply_count DESC LIMIT 10"
      },
      {
        label: "Approved profiles",
        description: "Show approved profiles in the read model.",
        sql: "SELECT profile_slug, username, post_count, thread_count FROM profiles WHERE is_approved = 1 ORDER BY username LIMIT 20"
      },
      {
        label: "Recent activity",
        description: "Show the ten newest activity records.",
        sql: "SELECT created_at, kind, label, author_label FROM activity ORDER BY created_at DESC, id DESC LIMIT 10"
      }
    ];

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

    function setQueryStatus(message, state) {
      if (queryStatus) {
        queryStatus.textContent = message;
        if (state) {
          queryStatus.dataset.state = state;
        } else {
          delete queryStatus.dataset.state;
        }
      }
    }

    function setLoading(isLoading) {
      if (loadButton) {
        loadButton.disabled = isLoading;
        loadButton.textContent = isLoading ? "Loading..." : "Load Database";
      }
    }

    function clearNode(node) {
      if (node) {
        while (node.firstChild) {
          node.removeChild(node.firstChild);
        }
      }
    }

    function renderRows(node, result, emptyMessage) {
      clearNode(node);
      if (!node) {
        return;
      }
      if (!result || result.columns.length === 0 || result.values.length === 0) {
        var empty = document.createElement("p");
        empty.className = "meta";
        empty.textContent = emptyMessage;
        node.appendChild(empty);
        return;
      }

      var table = document.createElement("table");
      var head = document.createElement("thead");
      var headRow = document.createElement("tr");
      result.columns.forEach(function (column) {
        var heading = document.createElement("th");
        heading.scope = "col";
        heading.textContent = column;
        headRow.appendChild(heading);
      });
      head.appendChild(headRow);
      table.appendChild(head);

      var body = document.createElement("tbody");
      result.values.forEach(function (row) {
        var rowNode = document.createElement("tr");
        row.forEach(function (value) {
          var cell = document.createElement("td");
          cell.textContent = value === null ? "NULL" : String(value);
          rowNode.appendChild(cell);
        });
        body.appendChild(rowNode);
      });
      table.appendChild(body);
      node.appendChild(table);
    }

    function quoteIdentifier(identifier) {
      return '"' + identifier.replace(/"/g, '""') + '"';
    }

    function renderTable(tableName) {
      if (!database || !tableName) {
        return;
      }
      try {
        var quotedName = quoteIdentifier(tableName);
        var columns = database.exec("PRAGMA table_info(" + quotedName + ")")[0];
        var rows = database.exec("SELECT * FROM " + quotedName + " LIMIT 20")[0];
        renderRows(tableDetails, columns, "No column information is available.");
        renderRows(tablePreview, rows, "This table has no rows.");
      } catch (error) {
        clearNode(tableDetails);
        clearNode(tablePreview);
        var message = document.createElement("p");
        message.className = "meta";
        message.textContent = error && error.message ? error.message : "The table could not be inspected.";
        tablePreview.appendChild(message);
      }
    }

    function populateExplorer() {
      if (!database || !tableSelect) {
        return;
      }
      var result = database.exec("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")[0];
      clearNode(tableSelect);
      if (!result || result.values.length === 0) {
        tableSelect.disabled = true;
        setStatus("Database loaded, but it contains no user tables.", "ok");
        return;
      }
      result.values.forEach(function (row) {
        var option = document.createElement("option");
        option.value = row[0];
        option.textContent = row[0];
        tableSelect.appendChild(option);
      });
      tableSelect.addEventListener("change", function () {
        renderTable(tableSelect.value);
      });
      if (explorer) {
        explorer.hidden = false;
      }
      renderTable(tableSelect.value);
    }

    function populateQuerySelector() {
      if (!querySelect) {
        return;
      }
      presetQueries.forEach(function (preset, index) {
        var option = document.createElement("option");
        option.value = String(index);
        option.textContent = preset.label + " — " + preset.description;
        querySelect.appendChild(option);
      });
      querySelect.addEventListener("change", function () {
        var preset = presetQueries[Number(querySelect.value)];
        if (preset && queryInput) {
          queryInput.value = preset.sql;
        }
      });
    }

    function isSingleSelectQuery(query) {
      var normalized = query.trim().replace(/;+$/, "").trim();
      return normalized !== "" && /^SELECT\b/i.test(normalized) && normalized.indexOf(";") === -1;
    }

    function runQuery() {
      if (!database || !queryInput) {
        setQueryStatus("Load the database before running a query.", "error");
        return;
      }
      var query = queryInput.value;
      if (!isSingleSelectQuery(query)) {
        clearNode(queryResults);
        setQueryStatus("Only one read-only SELECT statement is allowed.", "error");
        return;
      }
      try {
        var result = database.exec(query)[0];
        renderRows(queryResults, result, "The query returned no rows.");
        setQueryStatus("Query completed locally.", "ok");
      } catch (error) {
        clearNode(queryResults);
        setQueryStatus(error && error.message ? error.message : "The query could not be run.", "error");
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
        database = new SQL.Database(bytes);
        populateExplorer();
        populateQuerySelector();
        if (queryPanel) {
          queryPanel.hidden = false;
        }
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
    if (queryButton) {
      queryButton.addEventListener("click", runQuery);
    }
  });
})();
