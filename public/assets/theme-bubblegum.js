/*
 * Bubblegum theme script: windows that behave like windows
 * (FORUM_DRAGGABLE_WINDOWS_ENABLED; docs/concepts/window_controls.md).
 * Registered with theme_toggle.js, which loads it only when the flag is on.
 * It runs only while Bubblegum is the active theme and window controls are on
 * (layout.php sets data-window-controls before paint unless Preferences turned
 * them off). start() gives every .card a title bar with a collapse button;
 * stop() removes everything start() added, so another theme starts clean.
 */
(function () {
  var registry = window.forumThemeScripts;
  if (!registry) return;

  var runtime = window.forumBrowserRuntime || {};
  var root = document.documentElement;
  // Matches layout.php's pre-paint check, so a stored value can't inject CSS.
  var ID_PATTERN = /^[A-Za-z0-9_.:-]+$/;
  // Oldest collapses are forgotten first.
  var MAX_SAVED = 500;
  var session = null;

  function readSaved() {
    try {
      return (localStorage.getItem(runtime.collapsedWindowsStorageKey) || "").split(" ")
        .filter(function (id) { return ID_PATTERN.test(id); });
    } catch (error) {
      return [];
    }
  }

  function writeSaved(ids) {
    try {
      if (ids.length > 0) localStorage.setItem(runtime.collapsedWindowsStorageKey, ids.slice(-MAX_SAVED).join(" "));
      else localStorage.removeItem(runtime.collapsedWindowsStorageKey);
    } catch (error) {
    }
  }

  function postId(card) {
    var id = card.getAttribute("data-post-id");
    return id && ID_PATTERN.test(id) ? id : null;
  }

  // What a collapsed window shows in its title bar: a post's author, or
  // another window's first heading.
  function windowLabel(card) {
    var author = card.getAttribute("data-author");
    if (author) return author;
    var heading = card.querySelector("h1, h2, h3");
    return heading ? heading.textContent.replace(/\s+/g, " ").trim() : "";
  }

  function titlebarOf(card) {
    var last = card.lastElementChild;
    return last && last.hasAttribute("data-window-titlebar") ? last : null;
  }

  function setCollapsed(card, collapsed, remember) {
    var titlebar = titlebarOf(card);
    if (!titlebar) return;
    var button = titlebar.querySelector("[data-window-collapse]");
    if (collapsed) {
      // Focus inside a window that is about to hide moves to its button.
      if (card.contains(document.activeElement) && document.activeElement !== button) button.focus();
      card.setAttribute("data-window-collapsed", "");
    } else {
      card.removeAttribute("data-window-collapsed");
    }
    button.setAttribute("aria-expanded", collapsed ? "false" : "true");
    var id = postId(card);
    if (remember && id) {
      var ids = readSaved().filter(function (each) { return each !== id; });
      if (collapsed) ids.push(id);
      writeSaved(ids);
    }
  }

  // The title bar is the card's last child: an absolutely placed strip over
  // the drawn bar, so it shifts no first-child spacing in the card.
  function decorate(card, saved) {
    if (titlebarOf(card)) return;
    var label = windowLabel(card);
    var titlebar = document.createElement("div");
    titlebar.setAttribute("data-window-titlebar", "");
    var text = document.createElement("span");
    text.setAttribute("data-window-label", "");
    text.setAttribute("aria-hidden", "true");
    text.textContent = label;
    var button = document.createElement("button");
    button.type = "button";
    button.setAttribute("data-window-collapse", "");
    button.setAttribute("aria-expanded", "true");
    button.setAttribute("aria-label", label ? "Window: " + label : "Window");
    button.title = "Collapse or expand";
    titlebar.appendChild(text);
    titlebar.appendChild(button);
    card.appendChild(titlebar);
    card.setAttribute("data-window", "");
    var id = postId(card);
    if (id && saved.indexOf(id) !== -1) setCollapsed(card, true, false);
  }

  function decorateAll(scope) {
    var saved = readSaved();
    if (scope.matches && scope.matches(".card")) decorate(scope, saved);
    Array.prototype.forEach.call(scope.querySelectorAll ? scope.querySelectorAll(".card") : [], function (card) {
      decorate(card, saved);
    });
  }

  function cardOf(node) {
    var card = node && node.closest ? node.closest(".card") : null;
    return card && titlebarOf(card) ? card : null;
  }

  function start() {
    if (!root.hasAttribute("data-window-controls") || session) return;
    var listeners = [];
    function listen(target, type, handler) {
      target.addEventListener(type, handler);
      listeners.push([target, type, handler]);
    }

    decorateAll(document);
    // The script has marked the saved posts itself; the pre-paint copy can go.
    var prePaint = document.getElementById("window-controls-collapsed");
    if (prePaint) prePaint.parentNode.removeChild(prePaint);

    listen(document, "click", function (event) {
      var button = event.target.closest ? event.target.closest("[data-window-collapse]") : null;
      var card = cardOf(button);
      if (!card) return;
      setCollapsed(card, !card.hasAttribute("data-window-collapsed"), true);
    });
    listen(document, "dblclick", function (event) {
      if (!event.target.closest || !event.target.closest("[data-window-titlebar]")) return;
      var card = cardOf(event.target);
      if (card) setCollapsed(card, !card.hasAttribute("data-window-collapsed"), true);
    });

    // Windows added later (a posted reply, a disclosed composer) get a title bar too.
    var observer = new MutationObserver(function (records) {
      records.forEach(function (record) {
        Array.prototype.forEach.call(record.addedNodes, function (node) {
          if (node.nodeType === 1 && !node.hasAttribute("data-window-titlebar")) decorateAll(node);
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    session = { listeners: listeners, observer: observer };
  }

  function stop() {
    if (!session) return;
    session.observer.disconnect();
    session.listeners.forEach(function (entry) { entry[0].removeEventListener(entry[1], entry[2]); });
    Array.prototype.forEach.call(document.querySelectorAll("[data-window-titlebar]"), function (titlebar) {
      titlebar.parentNode.removeChild(titlebar);
    });
    Array.prototype.forEach.call(document.querySelectorAll("[data-window]"), function (card) {
      card.removeAttribute("data-window");
      card.removeAttribute("data-window-collapsed");
    });
    session = null;
  }

  // Preferences turns window controls on or off without a reload.
  window.forumWindowControls = {
    restart: function () {
      stop();
      if (registry.active() === "bubblegum") start();
    }
  };

  registry.register("bubblegum", { start: start, stop: stop });
})();
