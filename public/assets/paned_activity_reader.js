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
    var loadMoreGroup = document.querySelector("[data-paned-activity-load-more-group]");
    var loadMoreButtons = loadMoreGroup
      ? Array.prototype.slice.call(loadMoreGroup.querySelectorAll("[data-paned-activity-load-more]"))
      : [];

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

    function updateLoadMoreButtonVisibility(view) {
      loadMoreButtons.forEach(function (button) {
        var isCurrentView = button.getAttribute("data-paned-activity-view") === view;
        var hasMore = button.getAttribute("data-paned-activity-has-more") === "1";
        button.hidden = !(isCurrentView && hasMore);
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

      updateLoadMoreButtonVisibility(view);
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

    if (loadMoreGroup) {
      loadMoreGroup.addEventListener("click", function (event) {
        var button = event.target.closest ? event.target.closest("[data-paned-activity-load-more]") : null;
        if (!button || button.disabled) {
          return;
        }

        var view = button.getAttribute("data-paned-activity-view") || "all";
        var cursor = button.getAttribute("data-paned-activity-cursor") || "";
        var originalLabel = button.textContent;
        button.disabled = true;
        button.textContent = "Loading…";

        fetch("/api/forte_activity_page?view=" + encodeURIComponent(view) + "&cursor=" + encodeURIComponent(cursor))
          .then(function (response) {
            if (!response.ok) {
              throw new Error("activity page fetch failed");
            }
            return response.json();
          })
          .then(function (data) {
            if (data.status !== "ok") {
              throw new Error("activity page fetch failed");
            }

            listBody.insertAdjacentHTML("beforeend", data.html);
            rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));

            button.setAttribute("data-paned-activity-cursor", data.next_cursor ? JSON.stringify(data.next_cursor) : "");
            button.setAttribute("data-paned-activity-has-more", data.has_more ? "1" : "0");
            button.disabled = false;
            button.textContent = originalLabel;

            selectFilter(currentViewFromUrl());
          })
          .catch(function () {
            button.disabled = false;
            button.textContent = originalLabel;
          });
      });
    }

    function stepSelection(delta) {
      var visible = rows.filter(function (row) {
        return !row.hidden;
      });
      if (visible.length === 0) {
        return;
      }

      var currentId = currentSelectedItemId();
      var index = -1;
      for (var i = 0; i < visible.length; i++) {
        if (visible[i].getAttribute("data-paned-activity-id") === currentId) {
          index = i;
          break;
        }
      }

      var nextIndex = index === -1 ? 0 : index + delta;
      if (nextIndex < 0 || nextIndex >= visible.length) {
        return;
      }

      var nextRow = visible[nextIndex];
      var nextItemId = nextRow.getAttribute("data-paned-activity-id");
      selectItem(nextItemId);
      pushStateIfChanged(urlForState(currentViewFromUrl(), nextItemId));
      nextRow.scrollIntoView({ block: "nearest" });
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

    window.addEventListener("popstate", function () {
      restoreSelectionFromUrl(true);
    });

    restoreSelectionFromUrl(true);
  });
})();
