(function () {
  var filterTree;
  var listBody;
  var contentPane;
  var filterItems = [];
  var rows = [];
  var placeholder;
  var contentItems = [];
  var commitDetailCache = Object.create(null);
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
    var existingCommitArticle = contentPane ? contentPane.querySelector("[data-paned-activity-commit-detail]") : null;
    if (existingCommitArticle) {
      existingCommitArticle.hidden = true;
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

  // A commit row's detail isn't one of contentItems' pre-rendered articles
  // (unlike activity items, a commit's full manifest is fetched on demand -
  // see showCommitDetail) - it's a one-off article created/reused here and
  // kept out of the contentItems array entirely.
  function commitDetailArticle() {
    return contentPane ? contentPane.querySelector("[data-paned-activity-commit-detail]") : null;
  }

  function commitRowSubjectAndDate(sha) {
    var row = rows.filter(function (candidate) {
      return candidate.getAttribute("data-paned-activity-commit-sha") === sha;
    })[0];
    if (!row) {
      return { subject: "", date: "" };
    }
    var subjectEl = row.querySelector(".paned-list-subject");
    var dateEl = row.querySelector(".paned-list-date");
    return {
      subject: subjectEl ? subjectEl.textContent : "",
      date: dateEl ? dateEl.textContent : "",
    };
  }

  function renderCommitDetailArticle(sha, bodyHtml) {
    var article = commitDetailArticle();
    if (!article) {
      article = document.createElement("article");
      article.className = "paned-content-post";
      contentPane.appendChild(article);
    }
    article.setAttribute("data-paned-activity-commit-detail", sha);
    article.hidden = false;

    // The head (subject/date) comes straight from the already-rendered row,
    // so it appears immediately - only the file manifest itself needs a
    // fetch, since that's the part too expensive to pre-render per row.
    var rowInfo = commitRowSubjectAndDate(sha);
    var head = document.createElement("div");
    head.className = "paned-content-head";
    var subjectDiv = document.createElement("div");
    subjectDiv.className = "paned-content-subject";
    subjectDiv.textContent = rowInfo.subject || sha.slice(0, 12);
    head.appendChild(subjectDiv);
    if (rowInfo.date) {
      var metaDiv = document.createElement("div");
      metaDiv.className = "paned-content-meta";
      var dateSpan = document.createElement("span");
      dateSpan.textContent = rowInfo.date;
      metaDiv.appendChild(dateSpan);
      head.appendChild(metaDiv);
    }

    var card = document.createElement("div");
    card.className = "post-card paned-post-card";
    var body = document.createElement("div");
    body.className = "body";
    body.innerHTML = bodyHtml;
    card.appendChild(body);

    article.innerHTML = "";
    article.appendChild(head);
    article.appendChild(card);
  }

  function showCommitDetail(sha) {
    if (!contentPane) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(commitDetailCache, sha)) {
      renderCommitDetailArticle(sha, commitDetailCache[sha]);
      return;
    }

    renderCommitDetailArticle(sha, '<p class="meta">Loading…</p>');

    fetch("/api/forte_commit_detail?sha=" + encodeURIComponent(sha))
      .then(function (response) {
        if (!response.ok) {
          throw new Error("commit detail fetch failed");
        }
        return response.json();
      })
      .then(function (data) {
        if (data.status !== "ok") {
          throw new Error("commit detail fetch failed");
        }
        commitDetailCache[sha] = data.html;
        // A slower, now-stale response for a commit the user has since
        // navigated away from must not clobber whatever is shown now.
        var article = commitDetailArticle();
        if (article && article.getAttribute("data-paned-activity-commit-detail") === sha) {
          var body = article.querySelector(".body");
          if (body) {
            body.innerHTML = data.html;
          }
        }
      })
      .catch(function () {
        var article = commitDetailArticle();
        if (article && article.getAttribute("data-paned-activity-commit-detail") === sha) {
          var body = article.querySelector(".body");
          if (body) {
            body.innerHTML = '<p class="meta">Failed to load commit details.</p>';
          }
        }
      });
  }

  function selectItem(itemId) {
    if (placeholder) {
      placeholder.hidden = true;
    }

    var isCommit = itemId.indexOf("commit-") === 0;

    contentItems.forEach(function (article) {
      var isSelected = !isCommit && article.getAttribute("data-paned-activity-content-item-id") === itemId;
      article.hidden = !isSelected;
    });

    var existingCommitArticle = commitDetailArticle();
    if (isCommit) {
      showCommitDetail(itemId.slice("commit-".length));
    } else if (existingCommitArticle) {
      existingCommitArticle.hidden = true;
    }

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

  // The sort header hrefs are baked in by the server at render time for
  // whatever view was selected then. Switching views is otherwise purely
  // client-side (selectFilter() just toggles which already-loaded rows are
  // visible, with no round trip) - without this, a sort click after a
  // client-side filter switch would navigate using the stale href from the
  // last server render and silently land back on that older view.
  function updateSortHeaderHrefs(view) {
    if (!sortHead) {
      return;
    }

    var buttons = Array.prototype.slice.call(sortHead.querySelectorAll("[data-paned-sort-href]"));
    buttons.forEach(function (button) {
      var href = button.getAttribute("data-paned-sort-href");
      if (!href) {
        return;
      }

      var url = new URL(href, location.origin);
      if (view === "all") {
        url.searchParams.delete("view");
      } else {
        url.searchParams.set("view", view);
      }
      button.setAttribute("data-paned-sort-href", url.pathname + url.search);
    });
  }

  // Same staleness problem as updateSortHeaderHrefs() above: the Commits
  // view's first two columns are shas/subjects, not kind/label activity
  // records, so the server renders "Hash"/"Subject" instead of "Kind"/
  // "Label" there - but switching views is otherwise purely client-side,
  // so a filter click into or out of Commits needs to relabel them itself.
  function updateSortHeaderLabels(view) {
    if (!sortHead) {
      return;
    }

    var kindButton = sortHead.querySelector('[data-paned-sort-column="kind"]');
    var labelButton = sortHead.querySelector('[data-paned-sort-column="label"]');
    var isCommits = view === "commits";
    if (kindButton) {
      kindButton.textContent = isCommits ? "Hash" : "Kind";
    }
    if (labelButton) {
      labelButton.textContent = isCommits ? "Subject" : "Label";
    }
  }

  function selectFilter(view) {
    filterItems.forEach(function (item) {
      var isSelected = item.getAttribute("data-paned-activity-view") === view;
      item.classList.toggle("paned-folder-item--selected", isSelected);
      item.setAttribute("aria-selected", isSelected ? "true" : "false");
      item.setAttribute("tabindex", isSelected ? "0" : "-1");
    });

    updateSortHeaderHrefs(view);
    updateSortHeaderLabels(view);

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
      // Auto-picking the first row is a reasonable "just show me
      // something" default under date order (the newest/oldest item),
      // but under kind/label sort the alphabetically-first item is just
      // as likely to be some huge bootstrap/seed record as anything else
      // - dropping the user straight into e.g. a thousand-file commit
      // manifest they never asked to see reads as broken, not helpful,
      // so skip auto-selection for those sorts and leave the placeholder
      // showing until they actually pick something.
      var sortColumn = currentSortFromUrl();
      var nonDateSortActive = sortColumn !== "" && sortColumn !== "date";
      if (!nonDateSortActive) {
        var fallback = firstVisibleRow();
        if (fallback) {
          selectItem(fallback.getAttribute("data-paned-activity-id"));
          return;
        }
      }
      resetContentPane();
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

  // Mirrors showProfileSummary() in paned_board_reader.js - same dialog
  // pattern (native <dialog>, API-fetched content, loading/error states,
  // a "full page" link), just for a linked post/thread instead of a
  // profile. The dialog markup lives outside .paned-board-layout, so it
  // survives softNavigateToSort()'s wholesale swap and only needs wiring
  // once, not from inside init().
  function showContentSummary(postId, fullHref) {
    var dialog = document.querySelector("[data-paned-content-summary-dialog]");
    if (!dialog) {
      return;
    }

    var title = dialog.querySelector('[data-role="content-summary-title"]');
    var loading = dialog.querySelector('[data-role="content-summary-loading"]');
    var content = dialog.querySelector('[data-role="content-summary-content"]');
    var errorNode = dialog.querySelector('[data-role="content-summary-error"]');
    var kindNode = dialog.querySelector('[data-role="content-summary-kind"]');
    var authorNode = dialog.querySelector('[data-role="content-summary-author"]');
    var dateNode = dialog.querySelector('[data-role="content-summary-date"]');
    var repliesNode = dialog.querySelector('[data-role="content-summary-replies"]');
    var textNode = dialog.querySelector('[data-role="content-summary-text"]');
    var fullLink = dialog.querySelector('[data-role="content-summary-full-link"]');

    if (title) {
      title.textContent = "Loading…";
    }
    if (loading) {
      loading.hidden = false;
    }
    if (content) {
      content.hidden = true;
    }
    if (errorNode) {
      errorNode.hidden = true;
    }
    if (fullLink) {
      fullLink.href = fullHref;
    }

    dialog.showModal();

    fetch("/api/get_forte_content_summary?post_id=" + encodeURIComponent(postId))
      .then(function (response) {
        if (!response.ok) {
          throw new Error("content summary fetch failed");
        }
        return response.json();
      })
      .then(function (data) {
        if (data.status !== "ok") {
          throw new Error("content summary fetch failed");
        }
        if (loading) {
          loading.hidden = true;
        }
        if (title) {
          title.textContent = data.title || "Post";
        }
        if (kindNode) {
          kindNode.textContent = data.is_reply ? "Reply" : "Thread";
        }
        if (authorNode) {
          authorNode.textContent = data.author_label || "guest";
        }
        if (dateNode) {
          var parsedDate = data.created_at ? new Date(data.created_at) : null;
          dateNode.textContent = parsedDate && !isNaN(parsedDate.getTime())
            ? parsedDate.toLocaleString()
            : (data.created_at || "");
        }
        if (repliesNode) {
          repliesNode.textContent = data.reply_count;
        }
        if (textNode) {
          textNode.textContent = data.body_preview || "";
        }
        if (content) {
          content.hidden = false;
        }
      })
      .catch(function () {
        if (loading) {
          loading.hidden = true;
        }
        if (errorNode) {
          errorNode.hidden = false;
        }
      });
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

    document.addEventListener("click", function (event) {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
      }

      var link = event.target.closest ? event.target.closest("[data-forte-content-link]") : null;
      if (!link) {
        return;
      }

      var postId = link.getAttribute("data-post-id") || "";
      var dialog = document.querySelector("[data-paned-content-summary-dialog]");
      if (postId === "" || !dialog || typeof dialog.showModal !== "function") {
        return;
      }

      event.preventDefault();
      showContentSummary(postId, link.getAttribute("href") || "#");
    });

    var contentSummaryClose = document.querySelector("[data-paned-content-summary-close]");
    var contentSummaryDialog = document.querySelector("[data-paned-content-summary-dialog]");
    if (contentSummaryClose && contentSummaryDialog) {
      contentSummaryClose.addEventListener("click", function () {
        contentSummaryDialog.close();
      });
    }
    if (contentSummaryDialog) {
      // A click that lands on the <dialog> element itself (not one of its
      // children) is a click on the backdrop area, since the dialog's own
      // box is sized to its content - the standard way to detect a
      // click-outside on a native <dialog>.
      contentSummaryDialog.addEventListener("click", function (event) {
        if (event.target === contentSummaryDialog) {
          contentSummaryDialog.close();
        }
      });
    }
  });
})();
