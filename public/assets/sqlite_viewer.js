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
    var tableTabs = root.querySelectorAll("[data-sqlite-tab]");
    var queryPanel = root.querySelector('[data-role="sqlite-query-panel"]');
    var querySelect = root.querySelector('[data-role="sqlite-query-select"]');
    var queryInput = root.querySelector('[data-role="sqlite-query-input"]');
    var queryButton = root.querySelector('[data-action="run-sqlite-query"]');
    var queryStatus = root.querySelector('[data-role="sqlite-query-status"]');
    var queryResults = root.querySelector('[data-role="sqlite-query-results"]');
    var databaseUrl = "/downloads/read_model.sqlite3";
    var database = null;
    var maxPreviewRows = 20;
    var maxQueryRows = 50;
    var querySelectorPopulated = false;
    var presetQueries = [
    {
        "id": "board-liked-newest",
        "label": "Board: Liked + Newest",
        "description": "Reproduce the default board view with its joins, filters, fields, and ordering.",
        "category": "board",
        "sql": "SELECT threads.root_post_id,\n       threads.root_post_created_at,\n       threads.last_activity_at,\n       threads.subject,\n       threads.body_preview,\n       threads.reply_count,\n       threads.score_total,\n       threads.board_tags_json,\n       threads.thread_labels_json,\n       posts.author_label,\n       posts.author_profile_slug,\n       posts.post_score_total AS root_post_score_total,\n       profiles.username_token AS author_username_token,\n       COALESCE(profiles.is_approved, 0) AS author_is_approved\nFROM threads\nJOIN posts ON posts.post_id = threads.root_post_id\nLEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id\nWHERE NOT EXISTS (\n  SELECT 1\n  FROM json_each(threads.board_tags_json)\n  WHERE json_each.value = 'identity'\n)\nAND EXISTS (\n  SELECT 1\n  FROM json_each(threads.thread_labels_json)\n  WHERE json_each.value = 'like'\n)\nAND posts.post_score_total >= 0\nORDER BY CASE WHEN EXISTS (\n  SELECT 1\n  FROM json_each(threads.thread_labels_json)\n  WHERE json_each.value = 'pinned'\n) THEN 0 ELSE 1 END,\n         threads.root_post_created_at DESC,\n         threads.root_post_id DESC"
    },
    {
        "id": "recent-posts",
        "label": "Recent posts",
        "description": "Show the ten newest indexed posts.",
        "category": "content",
        "sql": "SELECT post_id, created_at, subject, author_label\nFROM posts\nORDER BY created_at DESC\nLIMIT 10"
    },
    {
        "id": "threads-by-reply-count",
        "label": "Threads by reply count",
        "description": "Show the most active indexed threads.",
        "category": "content",
        "sql": "SELECT root_post_id, subject, reply_count, last_activity_at\nFROM threads\nORDER BY reply_count DESC\nLIMIT 10"
    },
    {
        "id": "approved-profiles",
        "label": "Approved profiles",
        "description": "Show approved profiles in the read model.",
        "category": "people",
        "sql": "SELECT profile_slug, username, post_count, thread_count\nFROM profiles\nWHERE is_approved = 1\nORDER BY username\nLIMIT 20"
    },
    {
        "id": "recent-activity",
        "label": "Recent activity",
        "description": "Show the ten newest activity records.",
        "category": "activity",
        "sql": "SELECT created_at, kind, label, author_label\nFROM activity\nORDER BY created_at DESC, id DESC\nLIMIT 10"
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

    function activateTableTab(tabName) {
      tableTabs.forEach(function (tab) {
        var active = tab.dataset.sqliteTab === tabName;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", active ? "true" : "false");
      });
      if (tablePreview) {
        tablePreview.hidden = tabName !== "data";
      }
      if (tableDetails) {
        tableDetails.hidden = tabName !== "columns";
      }
    }

    function renderRows(node, result, emptyMessage, maxRows, sortable) {
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

      var visibleRows = maxRows ? result.values.slice(0, maxRows) : result.values;
      var table = document.createElement("table");
      var head = document.createElement("thead");
      var headRow = document.createElement("tr");
      var body = document.createElement("tbody");
      var sortDirections = {};

      function compareValues(left, right) {
        if (left === right) {
          return 0;
        }
        if (left === null) {
          return 1;
        }
        if (right === null) {
          return -1;
        }
        var leftNumber = Number(left);
        var rightNumber = Number(right);
        if (String(left).trim() !== "" && String(right).trim() !== "" && !Number.isNaN(leftNumber) && !Number.isNaN(rightNumber)) {
          return leftNumber - rightNumber;
        }
        return String(left).localeCompare(String(right), undefined, { numeric: true, sensitivity: "base" });
      }

      function renderBody(rows) {
        clearNode(body);
        rows.forEach(function (row) {
          var rowNode = document.createElement("tr");
          row.forEach(function (value) {
            var cell = document.createElement("td");
            cell.textContent = value === null ? "NULL" : String(value);
            rowNode.appendChild(cell);
          });
          body.appendChild(rowNode);
        });
      }

      result.columns.forEach(function (column, columnIndex) {
        var heading = document.createElement("th");
        heading.scope = "col";
        heading.setAttribute("aria-sort", "none");
        if (sortable) {
          var sortButton = document.createElement("button");
          sortButton.type = "button";
          sortButton.className = "sqlite-sort-button";
          sortButton.textContent = column;
          sortButton.addEventListener("click", function () {
            var direction = sortDirections[columnIndex] === "ascending" ? "descending" : "ascending";
            sortDirections = {};
            sortDirections[columnIndex] = direction;
            var sortedRows = visibleRows.slice().sort(function (left, right) {
              return compareValues(left[columnIndex], right[columnIndex]) * (direction === "ascending" ? 1 : -1);
            });
            headRow.querySelectorAll("th").forEach(function (header) {
              header.setAttribute("aria-sort", "none");
            });
            heading.setAttribute("aria-sort", direction);
            renderBody(sortedRows);
          });
          heading.appendChild(sortButton);
        } else {
          heading.textContent = column;
        }
        headRow.appendChild(heading);
      });
      head.appendChild(headRow);
      table.appendChild(head);

      renderBody(visibleRows);
      table.appendChild(body);
      node.appendChild(table);
      if (maxRows && result.values.length > maxRows) {
        var truncated = document.createElement("p");
        truncated.className = "meta";
        truncated.textContent = "Showing the first " + maxRows + " rows.";
        node.appendChild(truncated);
      }
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
        renderRows(tablePreview, rows, "This table has no rows.", maxPreviewRows, true);
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
        explorer.removeAttribute("hidden");
      }
      renderTable(tableSelect.value);
    }

    function populateQuerySelector() {
      if (!querySelect || querySelectorPopulated) {
        return;
      }
      querySelectorPopulated = true;
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
      querySelect.value = "0";
      if (queryInput) {
        queryInput.value = presetQueries[0].sql;
      }
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
        var normalized = query.trim().replace(/;+$/, "").trim();
        var result = database.exec("SELECT * FROM (" + normalized + ") LIMIT " + maxQueryRows)[0];
        renderRows(queryResults, result, "The query returned no rows.", maxQueryRows);
        setQueryStatus("Query completed locally. Results are capped at " + maxQueryRows + " rows.", "ok");
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
        if (explorer) {
          explorer.removeAttribute("hidden");
        }
        if (queryPanel) {
          queryPanel.removeAttribute("hidden");
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
    tableTabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        activateTableTab(tab.dataset.sqliteTab || "data");
      });
    });
    activateTableTab("data");
  });
})();
