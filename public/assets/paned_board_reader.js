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

    function resetContentPane() {
      contentPosts.forEach(function (article) {
        article.hidden = true;
      });
      rows.forEach(function (row) {
        row.classList.remove("paned-list-row--selected");
      });
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
        row.classList.toggle("paned-list-row--selected", row.getAttribute("data-paned-thread-id") === threadId);
      });
    }

    function selectFolder(tag) {
      folderItems.forEach(function (item) {
        item.classList.toggle("paned-folder-item--selected", item.getAttribute("data-paned-folder") === tag);
      });

      var selectedRowNowHidden = false;
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
      });

      if (selectedRowNowHidden) {
        resetContentPane();
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

    folderTree.addEventListener("click", function (event) {
      var item = event.target.closest ? event.target.closest("[data-paned-folder]") : null;
      if (item) {
        var tag = item.getAttribute("data-paned-folder");
        selectFolder(tag);
        if (tag !== currentTagFromUrl()) {
          history.pushState({ paneTag: tag }, "", urlForTag(tag));
        }
      }
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
