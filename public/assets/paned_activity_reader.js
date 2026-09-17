(function () {
  var filterTree;
  var listBody;
  var contentPane;
  var filterItems = [];
  var rows = [];
  var placeholder;
  var contentItems = [];
  var commitManifestBlocks = [];
  var statusCount;
  var loadMoreGroup;
  var loadMoreButtons = [];
  var sortHead;
  var loadedSort = "";
  var loadedDirection = "";
  var sortNavigationInFlight = false;

  function currentViewFromUrl() {
    var view = new URLSearchParams(location.search).get("view") || "all";
    return filterItems.some(function (item) {
      return item.getAttribute("data-paned-activity-view") === view;
    }) ? view : "all";
  }

  // Read straight through with no client-side validation table (unlike
  // currentViewFromUrl()): whatever lands here, valid or not, gets
  // normalized server-side by resolveActivitySort() the same way an
  // omitted/garbage query param already is on a full page load.
  function currentSortFromUrl() {
    return new URLSearchParams(location.search).get("sort") || "";
  }

  function currentDirectionFromUrl() {
    return new URLSearchParams(location.search).get("dir") || "";
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

  // Each commit's file manifest is rendered once (not once per item that
  // happens to share it - some bootstrap/seed commits touch thousands of
  // files and are referenced by most items, so duplicating that per item
  // made the page tens of megabytes). Selecting an item moves its
  // matching shared block into view inside that item's own article.
  function showCommitManifestFor(article) {
    var sha = article.getAttribute("data-paned-activity-commit-sha") || "";
    commitManifestBlocks.forEach(function (block) {
      if (sha !== "" && block.getAttribute("data-paned-activity-commit-manifest") === sha) {
        block.hidden = false;
        var body = article.querySelector(".body");
        if (body) {
          body.appendChild(block);
        }
      } else {
        block.hidden = true;
      }
    });
  }

  function selectItem(itemId) {
    if (placeholder) {
      placeholder.hidden = true;
    }
    contentItems.forEach(function (article) {
      var isSelected = article.getAttribute("data-paned-activity-content-item-id") === itemId;
      article.hidden = !isSelected;
      if (isSelected) {
        showCommitManifestFor(article);
      }
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
    var params = new URLSearchParams(location.search);
    params.delete("selected");
    if (view === "all") {
      params.delete("view");
    } else {
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

  function mergeAppendedRows(html, view) {
    var template = document.createElement("template");
    template.innerHTML = html;
    var incomingNodes = Array.prototype.slice.call(template.content.children);
    incomingNodes.forEach(function (node) {
      var id = node.getAttribute("data-paned-activity-id");
      var existingRow = listBody.querySelector('[data-paned-activity-id="' + id + '"]');
      if (existingRow) {
        // Same item reached from a different view's own page window
        // (e.g. a small view's first page can span further back in time
        // than this view's). Mark it as also belonging to this view
        // instead of appending a second row for the same id.
        existingRow.setAttribute("data-paned-activity-view-" + view, "1");
      } else {
        // The load-more button group lives inside listBody now (so it
        // scrolls with the rows instead of staying pinned below them)
        // and must stay last, so new rows are inserted before it rather
        // than appended after it.
        listBody.insertBefore(node, loadMoreGroup);
      }
    });
  }

  function mergeAppendedDetailArticles(html) {
    if (!contentPane || !html) {
      return;
    }
    var template = document.createElement("template");
    template.innerHTML = html;
    var incomingNodes = Array.prototype.slice.call(template.content.children);
    incomingNodes.forEach(function (node) {
      // A shared commit-manifest block (dedup by sha) or a per-item
      // article (dedup by item id) - a batch can contain both, and the
      // sha a later batch repeats must not be appended twice either.
      var manifestSha = node.getAttribute("data-paned-activity-commit-manifest");
      if (manifestSha !== null) {
        if (!contentPane.querySelector('[data-paned-activity-commit-manifest="' + manifestSha + '"]')) {
          contentPane.appendChild(node);
        }
        return;
      }

      var id = node.getAttribute("data-paned-activity-content-item-id");
      if (!contentPane.querySelector('[data-paned-activity-content-item-id="' + id + '"]')) {
        contentPane.appendChild(node);
      }
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

  // Fetches the sort header's target URL and swaps just the pane content in
  // place, instead of a real navigation - avoids the full-page reload
  // flash while still using the server's real sort/cursor computation (the
  // Step 1 "reset to page 1" decision still applies: this is a fresh
  // page-1 fetch, not a client-side re-sort of what's already loaded).
  // Falls back to a real navigation on any failure, so correctness never
  // depends on this working.
  function softNavigateToSort(href, shouldPushState) {
    if (sortNavigationInFlight) {
      return;
    }
    sortNavigationInFlight = true;

    fetch(href)
      .then(function (response) {
        if (!response.ok) {
          throw new Error("sort navigation fetch failed");
        }
        return response.text();
      })
      .then(function (text) {
        var parsed = new DOMParser().parseFromString(text, "text/html");
        var newLayout = parsed.querySelector(".paned-board-layout");
        var currentLayout = document.querySelector(".paned-board-layout");
        if (!newLayout || !currentLayout) {
          throw new Error("unexpected page structure in sort navigation response");
        }

        currentLayout.replaceWith(newLayout);
        if (shouldPushState) {
          history.pushState(null, "", href);
        }
        sortNavigationInFlight = false;
        init();
      })
      .catch(function () {
        window.location.href = href;
      });
  }

  // Re-run after DOMContentLoaded and again after every soft-navigated sort
  // change, since that replaces .paned-board-layout wholesale - everything
  // queried and bound here (rows, filter items, the sort/filter/load-more
  // click handlers, ...) lives inside that container and needs rebinding
  // against the fresh DOM. Elements outside it (Prev/Next toolbar buttons,
  // the popstate listener) are bound once, below, since they're never
  // replaced.
  function init() {
    filterTree = document.querySelector("[data-paned-activity-filter-tree]");
    listBody = document.querySelector("[data-paned-activity-list-body]");
    contentPane = document.querySelector("[data-paned-activity-content-pane]");
    if (!filterTree || !listBody || !contentPane) {
      return;
    }

    filterItems = Array.prototype.slice.call(filterTree.querySelectorAll("[data-paned-activity-view]"));
    rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));
    placeholder = contentPane.querySelector("[data-paned-activity-content-placeholder]");
    contentItems = Array.prototype.slice.call(contentPane.querySelectorAll("[data-paned-activity-content-item-id]"));
    commitManifestBlocks = Array.prototype.slice.call(contentPane.querySelectorAll("[data-paned-activity-commit-manifest]"));
    statusCount = document.querySelector("[data-paned-activity-status-count]");
    loadMoreGroup = document.querySelector("[data-paned-activity-load-more-group]");
    loadMoreButtons = loadMoreGroup
      ? Array.prototype.slice.call(loadMoreGroup.querySelectorAll("[data-paned-activity-load-more]"))
      : [];
    sortHead = document.querySelector("[data-paned-sort-head]");

    if (sortHead) {
      sortHead.addEventListener("click", function (event) {
        var button = event.target.closest ? event.target.closest("[data-paned-sort-column]") : null;
        var href = button ? button.getAttribute("data-paned-sort-href") : null;
        if (href) {
          softNavigateToSort(href, true);
        }
      });
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

        fetch(
          "/api/forte_activity_page?view=" + encodeURIComponent(view) +
            "&sort=" + encodeURIComponent(currentSortFromUrl()) +
            "&dir=" + encodeURIComponent(currentDirectionFromUrl()) +
            "&cursor=" + encodeURIComponent(cursor)
        )
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

            mergeAppendedRows(data.html, view);
            rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));

            mergeAppendedDetailArticles(data.detail_html);
            if (contentPane) {
              contentItems = Array.prototype.slice.call(
                contentPane.querySelectorAll("[data-paned-activity-content-item-id]")
              );
              commitManifestBlocks = Array.prototype.slice.call(
                contentPane.querySelectorAll("[data-paned-activity-commit-manifest]")
              );
            }

            // The left-pane folder count is the view's fixed total (set once
            // server-side) and doesn't change as more pages load, so there
            // is nothing to update here; only the status bar's "loaded so
            // far" count changes, via selectFilter() below.

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

    loadedSort = currentSortFromUrl();
    loadedDirection = currentDirectionFromUrl();
    restoreSelectionFromUrl(true);
  }

  document.addEventListener("DOMContentLoaded", function () {
    init();

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
      // view/selected changes only need re-toggling visibility of rows
      // already in the DOM; a sort/dir change means a different row set
      // entirely, so it needs the same fetch-and-swap init() does, just
      // without pushing another history entry (this one already exists).
      if (currentSortFromUrl() !== loadedSort || currentDirectionFromUrl() !== loadedDirection) {
        softNavigateToSort(location.href, false);
      } else {
        restoreSelectionFromUrl(true);
      }
    });
  });
})();
