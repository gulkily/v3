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
    var statusCount = document.querySelector("[data-paned-board-status-count]");
    var totalThreadCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-total-count"), 10) : rows.length;
    var totalTagCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-tag-count"), 10) : folderItems.length;
    var replyButton = document.querySelector("[data-paned-board-reply]");
    var composePanel = document.querySelector("[data-paned-compose-panel]");
    var newButton = document.querySelector("[data-paned-board-new]");
    var newThreadDialog = document.querySelector("[data-paned-new-thread-dialog]");
    var newThreadCancelButton = document.querySelector("[data-paned-new-thread-cancel]");
    var activeDetailRequest = 0;
    var activeThreadId = "";
    var preloadQueue = [];
    var preloadInFlight = false;
    var preloadScheduled = false;
    var maxCachedPanes = 24;
    var maxCachedBytes = 4 * 1024 * 1024;
    var paneCache = createPaneCache(maxCachedPanes, maxCachedBytes);

    function contentArticle() {
      return contentPane.querySelector("[data-paned-board-content-post-id]");
    }

    function detailStatus() {
      return contentPane.querySelector("[data-paned-board-detail-status]");
    }

    function setDetailStatus(message, retry) {
      var existing = detailStatus();
      var html = '<p class="meta" data-paned-board-detail-status>' + message + (retry ? ' <button type="button" data-paned-board-retry>Retry</button>' : '') + '</p>';
      if (existing) {
        existing.outerHTML = html;
      } else if (composePanel) {
        composePanel.insertAdjacentHTML("beforebegin", html);
      } else {
        contentPane.insertAdjacentHTML("beforeend", html);
      }
    }

    function clearDetailStatus() {
      var status = detailStatus();
      if (status) {
        status.remove();
      }
    }

    function htmlBytes(html) {
      return String(html).length * 2;
    }

    function createPaneCache(maxEntries, maxBytes) {
      var entries = {};
      var order = [];
      var bytes = 0;

      function remove(threadId) {
        if (!Object.prototype.hasOwnProperty.call(entries, threadId)) {
          return;
        }
        bytes -= entries[threadId].bytes;
        delete entries[threadId];
        order = order.filter(function (id) { return id !== threadId; });
      }

      function evict(excludedThreadId) {
        var candidate = order.filter(function (id) { return id !== excludedThreadId; })[0];
        if (!candidate) {
          return false;
        }
        remove(candidate);
        return true;
      }

      return {
        get: function (threadId) {
          if (!Object.prototype.hasOwnProperty.call(entries, threadId)) {
            return null;
          }
          order = order.filter(function (id) { return id !== threadId; });
          order.push(threadId);
          return entries[threadId].html;
        },
        set: function (threadId, html, excludedThreadId) {
          var paneBytes = htmlBytes(html);
          if (paneBytes > maxBytes) {
            return false;
          }
          remove(threadId);
          while ((order.length >= maxEntries || bytes + paneBytes > maxBytes) && evict(excludedThreadId)) {
          }
          if (order.length >= maxEntries || bytes + paneBytes > maxBytes) {
            return false;
          }
          entries[threadId] = { html: html, bytes: paneBytes };
          order.push(threadId);
          bytes += paneBytes;
          return true;
        },
        clear: function () {
          entries = {};
          order = [];
          bytes = 0;
        },
        snapshot: function () {
          return { count: order.length, bytes: bytes, order: order.slice() };
        }
      };
    }

    function cachedPane(threadId) {
      return paneCache.get(threadId);
    }

    function cachePane(threadId, html) {
      paneCache.set(threadId, html, currentSelectedThreadId());
    }

    function clearPaneCache() {
      paneCache.clear();
    }

    window.ForteBoardReader = { createPaneCache: createPaneCache };

    function preloadCandidates() {
      var selectedThreadId = currentSelectedThreadId();
      var visible = rows.filter(function (row) { return !row.hidden; });
      var index = visible.findIndex(function (row) {
        return row.getAttribute("data-paned-thread-id") === selectedThreadId;
      });
      if (index === -1) {
        return [];
      }

      var candidates = [];
      for (var offset = 1; offset < visible.length; offset++) {
        [index + offset, index - offset].forEach(function (candidateIndex) {
          var row = visible[candidateIndex];
          var threadId = row ? row.getAttribute("data-paned-thread-id") : "";
          if (threadId && !cachedPane(threadId)) {
            candidates.push(threadId);
          }
        });
      }

      return candidates;
    }

    function preloadNextThread() {
      preloadScheduled = false;
      if (preloadInFlight || preloadQueue.length === 0) {
        return;
      }
      var threadId = preloadQueue.shift();
      if (!threadId || cachedPane(threadId)) {
        schedulePreload();
        return;
      }

      preloadInFlight = true;
      fetch("/api/forte_thread_detail?thread_id=" + encodeURIComponent(threadId))
        .then(function (response) {
          if (!response.ok) {
            throw new Error("thread preload failed");
          }
          return response.json();
        })
        .then(function (data) {
          if (data.status === "ok" && typeof data.html === "string") {
            cachePane(threadId, data.html);
          }
        })
        .catch(function () {
          // A speculative preload must never disrupt the active pane.
        })
        .then(function () {
          preloadInFlight = false;
          schedulePreload();
        });
    }

    function schedulePreload() {
      if (preloadScheduled || preloadInFlight) {
        return;
      }
      preloadQueue = preloadCandidates();
      if (preloadQueue.length === 0) {
        return;
      }
      preloadScheduled = true;
      if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(preloadNextThread, { timeout: 1000 });
      } else {
        window.setTimeout(preloadNextThread, 0);
      }
    }

    function insertContentArticle(html) {
      var existing = contentArticle();
      if (existing) {
        existing.outerHTML = html;
      } else if (composePanel) {
        composePanel.insertAdjacentHTML("beforebegin", html);
      } else {
        contentPane.insertAdjacentHTML("beforeend", html);
      }

      var article = contentArticle();
      if (article && window.ForumThreadReactions && typeof window.ForumThreadReactions.bindWithin === "function") {
        window.ForumThreadReactions.bindWithin(article);
      }
      return article;
    }

    function showDetailFailure(threadId) {
      var article = contentArticle();
      if (article && article.getAttribute("data-paned-board-content-post-id") !== threadId) {
        setDetailStatus("Failed to load this thread.", true);
        return;
      }

      insertContentArticle(
        '<article class="paned-content-post" data-paned-board-content-post-id="' + threadId + '"><p class="meta">Failed to load this thread. <button type="button" data-paned-board-retry>Retry</button></p></article>'
      );
    }

    function loadThread(threadId, createdPostId) {
      var cached = cachedPane(threadId);
      if (cached) {
        insertContentArticle(cached);
        clearDetailStatus();
        schedulePreload();
        return;
      }
      var requestId = ++activeDetailRequest;
      var article = contentArticle();
      if (article) {
        setDetailStatus("Loading thread…", false);
      } else {
        insertContentArticle(
          '<article class="paned-content-post" data-paned-board-content-post-id="' + threadId + '"><p class="meta">Loading thread…</p></article>'
        );
      }

      var params = new URLSearchParams();
      params.set("thread_id", threadId);
      if (createdPostId) {
        params.set("created_post_id", createdPostId);
      }
      fetch("/api/forte_thread_detail?" + params.toString())
        .then(function (response) {
          if (!response.ok) {
            throw new Error("thread detail fetch failed");
          }
          return response.json();
        })
        .then(function (data) {
          if (data.status !== "ok" || typeof data.html !== "string") {
            throw new Error("thread detail fetch failed");
          }
          if (requestId === activeDetailRequest && currentSelectedThreadId() === threadId) {
            cachePane(threadId, data.html);
            insertContentArticle(data.html);
            clearDetailStatus();
            schedulePreload();
          }
        })
        .catch(function () {
          if (requestId === activeDetailRequest && currentSelectedThreadId() === threadId) {
            showDetailFailure(threadId);
          }
        });
    }

    function currentTagFromUrl() {
      return new URLSearchParams(location.search).get("tag") || "";
    }

    function currentSelectedThreadId() {
      var selectedRow = rows.filter(function (row) {
        return row.classList.contains("paned-list-row--selected");
      })[0];
      return selectedRow ? selectedRow.getAttribute("data-paned-thread-id") : activeThreadId;
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
      activeDetailRequest++;
      activeThreadId = "";
      var article = contentArticle();
      if (article) {
        article.remove();
      }
      clearDetailStatus();
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

    function selectThread(threadId, createdPostId) {
      activeThreadId = threadId;
      clearHighlights();
      if (placeholder) {
        placeholder.hidden = true;
      }
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

      var article = contentArticle();
      if (!article || article.getAttribute("data-paned-board-content-post-id") !== threadId) {
        loadThread(threadId, createdPostId || "");
      } else {
        cachePane(threadId, article.outerHTML);
        schedulePreload();
      }
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

      // A thread excluded from the board's own listing (identity/
      // bootstrap/approval-only) has no row here at all, even though the
      // server still rendered its content article when linked to directly
      // (see fetchThreadById()) - fall back to that article before giving
      // up; it just has no row to highlight or scroll into view.
      var article = contentArticle();
      var selectedPost = selectedRow || selected === "" || !article || article.getAttribute("data-paned-board-content-post-id") !== selected
        ? null
        : article;

      if (!selectedRow && !selectedPost) {
        resetContentPane();
        return;
      }

      selectThread(selected, createdPostId);
      if (selectedRow && scrollRowIntoView) {
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

    contentPane.addEventListener("click", function (event) {
      var retry = event.target.closest ? event.target.closest("[data-paned-board-retry]") : null;
      if (retry) {
        loadThread(currentSelectedThreadId(), "");
      }
    });

    document.addEventListener("forum:thread-reaction-applied", function () {
      clearPaneCache();
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
      visible[nextIndex].scrollIntoView({ block: "nearest" });
    }

    var sortDefaultDir = { subject: "asc", from: "asc", date: "desc", replies: "desc", score: "desc" };
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
      if (column === "replies" || column === "score") {
        return parseInt(row.getAttribute("data-paned-sort-" + column), 10) || 0;
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

    var profileSummaryDialog = document.querySelector("[data-paned-profile-summary-dialog]");
    var profileSummaryClose = document.querySelector("[data-paned-profile-summary-close]");

    function parseColonValue(text, key) {
      var prefix = key + ": ";
      var lines = String(text).split("\n");
      for (var i = 0; i < lines.length; i++) {
        if (lines[i].indexOf(prefix) === 0) {
          return lines[i].slice(prefix.length);
        }
      }
      return "";
    }

    function showProfileSummary(profileSlug, fullHref) {
      if (!profileSummaryDialog) {
        return;
      }

      var title = profileSummaryDialog.querySelector('[data-role="profile-summary-title"]');
      var loading = profileSummaryDialog.querySelector('[data-role="profile-summary-loading"]');
      var content = profileSummaryDialog.querySelector('[data-role="profile-summary-content"]');
      var errorNode = profileSummaryDialog.querySelector('[data-role="profile-summary-error"]');
      var approvedNode = profileSummaryDialog.querySelector('[data-role="profile-summary-approved"]');
      var approvedByRow = profileSummaryDialog.querySelector('[data-role="profile-summary-approved-by-row"]');
      var approvedByNode = profileSummaryDialog.querySelector('[data-role="profile-summary-approved-by"]');
      var threadsNode = profileSummaryDialog.querySelector('[data-role="profile-summary-threads"]');
      var postsNode = profileSummaryDialog.querySelector('[data-role="profile-summary-posts"]');
      var fullLink = profileSummaryDialog.querySelector('[data-role="profile-summary-full-link"]');

      if (title) {
        title.textContent = "Profile";
      }
      if (loading) {
        loading.hidden = false;
      }
      if (content) {
        content.hidden = true;
      }
      if (errorNode) {
        errorNode.hidden = true;
      }
      if (fullLink) {
        fullLink.href = fullHref;
      }

      profileSummaryDialog.showModal();

      fetch("/api/get_profile?profile_slug=" + encodeURIComponent(profileSlug))
        .then(function (response) {
          if (!response.ok) {
            throw new Error("profile fetch failed");
          }
          return response.text();
        })
        .then(function (text) {
          if (loading) {
            loading.hidden = true;
          }
          var username = parseColonValue(text, "Username");
          var approved = parseColonValue(text, "Approved") === "yes";
          var approvedBy = parseColonValue(text, "Approved-By");
          if (title) {
            title.textContent = username || "Profile";
          }
          if (approvedNode) {
            approvedNode.textContent = approved ? "yes" : "no";
          }
          if (approvedByRow && approvedByNode) {
            if (approved && approvedBy !== "") {
              approvedByNode.textContent = approvedBy;
              approvedByRow.hidden = false;
            } else {
              approvedByRow.hidden = true;
            }
          }
          if (threadsNode) {
            threadsNode.textContent = parseColonValue(text, "Threads");
          }
          if (postsNode) {
            postsNode.textContent = parseColonValue(text, "Posts");
          }
          if (content) {
            content.hidden = false;
          }
        })
        .catch(function () {
          if (loading) {
            loading.hidden = true;
          }
          if (errorNode) {
            errorNode.hidden = false;
          }
        });
    }

    document.addEventListener("click", function (event) {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
      }

      var link = event.target.closest ? event.target.closest("[data-forte-author-link]") : null;
      if (!link) {
        return;
      }

      var profileSlug = link.getAttribute("data-profile-slug") || "";
      if (profileSlug === "" || !profileSummaryDialog || typeof profileSummaryDialog.showModal !== "function") {
        return;
      }

      event.preventDefault();
      showProfileSummary(profileSlug, link.getAttribute("href") || "#");
    });

    if (profileSummaryClose && profileSummaryDialog) {
      profileSummaryClose.addEventListener("click", function () {
        profileSummaryDialog.close();
      });
    }

    restoreSelectionFromUrl(true);
  });
})();
