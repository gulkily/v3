(function () {
  function closestRow(el) {
    while (el && el.nodeType === 1) {
      if (el.classList && el.classList.contains("paned-list-row")) {
        return el;
      }
      el = el.parentNode;
    }
    return null;
  }

  document.addEventListener("DOMContentLoaded", function () {
    var listBody = document.querySelector("[data-paned-list-body]");
    var contentPane = document.querySelector("[data-paned-content-pane]");
    if (!listBody || !contentPane) {
      return;
    }

    var rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));
    var contentPosts = Array.prototype.slice.call(contentPane.querySelectorAll(".paned-content-post"));

    function visibleRows() {
      return rows.filter(function (row) {
        return !row.hidden;
      });
    }

    function selectPost(postId) {
      contentPosts.forEach(function (article) {
        article.hidden = article.getAttribute("data-paned-content-post-id") !== postId;
      });
      rows.forEach(function (row) {
        var isSelected = row.getAttribute("data-paned-post-id") === postId;
        row.classList.toggle("paned-list-row--selected", isSelected);
        row.setAttribute("aria-selected", isSelected ? "true" : "false");
        row.setAttribute("tabindex", isSelected ? "0" : "-1");
      });
    }

    function toggleCollapse(row) {
      var depth = parseInt(row.getAttribute("data-paned-depth"), 10);
      var toggle = row.querySelector("[data-paned-toggle]");
      var countMarker = row.querySelector("[data-paned-collapse-count]");
      var collapsing = !countMarker || countMarker.hidden;
      var index = rows.indexOf(row);
      var currentTabRow = rows.filter(function (r) {
        return r.getAttribute("tabindex") === "0";
      })[0];
      var currentTabRowNowHidden = false;

      for (var i = index + 1; i < rows.length; i++) {
        var candidateDepth = parseInt(rows[i].getAttribute("data-paned-depth"), 10);
        if (candidateDepth <= depth) {
          break;
        }
        rows[i].hidden = collapsing;
        if (collapsing && rows[i] === currentTabRow) {
          currentTabRowNowHidden = true;
        }
      }

      if (currentTabRowNowHidden) {
        currentTabRow.setAttribute("tabindex", "-1");
        row.setAttribute("tabindex", "0");
      }

      if (toggle) {
        toggle.innerHTML = collapsing ? "&#9656;" : "&#9662;";
      }
      if (countMarker) {
        countMarker.hidden = !collapsing;
      }
    }

    listBody.addEventListener("click", function (event) {
      var toggle = event.target.closest ? event.target.closest("[data-paned-toggle]") : null;
      if (toggle) {
        var toggleRow = closestRow(toggle);
        if (toggleRow) {
          toggleCollapse(toggleRow);
        }
        return;
      }

      var row = closestRow(event.target);
      if (row) {
        selectPost(row.getAttribute("data-paned-post-id"));
      }
    });

    function currentSelectedId() {
      var selectedRow = null;
      rows.forEach(function (row) {
        if (row.classList.contains("paned-list-row--selected")) {
          selectedRow = row;
        }
      });
      if (selectedRow) {
        return selectedRow.getAttribute("data-paned-post-id");
      }

      var visiblePost = null;
      contentPosts.forEach(function (post) {
        if (!visiblePost && !post.hidden) {
          visiblePost = post;
        }
      });
      return visiblePost ? visiblePost.getAttribute("data-paned-content-post-id") : null;
    }

    function stepSelection(delta) {
      var visible = visibleRows();
      if (visible.length === 0) {
        return;
      }

      var currentId = currentSelectedId();
      var index = -1;
      for (var i = 0; i < visible.length; i++) {
        if (visible[i].getAttribute("data-paned-post-id") === currentId) {
          index = i;
          break;
        }
      }

      var nextIndex = index === -1 ? 0 : index + delta;
      if (nextIndex < 0 || nextIndex >= visible.length) {
        return;
      }

      selectPost(visible[nextIndex].getAttribute("data-paned-post-id"));
    }

    var prevButton = document.querySelector("[data-paned-prev]");
    var nextButton = document.querySelector("[data-paned-next]");
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

    listBody.addEventListener("keydown", function (event) {
      if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
      }

      var visible = visibleRows();
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
      selectPost(nextRow.getAttribute("data-paned-post-id"));
      nextRow.focus();
    });
  });
})();
