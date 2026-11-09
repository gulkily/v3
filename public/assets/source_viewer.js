// Readable viewer for the site's plain-text source files (/source/current/*,
// /source/blob/{commit}/*, /source/commits/{commit}). Phone browsers show
// these raw, with long lines wrapped mid-value; this renders the same text as
// fields, a body, an armored PGP block or a commit's file list, with the raw
// file one tap away.
//
// Two parts so other pages can adopt it later:
// - ForumSourceViewer.render(container, text, href) builds the readable view.
// - The Forte binding at the bottom opens source links clicked inside a
//   Forte content pane in that pane, with Back and Show raw file.
(function () {
  "use strict";

  var SOURCE_PATH = /^\/source\/(?:current\/.+|blob\/[0-9a-fA-F]{7,64}\/.+|commits\/[0-9a-fA-F]{7,64})$/;
  var FIELD_LINE = /^([A-Za-z][A-Za-z0-9-]*):(?: (.*))?$/;
  var ARMOR_BEGIN = /^-----BEGIN PGP ([A-Z ]+)-----$/m;

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined) {
      node.textContent = text;
    }
    return node;
  }

  function sourceUrl(href) {
    try {
      var url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin || !SOURCE_PATH.test(url.pathname)) {
        return null;
      }
      return url;
    } catch (error) {
      return null;
    }
  }

  // What the URL names: a commit, or a file path (and the commit it was read at).
  function describe(href) {
    var url = sourceUrl(href);
    var path = url ? url.pathname : "";
    var match = /^\/source\/commits\/([0-9a-fA-F]+)$/.exec(path);
    if (match) {
      return { kind: "commit", commit: match[1], path: "", name: "Commit " + match[1].slice(0, 12) };
    }
    match = /^\/source\/blob\/([0-9a-fA-F]+)\/(.+)$/.exec(path);
    var commit = match ? match[1] : "";
    var filePath = match ? match[2] : path.replace(/^\/source\/current\//, "");
    try {
      filePath = decodeURIComponent(filePath);
    } catch (error) {}
    return { kind: "file", commit: commit, path: filePath, name: filePath.split("/").pop() };
  }

  function encodePath(path) {
    return path.split("/").map(encodeURIComponent).join("/");
  }

  // Leading "Key: value" lines up to the first blank line. A key with no
  // value collects the indented-or-plain lines under it (a commit's Files:).
  function parseFields(text) {
    var lines = text.replace(/\r\n?/g, "\n").split("\n");
    var fields = [];
    var index = 0;
    for (; index < lines.length; index += 1) {
      var line = lines[index];
      if (line === "") {
        break;
      }
      var match = FIELD_LINE.exec(line);
      if (match) {
        fields.push({ key: match[1], value: match[2] || "", items: [] });
      } else if (fields.length > 0 && fields[fields.length - 1].value === "") {
        fields[fields.length - 1].items.push(line);
      } else {
        return null;
      }
    }
    if (fields.length === 0) {
      return null;
    }
    var body = lines.slice(index + 1).join("\n").replace(/\s+$/, "");
    return { fields: fields, body: body };
  }

  function fingerprintFromPath(path) {
    var match = /openpgp-([0-9A-Fa-f]{40})\.asc$/.exec(path || "");
    return match ? match[1].toUpperCase().replace(/(.{4})(?=.)/g, "$1 ") : "";
  }

  function renderArmor(container, text, info) {
    var type = ARMOR_BEGIN.exec(text)[1];
    var label = type === "SIGNATURE" ? "Detached signature"
      : type === "PUBLIC KEY BLOCK" ? "Public key"
        : type.charAt(0) + type.slice(1).toLowerCase();
    var rows = [["Type", label]];
    var fingerprint = fingerprintFromPath(info.path);
    if (fingerprint !== "") {
      rows.push(["Fingerprint", fingerprint]);
    }
    if (/\.asc$/.test(info.path) && type === "SIGNATURE") {
      rows.push(["Signs", info.path.replace(/\.asc$/, "").split("/").pop()]);
    }
    container.appendChild(fieldTable(rows.map(function (row) {
      return { key: row[0], value: row[1], items: [] };
    }), info));
    container.appendChild(el("pre", "source-viewer-armor", text.replace(/\s+$/, "")));
  }

  function fieldTable(fields, info) {
    var table = el("dl", "source-viewer-fields");
    fields.forEach(function (field) {
      table.appendChild(el("dt", "", field.key));
      var value = el("dd");
      if (field.items.length > 0) {
        var list = el("ul", "source-viewer-list");
        field.items.forEach(function (item) {
          var entry = el("li");
          var fileMatch = info.kind === "commit" ? /^(\S+) (.+)$/.exec(item) : null;
          if (fileMatch) {
            entry.appendChild(el("span", "source-viewer-status", fileMatch[1]));
            entry.appendChild(document.createTextNode(" "));
            var link = el("a", "", fileMatch[2]);
            link.href = "/source/blob/" + info.commit + "/" + encodePath(fileMatch[2]);
            entry.appendChild(link);
          } else {
            entry.textContent = item;
          }
          list.appendChild(entry);
        });
        value.appendChild(list);
      } else {
        value.textContent = field.value;
      }
      table.appendChild(value);
    });
    return table;
  }

  function render(container, text, href) {
    var info = describe(href);
    container.textContent = "";
    if (ARMOR_BEGIN.test(text)) {
      renderArmor(container, text, info);
      return;
    }
    var record = parseFields(text);
    if (record) {
      container.appendChild(fieldTable(record.fields, info));
      if (record.body !== "") {
        container.appendChild(el("div", "source-viewer-body", record.body));
      }
      return;
    }
    container.appendChild(el("pre", "source-viewer-text", text));
  }

  window.ForumSourceViewer = { render: render, describe: describe, parseFields: parseFields, isSourceHref: function (href) {
    return sourceUrl(href) !== null;
  } };

  // ---- Forte binding -------------------------------------------------------

  var state = null; // { pane, origin, stack: [href], scrollTop, observer, viewer }

  function closeViewer() {
    if (!state) {
      return;
    }
    var closing = state;
    state = null;
    closing.observer.disconnect();
    closing.viewer.remove();
    closing.pane.removeAttribute("data-paned-source-viewing");
    closing.pane.scrollTop = closing.scrollTop;
  }

  function showCurrent() {
    var href = state.stack[state.stack.length - 1];
    var info = describe(href);
    var viewer = state.viewer;
    viewer.querySelector(".paned-content-subject").textContent = info.name;
    var meta = viewer.querySelector(".paned-content-meta");
    meta.textContent = "";
    var where = info.kind === "commit" ? info.commit
      : info.path + (info.commit !== "" ? " @ " + info.commit.slice(0, 12) : "");
    meta.appendChild(el("span", "source-viewer-path", where));
    var raw = viewer.querySelector("[data-paned-source-raw]");
    raw.href = href;
    raw.textContent = info.kind === "commit" ? "Show raw" : "Show raw file";
    var body = viewer.querySelector(".body");
    body.textContent = "";
    body.appendChild(el("p", "meta", "Loading…"));
    state.pane.scrollTop = 0;

    var current = state;
    fetch(href, { headers: { Accept: "text/plain" } })
      .then(function (response) {
        return response.text().then(function (text) {
          return { ok: response.ok, text: text };
        });
      })
      .then(function (result) {
        if (state !== current || current.stack[current.stack.length - 1] !== href) {
          return;
        }
        if (!result.ok) {
          body.textContent = "";
          body.appendChild(el("p", "meta", result.text.trim() || "Could not load this file."));
          return;
        }
        render(body, result.text, href);
      })
      .catch(function () {
        if (state !== current || current.stack[current.stack.length - 1] !== href) {
          return;
        }
        body.textContent = "";
        body.appendChild(el("p", "meta", "Could not load this file."));
      });
  }

  function buildViewer() {
    var viewer = el("article", "paned-content-post paned-source-viewer");
    viewer.setAttribute("data-paned-source-viewer", "");
    var head = el("div", "paned-content-head");
    head.appendChild(el("div", "paned-content-subject"));
    head.appendChild(el("div", "paned-content-meta"));
    viewer.appendChild(head);
    var actions = el("div", "paned-source-viewer-actions");
    var back = el("button", "", "Back");
    back.type = "button";
    back.setAttribute("data-paned-source-back", "");
    var raw = el("a");
    raw.setAttribute("data-paned-source-raw", "");
    actions.appendChild(back);
    actions.appendChild(raw);
    viewer.appendChild(actions);
    var card = el("div", "post-card paned-post-card");
    card.appendChild(el("div", "body source-viewer"));
    viewer.appendChild(card);
    return viewer;
  }

  // Each file opened in the viewer is a browser history entry (same URL,
  // with the open stack in history.state), so the browser's Back and the
  // viewer's Back move through the same steps. history.state is the source of
  // truth: popstate re-syncs the pane from it after the pane's own reader has
  // restored its selection.
  var HISTORY_KEY = "forteSourceViewer";

  function historyStack() {
    var entry = history.state;
    return entry && typeof entry === "object" && Array.isArray(entry[HISTORY_KEY]) ? entry[HISTORY_KEY] : null;
  }

  function historyEntry(stack) {
    var entry = {};
    if (history.state && typeof history.state === "object") {
      Object.keys(history.state).forEach(function (key) {
        entry[key] = history.state[key];
      });
    }
    if (stack) {
      entry[HISTORY_KEY] = stack;
    } else {
      delete entry[HISTORY_KEY];
    }
    return entry;
  }

  function visibleItem(pane) {
    var items = pane.querySelectorAll(".paned-content-post:not([data-paned-source-viewer])");
    for (var index = 0; index < items.length; index += 1) {
      if (!items[index].hidden) {
        return items[index];
      }
    }
    return null;
  }

  function showStack(pane, origin, stack) {
    if (state && state.pane === pane) {
      if (state.stack.join("\n") === stack.join("\n")) {
        return;
      }
      state.stack = stack.slice();
      showCurrent();
      return;
    }
    closeViewer();
    var viewer = buildViewer();
    state = { pane: pane, origin: origin, stack: stack.slice(), scrollTop: pane.scrollTop, viewer: viewer, observer: null };
    pane.setAttribute("data-paned-source-viewing", "");
    pane.insertBefore(viewer, pane.firstChild);
    // The reader that owns the pane may swap or hide the item the link came
    // from (another row selected, a view change); the viewer closes with it.
    state.observer = new MutationObserver(function () {
      if (state && state.origin && (!state.origin.isConnected || state.origin.hidden)) {
        closeViewer();
      }
    });
    state.observer.observe(pane, { childList: true, subtree: true, attributes: true, attributeFilter: ["hidden"] });
    showCurrent();
    viewer.querySelector("[data-paned-source-back]").focus({ preventScroll: true });
  }

  function openFromLink(pane, origin, href) {
    var stack = (state && state.pane === pane ? state.stack : []).concat([href]);
    history.pushState(historyEntry(stack), "", location.href);
    showStack(pane, origin, stack);
  }

  function syncFromHistory() {
    var stack = historyStack();
    if (!stack || stack.length === 0) {
      closeViewer();
      return;
    }
    var pane = state ? state.pane : document.querySelector(".paned-content-pane");
    if (!pane) {
      return;
    }
    var origin = state && state.origin && state.origin.isConnected && !state.origin.hidden ? state.origin : visibleItem(pane);
    showStack(pane, origin, stack);
  }

  // Leave history to step back through when the viewer's entries are live;
  // otherwise (opened before a reload dropped them) just close.
  function stepBack(steps) {
    if (historyStack()) {
      history.go(-steps);
    } else {
      closeViewer();
    }
  }

  window.addEventListener("popstate", function () {
    window.setTimeout(syncFromHistory, 0);
  });

  // A reload starts with the pane's normal content; drop this entry's viewer
  // state so Back from here doesn't reopen what the reload closed.
  if (historyStack()) {
    history.replaceState(historyEntry(null), "", location.href);
  }

  document.addEventListener("click", function (event) {
    if (event.target.closest && event.target.closest("[data-paned-source-back]")) {
      if (state) {
        stepBack(1);
      }
      return;
    }
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }
    var link = event.target.closest ? event.target.closest("a[href]") : null;
    if (!link || link.hasAttribute("data-paned-source-raw") || link.target === "_blank" || link.hasAttribute("download")) {
      return;
    }
    var pane = link.closest(".paned-content-pane");
    if (!pane || sourceUrl(link.getAttribute("href")) === null) {
      return;
    }
    event.preventDefault();
    var origin = link.closest("[data-paned-source-viewer]") ? (state && state.origin) : link.closest(".paned-content-post");
    openFromLink(pane, origin, sourceUrl(link.getAttribute("href")).pathname);
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && state && !document.querySelector("dialog[open]")) {
      stepBack(state.stack.length);
    }
  });
})();
