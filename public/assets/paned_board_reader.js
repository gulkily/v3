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
    var placeholder = contentPane.querySelector("[data-paned-board-content-placeholder]");
    var contentPosts = Array.prototype.slice.call(contentPane.querySelectorAll("[data-paned-board-content-post-id]"));
    var statusCount = document.querySelector("[data-paned-board-status-count]");
    var totalThreadCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-total-count"), 10) : rows.length;
    var totalTagCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-board-tag-count"), 10) : folderItems.length;

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
    }

    function selectThread(threadId) {
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

    function currentTagFromUrl() {
      return new URLSearchParams(location.search).get("tag") || "";
    }

    function urlForTag(tag) {
      return tag === "" ? "/forte" : "/forte?tag=" + encodeURIComponent(tag);
    }

    function applyFolderSelection(tag) {
      selectFolder(tag);
      if (tag !== currentTagFromUrl()) {
        history.pushState({ paneTag: tag }, "", urlForTag(tag));
      }
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

    window.addEventListener("popstate", function () {
      selectFolder(currentTagFromUrl());
    });

    listBody.addEventListener("click", function (event) {
      var row = event.target.closest ? event.target.closest(".paned-list-row") : null;
      if (row) {
        selectThread(row.getAttribute("data-paned-thread-id"));
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
      selectThread(nextRow.getAttribute("data-paned-thread-id"));
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

      selectThread(visible[nextIndex].getAttribute("data-paned-thread-id"));
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

    var replyCache = {};

    function toggleReplies(button) {
      var threadId = button.getAttribute("data-paned-reply-toggle");
      var container = contentPane.querySelector('[data-paned-reply-container="' + threadId + '"]');
      if (!container) {
        return;
      }

      var expanded = button.getAttribute("aria-expanded") === "true";
      if (expanded) {
        container.hidden = true;
        button.setAttribute("aria-expanded", "false");
        button.textContent = button.getAttribute("data-paned-reply-label-collapsed");
        return;
      }

      button.setAttribute("aria-expanded", "true");
      button.textContent = button.getAttribute("data-paned-reply-label-expanded");
      container.hidden = false;

      if (replyCache[threadId]) {
        container.innerHTML = replyCache[threadId];
        return;
      }

      var url = button.getAttribute("data-paned-reply-url");
      button.disabled = true;
      fetch(url)
        .then(function (response) {
          if (!response.ok) {
            throw new Error("Request failed: " + response.status);
          }
          return response.text();
        })
        .then(function (html) {
          replyCache[threadId] = html;
          container.innerHTML = html;
        })
        .catch(function () {
          container.innerHTML = '<p class="paned-reply-error">Could not load replies.</p>';
        })
        .then(function () {
          button.disabled = false;
        });
    }

    contentPane.addEventListener("click", function (event) {
      var toggle = event.target.closest ? event.target.closest("[data-paned-reply-toggle]") : null;
      if (toggle) {
        toggleReplies(toggle);
      }
    });
  });
})();
