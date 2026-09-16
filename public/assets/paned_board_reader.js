(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var folderTree = document.querySelector("[data-paned-folder-tree]");
    var listBody = document.querySelector("[data-paned-board-list-body]");
    var contentPane = document.querySelector("[data-paned-board-content-pane]");
    if (!folderTree || !listBody || !contentPane) {
      return;
    }

    var folderItems = Array.prototype.slice.call(folderTree.querySelectorAll("[data-paned-folder]"));
    var rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));
    var originalRowOrder = rows.slice();
    var placeholder = contentPane.querySelector("[data-paned-board-content-placeholder]");
    var contentPosts = Array.prototype.slice.call(contentPane.querySelectorAll("[data-paned-board-content-post-id]"));
    var statusCount = document.querySelector("[data-paned-board-status-count]");
    var totalThreadCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-total-count"), 10) : rows.length;
    var totalTagCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-tag-count"), 10) : folderItems.length;
    var replyButton = document.querySelector("[data-paned-board-reply]");
    var composePanel = document.querySelector("[data-paned-compose-panel]");
    var newButton = document.querySelector("[data-paned-board-new]");
    var newThreadDialog = document.querySelector("[data-paned-new-thread-dialog]");
    var newThreadCancelButton = document.querySelector("[data-paned-new-thread-cancel]");

    function currentTagFromUrl() {
      return new URLSearchParams(location.search).get("tag") || "";
    }

    function currentSelectedThreadId() {
      var selectedRow = rows.filter(function (row) {
        return row.classList.contains("paned-list-row--selected");
      })[0];
      return selectedRow ? selectedRow.getAttribute("data-paned-thread-id") : "";
    }

    function composeReturnToUrl(threadId) {
      var params = new URLSearchParams();
      var tag = currentTagFromUrl();
      if (tag !== "") {
        params.set("tag", tag);
      }
      if (threadId !== "") {
        params.set("selected", threadId);
      }
      var qs = params.toString();
      return "/forte" + (qs ? "?" + qs : "");
    }

    function setComposeTarget(threadId) {
      if (!composePanel) {
        return;
      }
      var threadIdField = composePanel.querySelector('input[name="thread_id"]');
      var parentIdField = composePanel.querySelector('input[name="parent_id"]');
      var returnToField = composePanel.querySelector('input[name="return_to"]');
      if (threadIdField) {
        threadIdField.value = threadId;
      }
      if (parentIdField) {
        parentIdField.value = threadId;
      }
      if (returnToField) {
        returnToField.value = composeReturnToUrl(threadId);
      }
    }

    function firstVisibleRow() {
      for (var i = 0; i < rows.length; i++) {
        if (!rows[i].hidden) {
          return rows[i];
        }
      }
      return null;
    }

    function resetContentPane() {
      contentPosts.forEach(function (article) {
        article.hidden = true;
      });
      rows.forEach(function (row) {
        row.classList.remove("paned-list-row--selected");
        row.setAttribute("aria-selected", "false");
        row.setAttribute("tabindex", "-1");
      });
      var fallback = firstVisibleRow();
      if (fallback) {
        fallback.setAttribute("tabindex", "0");
      }
      if (placeholder) {
        placeholder.hidden = false;
      }
      if (replyButton) {
        replyButton.disabled = true;
      }
      if (composePanel) {
        composePanel.hidden = true;
      }
      setComposeTarget("");
    }

    function clearHighlights() {
      Array.prototype.slice.call(contentPane.querySelectorAll(".paned-highlight-new")).forEach(function (el) {
        el.classList.remove("paned-highlight-new");
      });
    }

    function selectThread(threadId) {
      clearHighlights();
      if (placeholder) {
        placeholder.hidden = true;
      }
      contentPosts.forEach(function (article) {
        article.hidden = article.getAttribute("data-paned-board-content-post-id") !== threadId;
      });
      rows.forEach(function (row) {
        var isSelected = row.getAttribute("data-paned-thread-id") === threadId;
        row.classList.toggle("paned-list-row--selected", isSelected);
        row.setAttribute("aria-selected", isSelected ? "true" : "false");
        row.setAttribute("tabindex", isSelected ? "0" : "-1");
      });
      if (replyButton) {
        replyButton.disabled = false;
      }
      setComposeTarget(threadId);
    }

    function restoreSelectionFromUrl(scrollRowIntoView) {
      var params = new URLSearchParams(location.search);
      var selected = params.get("selected") || "";
      var createdPostId = params.get("created_post_id") || "";
      if (!/^[A-Za-z0-9._:-]+$/.test(createdPostId)) {
        createdPostId = "";
      }
      var selectedRow = selected === "" ? null : rows.filter(function (row) {
        return row.getAttribute("data-paned-thread-id") === selected && !row.hidden;
      })[0];

      if (!selectedRow) {
        resetContentPane();
        return;
      }

      selectThread(selected);
      if (scrollRowIntoView) {
        selectedRow.scrollIntoView({ block: "nearest" });
      }
      if (createdPostId !== "") {
        var highlightNode = contentPane.querySelector('[data-paned-reply-post-id="' + createdPostId + '"]');
        if (highlightNode) {
          highlightNode.classList.add("paned-highlight-new");
          highlightNode.scrollIntoView({ block: "nearest" });
        }
      }
    }

    function selectFolder(tag) {
      folderItems.forEach(function (item) {
        var isSelected = item.getAttribute("data-paned-folder") === tag;
        item.classList.toggle("paned-folder-item--selected", isSelected);
        item.setAttribute("aria-selected", isSelected ? "true" : "false");
        item.setAttribute("tabindex", isSelected ? "0" : "-1");
      });

      var selectedRowNowHidden = false;
      var currentTabRow = rows.filter(function (row) {
        return row.getAttribute("tabindex") === "0";
      })[0];
      var currentTabRowNowHidden = false;
      var visibleCount = 0;
      rows.forEach(function (row) {
        var visible = tag === "" || (row.getAttribute("data-paned-thread-tags") || "").split(",").indexOf(tag) !== -1;
        row.hidden = !visible;
        if (visible) {
          visibleCount++;
        }
        if (!visible && row.classList.contains("paned-list-row--selected")) {
          selectedRowNowHidden = true;
        }
        if (!visible && row === currentTabRow) {
          currentTabRowNowHidden = true;
        }
      });

      if (selectedRowNowHidden) {
        resetContentPane();
      } else if (currentTabRowNowHidden) {
        // Selection itself wasn't affected, but the roving tabindex was
        // sitting on a row that's no longer visible - move it forward.
        if (currentTabRow) {
          currentTabRow.setAttribute("tabindex", "-1");
        }
        var fallback = firstVisibleRow();
        if (fallback) {
          fallback.setAttribute("tabindex", "0");
        }
      }

      if (statusCount) {
        statusCount.textContent = tag === ""
          ? totalThreadCount + " thread" + (totalThreadCount === 1 ? "" : "s") + " · " + totalTagCount + " tags"
          : "Showing " + visibleCount + " of " + totalThreadCount + " threads (#" + tag + ")";
      }
    }

    function urlForState(tag, sortColumn, sortDir, selectedThreadId) {
      var params = new URLSearchParams();
      if (tag !== "") {
        params.set("tag", tag);
      }
      if (sortColumn !== "") {
        params.set("sort", sortColumn);
        params.set("dir", sortDir);
      }
      if (selectedThreadId) {
        params.set("selected", selectedThreadId);
      }
      var qs = params.toString();
      return "/forte" + (qs ? "?" + qs : "");
    }

    function pushStateIfChanged(url) {
      if (url !== location.pathname + location.search) {
        history.pushState(null, "", url);
      }
    }

    function replaceStateIfChanged(url) {
      if (url !== location.pathname + location.search) {
        history.replaceState(null, "", url);
      }
    }

    function readHistoryMode() {
      try {
        var value = localStorage.getItem("forte-board-history-mode");
        if (value === "always" || value === "never") {
          return value;
        }
      } catch (error) {
        // Storage unavailable (private browsing, disabled, etc.) -- fall back to the default.
      }
      return "click-only";
    }

    var historyMode = readHistoryMode();

    function selectionUrl(threadId) {
      var sort = currentSortState();
      return urlForState(currentTagFromUrl(), sort.column, sort.dir, threadId);
    }

    function syncSelectionUrlForClick(threadId) {
      var url = selectionUrl(threadId);
      if (historyMode === "never") {
        replaceStateIfChanged(url);
      } else {
        pushStateIfChanged(url);
      }
    }

    function syncSelectionUrlForStepping(threadId) {
      var url = selectionUrl(threadId);
      if (historyMode === "always") {
        pushStateIfChanged(url);
      } else {
        replaceStateIfChanged(url);
      }
    }

    function applyFolderSelection(tag) {
      selectFolder(tag);
      var sort = currentSortState();
      pushStateIfChanged(urlForState(tag, sort.column, sort.dir, currentSelectedThreadId()));
      setComposeTarget(currentSelectedThreadId());
    }

    folderTree.addEventListener("click", function (event) {
      var item = event.target.closest ? event.target.closest("[data-paned-folder]") : null;
      if (item) {
        applyFolderSelection(item.getAttribute("data-paned-folder"));
      }
    });

    folderTree.addEventListener("keydown", function (event) {
      if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
      }

      var currentIndex = folderItems.indexOf(document.activeElement);
      if (currentIndex === -1) {
        return;
      }

      var nextIndex = currentIndex + (event.key === "ArrowDown" ? 1 : -1);
      if (nextIndex < 0 || nextIndex >= folderItems.length) {
        return;
      }

      event.preventDefault();
      var nextItem = folderItems[nextIndex];
      applyFolderSelection(nextItem.getAttribute("data-paned-folder"));
      nextItem.focus();
    });

    function restoreOriginalOrder() {
      rows = originalRowOrder.slice();
      rows.forEach(function (row) {
        listBody.appendChild(row);
      });
      sortButtons.forEach(function (button) {
        button.parentElement.setAttribute("aria-sort", "none");
      });
    }

    window.addEventListener("popstate", function () {
      selectFolder(currentTagFromUrl());
      restoreSelectionFromUrl(true);

      var params = new URLSearchParams(location.search);
      var sortColumn = params.get("sort") || "";
      var sortDir = params.get("dir") || "";
      if (sortColumn === "") {
        restoreOriginalOrder();
        return;
      }

      if (sortDir !== "asc" && sortDir !== "desc") {
        sortDir = sortDefaultDir[sortColumn] || "asc";
      }
      applySort(sortColumn, sortDir);
    });

    listBody.addEventListener("click", function (event) {
      var row = event.target.closest ? event.target.closest(".paned-list-row") : null;
      if (row) {
        var threadId = row.getAttribute("data-paned-thread-id");
        selectThread(threadId);
        syncSelectionUrlForClick(threadId);
      }
    });

    listBody.addEventListener("keydown", function (event) {
      if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
      }

      var visible = rows.filter(function (row) {
        return !row.hidden;
      });
      var currentIndex = visible.indexOf(document.activeElement);
      if (currentIndex === -1) {
        return;
      }

      var nextIndex = currentIndex + (event.key === "ArrowDown" ? 1 : -1);
      if (nextIndex < 0 || nextIndex >= visible.length) {
        return;
      }

      event.preventDefault();
      var nextRow = visible[nextIndex];
      var nextRowThreadId = nextRow.getAttribute("data-paned-thread-id");
      selectThread(nextRowThreadId);
      syncSelectionUrlForStepping(nextRowThreadId);
      nextRow.focus();
    });

    function stepSelection(delta) {
      var visible = rows.filter(function (row) {
        return !row.hidden;
      });
      if (visible.length === 0) {
        return;
      }

      var currentId = null;
      rows.forEach(function (row) {
        if (row.classList.contains("paned-list-row--selected")) {
          currentId = row.getAttribute("data-paned-thread-id");
        }
      });

      var index = -1;
      for (var i = 0; i < visible.length; i++) {
        if (visible[i].getAttribute("data-paned-thread-id") === currentId) {
          index = i;
          break;
        }
      }

      var nextIndex = index === -1 ? 0 : index + delta;
      if (nextIndex < 0 || nextIndex >= visible.length) {
        return;
      }

      var steppedThreadId = visible[nextIndex].getAttribute("data-paned-thread-id");
      selectThread(steppedThreadId);
      syncSelectionUrlForStepping(steppedThreadId);
    }

    var sortDefaultDir = { subject: "asc", from: "asc", date: "desc", replies: "desc" };
    var sortHead = document.querySelector("[data-paned-sort-head]");
    var sortButtons = sortHead ? Array.prototype.slice.call(sortHead.querySelectorAll("[data-paned-sort-column]")) : [];

    function currentSortState() {
      for (var i = 0; i < sortButtons.length; i++) {
        var ariaSort = sortButtons[i].parentElement.getAttribute("aria-sort");
        if (ariaSort === "ascending" || ariaSort === "descending") {
          return {
            column: sortButtons[i].getAttribute("data-paned-sort-column"),
            dir: ariaSort === "descending" ? "desc" : "asc",
          };
        }
      }
      return { column: "", dir: "" };
    }

    function sortValueFor(row, column) {
      if (column === "replies") {
        return parseInt(row.getAttribute("data-paned-sort-replies"), 10) || 0;
      }
      return row.getAttribute("data-paned-sort-" + column) || "";
    }

    function applySort(column, dir) {
      rows.sort(function (a, b) {
        var va = sortValueFor(a, column);
        var vb = sortValueFor(b, column);
        if (va < vb) {
          return -1;
        }
        if (va > vb) {
          return 1;
        }
        return 0;
      });
      if (dir === "desc") {
        rows.reverse();
      }
      rows.forEach(function (row) {
        listBody.appendChild(row);
      });

      sortButtons.forEach(function (button) {
        var isActive = button.getAttribute("data-paned-sort-column") === column;
        button.parentElement.setAttribute("aria-sort", isActive ? (dir === "desc" ? "descending" : "ascending") : "none");
      });
    }

    if (sortHead) {
      sortHead.addEventListener("click", function (event) {
        var button = event.target.closest ? event.target.closest("[data-paned-sort-column]") : null;
        if (!button) {
          return;
        }

        var column = button.getAttribute("data-paned-sort-column");
        var current = currentSortState();
        var dir = current.column === column
          ? (current.dir === "desc" ? "asc" : "desc")
          : (sortDefaultDir[column] || "asc");

        applySort(column, dir);
        pushStateIfChanged(urlForState(currentTagFromUrl(), column, dir, currentSelectedThreadId()));
      });
    }

    var prevButton = document.querySelector("[data-paned-board-prev]");
    var nextButton = document.querySelector("[data-paned-board-next]");
    if (prevButton) {
      prevButton.addEventListener("click", function () {
        stepSelection(-1);
      });
    }
    if (nextButton) {
      nextButton.addEventListener("click", function () {
        stepSelection(1);
      });
    }

    if (replyButton && composePanel) {
      replyButton.addEventListener("click", function () {
        composePanel.hidden = !composePanel.hidden;
        if (!composePanel.hidden) {
          composePanel.scrollIntoView({ block: "nearest" });
          var bodyField = composePanel.querySelector('textarea[name="body"]');
          if (bodyField) {
            bodyField.focus();
          }
        }
      });
    }

    if (newButton && newThreadDialog && typeof newThreadDialog.showModal === "function") {
      newButton.addEventListener("click", function () {
        var returnToField = newThreadDialog.querySelector('input[name="return_to"]');
        if (returnToField) {
          returnToField.value = composeReturnToUrl("");
        }
        newThreadDialog.showModal();
        var subjectField = newThreadDialog.querySelector('input[name="subject"]');
        if (subjectField) {
          subjectField.focus();
        }
      });
    }

    if (newThreadCancelButton && newThreadDialog) {
      newThreadCancelButton.addEventListener("click", function () {
        newThreadDialog.close();
      });
    }

    restoreSelectionFromUrl(true);
  });
})();
