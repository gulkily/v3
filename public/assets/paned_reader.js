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

    function selectPost(postId) {
      contentPosts.forEach(function (article) {
        article.hidden = article.getAttribute("data-paned-content-post-id") !== postId;
      });
      rows.forEach(function (row) {
        var isSelected = row.getAttribute("data-paned-post-id") === postId;
        row.classList.toggle("paned-list-row--selected", isSelected);
      });
    }

    function toggleCollapse(row) {
      var depth = parseInt(row.getAttribute("data-paned-depth"), 10);
      var toggle = row.querySelector("[data-paned-toggle]");
      var countMarker = row.querySelector("[data-paned-collapse-count]");
      var collapsing = !countMarker || countMarker.hidden;
      var index = rows.indexOf(row);

      for (var i = index + 1; i < rows.length; i++) {
        var candidateDepth = parseInt(rows[i].getAttribute("data-paned-depth"), 10);
        if (candidateDepth <= depth) {
          break;
        }
        rows[i].hidden = collapsing;
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
  });
})();
