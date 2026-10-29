(function () {
  "use strict";

  function clearSnapshotNode(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function nl2br(value) {
    const text = String(value || "").replace(/^Image-v1: [a-f0-9]{64} (?:png|jpg) [0-9]+ [0-9]+ [0-9]+(?: (\S+))?$/gm, function (line, description) {
      try { return "Image unavailable offline" + (description ? " — " + decodeURIComponent(description) : ""); }
      catch (error) { return "Image unavailable offline (reference preserved in the snapshot)."; }
    });
    return escapeHtml(text).replace(/(\r\n|\r|\n)/g, "<br>$1");
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function nl2br(value) {
    const text = String(value || "").replace(/^Image-v1: [a-f0-9]{64} (?:png|jpg) [0-9]+ [0-9]+ [0-9]+(?: (\S+))?$/gm, function (line, description) {
      try { return "Image unavailable offline" + (description ? " — " + decodeURIComponent(description) : ""); }
      catch (error) { return "Image unavailable offline (reference preserved in the snapshot)."; }
    });
    return escapeHtml(text).replace(/(\r\n|\r|\n)/g, "<br>$1");
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
    compactArchiveIndicator: function (generatedAt) {
      var match = String(generatedAt || "").match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/);
      return match ? "archive " + match[1] + " " + match[2] + " UTC" : "archive unknown";
    },
    compactReaderIndicator: function (revision) {
      var match = String(revision || "").match(/offline_reader\.([a-f0-9]{12})\.js$/);
      return match ? "reader " + match[1] : "reader unknown";
    },
    queueSignedReaction: function (target, summary, payload) {
      if (!window.forumOutboxIntent || !window.forumOutboxStorage) {
        return Promise.reject(new Error("Outbox storage is unavailable on this device."));
      }
      return window.forumOutboxIntent.createSignedIntent({
        action: "reaction",
        state: "queued",
        target: target,
        summary: summary,
        payload: payload
      }).then(function (item) {
        return window.forumOutboxStorage.save(item).then(function () { return item; });
      });
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
    canonicalTag: function (tag) {
      return tag.replace(/[A-Z]/g, function (letter) { return letter.toLowerCase(); });
    },
    tagFromPathname: function (pathname) {
      var match = String(pathname || "").match(/^\/tags\/([a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*)\/?$/);
      return match ? snapshotPresentation.canonicalTag(match[1]) : "";
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
      return [...new Set([...(thread.boardTags || []), ...(thread.labels || [])].map(snapshotPresentation.canonicalTag))];
    },
    tagGroups: function (database) {
      var groups = Object.create(null);
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
        tagsActive: true
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
      var tag = snapshotPresentation.canonicalTag(options.tag);
      var group = snapshotPresentation.tagGroups(options.database).find(function (candidate) {
        return candidate.tag === tag;
      });
      clearSnapshotNode(options.content);
      options.content.hidden = false;
      var header = document.createElement("article");
      header.className = "card";
      var eyebrow = document.createElement("p");
      eyebrow.className = "eyebrow";
      eyebrow.textContent = "Saved tag";
      var heading = document.createElement("h1");
      heading.textContent = "#" + tag;
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
      if (group) header.appendChild(count);
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
    // Mirrors partials/board_toolbar.php: filter, sort menu, then Tags (current on /tags/).
    appendBoardControls: function (content, state, options) {
      var toolbar = document.createElement("div");
      toolbar.className = "board-toolbar";
      var filter = document.createElement("nav");
      filter.className = "board-filter";
      filter.setAttribute("aria-label", "Show");
      var sort = document.createElement("details");
      sort.className = "board-sort";
      sort.dataset.role = "board-sort";
      var summary = document.createElement("summary");
      summary.className = "nav-link";
      var summaryHint = document.createElement("span");
      summaryHint.className = "visually-hidden";
      summaryHint.textContent = "Sort by ";
      summary.appendChild(summaryHint);
      var menu = document.createElement("div");
      menu.className = "board-sort__menu";
      var boardPath = options.boardPath || "/";
      [
        { key: "all", label: "All", group: "view", parent: filter, current: "page" },
        { key: "liked", label: "Liked", group: "view", parent: filter, current: "page" },
        { key: "newest", label: "Newest", group: "sort", parent: menu, current: "true" },
        { key: "oldest", label: "Oldest", group: "sort", parent: menu, current: "true" },
        { key: "top", label: "Top", group: "sort", parent: menu, current: "true" }
      ].forEach(function (option) {
        var nextState = { view: state.view, sort: state.sort };
        nextState[option.group] = option.key;
        var isCurrent = !options.tagsActive && state[option.group] === option.key;
        var link = document.createElement("a");
        link.className = "nav-link" + (isCurrent ? " is-active" : "");
        link.href = snapshotPresentation.normalBoardUrl(boardPath, nextState);
        link.dataset.navKey = option.key;
        if (isCurrent) link.setAttribute("aria-current", option.current);
        link.textContent = option.label;
        if (options.onBoardChange) link.addEventListener("click", function (event) {
          event.preventDefault();
          sort.open = false;
          options.onBoardChange(nextState);
        });
        option.parent.appendChild(link);
        if (option.group === "sort" && state.sort === option.key) summary.appendChild(document.createTextNode(option.label));
      });
      sort.appendChild(summary);
      sort.appendChild(menu);
      var tagsLink = document.createElement("a");
      tagsLink.className = "nav-link board-tags-link" + (options.tagsActive ? " is-active" : "");
      tagsLink.href = "/tags/";
      if (options.tagsActive) tagsLink.setAttribute("aria-current", "page");
      tagsLink.textContent = "Tags";
      toolbar.appendChild(filter);
      toolbar.appendChild(sort);
      toolbar.appendChild(tagsLink);
      content.appendChild(toolbar);
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
      var postColumns = options.database.exec("PRAGMA table_info(posts)")[0];
      var hasAgentMetadata = postColumns && postColumns.values.some(function (column) { return column[1] === "agent_response_json"; });
      var result = options.database.exec(
        "SELECT post_id, parent_id, subject, body, author_label, created_at, " + (hasAgentMetadata ? "agent_response_json " : "NULL ")
        + "FROM posts WHERE thread_id = ? ORDER BY sequence_number ASC, post_id ASC",
        [options.threadId]
      )[0];
      var rows = result && result.values ? result.values : [];
      if (!rows.length) {
        options.setStatus("This thread is not in the saved snapshot. Reconnect to read it online.", "error");
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
      var expansions = {};
      rows = rows.filter(function (row) {
        var meta = null;
        try { meta = JSON.parse(row[6] || "null"); } catch (_) {}
        if (row[4] === "reply-agent" && meta && meta.type === "scripture_expansion") {
          // Snapshot publication has already checked both source versions and visibility.
          if (meta.expansion_of === row[1]) expansions[row[1]] = row;
          return false;
        }
        return true;
      });
      rows.forEach(function (row) {
        var isRoot = String(row[0]) === String(options.threadId);
        var post = document.createElement("article");
        post.className = isRoot ? "card post-card thread-root-card" : "card post-card";
        post.id = "post-" + String(row[0] || "");
        post.dataset.postId = String(row[0] || "");
        post.dataset.author = String(row[4] || "guest");
        if (isRoot) {
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
        if (isRoot) {
          var firstLineBreak = /\r\n|\r|\n/.exec(postBody);
          var firstLine = firstLineBreak ? postBody.slice(0, firstLineBreak.index) : postBody;
          var remainder = firstLineBreak ? postBody.slice(firstLineBreak.index + firstLineBreak[0].length) : "";
          if (firstLine.trim() === snapshotPresentation.threadTitle(row[2], row[3])) {
            postBody = remainder.replace(/^(?:\r\n|\r|\n)+/, "");
          }
        }
        var meta = document.createElement("p");
        meta.className = "meta";
        meta.textContent = "by " + String(row[4] || "guest") + (row[5] ? " on " + String(row[5]) : "");
        body.innerHTML = nl2br(postBody);
        if (isRoot) {
          post.appendChild(body);
          post.appendChild(meta);
        } else {
          post.appendChild(meta);
          post.appendChild(body);
        }
        if (expansions[row[0]]) {
          var details = document.createElement("details");
          details.className = "scripture-expansion";
          var summary = document.createElement("summary");
          summary.textContent = "Further scripture reflection";
          details.appendChild(summary);
          var expansionBody = document.createElement("div");
          expansionBody.className = "body";
          expansionBody.innerHTML = nl2br(String(expansions[row[0]][3] || ""));
          details.appendChild(expansionBody);
          post.appendChild(details);
        }
        var permalink = document.createElement("a");
        permalink.className = "post-card-permalink";
        permalink.href = "/posts/" + encodeURIComponent(String(row[0] || ""));
        permalink.title = "Post " + String(row[0] || "");
        permalink.setAttribute("aria-label", permalink.title);
        permalink.textContent = "#";
        post.appendChild(permalink);
        if (options.onQueueReaction) {
          var actions = document.createElement("div");
          actions.className = "button-row button-row-natural post-card-actions" + (isRoot ? " thread-root-actions" : "");
          var queueLike = document.createElement("button");
          queueLike.type = "button";
          queueLike.className = "thread-reaction-button";
          queueLike.setAttribute("data-action", "queue-offline-like");
          queueLike.setAttribute("data-tag", "like");
          queueLike.setAttribute("data-applied-label", "Liked");
          queueLike.setAttribute("aria-pressed", "false");
          queueLike.textContent = "Like";
          queueLike.addEventListener("click", (function (postId, isRoot, title, button) {
            return function () {
              button.disabled = true;
              options.onQueueReaction({ kind: isRoot ? "thread" : "post", id: postId }, "Like " + title, isRoot
                ? { kind: "thread_tag", threadId: postId, tag: "like" }
                : { kind: "post_tag", postId: postId, tag: "like" }
              ).then(function () {
                button.textContent = "Liked";
                button.setAttribute("aria-pressed", "true");
              }).catch(function () {
                button.disabled = false;
              });
            };
          })(String(row[0] || ""), isRoot, isRoot ? snapshotPresentation.threadTitle(row[2], row[3]) : "comment", queueLike));
          actions.appendChild(queueLike);
          post.appendChild(actions);
        }
        options.content.appendChild(post);
      });
      return true;
    }
  };
  snapshotPresentation.applyUpdate = function (database, update) {
    database.run("BEGIN");
    try {
      var columns = database.exec("PRAGMA table_info(posts)")[0];
      var updateColumns = update.exec("PRAGMA table_info(posts)")[0];
      var hasMetadata = function (result) { return result && result.values.some(function (column) { return column[1] === "agent_response_json"; }); };
      if (!hasMetadata(columns) && hasMetadata(updateColumns)) database.run("ALTER TABLE posts ADD COLUMN agent_response_json TEXT NULL");
      if (hasMetadata(updateColumns)) {
        database.run("CREATE TABLE IF NOT EXISTS unavailable_expansions (post_id TEXT PRIMARY KEY)");
        database.run("INSERT OR REPLACE INTO metadata (key, value) VALUES ('snapshot_version', '4')");
      }
      // Updates contain complete visible threads; retire old copies, including removed expansions.
      var threads = update.exec("SELECT root_post_id FROM threads")[0];
      if (threads) threads.values.forEach(function (row) { database.run("DELETE FROM posts WHERE thread_id = ?", [row[0]]); });
      ["threads", "posts", "public_keys"].forEach(function (table) {
        var result = update.exec("SELECT * FROM " + table)[0];
        if (!result) return;
        var names = result.columns.map(function (name) { return '"' + name.replace(/"/g, '""') + '"'; }).join(",");
        result.values.forEach(function (row) {
          database.run("INSERT OR REPLACE INTO " + table + " (" + names + ") VALUES (" + row.map(function () { return "?"; }).join(",") + ")", row);
        });
      });
      var unavailableTable = update.exec("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'unavailable_expansions'")[0];
      if (unavailableTable) {
        var unavailable = update.exec("SELECT post_id FROM unavailable_expansions")[0];
        if (unavailable) unavailable.values.forEach(function (row) { database.run("DELETE FROM posts WHERE post_id = ?", [row[0]]); });
      }
      var excludedTable = update.exec("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'excluded_posts'")[0];
      if (excludedTable) {
        var excluded = update.exec("SELECT post_id FROM excluded_posts")[0];
        if (excluded) excluded.values.forEach(function (row) {
          var affected = database.exec("SELECT thread_id FROM posts WHERE post_id = ?", [row[0]])[0];
          database.run("DELETE FROM posts WHERE post_id = ?", [row[0]]);
          database.run("UPDATE threads SET subject = NULL, body_preview = '', author_label = '', author_profile_slug = NULL, thread_labels_json = '[]', score_total = 0 WHERE root_post_id = ?", [row[0]]);
          if (affected) affected.values.forEach(function (thread) {
            database.run("UPDATE threads SET reply_count = (SELECT COUNT(*) FROM posts WHERE thread_id = ? AND post_id <> ?), last_post_id = COALESCE((SELECT post_id FROM posts WHERE thread_id = ? ORDER BY sequence_number DESC, post_id DESC LIMIT 1), ''), last_activity_at = COALESCE((SELECT created_at FROM posts WHERE thread_id = ? ORDER BY sequence_number DESC, post_id DESC LIMIT 1), root_post_created_at) WHERE root_post_id = ?", [thread[0], thread[0], thread[0], thread[0], thread[0]]);
          });
        });
        database.run("INSERT OR REPLACE INTO metadata (key, value) VALUES ('snapshot_version', '5')");
      }
      database.run("COMMIT");
    } catch (error) { database.run("ROLLBACK"); throw error; }
  };
  window.forumOfflineSnapshot = snapshotPresentation;

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-offline-reader]");
    if (!root) {
      return;
    }

    var status = root.querySelector('[data-role="offline-reader-status"]');
    var modeBar = root.querySelector('[data-role="offline-mode-bar"]');
    var archiveIndicator = root.querySelector('[data-role="offline-archive-indicator"]');
    var readerIndicator = root.querySelector('[data-role="offline-reader-indicator"]');
    var content = root.querySelector('[data-role="offline-reader-content"]');
    var snapshotUrl = root.getAttribute("data-snapshot-url") || "/offline/snapshot.sqlite3";
    var updateUrl = root.getAttribute("data-update-url") || "/offline/update.sqlite3";
    var runtimeUrl = root.getAttribute("data-runtime-url") || "/assets/sql-wasm.wasm";

    function setStatus(message, state) {
      if (!status) {
        return;
      }
      status.textContent = message;
      status.hidden = !message || (state !== "error" && state !== "outcome");
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
      var revision = root.getAttribute("data-reader-revision") || "unknown";
      if (archiveIndicator) {
        archiveIndicator.textContent = snapshotPresentation.compactArchiveIndicator(generatedAt);
        archiveIndicator.title = generatedAt || "unknown";
      }
      if (readerIndicator) {
        readerIndicator.textContent = snapshotPresentation.compactReaderIndicator(revision);
        readerIndicator.title = revision;
      }
      if (modeBar) {
        modeBar.title = "archive " + (generatedAt || "unknown") + " · reader " + revision;
      }
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
      setStatus("", "ok");
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
        onSelect: function (threadId) {
          window.location.assign(snapshotPresentation.normalThreadUrl(threadId));
        }
      });
    }

    function renderOfflineTagsIndex(database) {
      root.dataset.offlineNavigation = "tags";
      showOfflineMode();
      setStatus("", "ok");
      snapshotPresentation.renderTagsIndex({
        content: content,
        database: database,
      });
    }

    function renderOfflineTag(database, tag) {
      root.dataset.offlineNavigation = "tag";
      showOfflineMode();
      setStatus("", "ok");
      snapshotPresentation.renderTagResult({ content: content, database: database, tag: tag });
    }

    function renderOfflineThread(database, threadId) {
      root.dataset.offlineNavigation = "thread";
      showOfflineMode();
      setStatus("", "ok");
      snapshotPresentation.renderThreadDetail({
        content: content,
        database: database,
        onMissing: function () {
          clearNode(content);
          content.hidden = false;

        },
        setStatus: setStatus,
        onQueueReaction: function (target, summary, payload) {
          return snapshotPresentation.queueSignedReaction(target, summary, payload).then(function () {
            setStatus("Like queued. Open Tools → Outbox to review it.", "outcome");
          }).catch(function (error) {
            setStatus(error && error.message ? error.message : "Unable to queue Like.", "error");
            throw error;
          });
        },
        threadId: threadId
      });
    }

    window.addEventListener("online", function () {
      if (!window.forumOutboxSender || typeof window.forumOutboxSender.processQueuedOutbox !== "function") return;
      window.forumOutboxSender.processQueuedOutbox().then(function (items) {
        if (items.length) setStatus("Queued Outbox work was processed after reconnecting.", "outcome");
      });
    });

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
        try {
          var updateResponse = await window.fetch(updateUrl, { credentials: "omit" });
          if (updateResponse.ok) {
            var update = new SQL.Database(new Uint8Array(await updateResponse.arrayBuffer()));
            snapshotPresentation.applyUpdate(database, update);
            var generated = metadataValue(update, "generated_at");
            if (generated) database.run("INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)", ["last_update_at", generated]);
            update.close();
            var runtime = window.forumBrowserRuntime || {};
            if (window.caches && runtime.offlineCacheName) {
              var cache = await window.caches.open(runtime.offlineCacheName);
              await cache.put(new URL(snapshotUrl, window.location.origin).href, new Response(database.export()));
            }
          }
        } catch (error) {
          // A missing or invalid update must not prevent reading the saved base.
        }
        var generatedAt = metadataValue(database, "generated_at");
        setStatus("", "ok");
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
