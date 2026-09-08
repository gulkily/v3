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
    var titleLabel = document.querySelector("[data-paned-board-title-label]");

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
      rows.forEach(function (row) {
        var visible = tag === "" || (row.getAttribute("data-paned-thread-tags") || "").split(",").indexOf(tag) !== -1;
        row.hidden = !visible;
        if (!visible && row.classList.contains("paned-list-row--selected")) {
          selectedRowNowHidden = true;
        }
      });

      if (selectedRowNowHidden) {
        resetContentPane();
      }

      if (titleLabel) {
        titleLabel.textContent = "Forte — [" + (tag === "" ? "All Threads" : "#" + tag) + "]";
      }
    }

    folderTree.addEventListener("click", function (event) {
      var item = event.target.closest ? event.target.closest("[data-paned-folder]") : null;
      if (item) {
        selectFolder(item.getAttribute("data-paned-folder"));
      }
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
  });
})();
