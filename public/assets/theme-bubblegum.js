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
  // Arrow keys move a window this far; with Shift, five times as far.
  var KEY_STEP = 10;
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
    } else if (card.hasAttribute("data-window-collapsed")) {
      card.removeAttribute("data-window-collapsed");
      // A short pop as the window opens; theme-bubblegum.css skips it for reduced motion.
      card.removeAttribute("data-window-pop");
      void card.offsetWidth;
      card.setAttribute("data-window-pop", "");
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

  // The window the reader is in: clicked, tapped or tabbed into. The others fade.
  function activate(card) {
    if (!card || card.hasAttribute("data-window-active")) return;
    Array.prototype.forEach.call(document.querySelectorAll("[data-window-active]"), function (each) {
      each.removeAttribute("data-window-active");
    });
    card.setAttribute("data-window-active", "");
    root.setAttribute("data-window-has-active", "");
  }

  function cardOf(node) {
    var card = node && node.closest ? node.closest(".card") : null;
    return card && titlebarOf(card) ? card : null;
  }

  // Float mode: only with a fine pointer on a screen wide enough for a desktop.
  function floatAvailable() {
    return !!(window.matchMedia && window.matchMedia("(pointer: fine) and (min-width: 640px)").matches);
  }

  function moveWindow(card, x, y) {
    card.setAttribute("data-window-x", String(Math.round(x)));
    card.setAttribute("data-window-y", String(Math.round(y)));
    card.style.translate = Math.round(x) + "px " + Math.round(y) + "px";
  }

  function offset(card) {
    return [Number(card.getAttribute("data-window-x")) || 0, Number(card.getAttribute("data-window-y")) || 0];
  }

  function resetWindows() {
    Array.prototype.forEach.call(document.querySelectorAll("[data-window-x]"), function (card) {
      card.removeAttribute("data-window-x");
      card.removeAttribute("data-window-y");
      card.style.removeProperty("translate");
    });
  }

  function setFloat(on) {
    if (!session || !session.floatButton) return;
    if (on) root.setAttribute("data-window-float", "");
    else {
      root.removeAttribute("data-window-float");
      resetWindows();
    }
    session.floatButton.setAttribute("aria-pressed", on ? "true" : "false");
    session.resetButton.hidden = !on;
  }

  function addFloatControls() {
    if (!floatAvailable()) return;
    // Bottom-left corner, like a taskbar's start area: the header has no room.
    var place = document.querySelector("[data-shell]") || document.body;
    var controls = document.createElement("div");
    controls.setAttribute("data-window-float-controls", "");
    controls.setAttribute("role", "group");
    controls.setAttribute("aria-label", "Window layout");
    var floatButton = document.createElement("button");
    floatButton.type = "button";
    floatButton.setAttribute("data-window-float-toggle", "");
    floatButton.setAttribute("aria-pressed", "false");
    floatButton.textContent = "Float";
    floatButton.title = "Drag windows by their title bars";
    var resetButton = document.createElement("button");
    resetButton.type = "button";
    resetButton.setAttribute("data-window-float-reset", "");
    resetButton.textContent = "Reset";
    resetButton.title = "Put every window back";
    resetButton.hidden = true;
    controls.appendChild(floatButton);
    controls.appendChild(resetButton);
    place.appendChild(controls);
    session.floatButton = floatButton;
    session.resetButton = resetButton;
    session.floatControls = controls;
  }

  function start() {
    if (!root.hasAttribute("data-window-controls") || session) return;
    var listeners = [];
    function listen(target, type, handler) {
      target.addEventListener(type, handler);
      listeners.push([target, type, handler]);
    }

    session = { listeners: listeners, observer: null, drag: null };
    decorateAll(document);
    addFloatControls();
    // The script has marked the saved posts itself; the pre-paint copy can go.
    var prePaint = document.getElementById("window-controls-collapsed");
    if (prePaint) prePaint.parentNode.removeChild(prePaint);

    listen(document, "click", function (event) {
      if (!event.target.closest) return;
      if (event.target.closest("[data-window-float-toggle]")) {
        setFloat(!root.hasAttribute("data-window-float"));
        return;
      }
      if (event.target.closest("[data-window-float-reset]")) {
        resetWindows();
        return;
      }
      var button = event.target.closest("[data-window-collapse]");
      var card = cardOf(button);
      if (!card) return;
      setCollapsed(card, !card.hasAttribute("data-window-collapsed"), true);
    });
    listen(document, "dblclick", function (event) {
      if (!event.target.closest || !event.target.closest("[data-window-titlebar]")) return;
      var card = cardOf(event.target);
      if (card) setCollapsed(card, !card.hasAttribute("data-window-collapsed"), true);
    });

    listen(document, "pointerdown", function (event) {
      activate(cardOf(event.target));
      // Float mode: a primary-button press on a title bar (not its button) starts a drag.
      if (!root.hasAttribute("data-window-float") || event.button !== 0 || event.pointerType === "touch") return;
      var titlebar = event.target.closest ? event.target.closest("[data-window-titlebar]") : null;
      if (!titlebar || event.target.closest("[data-window-collapse]")) return;
      var card = cardOf(titlebar);
      if (!card) return;
      event.preventDefault();
      var start = offset(card);
      session.drag = { card: card, pointerId: event.pointerId, x: event.clientX - start[0], y: event.clientY - start[1] };
      card.setAttribute("data-window-dragging", "");
      if (titlebar.setPointerCapture) titlebar.setPointerCapture(event.pointerId);
    });
    listen(document, "pointermove", function (event) {
      var drag = session && session.drag;
      if (!drag || event.pointerId !== drag.pointerId) return;
      moveWindow(drag.card, event.clientX - drag.x, event.clientY - drag.y);
    });
    function endDrag(event) {
      var drag = session && session.drag;
      if (!drag || event.pointerId !== drag.pointerId) return;
      drag.card.removeAttribute("data-window-dragging");
      session.drag = null;
    }
    listen(document, "pointerup", endDrag);
    listen(document, "pointercancel", endDrag);
    listen(document, "keydown", function (event) {
      if (!root.hasAttribute("data-window-float") || event.altKey || event.ctrlKey || event.metaKey) return;
      var moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
      var button = event.target.closest ? event.target.closest("[data-window-collapse]") : null;
      var card = cardOf(button);
      if (!moves || !card) return;
      event.preventDefault();
      var step = event.shiftKey ? KEY_STEP * 5 : KEY_STEP;
      var current = offset(card);
      moveWindow(card, current[0] + moves[0] * step, current[1] + moves[1] * step);
    });
    listen(document, "focusin", function (event) { activate(cardOf(event.target)); });
    listen(document, "animationend", function (event) {
      if (event.target.hasAttribute && event.target.hasAttribute("data-window-pop")) event.target.removeAttribute("data-window-pop");
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

    session.observer = observer;
  }

  function stop() {
    if (!session) return;
    resetWindows();
    root.removeAttribute("data-window-float");
    if (session.floatControls && session.floatControls.parentNode) session.floatControls.parentNode.removeChild(session.floatControls);
    session.observer.disconnect();
    session.listeners.forEach(function (entry) { entry[0].removeEventListener(entry[1], entry[2]); });
    Array.prototype.forEach.call(document.querySelectorAll("[data-window-titlebar]"), function (titlebar) {
      titlebar.parentNode.removeChild(titlebar);
    });
    Array.prototype.forEach.call(document.querySelectorAll("[data-window]"), function (card) {
      card.removeAttribute("data-window");
      card.removeAttribute("data-window-collapsed");
      card.removeAttribute("data-window-active");
      card.removeAttribute("data-window-pop");
      card.removeAttribute("data-window-dragging");
    });
    root.removeAttribute("data-window-has-active");
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
