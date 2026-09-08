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
  });
})();
