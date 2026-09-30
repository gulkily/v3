(function () {
  "use strict";

  function clearSnapshotNode(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  var snapshotPresentation = {
    metadataValue: function (database, key) {
      var result = database.exec("SELECT value FROM metadata WHERE key = ?", [key])[0];
      return result && result.values && result.values[0] ? String(result.values[0][0] || "") : "";
    },
    readerRevisionFromShell: function (documentFragment) {
      var reader = documentFragment && documentFragment.querySelector ? documentFragment.querySelector("[data-offline-reader]") : null;
      return reader && reader.getAttribute("data-reader-revision") || "unknown";
    },
    normalThreadUrl: function (threadId) {
      return "/threads/" + encodeURIComponent(threadId);
    },
    boardPathFromPathname: function (pathname) {
      return ["/", "/threads", "/threads/"].indexOf(String(pathname || "")) !== -1
        ? String(pathname || "")
        : "";
    },
    normalBoardUrl: function (pathname, state) {
      return pathname + "?view=" + encodeURIComponent(state.view) + "&sort=" + encodeURIComponent(state.sort);
    },
    threadIdFromPathname: function (pathname) {
      var match = String(pathname || "").match(/^\/threads\/([^/]+)\/?$/);
      return match ? decodeURIComponent(match[1]) : "";
    },
    tagFromPathname: function (pathname) {
      var match = String(pathname || "").match(/^\/tags\/([a-z0-9]+(?:-[a-z0-9]+)*)\/?$/);
      return match ? match[1] : "";
    },
    threadTitle: function (subject, preview) {
      var title = String(subject || "").trim();
      if (title) return title;
      return snapshotPresentation.bodyExcerpt(preview, 80) || "Untitled thread";
    },
    bodyExcerpt: function (body, limit) {
      var normalized = String(body || "").replace(/\s+/g, " ").trim();
      if (!normalized) return "";
      var characters = Array.from(normalized);
      var maximum = Math.max(8, Number(limit) || 80);
      if (characters.length <= maximum) return normalized;
      var slice = characters.slice(0, maximum).join("");
      var lastSpace = slice.lastIndexOf(" ");
      if (lastSpace >= 24) slice = slice.slice(0, lastSpace);
      return slice.replace(/[\s.,;:!?]+$/, "") + "...";
    },
    heatLevel: function (timestamp, replyCount) {
      var then = Date.parse(String(timestamp || ""));
      if (Number.isNaN(then)) return 1;
      var ageSeconds = Math.max(0, (Date.now() - then) / 1000);
      var buckets = [[8, 3600], [7, 6 * 3600], [6, 24 * 3600], [5, 3 * 86400], [4, 7 * 86400], [3, 30 * 86400], [2, 90 * 86400]];
      var level = 1;
      for (var index = 0; index < buckets.length; index += 1) {
        if (ageSeconds <= buckets[index][1]) {
          level = buckets[index][0];
          break;
        }
      }
      if (Number(replyCount) >= 10) level += 2;
      else if (Number(replyCount) >= 3) level += 1;
      return Math.min(8, level);
    },
    boardRows: function (database) {
      var result = database.exec(
        "SELECT root_post_id, subject, body_preview, reply_count, last_activity_at, author_label, "
        + "root_post_created_at, thread_labels_json, board_tags_json, score_total FROM threads"
      )[0];
      return (result && result.values ? result.values : []).map(function (row) {
        return {
          id: String(row[0] || ""),
          subject: String(row[1] || ""),
          preview: String(row[2] || ""),
          replyCount: Number(row[3] || 0),
          lastActivityAt: String(row[4] || ""),
          author: String(row[5] || "guest"),
          createdAt: String(row[6] || ""),
          labels: snapshotPresentation.threadLabels(row[7]),
          boardTags: snapshotPresentation.tagsFromJson(row[8]),
          score: Number(row[9] || 0)
        };
      });
    },
    threadLabels: function (value) {
      return snapshotPresentation.tagsFromJson(value);
    },
    tagsFromJson: function (value) {
      try {
        var tags = JSON.parse(String(value || "[]"));
        return Array.isArray(tags) ? tags.filter(function (tag) { return typeof tag === "string" && tag !== ""; }) : [];
      } catch (error) {
        return [];
      }
    },
    threadTags: function (thread) {
      return [...new Set([...(thread.boardTags || []), ...(thread.labels || [])])];
    },
    tagGroups: function (database) {
      var groups = {};
      snapshotPresentation.boardRows(database).forEach(function (thread) {
        snapshotPresentation.threadTags(thread).forEach(function (tag) {
          if (!groups[tag]) groups[tag] = { tag: tag, threads: [] };
          groups[tag].threads.push(thread);
        });
      });
      return Object.values(groups).map(function (group) {
        group.threads.sort(function (left, right) {
          return right.lastActivityAt.localeCompare(left.lastActivityAt) || right.id.localeCompare(left.id);
        });
        return {
          tag: group.tag,
          count: group.threads.length,
          threads: group.threads,
          previewThreads: group.threads.slice(0, 5),
          hasMore: group.threads.length > 5,
          href: "/tags/" + encodeURIComponent(group.tag)
        };
      }).sort(function (left, right) {
        return (right.count - left.count) || left.tag.localeCompare(right.tag);
      });
    },
    renderTagsIndex: function (options) {
      var groups = snapshotPresentation.tagGroups(options.database);
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      snapshotPresentation.appendBoardControls(options.content, { view: "all", sort: "newest" }, {
        boardPath: "/threads/",
        tagsActive: true,
        onReconnectRequired: options.onReconnectRequired
      });

      var card = document.createElement("article");
      card.className = "card tags-section-card";
      if (!groups.length) {
        var empty = document.createElement("p");
        empty.className = "meta";
        empty.textContent = "No tags were included in this saved snapshot. Reconnect to browse live tags.";
        card.appendChild(empty);
      } else {
        var groupList = document.createElement("div");
        groupList.className = "tag-groups";
        groups.forEach(function (group) {
          var section = document.createElement("section");
          section.className = "tag-group";
          var header = document.createElement("div");
          header.className = "tag-group-header";
          var heading = document.createElement("h2");
          var tagLink = document.createElement("a");
          tagLink.href = group.href;
          tagLink.textContent = "#" + group.tag;
          heading.appendChild(tagLink);
          var count = document.createElement("p");
          count.className = "meta";
          count.textContent = group.count + (group.count === 1 ? " thread" : " threads");
          header.appendChild(heading);
          header.appendChild(count);
          section.appendChild(header);
          var threads = document.createElement("ul");
          threads.className = "tag-thread-list";
          group.previewThreads.forEach(function (thread) {
            var item = document.createElement("li");
            var threadLink = document.createElement("a");
            threadLink.href = snapshotPresentation.normalThreadUrl(thread.id);
            threadLink.textContent = snapshotPresentation.threadTitle(thread.subject, thread.preview);
            var author = document.createElement("span");
            author.className = "meta";
            author.textContent = "by " + thread.author;
            item.appendChild(threadLink);
            item.appendChild(author);
            threads.appendChild(item);
          });
          section.appendChild(threads);
          if (group.hasMore) {
            var footer = document.createElement("p");
            footer.className = "meta tag-group-footer";
            footer.textContent = "showing 5 newest of " + group.count;
            section.appendChild(footer);
          }
          groupList.appendChild(section);
        });
        card.appendChild(groupList);
      }
      options.content.appendChild(card);
      return groups;
    },
    renderTagResult: function (options) {
      var group = snapshotPresentation.tagGroups(options.database).find(function (candidate) {
        return candidate.tag === options.tag;
      });
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      var header = document.createElement("article");
      header.className = "card";
      var eyebrow = document.createElement("p");
      eyebrow.className = "eyebrow";
      eyebrow.textContent = "Saved tag";
      var heading = document.createElement("h1");
      heading.textContent = "#" + options.tag;
      var count = document.createElement("p");
      count.className = "meta";
      count.textContent = group ? group.count + (group.count === 1 ? " thread" : " threads") : "Not in saved snapshot";
      var navigation = document.createElement("p");
      navigation.className = "meta";
      var tagsLink = document.createElement("a");
      tagsLink.href = "/tags/";
      tagsLink.textContent = "Back to Tags";
      var boardLink = document.createElement("a");
      boardLink.href = "/";
      boardLink.textContent = "Back to Board";
      navigation.appendChild(tagsLink);
      navigation.appendChild(document.createTextNode(" | "));
      navigation.appendChild(boardLink);
      header.appendChild(eyebrow);
      header.appendChild(heading);
      header.appendChild(count);
      header.appendChild(navigation);
      options.content.appendChild(header);
      if (!group) {
        var unavailable = document.createElement("p");
        unavailable.className = "meta";
        unavailable.textContent = "This tag is not included in the saved snapshot. Reconnect to browse live results.";
        options.content.appendChild(unavailable);
        return false;
      }
      snapshotPresentation.appendThreadCards(options.content, group.threads);
      return true;
    },
    isPinned: function (thread) {
      return thread.labels.indexOf("pinned") !== -1;
    },
    compareBoardThreads: function (left, right, sort) {
      var pinned = Number(snapshotPresentation.isPinned(right)) - Number(snapshotPresentation.isPinned(left));
      if (pinned) return pinned;
      if (sort === "oldest") {
        return left.createdAt.localeCompare(right.createdAt) || left.id.localeCompare(right.id);
      }
      if (sort === "top") {
        return (right.score - left.score) || right.createdAt.localeCompare(left.createdAt) || right.id.localeCompare(left.id);
      }
      return right.createdAt.localeCompare(left.createdAt) || right.id.localeCompare(left.id);
    },
    appendBoardControls: function (content, state, options) {
      var controls = document.createElement("article");
      controls.className = "card";
      var nav = document.createElement("div");
      nav.className = "nav board-controls-nav";
      var boardPath = options.boardPath || "/";
      [{ key: "tags", label: "Tags", href: "/tags/", active: Boolean(options.tagsActive) }].concat([
        { key: "all", label: "All", group: "view" },
        { key: "liked", label: "Liked", group: "view" },
        { key: "newest", label: "Newest", group: "sort" },
        { key: "oldest", label: "Oldest", group: "sort" },
        { key: "top", label: "Top", group: "sort" }
      ]).forEach(function (option) {
        if (option.key === "tags") {
          var tagsLink = document.createElement("a");
          tagsLink.className = "nav-link" + (option.active ? " is-active" : "");
          tagsLink.href = option.href;
          tagsLink.textContent = option.label;
          nav.appendChild(tagsLink);
          return;
        }
        var nextState = { view: state.view, sort: state.sort };
        nextState[option.group] = option.key;
        var link = document.createElement("a");
        link.className = "nav-link" + (state[option.group] === option.key ? " is-active" : "");
        link.href = snapshotPresentation.normalBoardUrl(boardPath, nextState);
        link.textContent = option.label;
        if (options.onBoardChange) link.addEventListener("click", function (event) {
          event.preventDefault();
          options.onBoardChange(nextState);
        });
        nav.appendChild(link);
      });
      var newPost = document.createElement("a");
      newPost.className = "nav-link";
      newPost.href = "/compose/thread";
      newPost.textContent = "New Post (reconnect)";
      newPost.addEventListener("click", function (event) {
        event.preventDefault();
        if (options.onReconnectRequired) options.onReconnectRequired();
      });
      nav.appendChild(newPost);
      controls.appendChild(nav);
      content.appendChild(controls);
    },
    appendThreadCards: function (content, threads, onSelect) {
      threads.forEach(function (thread) {
        var card = document.createElement("article");
        card.className = "card thread-card";
        card.dataset.heat = String(snapshotPresentation.heatLevel(thread.lastActivityAt, thread.replyCount));
        var heading = document.createElement("h2");
        var link = document.createElement("a");
        link.href = snapshotPresentation.normalThreadUrl(thread.id);
        link.textContent = snapshotPresentation.threadTitle(thread.subject, thread.preview);
        link.addEventListener("click", function (event) {
          if (onSelect) {
            event.preventDefault();
            onSelect(thread.id);
          }
        });
        heading.appendChild(link);
        if (snapshotPresentation.isPinned(thread)) {
          var marker = document.createElement("span");
          marker.className = "pinned-thread-marker";
          marker.textContent = "Pinned";
          heading.appendChild(document.createTextNode(" "));
          heading.appendChild(marker);
        }
        card.appendChild(heading);
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = "by " + thread.author + (thread.createdAt ? " on " + thread.createdAt : "");
        card.appendChild(meta);
        if (thread.preview.trim() !== snapshotPresentation.threadTitle(thread.subject, thread.preview).trim()) {
          var preview = document.createElement("p");
          preview.className = "thread-card__preview";
          preview.textContent = thread.preview;
          card.appendChild(preview);
        }
        if (thread.replyCount > 0) {
          var replies = document.createElement("p");
          replies.className = "meta";
          replies.textContent = thread.replyCount + (thread.replyCount === 1 ? " reply" : " replies");
          card.appendChild(replies);
        }
        content.appendChild(card);
      });
    },
    renderThreadList: function (options) {
      var rows = snapshotPresentation.boardRows(options.database);
      var state = options.boardState || { view: "all", sort: "newest" };
      if (state.view === "liked") {
        rows = rows.filter(function (thread) {
          return thread.labels.indexOf("like") !== -1 && thread.score >= 0;
        });
      }
      rows.sort(function (left, right) {
        return snapshotPresentation.compareBoardThreads(left, right, state.sort);
      });
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      if (options.onBoardChange) {
        snapshotPresentation.appendBoardControls(options.content, state, {
          boardPath: options.boardPath || "/",
          onBoardChange: options.onBoardChange,
          onReconnectRequired: options.onReconnectRequired
        });
      }
      snapshotPresentation.appendThreadCards(options.content, rows, options.onSelect);
      if (!rows.length) {
        var empty = document.createElement("p");
        empty.className = "meta";
        empty.textContent = options.emptyMessage || "No recent public threads were included in this snapshot.";
        options.content.appendChild(empty);
        return false;
      }
      return true;
    },
    renderThreadDetail: function (options) {
      var threadResult = options.database.exec(
        "SELECT last_activity_at, reply_count FROM threads WHERE root_post_id = ?",
        [options.threadId]
      )[0];
      var threadRow = threadResult && threadResult.values && threadResult.values[0] ? threadResult.values[0] : null;
      var result = options.database.exec(
        "SELECT post_id, parent_id, subject, body, author_label, created_at "
        + "FROM posts WHERE thread_id = ? ORDER BY sequence_number ASC, post_id ASC",
        [options.threadId]
      )[0];
      var rows = result && result.values ? result.values : [];
      if (!rows.length) {
        options.setStatus("That thread is not included in this offline snapshot.", "error");
        if (options.onMissing) options.onMissing();
        return false;
      }
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      if (options.onBack) {
        var back = document.createElement("button");
        back.type = "button";
        back.className = "nav-link";
        back.textContent = options.backLabel || "Back to saved threads";
        back.addEventListener("click", options.onBack);
        options.content.appendChild(back);
      }
      rows.forEach(function (row, index) {
        var post = document.createElement("article");
        post.className = index === 0 ? "card post-card thread-root-card" : "card post-card";
        if (index === 0) {
          var heading = document.createElement("h1");
          heading.textContent = snapshotPresentation.threadTitle(row[2], row[3]);
          post.appendChild(heading);
          post.dataset.heat = String(snapshotPresentation.heatLevel(
            threadRow ? threadRow[0] : row[5],
            threadRow ? threadRow[1] : 0
          ));
        } else {
          post.dataset.heat = String(snapshotPresentation.heatLevel(row[5], 0));
        }
        var body = document.createElement("div");
        body.className = "body";
        var postBody = String(row[3] || "");
        if (index === 0) {
          var segments = postBody.split(/\r\n|\r|\n/, 2);
          if (segments[0].trim() === snapshotPresentation.threadTitle(row[2], row[3])) {
            postBody = String(segments[1] || "").replace(/^(?:\r\n|\r|\n)+/, "");
          }
        }
        body.textContent = postBody;
        post.appendChild(body);
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = "by " + String(row[4] || "guest") + (row[5] ? " on " + String(row[5]) : "");
        post.appendChild(meta);
        options.content.appendChild(post);
      });
      return true;
    }
  };
  window.forumOfflineSnapshot = snapshotPresentation;

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-offline-reader]");
    if (!root) {
      return;
    }

    var status = root.querySelector('[data-role="offline-reader-status"]');
    var details = root.querySelector('[data-role="offline-reader-details"]');
    var modeBar = root.querySelector('[data-role="offline-mode-bar"]');
    var content = root.querySelector('[data-role="offline-reader-content"]');
    var snapshotUrl = root.getAttribute("data-snapshot-url") || "/offline/snapshot.sqlite3";
    var runtimeUrl = root.getAttribute("data-runtime-url") || "/assets/sql-wasm.wasm";

    function setStatus(message, state) {
      if (!status) {
        return;
      }
      status.textContent = message;
      status.hidden = state !== "error";
      if (state) {
        status.dataset.state = state;
      } else {
        delete status.dataset.state;
      }
    }

    function showOfflineMode() {
      if (modeBar) modeBar.hidden = false;
    }

    function setReaderDetails(generatedAt) {
      if (!details) return;
      var revision = root.getAttribute("data-reader-revision") || "unknown";
      details.textContent = "Saved archive generated: " + (generatedAt || "unknown") + ". Reader revision: " + revision + ".";
      details.hidden = false;
    }

    function metadataValue(database, key) {
      return snapshotPresentation.metadataValue(database, key);
    }

    function clearNode(node) {
      while (node && node.firstChild) {
        node.removeChild(node.firstChild);
      }
    }

    function threadTitle(subject, preview) {
      return snapshotPresentation.threadTitle(subject, preview);
    }

    function renderThreadList(database) {
      if (!content) return;
      snapshotPresentation.renderThreadList({
        content: content,
        database: database,
        onSelect: function (threadId) { selectThread(database, threadId); }
      });
    }

    function threadIdFromHash() {
      var match = String(window.location.hash || "").match(/^#thread=(.+)$/);
      return match ? decodeURIComponent(match[1]) : "";
    }

    function renderThreadDetail(database, threadId) {
      if (!content) return;
      snapshotPresentation.renderThreadDetail({
        content: content,
        database: database,
        onBack: function () {
          window.location.hash = "";
          renderThreadList(database);
        },
        onMissing: function () { renderThreadList(database); },
        setStatus: setStatus,
        threadId: threadId
      });
    }

    function selectThread(database, threadId) {
      if (window.location.hash !== "#thread=" + encodeURIComponent(threadId)) {
        window.location.hash = "thread=" + encodeURIComponent(threadId);
      }
      renderThreadDetail(database, threadId);
    }

    function renderOfflineBoard(database) {
      root.dataset.offlineNavigation = "board";
      showOfflineMode();
      setStatus("Showing saved board content offline.", "ok");
      var boardPath = snapshotPresentation.boardPathFromPathname(window.location.pathname);
      var parameters = new URLSearchParams(window.location.search);
      var boardState = {
        view: parameters.get("view") === "liked" ? "liked" : "all",
        sort: ["newest", "oldest", "top"].indexOf(parameters.get("sort")) !== -1
          ? parameters.get("sort")
          : "newest"
      };
      snapshotPresentation.renderThreadList({
        content: content,
        database: database,
        boardState: boardState,
        boardPath: boardPath,
        emptyMessage: boardState.view === "liked"
          ? "No liked public threads were included in this snapshot."
          : "No recent public threads were included in this snapshot.",
        onBoardChange: function (nextState) {
          var nextUrl = snapshotPresentation.normalBoardUrl(boardPath, nextState);
          window.history.pushState({}, "", nextUrl);
          renderOfflineBoard(database);
        },
        onReconnectRequired: function () {
          setStatus("New Post requires a connection. Reconnect to create a post.", "error");
        },
        onSelect: function (threadId) {
          window.location.assign(snapshotPresentation.normalThreadUrl(threadId));
        }
      });
    }

    function renderOfflineTagsIndex(database) {
      root.dataset.offlineNavigation = "tags";
      showOfflineMode();
      setStatus("Showing saved tags offline.", "ok");
      snapshotPresentation.renderTagsIndex({
        content: content,
        database: database,
        onReconnectRequired: function () {
          setStatus("New Post requires a connection. Reconnect to create a post.", "error");
        }
      });
    }

    function renderOfflineTag(database, tag) {
      root.dataset.offlineNavigation = "tag";
      showOfflineMode();
      setStatus("Showing saved #" + tag + " results offline.", "ok");
      if (!snapshotPresentation.renderTagResult({ content: content, database: database, tag: tag })) {
        setStatus("That tag is not included in this offline snapshot. Reconnect to browse live results.", "error");
      }
    }

    function renderOfflineThread(database, threadId) {
      root.dataset.offlineNavigation = "thread";
      showOfflineMode();
      setStatus("Showing saved thread content offline.", "ok");
      snapshotPresentation.renderThreadDetail({
        content: content,
        database: database,
        onMissing: function () {
          clearNode(content);
          content.hidden = false;
          var unavailable = document.createElement("p");
          unavailable.className = "meta";
          unavailable.textContent = "This thread is not in the saved snapshot. Reconnect to read it online.";
          content.appendChild(unavailable);
        },
        setStatus: setStatus,
        threadId: threadId
      });
    }

    async function loadSnapshot() {
      setStatus("Loading the local reading snapshot...", "loading");
      try {
        if (typeof window.initSqlJs !== "function") {
          throw new Error("The browser SQLite runtime is unavailable.");
        }
        var response = await window.fetch(snapshotUrl, { credentials: "omit" });
        if (!response.ok) {
          throw new Error("No offline reading snapshot is available yet. Open the site while online and try again.");
        }
        var bytes = new Uint8Array(await response.arrayBuffer());
        var SQL = await window.initSqlJs({
          locateFile: function (fileName) {
            return fileName === "sql-wasm.wasm" ? runtimeUrl : "/assets/" + fileName;
          }
        });
        var database = new SQL.Database(bytes);
        var generatedAt = metadataValue(database, "generated_at");
        setStatus("Offline snapshot is ready.", "ok");
        root.dispatchEvent(new CustomEvent("forum-offline-reader-ready", {
          detail: { database: database, generatedAt: generatedAt }
        }));
      } catch (error) {
        setStatus(error && error.message ? error.message : "The offline reading snapshot could not be loaded.", "error");
      }
    }

    root.addEventListener("forum-offline-reader-ready", function (event) {
      var database = event.detail.database;
      setReaderDetails(event.detail.generatedAt);
      if (snapshotPresentation.boardPathFromPathname(window.location.pathname)) {
        renderOfflineBoard(database);
        window.addEventListener("popstate", function () {
          renderOfflineBoard(database);
        });
        return;
      }
      if (window.location.pathname === "/tags" || window.location.pathname === "/tags/") {
        renderOfflineTagsIndex(database);
        return;
      }
      var tag = snapshotPresentation.tagFromPathname(window.location.pathname);
      if (tag) {
        renderOfflineTag(database, tag);
        return;
      }
      var pathThreadId = snapshotPresentation.threadIdFromPathname(window.location.pathname);
      if (pathThreadId) {
        renderOfflineThread(database, pathThreadId);
        return;
      }
      var threadId = threadIdFromHash();
      if (threadId) {
        renderThreadDetail(database, threadId);
      } else {
        renderThreadList(database);
      }
      window.addEventListener("hashchange", function () {
        var selectedThreadId = threadIdFromHash();
        if (selectedThreadId) {
          renderThreadDetail(database, selectedThreadId);
        } else {
          renderThreadList(database);
        }
      });
    });

    loadSnapshot();
  });
})();
