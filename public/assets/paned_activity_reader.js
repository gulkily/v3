(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var filterTree = document.querySelector("[data-paned-activity-filter-tree]");
    var listBody = document.querySelector("[data-paned-activity-list-body]");
    var contentPane = document.querySelector("[data-paned-activity-content-pane]");
    if (!filterTree || !listBody || !contentPane) {
      return;
    }

    var filterItems = Array.prototype.slice.call(filterTree.querySelectorAll("[data-paned-activity-view]"));
    var rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));
    var placeholder = contentPane.querySelector("[data-paned-activity-content-placeholder]");
    var contentItems = Array.prototype.slice.call(contentPane.querySelectorAll("[data-paned-activity-content-item-id]"));
    var statusCount = document.querySelector("[data-paned-activity-status-count]");

    function currentViewFromUrl() {
      var view = new URLSearchParams(location.search).get("view") || "all";
      return filterItems.some(function (item) {
        return item.getAttribute("data-paned-activity-view") === view;
      }) ? view : "all";
    }

    function firstVisibleRow() {
      for (var i = 0; i < rows.length; i++) {
        if (!rows[i].hidden) {
          return rows[i];
        }
      }
      return null;
    }

    function currentSelectedItemId() {
      var selectedRow = rows.filter(function (row) {
        return row.classList.contains("paned-list-row--selected");
      })[0];
      return selectedRow ? selectedRow.getAttribute("data-paned-activity-id") : "";
    }

    function resetContentPane() {
      contentItems.forEach(function (article) {
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

    function selectItem(itemId) {
      if (placeholder) {
        placeholder.hidden = true;
      }
      contentItems.forEach(function (article) {
        article.hidden = article.getAttribute("data-paned-activity-content-item-id") !== itemId;
      });
      rows.forEach(function (row) {
        var isSelected = row.getAttribute("data-paned-activity-id") === itemId;
        row.classList.toggle("paned-list-row--selected", isSelected);
        row.setAttribute("aria-selected", isSelected ? "true" : "false");
        row.setAttribute("tabindex", isSelected ? "0" : "-1");
      });
    }

    function selectFilter(view) {
      filterItems.forEach(function (item) {
        var isSelected = item.getAttribute("data-paned-activity-view") === view;
        item.classList.toggle("paned-folder-item--selected", isSelected);
        item.setAttribute("aria-selected", isSelected ? "true" : "false");
        item.setAttribute("tabindex", isSelected ? "0" : "-1");
      });

      var selectedRowNowHidden = false;
      var visibleCount = 0;
      rows.forEach(function (row) {
        var visible = row.getAttribute("data-paned-activity-view-" + view) === "1";
        row.hidden = !visible;
        if (visible) {
          visibleCount++;
        }
        if (!visible && row.classList.contains("paned-list-row--selected")) {
          selectedRowNowHidden = true;
        }
      });

      if (selectedRowNowHidden) {
        var fallback = firstVisibleRow();
        if (fallback) {
          selectItem(fallback.getAttribute("data-paned-activity-id"));
        } else {
          resetContentPane();
        }
      }

      if (statusCount) {
        statusCount.textContent = visibleCount + " item" + (visibleCount === 1 ? "" : "s");
      }
    }

    function urlForState(view, itemId) {
      var params = new URLSearchParams();
      if (view !== "all") {
        params.set("view", view);
      }
      if (itemId) {
        params.set("selected", itemId);
      }
      var qs = params.toString();
      return "/forte/activity/" + (qs ? "?" + qs : "");
    }

    function pushStateIfChanged(url) {
      if (url !== location.pathname + location.search) {
        history.pushState(null, "", url);
      }
    }

    function applyFilterSelection(view) {
      selectFilter(view);
      pushStateIfChanged(urlForState(view, currentSelectedItemId()));
    }

    function restoreSelectionFromUrl(scrollRowIntoView) {
      var view = currentViewFromUrl();
      selectFilter(view);

      var params = new URLSearchParams(location.search);
      var selected = params.get("selected") || "";
      var selectedRow = selected === "" ? null : rows.filter(function (row) {
        return row.getAttribute("data-paned-activity-id") === selected && !row.hidden;
      })[0];

      if (!selectedRow) {
        var fallback = firstVisibleRow();
        if (fallback) {
          selectItem(fallback.getAttribute("data-paned-activity-id"));
        } else {
          resetContentPane();
        }
        return;
      }

      selectItem(selected);
      if (scrollRowIntoView) {
        selectedRow.scrollIntoView({ block: "nearest" });
      }
    }

    filterTree.addEventListener("click", function (event) {
      var item = event.target.closest ? event.target.closest("[data-paned-activity-view]") : null;
      if (item) {
        applyFilterSelection(item.getAttribute("data-paned-activity-view"));
      }
    });

    filterTree.addEventListener("keydown", function (event) {
      if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
        return;
      }

      var currentIndex = filterItems.indexOf(document.activeElement);
      if (currentIndex === -1) {
        return;
      }

      var nextIndex = currentIndex + (event.key === "ArrowDown" ? 1 : -1);
      if (nextIndex < 0 || nextIndex >= filterItems.length) {
        return;
      }

      event.preventDefault();
      var nextItem = filterItems[nextIndex];
      applyFilterSelection(nextItem.getAttribute("data-paned-activity-view"));
      nextItem.focus();
    });

    listBody.addEventListener("click", function (event) {
      var row = event.target.closest ? event.target.closest(".paned-list-row") : null;
      if (row) {
        var itemId = row.getAttribute("data-paned-activity-id");
        selectItem(itemId);
        pushStateIfChanged(urlForState(currentViewFromUrl(), itemId));
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
      var nextItemId = nextRow.getAttribute("data-paned-activity-id");
      selectItem(nextItemId);
      pushStateIfChanged(urlForState(currentViewFromUrl(), nextItemId));
      nextRow.focus();
    });

    window.addEventListener("popstate", function () {
      restoreSelectionFromUrl(true);
    });

    restoreSelectionFromUrl(true);
  });
})();
