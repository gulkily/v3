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
    var placeholder = detailPane.querySelector("[data-paned-user-detail-placeholder]");
    var statusCount = document.querySelector("[data-paned-users-status-count]");
    var totalUserCount = statusCount ? parseInt(statusCount.getAttribute("data-paned-users-total-count"), 10) : rows.length;
    var detailCache = {};

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

    function urlForState(category, selectedToken) {
      var params = new URLSearchParams();
      if (category !== "all") {
        params.set("view", category);
      }
      if (selectedToken !== "") {
        params.set("selected", selectedToken);
      }
      var qs = params.toString();
      return "/forte/users/" + (qs ? "?" + qs : "");
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
      pushStateIfChanged(urlForState(category, currentSelectedToken()));
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
      pushStateIfChanged(urlForState(currentCategoryFromUrl(), token));
    });

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

    window.addEventListener("popstate", restoreFromUrl);

    restoreFromUrl();
  });
})();
