(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var filterList = document.querySelector("[data-paned-users-filter-list]");
    var listBody = document.querySelector("[data-paned-users-list-body]");
    var detailPane = document.querySelector("[data-paned-user-detail-pane]");
    if (!filterList || !listBody || !detailPane) {
      return;
    }

    var filterItems = Array.prototype.slice.call(filterList.querySelectorAll("[data-paned-user-category]"));
    var rows = Array.prototype.slice.call(listBody.querySelectorAll(".paned-list-row"));
    var originalRowOrder = rows.slice();
    var placeholder = detailPane.querySelector("[data-paned-user-detail-placeholder]");
    var statusCount = document.querySelector("[data-paned-users-status-count]");
    var totalUserCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-users-total-count"), 10) : rows.length;
    var detailCache = {};
    var sortDefaultDir = { username: "asc", threads: "desc", posts: "desc" };
    var sortHead = document.querySelector("[data-paned-sort-head]");
    var sortButtons = sortHead ? Array.prototype.slice.call(sortHead.querySelectorAll("[data-paned-sort-column]")) : [];

    function currentCategoryFromUrl() {
      return new URLSearchParams(location.search).get("view") || "all";
    }

    function currentSelectedFromUrl() {
      return new URLSearchParams(location.search).get("selected") || "";
    }

    function currentSelectedToken() {
      var selectedRow = rows.filter(function (row) {
        return row.classList.contains("paned-list-row--selected");
      })[0];
      return selectedRow ? selectedRow.getAttribute("data-paned-user-token") : "";
    }

    function firstVisibleRow() {
      for (var i = 0; i < rows.length; i++) {
        if (!rows[i].hidden) {
          return rows[i];
        }
      }
      return null;
    }

    function urlForState(category, selectedToken, sortColumn, sortDir) {
      var params = new URLSearchParams();
      if (category !== "all") {
        params.set("view", category);
      }
      if (sortColumn !== "") {
        params.set("sort", sortColumn);
        params.set("dir", sortDir);
      }
      if (selectedToken !== "") {
        params.set("selected", selectedToken);
      }
      var qs = params.toString();
      return "/forte/users/" + (qs ? "?" + qs : "");
    }

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

    function pushStateIfChanged(url) {
      if (url !== location.pathname + location.search) {
        history.pushState(null, "", url);
      }
    }

    function detailArticle() {
      return detailPane.querySelector("[data-paned-user-detail-token]");
    }

    function resetDetailPane() {
      var existingArticle = detailArticle();
      if (existingArticle) {
        existingArticle.hidden = true;
      }
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

    function renderDetail(token, html) {
      if (placeholder) {
        placeholder.hidden = true;
      }
      var article = detailArticle();
      if (article) {
        article.outerHTML = html;
      } else {
        detailPane.insertAdjacentHTML("beforeend", html);
      }
    }

    function showUserDetail(token) {
      if (Object.prototype.hasOwnProperty.call(detailCache, token)) {
        renderDetail(token, detailCache[token]);
        return;
      }

      renderDetail(
        token,
        '<article class="paned-content-post" data-paned-user-detail-token="' + token + '"><p class="meta">Loading…</p></article>'
      );

      fetch("/api/forte_user_detail?username_token=" + encodeURIComponent(token))
        .then(function (response) {
          if (!response.ok) {
            throw new Error("user detail fetch failed");
          }
          return response.json();
        })
        .then(function (data) {
          if (data.status !== "ok") {
            throw new Error("user detail fetch failed");
          }
          detailCache[token] = data.html;
          // A slower, now-stale response for a user the viewer has since
          // navigated away from must not clobber whatever is shown now.
          var article = detailArticle();
          if (article && article.getAttribute("data-paned-user-detail-token") === token) {
            renderDetail(token, data.html);
          }
        })
        .catch(function () {
          var article = detailArticle();
          if (article && article.getAttribute("data-paned-user-detail-token") === token) {
            article.innerHTML = '<p class="meta">Failed to load user details.</p>';
          }
        });
    }

    function selectUser(token) {
      rows.forEach(function (row) {
        var isSelected = row.getAttribute("data-paned-user-token") === token;
        row.classList.toggle("paned-list-row--selected", isSelected);
        row.setAttribute("aria-selected", isSelected ? "true" : "false");
        row.setAttribute("tabindex", isSelected ? "0" : "-1");
      });
      showUserDetail(token);
    }

    function categoryLabel(category) {
      var item = filterItems.filter(function (filterItem) {
        return filterItem.getAttribute("data-paned-user-category") === category;
      })[0];
      var labelNode = item ? item.querySelector("span") : null;
      return labelNode ? labelNode.textContent : category;
    }

    function selectFilter(category) {
      filterItems.forEach(function (item) {
        var isSelected = item.getAttribute("data-paned-user-category") === category;
        item.classList.toggle("paned-folder-item--selected", isSelected);
        item.setAttribute("aria-selected", isSelected ? "true" : "false");
        item.setAttribute("tabindex", isSelected ? "0" : "-1");
      });

      var selectedRowNowHidden = false;
      var visibleCount = 0;
      rows.forEach(function (row) {
        var visible = row.getAttribute("data-paned-user-category-" + category) === "1";
        row.hidden = !visible;
        if (visible) {
          visibleCount++;
        }
        if (!visible && row.classList.contains("paned-list-row--selected")) {
          selectedRowNowHidden = true;
        }
      });

      if (selectedRowNowHidden) {
        resetDetailPane();
      }

      if (statusCount) {
        if (category === "all") {
          statusCount.textContent = totalUserCount + " user" + (totalUserCount === 1 ? "" : "s");
        } else if (category === "not-approved") {
          statusCount.textContent = visibleCount + " pending user" + (visibleCount === 1 ? "" : "s");
        } else {
          statusCount.textContent = "Showing " + visibleCount + " of " + totalUserCount + " users (" + categoryLabel(category) + ")";
        }
      }
    }

    function applyFilterSelection(category) {
      selectFilter(category);
      var sort = currentSortState();
      pushStateIfChanged(urlForState(category, currentSelectedToken(), sort.column, sort.dir));
    }

    filterList.addEventListener("click", function (event) {
      var item = event.target.closest ? event.target.closest("[data-paned-user-category]") : null;
      if (item) {
        applyFilterSelection(item.getAttribute("data-paned-user-category"));
      }
    });

    listBody.addEventListener("click", function (event) {
      var row = event.target.closest ? event.target.closest(".paned-list-row") : null;
      if (!row || row.hidden) {
        return;
      }
      var token = row.getAttribute("data-paned-user-token");
      selectUser(token);
      var sort = currentSortState();
      pushStateIfChanged(urlForState(currentCategoryFromUrl(), token, sort.column, sort.dir));
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

    function sortValueFor(row, column) {
      if (column === "threads" || column === "posts") {
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
        pushStateIfChanged(urlForState(currentCategoryFromUrl(), currentSelectedToken(), column, dir));
      });
    }

    function restoreFromUrl() {
      selectFilter(currentCategoryFromUrl());

      var selected = currentSelectedFromUrl();
      var selectedRow = selected === "" ? null : rows.filter(function (row) {
        return row.getAttribute("data-paned-user-token") === selected;
      })[0];

      if (!selectedRow) {
        resetDetailPane();
        return;
      }

      selectUser(selected);
    }

    window.addEventListener("popstate", function () {
      restoreFromUrl();

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

    restoreFromUrl();
  });
})();
